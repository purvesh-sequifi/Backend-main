<?php

use App\Http\Controllers\API\V2\PositionCommission\PositionCommissionController;
// use App\Http\Controllers\API\Setting\PositionCommissionController;
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

// Protecting Routes

Route::get('/positions_status/{id}', [PositionCommissionController::class, 'positionsStatus']);
