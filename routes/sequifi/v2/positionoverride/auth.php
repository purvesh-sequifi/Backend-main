<?php

use App\Http\Controllers\API\V2\PositionOverride\PositionOverrideController;
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

Route::get('/index', [PositionOverrideController::class, 'index']);
Route::post('/add-position-override', [PositionOverrideController::class, 'store'])->name('add-position-override');
Route::get('/edit-position-override/{id}', [PositionOverrideController::class, 'edit'])->name('edit-position-override');
Route::delete('/delete-position-override/{id}', [PositionOverrideController::class, 'delete'])->name('delete-position-override');
