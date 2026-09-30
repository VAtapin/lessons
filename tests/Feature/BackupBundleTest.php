<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupBundle;
use App\Application\Operations\BackupFailure;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\DatabaseDumpProcess;
use App\Application\Operations\PrivateBackupFiles;
use App\Models\MediaAsset;
use App\Models\MediaVersion;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

final class BackupBundleTest extends TestCase
{
    use RefreshDatabase;

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = str_replace('\\', '/', sys_get_temp_dir()).'/lessons-bundle-test-'.bin2hex(random_bytes(8));
        mkdir($this->temporary, 0700);
    }

    protected function tearDown(): void
    {
        $this->removeTemporary($this->temporary);
        parent::tearDown();
    }

    public function test_bundle_contains_verified_sql_and_every_immutable_version_including_archived_assets(): void
    {
        $asset = $this->asset(true);
        $old = $this->version($asset, 'old immutable fixture');
        $current = $this->version($asset, 'new immutable fixture');
        $asset->update(['current_version_id' => $current->id]);
        $other = $this->version($this->asset(), 'second owner fixture');
        $bundle = $this->create($this->backup());
        $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['formatVersion']);
        $this->assertNotFalse(strtotime($manifest['createdAt']));
        $this->assertSame(['file' => 'database.sql', 'bytes' => filesize($bundle.'/database.sql'),
            'sha256' => hash_file('sha256', $bundle.'/database.sql')], $manifest['database']);
        $this->assertCount(3, $manifest['media']);
        foreach ([$old, $current, $other] as $version) {
            $entry = collect($manifest['media'])->firstWhere('versionId', $version->id);
            $this->assertSame(['assetId' => $version->media_asset_id, 'versionId' => $version->id,
                'versionNo' => $version->version_no, 'file' => 'media/'.$version->storage_key,
                'bytes' => $version->bytes, 'sha256' => $version->sha256], $entry);
            $this->assertSame(file_get_contents($this->temporary.'/media/'.$version->storage_key), file_get_contents($bundle.'/'.$entry['file']));
        }
        $this->assertSame([$bundle], glob($this->temporary.'/backups/*'));
        $this->assertSame(['database.sql', 'manifest.json', 'media'], array_values(array_diff(scandir($bundle), ['.', '..'])));
        $this->assertModes($bundle);
        $this->assertStringNotContainsString('test-only-password', file_get_contents($bundle.'/manifest.json'));
    }

    public function test_versions_committed_after_sql_are_included_as_safe_extra_files(): void
    {
        $asset = $this->asset();
        $before = $this->version($asset, 'before SQL fixture');
        $after = null;
        $backup = $this->backup(function () use ($asset, &$after): void {
            $after = $this->version($asset, 'after SQL fixture');
        });
        $bundle = $this->create($backup);
        $entries = json_decode(file_get_contents($bundle.'/manifest.json'), true)['media'];
        $this->assertEqualsCanonicalizing([$before->id, $after->id], array_column($entries, 'versionId'));
    }

    public function test_empty_media_library_without_storage_directory_still_creates_complete_bundle(): void
    {
        $bundle = $this->create($this->backup());
        $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true);
        $this->assertSame([], $manifest['media']);
        $this->assertFileExists($bundle.'/database.sql');
        $this->assertDirectoryDoesNotExist($this->temporary.'/media');
    }

    public function test_missing_corrupt_or_size_changed_media_fails_and_preserves_previous_bundle_and_source_files(): void
    {
        $version = $this->version($this->asset(), 'immutable fixture');
        $previous = $this->create($this->backup());
        $manifestBefore = file_get_contents($previous.'/manifest.json');
        $source = $this->temporary.'/media/'.$version->storage_key;
        foreach (['missing', 'corrupt', 'size'] as $failure) {
            if ($failure === 'missing') {
                unlink($source);
            } else {
                file_put_contents($source, $failure === 'corrupt' ? str_repeat('x', $version->bytes) : 'short');
            }
            try {
                $this->create($this->backup());
                $this->fail('Missing or corrupt media must fail closed.');
            } catch (BackupFailure) {
                $this->assertSame([$previous], glob($this->temporary.'/backups/*'));
                $this->assertSame($manifestBefore, file_get_contents($previous.'/manifest.json'));
            }
            if ($failure !== 'missing') {
                $this->assertFileExists($source);
            }
        }
    }

    public function test_dump_failure_removes_only_new_partial_bundle_and_masks_private_diagnostics(): void
    {
        $previous = $this->create($this->backup());
        try {
            $this->create($this->backup(function (): void {
                throw new RuntimeException('test-only-password SQL private diagnostic');
            }));
            $this->fail('A failed dump must fail the entire bundle.');
        } catch (BackupFailure $failure) {
            $this->assertStringNotContainsString('test-only-password', $failure->getMessage());
            $this->assertSame([$previous], glob($this->temporary.'/backups/*'));
        }
    }

    public function test_noncanonical_storage_keys_or_mismatched_exact_ids_are_rejected_without_external_reads(): void
    {
        $version = $this->version($this->asset(), 'fixture');
        $outside = $this->temporary.'/outside-secret';
        file_put_contents($outside, 'must not copy');
        foreach (['../outside-secret', $outside, 'versions/'.$version->media_asset_id.'/'.Str::uuid().'.png',
            str_replace('versions/', 'versions//', $version->storage_key)] as $key) {
            DB::table('media_versions')->where('id', $version->id)->update(['storage_key' => $key]);
            try {
                $this->create($this->backup());
                $this->fail('Unsafe storage keys must fail.');
            } catch (BackupFailure $failure) {
                $this->assertStringContainsString('canonical storage key', $failure->getMessage());
                $this->assertSame([], glob($this->temporary.'/backups/*'));
                $this->assertSame('must not copy', file_get_contents($outside));
            }
        }
    }

    public function test_symlinked_source_and_symlinked_media_root_are_rejected(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Linux CI enforces symlink rejection; Windows needs symlink privileges.');
        }
        $version = $this->version($this->asset(), 'fixture');
        $source = $this->temporary.'/media/'.$version->storage_key;
        rename($source, $this->temporary.'/outside');
        symlink($this->temporary.'/outside', $source);
        try {
            $this->create($this->backup());
            $this->fail('Symlinked source must fail.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('symbolic links', $failure->getMessage());
            $this->assertSame([], glob($this->temporary.'/backups/*'));
        }
        rename($this->temporary.'/media', $this->temporary.'/real-media');
        symlink($this->temporary.'/real-media', $this->temporary.'/media');
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('symbolic links');
        $this->create($this->backup());
    }

    public function test_unknown_media_schema_is_rejected_before_dump_and_initialization_has_separate_path(): void
    {
        $database = new SQLiteConnection(new \PDO('sqlite::memory:'), '', '', $this->connectionConfig());
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        try {
            $this->backup(process: $process)->create($database, $this->temporary.'/backups', base_path(), $this->temporary.'/media', 30);
            $this->fail('Unknown schema must fail.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('Media schema is missing or incompatible', $failure->getMessage());
            $this->assertDirectoryDoesNotExist($this->temporary.'/backups');
        }
        $script = file_get_contents(base_path('scripts/deploy-plesk.sh'));
        $this->assertStringContainsString('php artisan lessons:database-preflight --empty', $script);
        $this->assertStringContainsString('php artisan lessons:backup', $script);
        $this->assertStringNotContainsString('php artisan lessons:database-backup', $script);
    }

    public function test_unsafe_backup_and_media_paths_are_rejected_without_creating_artifacts(): void
    {
        foreach ([base_path('public/backups'), 'relative/backups', $this->temporary.'/../escape'] as $directory) {
            try {
                $this->backup()->create($this->database(), $directory, base_path(), $this->temporary.'/media', 30);
                $this->fail('Unsafe backup paths must fail.');
            } catch (BackupFailure $failure) {
                $this->assertStringContainsString('Backup directory', $failure->getMessage());
            }
        }
        foreach (['relative/media', $this->temporary.'/../media', $this->temporary.'/backups/media'] as $media) {
            try {
                $this->backup()->create($this->database(), $this->temporary.'/backups', base_path(), $media, 30);
                $this->fail('Unsafe media paths must fail.');
            } catch (BackupFailure) {
                $this->assertDirectoryDoesNotExist($this->temporary.'/backups');
            }
        }
    }

    public function test_cleanup_refuses_arbitrary_directories_and_never_follows_child_symlinks(): void
    {
        $outside = $this->temporary.'/keep';
        mkdir($outside, 0700);
        file_put_contents($outside.'/keep.txt', 'preserve');
        $files = new PrivateBackupFiles;
        try {
            $files->cleanup($outside, $this->temporary);
            $this->fail('Arbitrary recursive deletion must be refused.');
        } catch (BackupFailure) {
            $this->assertFileExists($outside.'/keep.txt');
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            $partial = $this->temporary.'/lessons-20261001-120000-0123456789abcdef.partial';
            mkdir($partial, 0700);
            symlink($outside, $partial.'/linked-child');
            $files->cleanup($partial, $this->temporary);
            $this->assertDirectoryDoesNotExist($partial);
            $this->assertSame('preserve', file_get_contents($outside.'/keep.txt'));
        }
    }

    public function test_existing_world_readable_backup_directory_is_rejected_without_touching_old_files(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Exact POSIX permissions are enforced by Linux CI and production.');
        }
        $directory = $this->temporary.'/backups';
        mkdir($directory, 0755);
        chmod($directory, 0755);
        file_put_contents($directory.'/previous', 'preserve');
        try {
            $this->create($this->backup());
            $this->fail('A public backup directory must be rejected.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('permissions', $failure->getMessage());
            $this->assertSame([$directory.'/previous'], glob($directory.'/*'));
            $this->assertSame('preserve', file_get_contents($directory.'/previous'));
        }
    }

    public function test_command_success_reports_bundle_path_and_manifest_checksum_without_private_content(): void
    {
        $this->version($this->asset(), 'private media fixture');
        config(['filesystems.disks.media.root' => $this->temporary.'/media']);
        $this->app->instance(BackupBundle::class, $this->backup());
        $database = $this->database();
        $manager = DB::getFacadeRoot();
        try {
            DB::shouldReceive('connection')->once()->andReturn($database);
            $this->assertSame(0, Artisan::call('lessons:backup', ['--directory' => $this->temporary.'/backups', '--timeout' => 30]));
            $output = Artisan::output();
            $bundle = glob($this->temporary.'/backups/*')[0];
            $this->assertStringContainsString($bundle, $output);
            $this->assertStringContainsString(hash_file('sha256', $bundle.'/manifest.json'), $output);
            foreach (['private media fixture', 'private test title', 'test-only-password', 'test fixture SQL'] as $private) {
                $this->assertStringNotContainsString($private, $output);
            }
        } finally {
            DB::swap($manager);
        }
    }

    public function test_command_rejects_invalid_options_and_masks_database_exceptions(): void
    {
        foreach (['0', '3601', 'invalid'] as $timeout) {
            $this->assertSame(1, Artisan::call('lessons:backup', ['--timeout' => $timeout]));
            $this->assertStringContainsString('timeout must be an integer', Artisan::output());
        }
        config(['filesystems.disks.media.driver' => 's3']);
        $this->assertSame(1, Artisan::call('lessons:backup'));
        $this->assertStringContainsString('configured local media disk', Artisan::output());
        config(['filesystems.disks.media.driver' => 'local']);
        $manager = DB::getFacadeRoot();
        try {
            DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('private-password-SQL'));
            $this->assertSame(1, Artisan::call('lessons:backup'));
            $this->assertStringNotContainsString('private-password', Artisan::output());
        } finally {
            DB::swap($manager);
        }
    }

    private function asset(bool $archived = false): MediaAsset
    {
        return MediaAsset::create(['owner_key' => (string) Str::uuid(), 'title' => 'private test title', 'tags' => [],
            'author' => 'test', 'source' => 'test', 'rights_basis' => 'self_created', 'usage_rights' => 'test',
            'revision' => 1, 'archived' => $archived]);
    }

    private function version(MediaAsset $asset, string $contents): MediaVersion
    {
        $id = (string) Str::uuid();
        $key = 'versions/'.$asset->id.'/'.$id.'.png';
        $path = $this->temporary.'/media/'.$key;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $contents);

        return MediaVersion::forceCreate(['id' => $id, 'media_asset_id' => $asset->id,
            'version_no' => (int) $asset->versions()->max('version_no') + 1, 'storage_key' => $key,
            'mime' => 'image/png', 'bytes' => strlen($contents), 'width' => 1, 'height' => 1,
            'sha256' => hash('sha256', $contents), 'attribution' => []]);
    }

    private function backup(?callable $duringDump = null, ?DatabaseDumpProcess $process = null): BackupBundle
    {
        if ($process === null) {
            $process = Mockery::mock(DatabaseDumpProcess::class);
            $process->shouldReceive('run')->andReturnUsing(function (array $arguments) use ($duringDump): void {
                $result = collect($arguments)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));
                file_put_contents(substr($result, strlen('--result-file=')), "-- test fixture SQL, not an integration dump\n");
                if ($duringDump !== null) {
                    $duringDump();
                }
            });
        }
        $finder = Mockery::mock(ExecutableFinder::class);
        $finder->shouldReceive('find')->andReturn('/trusted/mariadb-dump');

        return new BackupBundle(new DatabaseBackup($process, $finder), new PrivateBackupFiles);
    }

    private function create(BackupBundle $backup): string
    {
        return $backup->create($this->database(), $this->temporary.'/backups', base_path(), $this->temporary.'/media', 30);
    }

    private function database(): Connection
    {
        $database = DB::connection();

        // Query the real test schema while faking only the external dump executable.
        return $database->getDriverName() === 'sqlite'
            ? new SQLiteConnection($database->getPdo(), '', '', $this->connectionConfig()) : $database;
    }

    private function connectionConfig(): array
    {
        return ['driver' => 'mysql', 'database' => 'lessons_test', 'username' => 'test', 'password' => 'test-only-password'];
    }

    private function assertModes(string $path): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(is_dir($path) ? 0700 : 0600, fileperms($path) & 0777);
        }
        if (is_dir($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $this->assertModes($path.'/'.$entry);
            }
        }
    }

    private function removeTemporary(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
                $this->removeTemporary($path.'/'.$entry);
            }
            rmdir($path);
        }
    }
}
