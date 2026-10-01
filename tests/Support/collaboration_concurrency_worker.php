<?php

declare(strict_types=1);

use App\Application\Collaboration\CollaborationConflict;
use App\Application\Collaboration\CollaborationService;
use App\Application\Collaboration\TeacherActor;
use App\Application\Collaboration\TeacherInvitations;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\TeachingSession;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\RuntimeConcurrencyGuard;

ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (getenv('APP_ENV') !== 'testing' || PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException('Worker requires Linux testing environment.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $request = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($request) || ! in_array($request['worker'] ?? null, [0, 1], true)) {
        throw new RuntimeException('Invalid worker request.');
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $database = RuntimeConcurrencyGuard::database($app);
    $directory = RuntimeConcurrencyGuard::privateDirectory($request['runId'] ?? '');
    $path = $directory.'/fixture.json';
    if (! is_file($path) || is_link($path) || (fileperms($path) & 0777) !== 0600) {
        throw new RuntimeException('Private existing fixture required.');
    }
    $fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $session = TeachingSession::query()->with('version.material')->findOrFail($fixture['sessionId']);
    if ($session->owner_key !== $fixture['ownerKey'] || $session->version->material->owner_key !== $fixture['ownerKey']
        || $session->version->document['content']['ru']['title'] !== 'Collaboration concurrency fixture '.$request['runId']) {
        throw new RuntimeException('Worker may only operate on its isolated existing fixture.');
    }
    $operation = $fixture['operations'][$request['worker']];
    if (! in_array($operation['kind'], ['accept', 'transfer', 'owner-command'], true)) {
        throw new RuntimeException('Unsupported fixture action.');
    }
    $database->statement('SET SESSION innodb_lock_wait_timeout = 20');
    $connectionId = (int) $database->selectOne('SELECT CONNECTION_ID() AS id')->id;
    $ready = $directory.'/ready-'.$request['worker'];
    $file = fopen($ready.'.partial', 'x');
    if ($file === false) {
        throw new RuntimeException('Cannot create barrier.');
    }
    try {
        if (! chmod($ready.'.partial', 0600) || fwrite($file, (string) $connectionId) !== strlen((string) $connectionId)) {
            throw new RuntimeException('Cannot write barrier.');
        }
    } finally {
        fclose($file);
    }
    if (! rename($ready.'.partial', $ready)) {
        throw new RuntimeException('Cannot finalize barrier.');
    }
    $deadline = microtime(true) + 20;
    while (! is_file($directory.'/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Barrier timed out.');
        }
        usleep(20_000);
        clearstatcache();
    }
    try {
        $actor = TeacherActor::owner($fixture['ownerKey']);
        if ($operation['kind'] === 'accept') {
            $app->make(TeacherInvitations::class)->accept($fixture['token'], 'Race helper '.$request['worker'], []);
        } elseif ($operation['kind'] === 'transfer') {
            $app->make(CollaborationService::class)->command($actor, $session->id, $operation['commandId'], 1, 0,
                'presenter.transfer', ['grantId' => $operation['grantId']]);
        } else {
            $app->make(RuntimeService::class)->actorCommand($actor, $session->id, $operation['commandId'], 1, 'wave', [], [], 0);
        }
        $result = ['status' => 200, 'code' => null];
    } catch (CollaborationConflict $conflict) {
        $result = ['status' => 409, 'code' => $conflict->problemCode];
    } catch (ApiProblem $problem) {
        $result = ['status' => $problem->status, 'code' => $problem->problemCode];
    }
    // Never emit tokens, proofs, cookies, payloads or raw SQL diagnostics.
    echo json_encode($result + ['connectionId' => $connectionId], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"status":500,"code":"worker_failed"}';
    exit(1);
}
