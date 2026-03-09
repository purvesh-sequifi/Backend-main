<?php

use App\Http\Controllers\API\Emptimercard\EmptimercardController;
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

Route::post('/timer_add', [EmptimercardController::class, 'timer_add']);
Route::get('/today_card', [EmptimercardController::class, 'today_card']);
Route::post('/timesheets', [EmptimercardController::class, 'timesheets']);
Route::post('/my_schedules', [EmptimercardController::class, 'mySchedule']);
Route::post('/your_earnings', [EmptimercardController::class, 'your_earnings']);
Route::post('/early_timeout_remainder', [EmptimercardController::class, 'early_timeout_remainder_and_leavs']);
Route::post('/time_formate_update', [EmptimercardController::class, 'timeformateupdate']);
// Route::post('/payroll_wages_create', [EmptimercardController::class, "payroll_wages_create"]);
