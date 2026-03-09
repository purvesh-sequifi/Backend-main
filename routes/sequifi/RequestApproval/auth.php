<?php

use App\Http\Controllers\API\RequestApproval\FineFeeBonusesController;
use App\Http\Controllers\API\RequestApproval\RequestApprovalController;

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

Route::resource('/request-approval', RequestApprovalController::class);
// Route::post('/request-approval',[ RequestApprovalController::class,'index']);
Route::get('/adjustment-type', [RequestApprovalController::class, 'getAdjustmenttype']);
Route::post('/filter-request', [RequestApprovalController::class, 'filter']);
Route::post('/update-status-of-request', [RequestApprovalController::class, 'updateStatusOfRequest']);
Route::get('/approval-list', [RequestApprovalController::class, 'approvallist']);
Route::get('/approvalListForHistory', [RequestApprovalController::class, 'approvalListForHistory']);
Route::post('/search-request-approval', [RequestApprovalController::class, 'index']);
Route::post('/filter-approval', [RequestApprovalController::class, 'filterapproval']);
Route::post('/search-approval', [RequestApprovalController::class, 'searchApproval']);
Route::get('/getRequestApprovalStatusByReq_No/{id}', [RequestApprovalController::class, 'getRequestApprovalStatusByReq_No']);
Route::post('/testApi', [RequestApprovalController::class, 'testApi']);

Route::post('/requestApprovalComment', [RequestApprovalController::class, 'requestApprovalComment']);
Route::get('/RequestHistory', [RequestApprovalController::class, 'RequestHistory']);

Route::post('/searchRequestByPid', [RequestApprovalController::class, 'searchRequestByPid']);
Route::get('/DeletePidForRequestApprovel/{id}', [RequestApprovalController::class, 'DeletePidForRequestApprovel']);

// fine-fee
Route::resource('/fine-fee', FineFeeBonusesController::class);
Route::get('/get-fine-fee', [FineFeeBonusesController::class, 'getfinefee']);
Route::get('/get-bonuses', [FineFeeBonusesController::class, 'getbonuses']);

Route::get('/exportRequestApprovalHistory', [RequestApprovalController::class, 'exportRequestApprovalHistory'])->name('exportRequestApprovalHistory');

Route::post('/get_time_adjustment_by_user', [RequestApprovalController::class, 'getTimeAdjustmentByUser']);
Route::post('/get_pto_hours_by_user', [RequestApprovalController::class, 'getPOThourByUser']);
