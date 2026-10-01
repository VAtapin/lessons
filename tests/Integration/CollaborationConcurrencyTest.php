<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Collaboration\TeacherInvitations;
use App\Application\Runtime\RuntimeService;
use App\Application\Studio\StudioService;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\MediaOwnerQuota;
use App\Models\TeacherGrant;
use App\Models\TeachingSession;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\RuntimeConcurrencyGuard;
use Tests\TestCase;

/** Independent real connections, committed fixtures, observed InnoDB waits. */
final class CollaborationConcurrencyTest extends TestCase
{
    private ?Connection $database = null;

    private ?string $owner = null;

    private ?string $materialId = null;

    private ?string $sessionId = null;

    private ?string $runId = null;

    private ?string $directory = null;

    private string $token;

    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY === 'Windows' || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Collaboration concurrency requires Linux and dedicated loopback MariaDB lessons_test.');
        }
        $this->database = RuntimeConcurrencyGuard::database($this->app);
        if (! preg_match('/\A10\.6\..*MariaDB/', $this->database->selectOne('SELECT VERSION() AS version')->version)) {
            $this->markTestSkipped('Observed InnoDB lock waits require MariaDB 10.6.');
        }
        $this->assertSame(0, $this->database->transactionLevel());
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]));
        $this->owner = (string) Str::uuid();
        $this->runId = (string) Str::uuid();
        $this->directory = RuntimeConcurrencyGuard::directory($this->runId);
        $mask = umask(0077);
        try {
            $this->assertTrue(mkdir($this->directory, 0700));
        } finally {
            umask($mask);
        }
        $document = ['id' => 'collaboration-race', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Collaboration concurrency fixture '.$this->runId]],
            'stages' => [['id' => 'first', 'content' => ['ru' => ['title' => 'First']],
                'blocks' => [['id' => 'text', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Fixture']]]]]]];
        $material = app(StudioService::class)->create($this->owner, $document);
        $this->materialId = $material->id;
        $state = app(RuntimeService::class)->start($this->owner, $material->id, $material->revision, 'ru');
        $this->sessionId = $state['id'];
        $invite = app(TeacherInvitations::class)->create(TeachingSession::query()->findOrFail($this->sessionId), []);
        $this->token = substr($invite['url'], strpos($invite['url'], '#token=') + 7);
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
                $session = TeachingSession::query()->where('owner_key', $this->owner)->findOrFail($this->sessionId);
                $this->assertSame('Collaboration concurrency fixture '.$this->runId, $session->version->document['content']['ru']['title']);
                $session->delete();
                LessonMaterial::query()->whereKey($this->materialId)->where('owner_key', $this->owner)->update(['current_version_id' => null]);
                LessonVersion::query()->where('lesson_material_id', $this->materialId)->delete();
                LessonMaterial::query()->whereKey($this->materialId)->where('owner_key', $this->owner)->delete();
                MediaOwnerQuota::query()->whereKey($this->owner)->delete();
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

    public function test_one_invitation_has_exactly_one_accepting_browser(): void
    {
        $results = $this->race([['kind' => 'accept'], ['kind' => 'accept']]);
        $this->assertEqualsCanonicalizing([200, 404], array_column($results, 'status'));
        $this->assertSame(1, TeacherGrant::query()->where('teaching_session_id', $this->sessionId)->count());
        $this->assertSame(1, TeachingSession::query()->findOrFail($this->sessionId)->revision);
    }

    public function test_transfer_and_old_presenter_command_serialize_with_exactly_one_winner(): void
    {
        $grant = app(TeacherInvitations::class)->accept($this->token, 'Helper', [])['grant'];
        $results = $this->race([
            ['kind' => 'transfer', 'commandId' => (string) Str::uuid(), 'grantId' => $grant['id']],
            ['kind' => 'owner-command', 'commandId' => (string) Str::uuid()],
        ]);
        $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'));
        $state = TeachingSession::query()->findOrFail($this->sessionId);
        $this->assertSame(2, $state->revision);
        $this->assertSame(1, $state->commandReceipts()->count());
        if ($results[0]['status'] === 200) {
            $this->assertSame($grant['id'], $state->presenter_grant_id);
            $this->assertSame(1, (int) $state->presenter_epoch);
            $this->assertNull($state->wave_id);
            $this->assertContains($results[1]['code'], ['control_conflict', 'presenter_required']);
        } else {
            $this->assertTrue((bool) $state->presenter_is_owner);
            $this->assertNotNull($state->wave_id);
            $this->assertSame('revision_conflict', $results[0]['code']);
        }
    }

    private function race(array $operations): array
    {
        $this->privateFile('fixture.json', json_encode(['ownerKey' => $this->owner, 'sessionId' => $this->sessionId,
            'token' => $this->token, 'operations' => $operations], JSON_THROW_ON_ERROR));
        $config = $this->database->getConfig();
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => config('app.key'),
            'APP_CONFIG_CACHE' => $this->directory.'/unused-config-cache.php', 'DB_CONNECTION' => $config['driver'],
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) ($config['port'] ?? 3306), 'DB_DATABASE' => 'lessons_test',
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null'];
        $parentId = (int) $this->database->selectOne('SELECT CONNECTION_ID() AS id')->id;
        $this->database->beginTransaction();
        try {
            $this->database->table('teaching_sessions')->where('id', $this->sessionId)->lockForUpdate()->first();
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/collaboration_concurrency_worker.php')], base_path(), $environment,
                    json_encode(['runId' => $this->runId, 'worker' => $index], JSON_THROW_ON_ERROR), 45);
                $process->start();
                $this->processes[] = $process;
            }
            $this->waitUntil(fn (): bool => is_file($this->directory.'/ready-0') && is_file($this->directory.'/ready-1'));
            $workerIds = [(int) file_get_contents($this->directory.'/ready-0'), (int) file_get_contents($this->directory.'/ready-1')];
            $this->assertCount(3, array_unique([$parentId, ...$workerIds]));
            $this->privateFile('go', 'start');
            $this->waitUntil(function () use ($workerIds, $parentId): bool {
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
            $this->assertSame(0, $process->wait(), 'Independent worker must finish without infrastructure failure.');
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
                $this->assertTrue($process->isRunning(), 'Workers must overlap until the parent releases its lock.');
            }
            if ($condition()) {
                return;
            }
            usleep(100_000);
        } while (microtime(true) < $deadline);
        $this->fail('Both workers must reach the barrier and observed InnoDB lock waits.');
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
