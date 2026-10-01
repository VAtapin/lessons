<?php

use App\Http\Controllers\CatalogAdministrationController;
use App\Http\Controllers\CommonTemplateController;
use App\Http\Middleware\RequireCatalogAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/api/catalog/taxonomy', [CatalogAdministrationController::class, 'publicTerms']);
Route::get('/api/catalog/templates', [CommonTemplateController::class, 'index']);
Route::get('/api/catalog/templates/{id}', [CommonTemplateController::class, 'show']);
Route::post('/api/catalog/templates/{id}/instantiate', [CommonTemplateController::class, 'instantiate'])->middleware('throttle:studio-write');
Route::prefix('api/studio/catalog/submissions')->group(function (): void {
    Route::get('/', [CatalogAdministrationController::class, 'authorList']);
    Route::post('/', [CatalogAdministrationController::class, 'submit'])->middleware('throttle:studio-write');
});
Route::prefix('api/admin')->middleware(RequireCatalogAdmin::class)->group(function (): void {
    Route::get('/', [CatalogAdministrationController::class, 'account']);
    Route::get('/submissions', [CatalogAdministrationController::class, 'queue']);
    Route::get('/submissions/{id}', [CatalogAdministrationController::class, 'show']);
    Route::get('/catalog', [CatalogAdministrationController::class, 'entries']);
    Route::get('/taxonomy', [CatalogAdministrationController::class, 'terms']);
    Route::get('/templates', [CommonTemplateController::class, 'adminIndex']);
    Route::middleware('throttle:studio-write')->group(function (): void {
        Route::post('/submissions/{id}/review', [CatalogAdministrationController::class, 'review']);
        Route::post('/catalog/{slug}/visibility', [CatalogAdministrationController::class, 'visibility']);
        Route::post('/taxonomy', [CatalogAdministrationController::class, 'saveTerm']);
        Route::put('/taxonomy/{id}', [CatalogAdministrationController::class, 'saveTerm']);
        Route::post('/templates', [CommonTemplateController::class, 'save']);
        Route::put('/templates/{id}', [CommonTemplateController::class, 'save']);
        Route::post('/templates/{id}/visibility', [CommonTemplateController::class, 'visibility']);
    });
});
