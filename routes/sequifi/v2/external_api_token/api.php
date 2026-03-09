<?php

use App\Http\Controllers\ExternalAPIs\ExternalApiController;
use Illuminate\Support\Facades\Route;

// API Token Generation & Management (Requires Sanctum Authentication)

Route::post('/generate-api-token', [ExternalApiController::class, 'generateApiToken'])
    ->middleware(['auth:sanctum', 'throttle:5,1'])
    ->name('external-api.generate-token');

Route::post('/refresh-api-token', [ExternalApiController::class, 'refreshApiToken'])
    ->middleware(['auth:sanctum', 'throttle:10,1'])
    ->name('external-api.refresh-token');

Route::post('/revoke-api-token', [ExternalApiController::class, 'revokeApiToken'])
    ->middleware(['auth:sanctum', 'throttle:10,1'])
    ->name('external-api.revoke-token');

Route::post('/revoke-all-tokens-by-name', [ExternalApiController::class, 'revokeAllTokensByName'])
    ->middleware(['auth:sanctum', 'throttle:5,1'])
    ->name('external-api.revoke-all-tokens');

// API Information & Statistics (Read-only endpoints)

Route::get('/available-api-scopes', [ExternalApiController::class, 'getAvailableApiScopes'])
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->name('external-api.available-scopes');

Route::get('/token-usage-statistics', [ExternalApiController::class, 'getTokenUsageStatistics'])
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->name('external-api.usage-statistics');
