<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\DeletionRequestController;
use App\Http\Middleware\RequireAccount;
use Illuminate\Support\Facades\Route;

Route::get('/api/account', [AccountController::class, 'show']);
Route::post('/api/auth/register', [AccountController::class, 'register'])->middleware('throttle:account-register');
Route::post('/api/auth/login', [AccountController::class, 'login'])->middleware('throttle:account-login');
Route::post('/api/auth/logout', [AccountController::class, 'logout']);
Route::post('/api/auth/forgot-password', [AccountController::class, 'forgot'])->middleware('throttle:account-forgot');
Route::post('/api/auth/reset-password', [AccountController::class, 'reset'])->middleware('throttle:account-reset');
Route::post('/api/account/guest-continue', [AccountController::class, 'continueGuest']);
Route::middleware(RequireAccount::class)->group(function (): void {
    Route::get('/api/account/deletion-request', [DeletionRequestController::class, 'show']);
    Route::post('/api/account/deletion-request', [DeletionRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::post('/api/account/deletion-request/cancel', [DeletionRequestController::class, 'cancel'])->middleware('throttle:5,1');
    Route::patch('/api/account', [AccountController::class, 'update']);
    Route::get('/api/account/guest-claim', [AccountController::class, 'claimPreview']);
    Route::post('/api/account/guest-claim', [AccountController::class, 'claim'])->middleware('throttle:studio-write');
    Route::post('/api/auth/verification-notification', [AccountController::class, 'verificationNotification'])->middleware('throttle:account-resend');
    Route::get('/email/verify/{id}/{hash}', [AccountController::class, 'verify'])->middleware(['signed', 'throttle:account-verify'])->name('verification.verify');
});
