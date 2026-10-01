<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// This router is never installed in public/ or registered as an application route.
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing'
        || ! preg_match('/\A127(?:\.[0-9]{1,3}){3}\z/', $_SERVER['REMOTE_ADDR'] ?? '')
        || (int) getenv('PHP_CLI_SERVER_WORKERS') < 2) {
        throw new RuntimeException('Only the loopback multiworker testing server is allowed.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require __DIR__.'/guard.php';
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    if ($app->runningInConsole()) {
        throw new RuntimeException('The HTTP server must retain real CSRF middleware.');
    }
    loadDatabase($app);
    $request = Request::capture();
    $response = $kernel->handle($request);
    $response->headers->set('X-Lessons-Load-Test', 'testing/lessons_test/MariaDB10.6');
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo '{"error":{"code":"load_guard_failed"}}';
}
