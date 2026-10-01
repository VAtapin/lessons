<?php

declare(strict_types=1);

use App\Application\Studio\EditorProblem;
use App\Application\Studio\EditorSaveRequest;
use App\Application\Studio\StudioService;
use App\Models\LessonMaterial;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\RuntimeConcurrencyGuard;

// Only existing isolated fixtures; no migration, deletion, HTTP or production connection.
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (getenv('APP_ENV') !== 'testing' || PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException('Worker requires Linux testing environment.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $request = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($request) || ! in_array($request['worker'] ?? null, [0, 1], true)) {
        throw new RuntimeException('Invalid worker fixture request.');
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $database = RuntimeConcurrencyGuard::database($app);
    $directory = RuntimeConcurrencyGuard::privateDirectory($request['runId'] ?? '');
    $fixturePath = $directory.'/fixture.json';
    if (! is_file($fixturePath) || is_link($fixturePath) || (fileperms($fixturePath) & 0777) !== 0600) {
        throw new RuntimeException('Private existing fixture required.');
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $material = LessonMaterial::with('currentVersion')->findOrFail($fixture['materialId']);
    if ($material->owner_key !== $fixture['ownerKey'] || $material->currentVersion->status !== 'draft'
        || $material->currentVersion->purpose !== 'authoring'
        || $material->currentVersion->document['content']['ru']['title'] !== 'Editor concurrency fixture '.$request['runId']) {
        throw new RuntimeException('Worker may only mutate its existing fixture material.');
    }
    $save = EditorSaveRequest::fromJson(json_encode($fixture['operations'][$request['worker']], JSON_THROW_ON_ERROR));
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
            throw new RuntimeException('Barrier timeout.');
        }
        usleep(20_000);
        clearstatcache();
    }
    try {
        $saved = $app->make(StudioService::class)->saveEditor($fixture['ownerKey'], $material->id, $save);
        $result = ['status' => 200, 'code' => null, 'acknowledgedSaveId' => $saved['acknowledgedSaveId'],
            'appliedRevision' => $saved['appliedRevision'], 'appliedVersionId' => $saved['appliedVersionId']];
    } catch (EditorProblem $problem) {
        $result = ['status' => $problem->status, 'code' => $problem->problemCode];
    }
    echo json_encode($result + ['connectionId' => $connectionId], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"status":500,"code":"worker_failed"}';
    exit(1);
}
