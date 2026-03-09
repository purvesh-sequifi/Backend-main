<?php

use App\Http\Controllers\API\V2\PositionSettlement\PositionSettlementController;
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

Route::get('/index', [PositionSettlementController::class, 'index']);
Route::post('/add-position-settlement', [PositionSettlementController::class, 'store'])->name('add-position-settlement');
Route::get('/edit-position-settlement/{id}', [PositionSettlementController::class, 'edit'])->name('edit-position-settlement');
Route::delete('/delete-position-settlement/{id}', [PositionSettlementController::class, 'delete'])->name('delete-position-settlement');
