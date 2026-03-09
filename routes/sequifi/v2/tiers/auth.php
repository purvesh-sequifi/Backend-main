<?php

use App\Http\Controllers\API\V2\Tiers\TiersSchemaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/tiers', [TiersSchemaController::class, 'index']); //
Route::post('/add-edit-tiers-schemas', [TiersSchemaController::class, 'store']); //
Route::patch('/{type}/{id}', [TiersSchemaController::class, 'activateDeActive']); //
Route::get('/show/{id}', [TiersSchemaController::class, 'show']); //
Route::get('tiers-audit-logs', [TiersSchemaController::class, 'getAuditLogs']);
Route::get('tiers-user-mapped/{id}', [TiersSchemaController::class, 'tiersUserMapped']);
Route::get('/get-tier-systems', [TiersSchemaController::class, 'getTierSystems']); //
Route::get('/get-tier-durations', [TiersSchemaController::class, 'getTierDurations']); //
Route::get('tiers-level-dropdown', [TiersSchemaController::class, 'tiersLevelDropdown']);
Route::get('tiers-dropdown', [TiersSchemaController::class, 'tiersDropdown']); //
Route::get('tiers-update-by', [TiersSchemaController::class, 'getUpdateByUsers']);

Route::get('/get_tier_systems', [TiersSchemaController::class, 'getTierSystems']);
Route::get('/get_tier_durations', [TiersSchemaController::class, 'getTierDurations']);
Route::get('/tiershow/{id}', [TiersSchemaController::class, 'show']);

// get_tier_systems = get-tier-systems
// get_tier_durations = get-tier-durations
// tiershow/{id} = show/{id}
