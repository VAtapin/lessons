<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Studio\StudioService;
use App\Models\LessonMaterial;
use App\Models\LessonSaveReceipt;
use App\Models\LessonVersion;
use App\Models\MediaOwnerQuota;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\EditorFixture;
use Tests\Support\RuntimeConcurrencyGuard;
use Tests\TestCase;

/** Independent PHP connections and observed InnoDB waits; no fake overlap or hidden test transaction. */
final class EditorSaveConcurrencyTest extends TestCase
{
    use EditorFixture;

    private ?Connection $database = null;

    private ?string $materialId = null;

    private ?string $owner = null;

    private ?string $runId = null;

    private ?string $directory = null;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY === 'Windows' || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Editor concurrency requires Linux and dedicated loopback MariaDB lessons_test.');
        }
        $this->database = RuntimeConcurrencyGuard::database($this->app);
        if (! preg_match('/\A10\.6\..*MariaDB/', $this->database->selectOne('SELECT VERSION() AS version')->version)) {
            $this->markTestSkipped('Editor lock-wait observation requires MariaDB 10.6.');
        }
        $this->assertSame(0, $this->database->transactionLevel());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
        $this->runId = (string) Str::uuid();
        $this->owner = (string) Str::uuid();
        $this->directory = RuntimeConcurrencyGuard::directory($this->runId);
        $mask = umask(0077);
        try {
            $this->assertTrue(mkdir($this->directory, 0700));
        } finally {
            umask($mask);
        }
        $document = $this->editorDocument();
        $document['content']['ru']['title'] = 'Editor concurrency fixture '.$this->runId;
        $this->materialId = app(StudioService::class)->create($this->owner, $document)->id;
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
            if ($this->materialId !== null) {
                RuntimeConcurrencyGuard::database($this->app);
                $this->database->transaction(function (): void {
                    $material = LessonMaterial::query()->whereKey($this->materialId)->where('owner_key', $this->owner)->firstOrFail();
                    LessonSaveReceipt::query()->where('lesson_material_id', $material->id)->delete();
                    $material->current_version_id = null;
                    $material->save();
                    LessonVersion::query()->where('lesson_material_id', $material->id)->delete();
                    $material->delete();
                    MediaOwnerQuota::query()->whereKey($this->owner)->where('used_bytes', 0)->delete();
                });
            }
            if ($this->directory !== null && is_dir($this->directory)) {
                $directory = RuntimeConcurrencyGuard::privateDirectory($this->runId);
                foreach (scandir($directory) as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $this->assertFalse(is_dir($directory.'/'.$entry));
                        unlink($directory.'/'.$entry);
                    }
                }
                rmdir($directory);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_different_save_ids_same_expected_revision_have_one_winner_and_preserve_strict_baseline(): void
    {
        $before = LessonVersion::where('lesson_material_id', $this->materialId)->firstOrFail()->document;
        $first = $this->body('First working title');
        $second = $this->body('Second working title');
        $results = $this->race([$first, $second]);
        $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'));
        $loser = array_search(409, array_column($results, 'status'), true);
        $winner = 1 - $loser;
        $this->assertSame('revision_conflict', $results[$loser]['code']);
        $this->assertSame(2, $results[$winner]['appliedRevision']);
        $material = LessonMaterial::with('currentVersion')->findOrFail($this->materialId);
        $this->assertSame(2, $material->revision);
        $this->assertSame([$first, $second][$winner]['document']['content']['ru']['title'], $material->currentVersion->editor_draft['content']['ru']['title']);
        $this->assertSame($before, $material->currentVersion->document);
        $this->assertSame(1, LessonSaveReceipt::where('lesson_material_id', $this->materialId)->count());
        $this->assertSame([$first, $second][$winner]['saveId'], LessonSaveReceipt::where('lesson_material_id', $this->materialId)->firstOrFail()->save_id);
        $this->assertSame(1, LessonVersion::where('lesson_material_id', $this->materialId)->count());
    }

    public function test_concurrent_same_uuid_fingerprint_applies_exactly_once_and_acknowledges_both_workers(): void
    {
        $body = $this->body('Single accepted edit');
        $second = $body;
        $second['saveId'] = strtoupper($body['saveId']);
        $results = $this->race([$body, $second]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame([2, 2], array_column($results, 'appliedRevision'));
        $this->assertSame([$body['saveId'], $body['saveId']], array_column($results, 'acknowledgedSaveId'));
        $this->assertSame($results[0]['appliedVersionId'], $results[1]['appliedVersionId']);
        $this->assertSame(2, LessonMaterial::findOrFail($this->materialId)->revision);
        $this->assertSame(1, LessonSaveReceipt::where('lesson_material_id', $this->materialId)->count());
        $this->assertSame(1, LessonVersion::where('lesson_material_id', $this->materialId)->count());
    }

    private function body(string $title): array
    {
        $material = LessonMaterial::with('currentVersion')->findOrFail($this->materialId);
        $document = $this->blankLocale($material->currentVersion->document, 'de');
        $document['content']['ru']['title'] = $title;

        return ['saveId' => (string) Str::uuid(), 'expectedRevision' => 1, 'document' => $document];
    }

    private function race(array $operations): array
    {
        $this->privateFile('fixture.json', json_encode(['ownerKey' => $this->owner, 'materialId' => $this->materialId, 'operations' => $operations], JSON_THROW_ON_ERROR));
        $config = $this->database->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'), 'APP_CONFIG_CACHE' => $this->directory.'/unused-config-cache.php',
            'DB_CONNECTION' => $config['driver'], 'DB_HOST' => $config['host'], 'DB_PORT' => (string) ($config['port'] ?? 3306), 'DB_DATABASE' => 'lessons_test',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'];
        $parentId = (int) $this->database->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $this->database->beginTransaction();
        try {
            $this->database->table('lesson_materials')->where('id', $this->materialId)->lockForUpdate()->first();
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/editor_save_concurrency_worker.php')], base_path(), $environment,
                    json_encode(['runId' => $this->runId, 'worker' => $index], JSON_THROW_ON_ERROR), 45);
                $process->start();
                $this->processes[] = $process;
            }
            $this->waitUntil(fn () => is_file($this->directory.'/ready-0') && is_file($this->directory.'/ready-1'));
            $workerIds = [(int) file_get_contents($this->directory.'/ready-0'), (int) file_get_contents($this->directory.'/ready-1')];
            $this->assertCount(3, array_unique([$parentId, ...$workerIds]));
            $this->privateFile('go', 'start');
            $this->waitUntil(function () use ($parentId, $workerIds): bool {
                $waiting = $this->database->select('SELECT DISTINCT requesting.trx_mysql_thread_id AS connection_id
                    FROM information_schema.INNODB_LOCK_WAITS waits
                    JOIN information_schema.INNODB_TRX requesting ON requesting.trx_id = waits.requesting_trx_id
                    JOIN information_schema.INNODB_TRX blocking ON blocking.trx_id = waits.blocking_trx_id
                    WHERE blocking.trx_mysql_thread_id IN (?, ?, ?) AND requesting.trx_mysql_thread_id IN (?, ?)', [$parentId, ...$workerIds, ...$workerIds]);

                return count($waiting) === 2;
            });
        } finally {
            $this->database->rollBack();
        }
        $results = [];
        foreach ($this->processes as $process) {
            $this->assertSame(0, $process->wait(), 'Isolated editor worker must finish successfully.');
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertSame($workerIds, array_column($results, 'connectionId'));

        return $results;
    }

    private function waitUntil(callable $condition): void
    {
        $deadline = microtime(true) + 15;
        do {
            clearstatcache();
            foreach ($this->processes as $process) {
                $this->assertTrue($process->isRunning(), 'Both real workers must remain alive at the overlap barrier.');
            }
            if ($condition()) {
                return;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);
        $this->fail('Both editor operations must overlap in observed InnoDB lock waits.');
    }

    private function privateFile(string $name, string $contents): void
    {
        $path = $this->directory.'/'.$name;
        $file = fopen($path, 'x');
        $this->assertNotFalse($file);
        try {
            $this->assertTrue(chmod($path, 0600));
            $this->assertSame(strlen($contents), fwrite($file, $contents));
        } finally {
            fclose($file);
        }
    }
}
