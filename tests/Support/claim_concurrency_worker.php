<?php

declare(strict_types=1);

use App\Application\Account\AuthService;
use App\Application\Account\GuestClaimService;
use App\Application\Media\MediaLibraryService;
use App\Application\Shared\ApiProblem;
use App\Application\Studio\StudioService;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\Support\RuntimeConcurrencyGuard;

ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (getenv('APP_ENV') !== 'testing' || PHP_OS_FAMILY === 'Windows') {
        throw new RuntimeException('Worker requires Linux testing.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    if (! in_array($input['worker'] ?? null, [0, 1], true)) {
        throw new RuntimeException('Unsupported worker.');
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $database = RuntimeConcurrencyGuard::database($app);
    $directory = RuntimeConcurrencyGuard::privateDirectory($input['runId'] ?? '');
    $path = $directory.'/fixture.json';
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Existing isolated fixture required.');
    }
    $fixture = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $material = LessonMaterial::query()->with('currentVersion')->findOrFail($fixture['materialId']);
    $user = User::query()->findOrFail($fixture['userId']);
    $title = 'Claim concurrency fixture '.$input['runId'];
    if ($material->owner_key !== $fixture['source'] || $material->currentVersion->document['content']['ru']['title'] !== $title
        || $user->owner_key !== $fixture['target'] || ! $user->hasVerifiedEmail()
        || ! in_array($fixture['write'], ['create', 'upload'], true)) {
        throw new RuntimeException('Worker may only mutate its existing isolated fixture.');
    }
    config(['filesystems.disks.media.root' => $directory.'/media']);
    $database->statement('SET SESSION innodb_lock_wait_timeout = 20');
    $connectionId = (int) $database->selectOne('SELECT CONNECTION_ID() AS id')->id;
    $ready = $directory.'/ready-'.$input['worker'];
    $file = fopen($ready, 'x');
    if ($file === false) {
        throw new RuntimeException('Cannot create barrier.');
    }
    try {
        if (! chmod($ready, 0600) || fwrite($file, (string) $connectionId) !== strlen((string) $connectionId)) {
            throw new RuntimeException('Cannot write barrier.');
        }
    } finally {
        fclose($file);
    }
    $deadline = microtime(true) + 20;
    while (! is_file($directory.'/go')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout.');
        }
        usleep(20_000);
        clearstatcache();
    }
    try {
        if ($input['worker'] === 0) {
            $request = Request::create('/api/account/guest-claim', 'POST');
            $store = new Store('test-claim', new ArraySessionHandler(120));
            $store->start();
            $store->put(AuthService::GUEST_PROOF, $fixture['source']);
            $request->setLaravelSession($store);
            $request->setUserResolver(fn () => $user);
            $app->make(GuestClaimService::class)->claim($request);
        } elseif ($fixture['write'] === 'create') {
            $document = $material->currentVersion->document;
            $app->make(StudioService::class)->create($fixture['source'], $document);
        } else {
            $imagePath = $directory.'/image.png';
            if (! is_file($imagePath) || is_link($imagePath)) {
                throw new RuntimeException('Existing fixture image required.');
            }
            $app->make(MediaLibraryService::class)->upload($fixture['source'], new UploadedFile($imagePath, 'fixture.png', 'image/png', null, true),
                ['title' => $title, 'tags' => [], 'author' => 'Test', 'source' => 'Generated test fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Tests only']);
        }
        $result = ['status' => 200, 'code' => null];
    } catch (ApiProblem $problem) {
        $result = ['status' => $problem->status, 'code' => $problem->problemCode];
    }
    echo json_encode($result + ['connectionId' => $connectionId], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable) {
    echo '{"status":500,"code":"worker_failed"}';
    exit(1);
}
