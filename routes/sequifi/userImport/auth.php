<?php

use App\Http\Controllers\API\UserImportController;
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

Route::post('/import', [UserImportController::class, 'import']);
Route::post('/moxie-user-import', [UserImportController::class, 'moxieUserImport']);
Route::post('/moxie-manager-update', [UserImportController::class, 'moxieManagerUpdate']);
Route::post('/hawx-user-import', [UserImportController::class, 'hawxUserImport']);
Route::post('/hawx-manager-import', [UserImportController::class, 'hawxManagerImport']);
