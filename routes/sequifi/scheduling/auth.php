<?php

use App\Http\Controllers\API\Schedule\ScheduleController;

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
// scheduling
Route::get('/get_scheduling_config', [ScheduleController::class, 'get_scheduling_config'])->name('get_scheduling_config');
Route::post('/scheduling_config', [ScheduleController::class, 'scheduling_config'])->name('scheduling_config');
// Route::post('/create_scheduling', [ScheduleController::class, 'createScheduling'])->name('createScheduling');
Route::post('/create_user_schedule', [ScheduleController::class, 'createUserSchedule'])->name('createUserSchedule');
// Route::post('/create_scheduling_multiple', [ScheduleController::class, 'createSchedulingMultiple'])->name('createSchedulingMultiple');
Route::post('/get_user_schedules', [ScheduleController::class, 'getUserSchedules'])->name('getUserSchedules');
Route::post('/user_schedules_export', [ScheduleController::class, 'userSchedulesExport'])->name('userSchedulesExport');
Route::post('/w2_user_list', [ScheduleController::class, 'w2UserList'])->name('w2UserList');
Route::post('/update_user_schedule_flexible', [ScheduleController::class, 'updateUserScheduleFlexible'])->name('updateUserScheduleFlexible');
Route::post('/update_user_schedule_repeat', [ScheduleController::class, 'updateUserScheduleRepeat'])->name('updateUserScheduleRepeat');
Route::post('/get_schedules_with_checkin_checkout', [ScheduleController::class, 'getSchedulesWithCheckinCheckout'])->name('getSchedulesWithCheckinCheckout');
Route::post('/update_user_schedule', [ScheduleController::class, 'updateUserSchedule'])->name('updateUserSchedule');
Route::post('/get_user_attendence_details', [ScheduleController::class, 'getUserAttendenceDetails'])->name('getUserAttendenceDetails');
Route::post('/user_adjustment_request', [ScheduleController::class, 'userAdjustmentRequest'])->name('userAdjustmentRequest');
Route::post('/user_audit_logs', [ScheduleController::class, 'userAuditLogs'])->name('userAuditLogs');
Route::post('/user_attendence_approval', [ScheduleController::class, 'userAttendenceAppoval'])->name('userAttendenceAppoval');
Route::post('/get_user_attendence_approved_status', [ScheduleController::class, 'getUserAttendenceApprovedStatus'])->name('getUserAttendenceApprovedStatus');
Route::post('/approved_attendance_status', [ScheduleController::class, 'approvedAttendanceStatus'])->name('approvedAttendanceStatus');
