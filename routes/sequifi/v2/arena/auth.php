<?php

use App\Http\Controllers\API\V2\Arena\ArenaController;
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

Route::post('/planaddupgrade', [ArenaController::class, 'crmplanaddupgrade']);
Route::post('/getplanactive', [ArenaController::class, 'getplanactive']);
Route::post('/activedeactive', [ArenaController::class, 'activedeactivecrm']);
Route::post('/get_userdata', [ArenaController::class, 'get_userdata']);
Route::post('/office_and_position_wise_user_list', [ArenaController::class, 'office_and_position_wise_user_list']); // User list based on Office and Position for Arena
