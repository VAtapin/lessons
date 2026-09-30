<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/{locale}', HomeController::class)
    ->where('locale', implode('|', config('lessons.ui_locales')))
    ->name('home.localized');
