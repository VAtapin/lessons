<?php

use App\Application\Shared\ApiProblem;
use App\Http\Middleware\AccountSession;
use App\Http\Middleware\LessonJsonMaps;
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
        $middleware->web(append: [AccountSession::class, LessonJsonMaps::class]);
        // The domain validates authored JSON; optional empty captions/notes and
        // deliberate text whitespace must survive the standard form transforms.
        $authoredDocument = fn (Request $request) => $request->isMethod('POST') && $request->is('api/studio/lessons')
            || $request->isMethod('PUT') && $request->is('api/studio/lessons/*');
        $runtimeCommand = fn (Request $request) => $request->isMethod('POST') && $request->is('api/studio/sessions/*/commands');
        $runtimeAnswer = fn (Request $request) => $request->isMethod('POST')
            && $request->is('api/participation/*/answers', 'api/studio/rehearsals/*/answers');
        $historyNotes = fn (Request $request) => $request->isMethod('PATCH') && $request->is('api/studio/sessions/*/history');
        $libraryContent = fn (Request $request) => in_array($request->method(), ['POST', 'PUT'], true)
            && ($request->is('api/studio/templates', 'api/studio/templates/*', 'api/studio/media', 'api/studio/media/*'));
        $authInput = fn (Request $request) => $request->is('api/auth/*', 'api/account', 'api/account/*');
        $middleware->trimStrings(except: [$authInput, $authoredDocument, $runtimeCommand, $runtimeAnswer, $historyNotes, $libraryContent]);
        $middleware->convertEmptyStringsToNull(except: [$authInput, $authoredDocument, $runtimeCommand, $runtimeAnswer, $historyNotes, $libraryContent]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (ApiProblem $problem) => response()->json(
            ['error' => ['code' => $problem->problemCode]], $problem->status,
        ));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
