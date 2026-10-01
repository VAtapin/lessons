<?php

use App\Http\Controllers\Studio\LessonController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/studio/lessons')->group(function () {
    Route::get('/', [LessonController::class, 'index']);
    Route::post('/', [LessonController::class, 'store']);
    Route::post('/trash/purge', [LessonController::class, 'emptyTrash'])->middleware('throttle:studio-write');
    Route::get('/{id}', [LessonController::class, 'show']);
    Route::put('/{id}', [LessonController::class, 'update']);
    Route::post('/{id}/release', [LessonController::class, 'release']);
    Route::post('/{id}/archive', [LessonController::class, 'archive'])->middleware('throttle:studio-write');
    Route::post('/{id}/purge', [LessonController::class, 'purge'])->middleware('throttle:studio-write');
    Route::post('/{id}/preview', [LessonController::class, 'preview'])->middleware('throttle:studio-write');
});
