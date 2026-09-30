<?php

use App\Application\Shared\MediaCatalogue;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WorkspacePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/{locale}', HomeController::class)
    ->where('locale', implode('|', config('lessons.ui_locales')))
    ->name('home.localized');

Route::get('/media/builtin/{versionId}', function (string $versionId, MediaCatalogue $media) {
    $entry = collect(config('media_builtin'))->firstWhere('versionId', $versionId);
    abort_unless($entry !== null, 404);
    $media->resolve($entry['assetId'], $versionId);

    return response()->file(base_path($entry['file']), [
        'Content-Type' => $entry['mime'], 'Cache-Control' => 'public, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});

Route::prefix('{locale}')->where(['locale' => implode('|', config('lessons.ui_locales'))])->group(function () {
    Route::get('/studio', [WorkspacePageController::class, 'studio']);
    Route::get('/library', [WorkspacePageController::class, 'library']);
    Route::get('/media', [WorkspacePageController::class, 'media']);
    Route::get('/studio/lessons/{lessonId}', [WorkspacePageController::class, 'editor']);
    Route::get('/teach/{sessionId}', [WorkspacePageController::class, 'teacher']);
    Route::get('/control/{sessionId}', [WorkspacePageController::class, 'control']);
    Route::get('/join', [WorkspacePageController::class, 'join']);
    Route::get('/participate/{sessionId}', [WorkspacePageController::class, 'student']);
    Route::get('/project/{projectorToken}', [WorkspacePageController::class, 'projector']);
});

require __DIR__.'/studio.php';
require __DIR__.'/runtime.php';
require __DIR__.'/media.php';
require __DIR__.'/templates.php';
