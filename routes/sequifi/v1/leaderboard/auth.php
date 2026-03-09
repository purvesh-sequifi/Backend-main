<?php

use App\Http\Controllers\API\LeaderBoard\LeaderBoardController;
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

Route::post('/leaaderboard_list_by_office', [LeaderBoardController::class, 'leaderboardListByOffice']);
Route::post('/leaaderboard_list_by_user', [LeaderBoardController::class, 'leaderboardListByUsers']);
