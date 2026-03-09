<?php

use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\V2\Position\PositionController;
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

Route::get('/position', [PositionController::class, 'indexOptimized']);
Route::post('/add-position', [PositionController::class, 'store']);
Route::get('/edit-position/{id}', [PositionController::class, 'edit']);
Route::put('/update-position/{id}', [PositionController::class, 'update']);
Route::post('/delete-position/{id}', [PositionController::class, 'delete']);
Route::get('/edit-position-all/{id}', [PositionController::class, 'editPositionAll']);
Route::get('/dropdown-product-by-position/{id}', [PositionController::class, 'dropDownProductByPosition']);
Route::post('/product-position-wise/', [PositionController::class, 'positionProductWise']);

// POSITION SETUP
Route::post('/add-position-wages', [PositionController::class, 'wages']);
Route::post('/add-position-commission', [PositionController::class, 'commission']);
Route::post('/add-position-upfront', [PositionController::class, 'upfront']);
Route::post('/add-position-override', [PositionController::class, 'override']);
Route::post('/add-position-settlement', [PositionController::class, 'settlement']);
Route::post('/add-position-deduction', [PositionController::class, 'deduction']);
Route::delete('/remove-deduction/{id}', [PositionController::class, 'removeDeduction']);
Route::post('/position-user-count', [PositionController::class, 'positionUserCount']);
Route::get('/locations/{sub_position_id}', [LocationController::class, 'locations_by_position']);
Route::match(['get', 'post'], '/usersByOfficeID/{id}/{position_id?}', [PositionController::class, 'usersByOfficeID']);
