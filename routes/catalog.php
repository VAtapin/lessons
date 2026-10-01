<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/catalog')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index']);
    Route::get('/{slug}', [CatalogController::class, 'show']);
    Route::post('/{slug}/use', [CatalogController::class, 'use'])->middleware('throttle:studio-write');
    Route::post('/{slug}/start', [CatalogController::class, 'start'])->middleware('throttle:studio-write');
});
