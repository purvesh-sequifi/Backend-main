<?php

use App\Http\Controllers\API\V2\PositionWages\PositionWagesController;
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

Route::get('/index', [PositionWagesController::class, 'index']);
Route::post('/add-position-wages', [PositionWagesController::class, 'store'])->name('add-position-wages');
Route::get('/position-wages-by-id/{id}', [PositionWagesController::class, 'edit'])->name('position-wages-by-id');
Route::delete('/delete-position-wages-by-id/{id}', [PositionWagesController::class, 'remove'])->name('delete-position-wages-by-id');
// Route::post('/add-position-commision', [PositionWagesController::class, "commissionstore"])->name('add-position-commision');
// Route::post('/add-position-upfront', [PositionWagesController::class, "upfrontstore"])->name('add-position-upfront');
// Route::post('/position_commission_deduction',[PositionWagesController::class,'PositionCommissionDeduction'])->name('position_commission_deduction');
// Route::post('/add-position-commission-override',[PositionWagesController::class,'PositionCommissionoverride'])->name('add-position-commission-override');
// Route::get('/get-position-commission-override',[PositionWagesController::class,'getPositionCommissionoverride'])->name('get-add-position-commission-override');
// Route::post('/add-position-commission-settelement',[PositionWagesController::class,'PositionCommissionSettelement'])->name('add-position-commission-settelement');
