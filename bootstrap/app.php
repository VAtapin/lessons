<?php

use App\Application\Shared\ApiProblem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The domain validates authored JSON; optional empty captions/notes and
        // deliberate text whitespace must survive the standard form transforms.
        $authoredDocument = fn (Request $request) => $request->isMethod('POST') && $request->is('api/studio/lessons')
            || $request->isMethod('PUT') && $request->is('api/studio/lessons/*');
        $runtimeCommand = fn (Request $request) => $request->isMethod('POST') && $request->is('api/studio/sessions/*/commands');
        $middleware->trimStrings(except: [$authoredDocument, $runtimeCommand]);
        $middleware->convertEmptyStringsToNull(except: [$authoredDocument, $runtimeCommand]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ApiProblem $problem) => response()->json(
            ['error' => ['code' => $problem->problemCode]], $problem->status,
        ));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
