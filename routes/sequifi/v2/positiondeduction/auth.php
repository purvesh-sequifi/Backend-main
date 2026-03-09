<?php

use App\Http\Controllers\API\V2\PositionDeduction\PositionDeductionController;
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

Route::get('/index', [PositionDeductionController::class, 'index']);
Route::post('/add-position-deduction', [PositionDeductionController::class, 'store'])->name('add-position-deduction');
Route::get('/edit-position-deduction/{id}', [PositionDeductionController::class, 'edit'])->name('edit-position-deduction');
Route::delete('/delete-position-deduction/{id}', [PositionDeductionController::class, 'delete'])->name('delete-position-deduction');
