<?php

use App\Http\Controllers\API\V2\OverridePool\OverridePoolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Override Pool Routes
|--------------------------------------------------------------------------
|
| Routes for the Grow Marketing Override Pool Calculator feature.
| All routes are authenticated via auth:sanctum (set in parent group).
|
*/

// Dashboard summary widgets (eligible agents, overpaid count, pool total, advances, Q4 true-up)
Route::get('/dashboard', [OverridePoolController::class, 'dashboard']);

// Trigger or retrieve pool calculation for a given year
Route::get('/calculate', [OverridePoolController::class, 'calculate']);

// Get step-by-step breakdown for a single user
Route::get('/user/{userId}', [OverridePoolController::class, 'userDetail'])->where('userId', '[0-9]+');

// Quarterly advance management
Route::post('/advances', [OverridePoolController::class, 'saveAdvances']);
Route::get('/advances', [OverridePoolController::class, 'getAdvances']);

// Pool percentage tier configuration (configurable rules)
Route::get('/tiers', [OverridePoolController::class, 'getTiers']);
Route::post('/tiers', [OverridePoolController::class, 'saveTiers']);
