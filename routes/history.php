<?php

use App\Http\Controllers\Studio\HistoryController;
use App\Http\Controllers\Studio\MediaFileController;
use Illuminate\Support\Facades\Route;

Route::get('/api/studio/sessions', [HistoryController::class, 'index']);
Route::get('/api/studio/sessions/{id}/history', [HistoryController::class, 'show']);
Route::patch('/api/studio/sessions/{id}/history', [HistoryController::class, 'update'])->middleware('throttle:studio-write');
Route::post('/api/studio/sessions/{id}/again', [HistoryController::class, 'again'])->middleware('throttle:studio-write');
Route::get('/api/studio/lessons/{id}/versions', [HistoryController::class, 'versions']);
Route::get('/api/studio/lessons/{id}/versions/{versionId}', [HistoryController::class, 'version']);
Route::post('/api/studio/lessons/{id}/favorite', [HistoryController::class, 'favorite'])->middleware('throttle:studio-write');
Route::post('/api/studio/lessons/{id}/rehearsals', [HistoryController::class, 'rehearsal'])->middleware('throttle:studio-write');
Route::get('/api/studio/rehearsals/{id}/preview/{audience}', [HistoryController::class, 'preview']);
Route::post('/api/studio/rehearsals/{id}/answers', [HistoryController::class, 'answer'])->middleware('throttle:studio-write');
Route::get('/media/rehearsal/{sessionId}/{assetId}/{versionId}', [MediaFileController::class, 'rehearsal']);
