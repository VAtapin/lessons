<?php

use App\Http\Controllers\Runtime\RuntimeController;
use Illuminate\Support\Facades\Route;

Route::post('/api/studio/lessons/{id}/sessions', [RuntimeController::class, 'start'])->middleware('throttle:studio-write');
Route::get('/api/studio/sessions/{id}', [RuntimeController::class, 'teacher'])->middleware('throttle:lesson-poll');
Route::post('/api/studio/sessions/{id}/stage', [RuntimeController::class, 'navigate'])->middleware('throttle:studio-write');
Route::post('/api/join', [RuntimeController::class, 'join'])->middleware('throttle:lesson-join');
Route::get('/api/participation/{id}', [RuntimeController::class, 'student'])->middleware('throttle:lesson-poll');
Route::post('/api/participation/{id}/answers', [RuntimeController::class, 'answer'])->middleware('throttle:studio-write');
Route::get('/api/projection/{token}', [RuntimeController::class, 'projector'])->middleware('throttle:lesson-poll');
