<?php

use App\Http\Controllers\API\V2\PositionUpfront\PositionUpfrontController;
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

Route::get('/index', [PositionUpfrontController::class, 'index']);
Route::post('/add-position-upfront', [PositionUpfrontController::class, 'store'])->name('add-position-upfront');
Route::get('/edit-position-upfront/{id}', [PositionUpfrontController::class, 'edit'])->name('edit-position-upfront');
Route::delete('/delete-position-upfront/{id}', [PositionUpfrontController::class, 'delete'])->name('delete-position-upfront');
