<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Models\GuestWorkspaceClaim;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\MediaAsset;
use App\Models\MediaOwnerQuota;
use App\Models\MediaVersion;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\RuntimeConcurrencyGuard;
use Tests\TestCase;

final class ClaimConcurrencyTest extends TestCase
{
    private ?Connection $database = null;

    private ?string $source = null;

    private ?string $target = null;

    private ?string $runId = null;

    private ?string $directory = null;

    private ?User $user = null;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY === 'Windows' || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Claim concurrency requires Linux and dedicated loopback MariaDB lessons_test.');
        }
        $this->database = RuntimeConcurrencyGuard::database($this->app);
        if (! preg_match('/\A10\.6\..*MariaDB/', $this->database->selectOne('SELECT VERSION() AS version')->version)) {
            $this->markTestSkipped('Claim lock-wait observation requires MariaDB 10.6.');
        }
        $this->assertSame(0, $this->database->transactionLevel());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
        $this->source = (string) Str::uuid();
        $this->target = (string) Str::uuid();
        $this->runId = (string) Str::uuid();
        $this->directory = RuntimeConcurrencyGuard::directory($this->runId);
        $mask = umask(0077);
        try {
            $this->assertTrue(mkdir($this->directory, 0700));
        } finally {
            umask($mask);
        }
        config(['filesystems.disks.media.root' => $this->directory.'/media']);
        $this->user = User::factory()->create(['email' => 'claim-'.$this->runId.'@example.test']);
        $this->user->forceFill(['owner_key' => $this->target])->save();
        OwnerMutation::transaction([$this->source, $this->target], fn () => null);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            if ($this->database !== null && $this->database->transactionLevel() > 0) {
                $this->database->rollBack();
            }
            if ($this->source !== null && $this->target !== null) {
                RuntimeConcurrencyGuard::database($this->app);
                $owners = [$this->source, $this->target];
                $materials = LessonMaterial::query()->whereIn('owner_key', $owners)->get();
                foreach ($materials as $material) {
                    $this->assertSame('Claim concurrency fixture '.$this->runId, $material->currentVersion->document['content']['ru']['title']);
                    $material->current_version_id = null;
                    $material->save();
                    LessonVersion::query()->where('lesson_material_id', $material->id)->delete();
                    $material->delete();
                }
                foreach (MediaAsset::query()->whereIn('owner_key', $owners)->get() as $asset) {
                    $this->assertSame('Claim concurrency fixture '.$this->runId, $asset->title);
                    foreach ($asset->versions as $version) {
                        Storage::disk('media')->delete($version->storage_key);
                    }
                    $asset->current_version_id = null;
                    $asset->save();
                    $asset->versions()->delete();
                    $asset->delete();
                }
                GuestWorkspaceClaim::query()->whereKey($this->source)->where('target_user_id', $this->user?->id)->delete();
                MediaOwnerQuota::query()->whereIn('owner_key', $owners)->delete();
                $this->user?->delete();
            }
            if ($this->directory !== null && is_dir($this->directory)) {
                $directory = RuntimeConcurrencyGuard::privateDirectory($this->runId);
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $file) {
                    $this->assertFalse($file->isLink());
                    $this->assertStringStartsWith($directory.'/', str_replace('\\', '/', $file->getRealPath()));
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($directory);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_claim_serializes_with_guest_create_without_orphan_or_duplicate_ownership(): void
    {
        $this->race('create');
    }

    public function test_claim_serializes_with_guest_upload_and_preserves_exact_quota_and_private_files(): void
    {
        $this->race('upload');
    }

    private function race(string $write): void
    {
        $document = ['id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Claim concurrency fixture '.$this->runId]], 'stages' => [
                ['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [
                    ['id' => 'text', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Fixture']]],
                ]],
            ]];
        $material = app(StudioService::class)->create($this->source, $document);
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $this->directory.'/image.png');
        imagedestroy($image);
        chmod($this->directory.'/image.png', 0600);
        $this->privateFile('fixture.json', json_encode(['source' => $this->source, 'target' => $this->target, 'userId' => $this->user->id,
            'materialId' => $material->id, 'write' => $write], JSON_THROW_ON_ERROR));
        $config = $this->database->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'),
            'APP_CONFIG_CACHE' => $this->directory.'/unused-config-cache.php', 'DB_CONNECTION' => $config['driver'],
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) ($config['port'] ?? 3306), 'DB_DATABASE' => 'lessons_test',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'];
        $parent = (int) $this->database->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $this->database->beginTransaction();
        try {
            $owners = [$this->source, $this->target];
            sort($owners, SORT_STRING);
            foreach ($owners as $owner) {
                MediaOwnerQuota::query()->whereKey($owner)->lockForUpdate()->firstOrFail();
            }
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/claim_concurrency_worker.php')], base_path(), $environment,
                    json_encode(['runId' => $this->runId, 'worker' => $index], JSON_THROW_ON_ERROR), 45);
                $process->start();
                $this->processes[] = $process;
            }
            $this->waitUntil(fn () => is_file($this->directory.'/ready-0') && is_file($this->directory.'/ready-1'));
            $workers = [(int) file_get_contents($this->directory.'/ready-0'), (int) file_get_contents($this->directory.'/ready-1')];
            $this->assertCount(3, array_unique([$parent, ...$workers]));
            $this->privateFile('go', 'start');
            $this->waitUntil(function () use ($workers, $parent): bool {
                $waiting = $this->database->select('SELECT DISTINCT requesting.trx_mysql_thread_id AS connection_id
                    FROM information_schema.INNODB_LOCK_WAITS waits
                    JOIN information_schema.INNODB_TRX requesting ON requesting.trx_id = waits.requesting_trx_id
                    JOIN information_schema.INNODB_TRX blocking ON blocking.trx_id = waits.blocking_trx_id
                    WHERE blocking.trx_mysql_thread_id IN (?, ?, ?) AND requesting.trx_mysql_thread_id IN (?, ?)', [$parent, ...$workers, ...$workers]);

                return count($waiting) === 2;
            });
        } finally {
            $this->database->rollBack();
        }
        $results = [];
        foreach ($this->processes as $process) {
            $this->assertSame(0, $process->wait(), 'Infrastructure failures must not be treated as a race loser.');
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertSame($workers, array_column($results, 'connectionId'));
        $this->assertSame(200, $results[0]['status']);
        $this->assertContains($results[1]['status'], [200, 409]);
        if ($results[1]['status'] === 409) {
            $this->assertSame('identity_changed', $results[1]['code']);
        }
        $this->assertSame(0, LessonMaterial::query()->where('owner_key', $this->source)->count());
        $this->assertSame(0, MediaAsset::query()->where('owner_key', $this->source)->count());
        $this->assertSame($this->target, $material->fresh()->owner_key);
        $this->assertSame($write === 'create' && $results[1]['status'] === 200 ? 2 : 1, LessonMaterial::query()->where('owner_key', $this->target)->count());
        $versions = MediaVersion::query()->whereHas('asset', fn ($query) => $query->where('owner_key', $this->target))->get();
        $this->assertCount($write === 'upload' && $results[1]['status'] === 200 ? 1 : 0, $versions);
        $this->assertCount($versions->count(), Storage::disk('media')->allFiles());
        foreach ($versions as $version) {
            $this->assertSame($version->sha256, hash('sha256', Storage::disk('media')->get($version->storage_key)));
            $this->assertSame($version->bytes, strlen(Storage::disk('media')->get($version->storage_key)));
        }
        $this->assertSame((int) $versions->sum('bytes'), MediaOwnerQuota::findOrFail($this->target)->used_bytes);
        $this->assertSame(0, MediaOwnerQuota::findOrFail($this->source)->used_bytes);
        $this->assertSame(1, GuestWorkspaceClaim::query()->whereKey($this->source)->count());
    }

    private function waitUntil(callable $condition): void
    {
        $deadline = microtime(true) + 15;
        do {
            clearstatcache();
            foreach ($this->processes as $process) {
                $this->assertTrue($process->isRunning(), 'Both isolated operations must remain alive behind the owner mutex.');
            }
            if ($condition()) {
                return;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);
        $this->fail('Both operations must overlap in observed InnoDB lock waits.');
    }

    private function privateFile(string $name, string $contents): void
    {
        $file = fopen($this->directory.'/'.$name, 'x');
        $this->assertNotFalse($file);
        try {
            $this->assertTrue(chmod($this->directory.'/'.$name, 0600));
            $this->assertSame(strlen($contents), fwrite($file, $contents));
        } finally {
            fclose($file);
        }
    }
}
