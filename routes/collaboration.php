<?php

use App\Http\Controllers\CollaborationPageController;
use App\Http\Controllers\Runtime\CollaborationController;
use App\Http\Controllers\Studio\MediaFileController;
use Illuminate\Support\Facades\Route;

Route::get('/api/studio/sessions/{id}/collaboration', [CollaborationController::class, 'overview'])->middleware('throttle:lesson-poll');
Route::post('/api/studio/sessions/{id}/collaboration/commands', [CollaborationController::class, 'command'])->middleware('throttle:studio-write');
Route::post('/api/teacher-invitations/accept', [CollaborationController::class, 'accept'])->middleware('throttle:lesson-join');
Route::get('/api/conduct/sessions/{id}', [CollaborationController::class, 'teacher'])->middleware('throttle:lesson-poll');
Route::post('/api/conduct/sessions/{id}/commands', [CollaborationController::class, 'teacherCommand'])->middleware('throttle:studio-write');
Route::get('/api/conduct/sessions/{id}/projection', [CollaborationController::class, 'projection'])->middleware('throttle:lesson-poll');
Route::get('/media/conduct/{sessionId}/{assetId}/{versionId}', [MediaFileController::class, 'conduct']);

Route::prefix('{locale}')->where(['locale' => implode('|', config('lessons.ui_locales'))])->group(function (): void {
    Route::get('/teacher-invitations', [CollaborationPageController::class, 'invitation']);
    Route::get('/conduct/{sessionId}', [CollaborationPageController::class, 'teacher']);
    Route::get('/conduct/{sessionId}/projector', [CollaborationPageController::class, 'projector']);
    Route::get('/conduct/{sessionId}/control', [CollaborationPageController::class, 'control']);
});
