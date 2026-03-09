<?php

use App\Http\Controllers\API\Office\OfficeEmployeeController;

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

// employee profile
Route::resource('/office-employee', OfficeEmployeeController::class);
