<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Operations\BackupBundle;
use App\Application\Operations\BackupFailure;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\DatabaseDumpProcess;
use App\Application\Operations\DatabaseRestoreProcess;
use App\Application\Operations\PrivateBackupFiles;
use App\Application\Operations\RestoreBundle;
use App\Application\Operations\RestoreDatabaseLock;
use App\Application\Operations\RestoreTarget;
use App\Application\Studio\StudioService;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Support\EditorFixture;
use Tests\Support\RestoreRuntimeFixture;
use Tests\Support\RuntimeConcurrencyGuard;
use Tests\TestCase;

/** Real mariadb-dump -> verified bundle -> real mariadb import, never a fake process. */
final class BackupRestoreTest extends TestCase
{
    use EditorFixture, RestoreRuntimeFixture;

    private ?Connection $source = null;

    private ?Connection $target = null;

    private bool $ownsEmptyTarget = false;

    private ?string $directory = null;

    private ?string $assetId = null;

    private ?string $materialId = null;

    private ?string $owner = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY === 'Windows' || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Real SQL/media restore requires Linux, mariadb-client, MariaDB 10.6 and dedicated lessons_test/lessons_restore_test databases.');
        }
        $this->source = RuntimeConcurrencyGuard::database($this->app);
        $this->assertMatchesRegularExpression('/\A10\.6\..*MariaDB/', $this->source->selectOne('SELECT VERSION() AS version')->version);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
        $username = getenv('LESSONS_RESTORE_TEST_USERNAME');
        $password = getenv('LESSONS_RESTORE_TEST_PASSWORD');
        $this->assertIsString($username, 'MariaDB CI must provision a restricted restore account; missing credentials are a failure, never a skip.');
        $this->assertIsString($password);
        $config = array_replace($this->source->getConfig(), ['database' => 'lessons_restore_test', 'username' => $username, 'password' => $password, 'url' => null]);
        config(['database.connections.restore_acceptance' => $config]);
        $this->target = DB::connection('restore_acceptance');
        (new RestoreTarget)->assertSafe($this->target, 'testing');
        $this->ownsEmptyTarget = true;
        $this->directory = str_replace('\\', '/', realpath(sys_get_temp_dir())).'/lessons-restore-proof-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->assertSame(0, $this->source->table('media_versions')->count(), 'Acceptance source must not contain unmanaged private media fixtures.');
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        try {
            if ($this->source !== null) {
                config(['database.default' => $this->source->getName()]);
                DB::setDefaultConnection($this->source->getName());
            }
            if ($this->target !== null && $this->ownsEmptyTarget) {
                (new RestoreTarget)->assertSafe($this->target, 'testing', empty: false);
                $this->target->getSchemaBuilder()->dropAllTables();
            }
            if ($this->materialId !== null) {
                TeachingSession::whereIn('id', $this->restoreSessionIds)->where('owner_key', $this->owner)->delete();
                LessonMaterial::whereKey($this->materialId)->update(['current_version_id' => null]);
                LessonVersion::where('lesson_material_id', $this->materialId)->delete();
                LessonMaterial::whereKey($this->materialId)->delete();
                MediaOwnerQuota::whereKey($this->owner)->where('used_bytes', 0)->delete();
            }
            if ($this->assetId !== null) {
                MediaAsset::whereKey($this->assetId)->update(['current_version_id' => null]);
                MediaVersion::where('media_asset_id', $this->assetId)->delete();
                MediaAsset::whereKey($this->assetId)->delete();
            }
            if ($this->directory !== null) {
                $this->removeFixture($this->directory);
            }
        } finally {
            CarbonImmutable::setTestNow();
            parent::tearDown();
        }
    }

    public function test_real_bundle_restores_sql_and_archived_immutable_media_and_refuses_corruption_before_writes(): void
    {
        $this->owner = (string) Str::uuid();
        $asset = MediaAsset::create(['owner_key' => $this->owner, 'title' => 'Synthetic восстановление / Wiederherstellung', 'tags' => ['restore-proof'],
            'author' => 'Synthetic fixture', 'source' => 'test-only', 'rights_basis' => 'self_created', 'usage_rights' => 'test', 'revision' => 1, 'archived' => true]);
        $this->assetId = $asset->id;
        $old = $this->version($asset, 1);
        $current = $this->version($asset, 2);
        $asset->update(['current_version_id' => $current->id]);
        $document = $this->restoreRuntimeDocument();
        $document['content']['ru']['title'] = 'Synthetic exact SQL snapshot <literal> & Unicode';
        $material = app(StudioService::class)->create($this->owner, $document);
        $this->materialId = $material->id;
        $runtimeFixture = $this->createRestoreRuntimeFixture($this->owner, $material);
        $version = LessonVersion::where('lesson_material_id', $material->id)->firstOrFail();
        $expectedDocument = $version->document;
        $sourceSessions = $this->source->table('teaching_sessions')->whereIn('id', $this->restoreSessionIds)->orderBy('id')->get()->all();
        $sourceAnswers = $this->source->table('session_answers')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all();
        $sourceParticipants = $this->source->table('session_participants')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all();
        $sourceReceipts = $this->source->table('session_command_receipts')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all();
        $concurrent = null;
        $afterRealSnapshot = function () use ($asset, &$concurrent): void {
            $concurrent = $this->version($asset, 3);
        };
        $dump = new class($afterRealSnapshot) extends DatabaseDumpProcess
        {
            public function __construct(private \Closure $afterSnapshot) {}

            public function run(array $arguments, int $timeout): void
            {
                parent::run($arguments, $timeout);
                ($this->afterSnapshot)();
            }
        };
        $backup = new BackupBundle(new DatabaseBackup($dump, new ExecutableFinder), new PrivateBackupFiles);
        $bundle = $backup->create($this->source, $this->directory.'/backups', base_path(), $this->directory.'/source-media', 60);
        $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing([$old->id, $current->id], $manifest['requiredMediaVersionIds']);
        $this->assertCount(3, $manifest['media']);
        $hash = hash_file('sha256', $bundle.'/manifest.json');
        $this->assertStringNotContainsString('CREATE DATABASE', file_get_contents($bundle.'/database.sql'));
        $this->assertStringNotContainsString('USE `lessons_test`', file_get_contents($bundle.'/database.sql'));
        config(['database.default' => 'restore_acceptance']);
        DB::setDefaultConnection('restore_acceptance');
        $options = ['bundle' => $bundle, '--manifest-sha256' => $hash, '--media' => $this->directory.'/restored-media', '--timeout' => 60];
        foreach (['database.sql', 'media/'.$old->storage_key] as $file) {
            $bytes = file_get_contents($bundle.'/'.$file);
            file_put_contents($bundle.'/'.$file, $bytes.'tampered');
            $this->assertSame(1, Artisan::call('lessons:restore-test', $options));
            $this->assertStringContainsString('checksum verification failed', Artisan::output());
            (new RestoreTarget)->assertSafe($this->target, 'testing');
            $this->assertDirectoryDoesNotExist($options['--media']);
            file_put_contents($bundle.'/'.$file, $bytes);
        }
        // Fault injection after a real import: every pre-dump immutable version must be restored.
        $importer = new class($this->target, $old->id) extends DatabaseRestoreProcess
        {
            public function __construct(private Connection $target, private string $missingVersion) {}

            public function run(array $arguments, string $sql, int $timeout): void
            {
                parent::run($arguments, $sql, $timeout);
                $this->target->table('media_versions')->where('id', $this->missingVersion)->delete();
            }
        };
        $this->app->instance(DatabaseRestoreProcess::class, $importer);
        $this->assertSame(1, Artisan::call('lessons:restore-test', $options));
        $this->assertStringContainsString('missing immutable media versions', Artisan::output());
        $this->assertDirectoryDoesNotExist($options['--media']);
        $this->assertSame(1, (int) $this->target->selectOne('SELECT IS_FREE_LOCK(?) AS available', [RestoreDatabaseLock::NAME])->available, 'Failed import coverage must release its named lock.');
        (new RestoreTarget)->assertSafe($this->target, 'testing', empty: false);
        $this->target->getSchemaBuilder()->dropAllTables();
        $this->app->forgetInstance(DatabaseRestoreProcess::class);
        config(['database.connections.restore_overlap' => $this->target->getConfig()]);
        $second = DB::connection('restore_overlap');
        (new RestoreTarget)->assertSafe($second, 'testing');
        $this->assertNotSame($this->target->selectOne('SELECT CONNECTION_ID() AS id')->id, $second->selectOne('SELECT CONNECTION_ID() AS id')->id);
        $overlapAttempted = false;
        $beforeImport = function () use ($second, $options, &$overlapAttempted): void {
            // First CLI holds its target named lock but has not yet sent the actual dump to mariadb.
            $this->assertSame(0, (int) $second->selectOne('SELECT IS_FREE_LOCK(?) AS available', [RestoreDatabaseLock::NAME])->available);
            try {
                app(RestoreBundle::class)->restore($second, 'testing', $options['bundle'], $options['--manifest-sha256'], $this->directory.'/overlap-media', base_path(), 60);
                $this->fail('A second importer entered the held isolated target.');
            } catch (BackupFailure $failure) {
                $this->assertStringContainsString('Another isolated restore owns', $failure->getMessage());
            }
            (new RestoreTarget)->assertSafe($second, 'testing');
            $this->assertDirectoryDoesNotExist($this->directory.'/overlap-media');
            $this->assertCount(1, glob($this->directory.'/*.partial'), 'Second CLI must clean only its staging files, leaving the first intact.');
            $overlapAttempted = true;
        };
        $overlapImporter = new class($beforeImport) extends DatabaseRestoreProcess
        {
            private int $calls = 0;

            public function __construct(private \Closure $beforeImport) {}

            public function run(array $arguments, string $sql, int $timeout): void
            {
                if (++$this->calls !== 1) {
                    throw new \RuntimeException('Second importer must never receive SQL while the first target lock is held.');
                }
                ($this->beforeImport)();
                parent::run($arguments, $sql, $timeout);
            }
        };
        $this->app->instance(DatabaseRestoreProcess::class, $overlapImporter);
        $restoreOutput = new BufferedOutput;
        $restoreExit = Artisan::call('lessons:restore-test', $options, $restoreOutput);
        $restoreText = $restoreOutput->fetch();
        $this->assertSame(0, $restoreExit, $restoreText);
        $this->assertTrue($overlapAttempted, 'The overlapping two-connection check must precede the real SQL import.');
        $this->assertSame(1, (int) $second->selectOne('SELECT IS_FREE_LOCK(?) AS available', [RestoreDatabaseLock::NAME])->available, 'Successful restore must release its named lock.');
        $this->app->forgetInstance(DatabaseRestoreProcess::class);
        $result = json_decode($restoreText, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['database' => 'lessons_restore_test', 'mediaVersions' => 3, 'unreferencedMediaVersions' => 1, 'manifestSha256' => $hash], $result);
        $restored = $this->target->table('lesson_versions')->where('id', $version->id)->first();
        $this->assertSame($expectedDocument, json_decode($restored->document, true, flags: JSON_THROW_ON_ERROR));
        $restoredAsset = $this->target->table('media_assets')->where('id', $asset->id)->first();
        $this->assertSame($current->id, $restoredAsset->current_version_id);
        $this->assertSame(1, (int) $restoredAsset->archived);
        foreach ([$old, $current] as $media) {
            $row = $this->target->table('media_versions')->where('id', $media->id)->first();
            $this->assertSame($media->sha256, $row->sha256);
            $this->assertSame(file_get_contents($this->directory.'/source-media/'.$media->storage_key), file_get_contents($options['--media'].'/'.$media->storage_key));
            $this->assertSame(0600, fileperms($options['--media'].'/'.$media->storage_key) & 0777);
        }
        $this->assertNull($this->target->table('media_versions')->where('id', $concurrent->id)->first());
        $this->assertNotNull($this->source->table('media_versions')->where('id', $concurrent->id)->first());
        $this->assertSame($concurrent->sha256, hash_file('sha256', $options['--media'].'/'.$concurrent->storage_key));
        $this->assertSame(0700, fileperms($options['--media']) & 0777);
        $this->assertSame([], glob($this->directory.'/*.partial'));
        $this->assertEquals($sourceSessions, $this->target->table('teaching_sessions')->whereIn('id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceAnswers, $this->target->table('session_answers')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceParticipants, $this->target->table('session_participants')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceReceipts, $this->target->table('session_command_receipts')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        (new RestoreTarget)->assertSafe($this->target, 'testing', empty: false);
        $this->assertRestoredRuntimeFixture($this->owner, $runtimeFixture);
        $this->assertSame(1, Artisan::call('lessons:restore-test', $options));
        $this->assertStringContainsString('must be empty', Artisan::output());
        $this->assertSame($expectedDocument, json_decode($this->source->table('lesson_versions')->where('id', $version->id)->value('document'), true));
        $this->assertEquals($sourceSessions, $this->source->table('teaching_sessions')->whereIn('id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceAnswers, $this->source->table('session_answers')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceParticipants, $this->source->table('session_participants')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
        $this->assertEquals($sourceReceipts, $this->source->table('session_command_receipts')->whereIn('teaching_session_id', $this->restoreSessionIds)->orderBy('id')->get()->all());
    }

    private function version(MediaAsset $asset, int $number): MediaVersion
    {
        $image = imagecreatetruecolor($number, 1);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $id = (string) Str::uuid();
        $key = 'versions/'.$asset->id.'/'.$id.'.png';
        $path = $this->directory.'/source-media/'.$key;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $bytes);

        return MediaVersion::forceCreate(['id' => $id, 'media_asset_id' => $asset->id, 'version_no' => $number, 'storage_key' => $key,
            'mime' => 'image/png', 'bytes' => strlen($bytes), 'width' => $number, 'height' => 1, 'sha256' => hash('sha256', $bytes), 'attribution' => []]);
    }

    private function removeFixture(string $path): void
    {
        $root = str_replace('\\', '/', realpath($this->directory));
        $actual = str_replace('\\', '/', realpath($path));
        $this->assertFalse(is_link($path));
        $this->assertTrue($actual === $root || str_starts_with($actual, $root.'/'));
        if (is_dir($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $name) {
                $this->removeFixture($path.'/'.$name);
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}
