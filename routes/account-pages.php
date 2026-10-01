<?php

use App\Http\Controllers\WorkspacePageController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}')->where(['locale' => implode('|', config('lessons.ui_locales'))])->group(function () {
    foreach (['login', 'register', 'forgot-password', 'verify-email', 'account'] as $kind) {
        Route::get('/'.$kind, [WorkspacePageController::class, 'authentication'])->defaults('kind', $kind);
    }
    Route::get('/reset-password/{token}', [WorkspacePageController::class, 'resetPassword']);
    Route::get('/history', [WorkspacePageController::class, 'history']);
    Route::get('/history/{sessionId}', [WorkspacePageController::class, 'history']);
    Route::get('/rehearsal/{sessionId}/{audience}', [WorkspacePageController::class, 'rehearsal'])->where('audience', 'student|projector');
});
