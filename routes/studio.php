<?php

use App\Http\Controllers\Studio\LessonController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/studio/lessons')->group(function () {
    Route::get('/', [LessonController::class, 'index']);
    Route::post('/', [LessonController::class, 'store']);
    Route::get('/{id}', [LessonController::class, 'show']);
    Route::put('/{id}', [LessonController::class, 'update']);
    Route::post('/{id}/release', [LessonController::class, 'release']);
});
