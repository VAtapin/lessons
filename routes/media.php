<?php

use App\Http\Controllers\Studio\MediaController;
use App\Http\Controllers\Studio\MediaFileController;
use Illuminate\Support\Facades\Route;

Route::get('/api/studio/media', [MediaController::class, 'index']);
Route::post('/api/studio/media', [MediaController::class, 'store'])->middleware('throttle:studio-write');
Route::get('/api/studio/media/{id}', [MediaController::class, 'show']);
Route::put('/api/studio/media/{id}', [MediaController::class, 'update'])->middleware('throttle:studio-write');
Route::post('/api/studio/media/{id}/versions', [MediaController::class, 'replace'])->middleware('throttle:studio-write');
Route::post('/api/studio/media/{id}/archive', [MediaController::class, 'archive'])->middleware('throttle:studio-write');
Route::get('/media/owned/{assetId}/{versionId}', [MediaFileController::class, 'owned']);
Route::get('/media/participation/{sessionId}/{assetId}/{versionId}', [MediaFileController::class, 'participation']);
Route::get('/media/projection/{token}/{assetId}/{versionId}', [MediaFileController::class, 'projection']);
