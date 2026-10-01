<?php

declare(strict_types=1);

use App\Application\Runtime\RuntimeConflict;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\RuntimeConcurrencyGuard;

// No HTTP endpoint, migrations, cleanup or arbitrary operations are exposed by this worker.
// Its environment and stdin contain only the dedicated test connection and fixture identifiers.
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (getenv('APP_ENV') !== 'testing' || PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException('Worker requires the Linux test environment.');
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
    $fixturePath = $directory.'/fixture.json';
    if (! is_file($fixturePath) || is_link($fixturePath)) {
        throw new RuntimeException('Existing fixture manifest is required.');
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $session = TeachingSession::query()->with('version.material')->findOrFail($fixture['sessionId']);
    if ($session->owner_key !== $fixture['ownerKey'] || $session->version->material->owner_key !== $fixture['ownerKey']
        || $session->version->document['content']['ru']['title'] !== 'Runtime concurrency fixture '.$request['runId']) {
        throw new RuntimeException('Worker may only operate on its existing isolated fixture.');
    }
    $operation = $fixture['operations'][$request['worker']];
    if ($operation['kind'] === 'role') {
        if (! in_array($operation['participantId'], $fixture['participantIds'], true)
            || ! SessionParticipant::query()->whereKey($operation['participantId'])->where('teaching_session_id', $session->id)->exists()) {
            throw new RuntimeException('Worker participant must belong to the existing fixture.');
        }
    } elseif ($operation['kind'] !== 'command'
        || ! in_array($operation['action'], ['answer.moderate', 'message.set'], true)) {
        throw new RuntimeException('Worker action is not supported.');
    }
    $database->statement('SET SESSION innodb_lock_wait_timeout = 20');
    $connectionId = (int) $database->selectOne('SELECT CONNECTION_ID() AS id')->id;
    $ready = $directory.'/ready-'.$request['worker'];
    $file = fopen($ready.'.partial', 'x');
    if ($file === false) {
        throw new RuntimeException('Cannot create worker barrier.');
    }
    try {
        if (! chmod($ready.'.partial', 0600) || fwrite($file, (string) $connectionId) !== strlen((string) $connectionId)) {
            throw new RuntimeException('Cannot write worker barrier.');
        }
    } finally {
        fclose($file);
    }
    if (! rename($ready.'.partial', $ready)) {
        throw new RuntimeException('Cannot finalize worker barrier.');
    }
    $deadline = microtime(true) + 20;
    while (! is_file($directory.'/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(20_000);
        clearstatcache();
    }
    $runtime = $app->make(RuntimeService::class);
    try {
        if ($operation['kind'] === 'role') {
            $state = $runtime->answer($session->id, $operation['participantId'], 'first', 'roles',
                ['stageId' => 'first', 'blockId' => 'roles', 'value' => ['roleId' => 'a']]);
        } else {
            $response = $runtime->command($fixture['ownerKey'], $session->id, $operation['commandId'],
                $operation['expectedRevision'], $operation['action'], $operation['payload']);
            $state = $response['session'];
        }
        $result = ['status' => 200, 'code' => null, 'revision' => $state['revision'],
            'acknowledgedCommandId' => $response['acknowledgedCommandId'] ?? null];
    } catch (RuntimeConflict $conflict) {
        $result = ['status' => 409, 'code' => $conflict->problemCode, 'revision' => $conflict->state['revision']];
    } catch (ApiProblem $problem) {
        $result = ['status' => $problem->status, 'code' => $problem->problemCode];
    }
    echo json_encode($result + ['connectionId' => $connectionId], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    // SQL exceptions can contain connection credentials or fixture content.
    echo '{"status":500,"code":"worker_failed"}';
    exit(1);
}
