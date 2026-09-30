<?php

use App\Http\Controllers\Studio\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/studio/templates')->group(function () {
    Route::get('/', [TemplateController::class, 'index']);
    Route::get('/{id}', [TemplateController::class, 'show']);
    Route::middleware('throttle:studio-write')->group(function () {
        Route::post('/', [TemplateController::class, 'store']);
        Route::put('/{id}', [TemplateController::class, 'update']);
        Route::post('/{id}/archive', [TemplateController::class, 'archive']);
        Route::post('/{id}/versions/{versionId}/instantiate', [TemplateController::class, 'instantiate']);
    });
});
