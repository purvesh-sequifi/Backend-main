<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V2\Payroll\RequestApprovalController;

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

// ADD worker_type and pay_frequency to All The payroll related table & Migrate old Data

Route::post('/create', [RequestApprovalController::class, 'create']); // RequestApproval/request-approval //
Route::get('/list', [RequestApprovalController::class, 'list']); // RequestApproval/request-approval //
Route::post('/update-status', [RequestApprovalController::class, 'updateStatus']); // RequestApproval/update-status-of-request //
Route::get('/view/{id}', [RequestApprovalController::class, 'view']); // RequestApproval/getRequestApprovalStatusByReq_No/R000001 //

Route::get('/adjustment-type', [RequestApprovalController::class, 'adjustmentType']); // RequestApproval/adjustment-type //
Route::get('/approval-list', [RequestApprovalController::class, 'approvalList']); // RequestApproval/approval-list - Pending, Approved //
Route::get('/export', [RequestApprovalController::class, 'export']); // RequestApproval/exportRequestApprovalHistory //
Route::post('/comment', [RequestApprovalController::class, 'comment']); // RequestApproval/requestApprovalComment //
Route::post('/user-pto-hours', [RequestApprovalController::class, 'userPtoHours']); // RequestApproval/get_pto_hours_by_user //
Route::post('/user-time-adjustment', [RequestApprovalController::class, 'userTimeAdjustment']); // RequestApproval/get_time_adjustment_by_user //
