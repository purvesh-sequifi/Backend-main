<?php

use App\Http\Controllers\API\Everee\EvereeController;

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

// Route::post('/connect_everee',[EvereeController::class,'connect_everee']);
Route::post('/add_location_everee', [EvereeController::class, 'add_locations']);
Route::post('/add_contractors', [EvereeController::class, 'add_contractors']);
// Route::get('/delete_everee_onboarding',[EvereeController::class,'delete_everee_onboarding']);
