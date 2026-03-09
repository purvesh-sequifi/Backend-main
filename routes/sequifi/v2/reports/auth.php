<?php

use App\Http\Controllers\API\V2\Recon\ReconAdminReportController;
use App\Http\Controllers\API\V2\Recon\UserReconciliationController;
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

// Plaid API

// Route::resource('/',PlaidTransactionController::class);

// report

Route::get('/reconciliationPayrollHistoriesList', [ReconAdminReportController::class, 'mainReport']);
Route::post('/payrollReconciliationList', [ReconAdminReportController::class, 'userReconReport']);
Route::get('/reportReconCommissionbyEmployeeId/{id}', [ReconAdminReportController::class, 'reportReconCommissionbyEmployeeId']);
Route::get('/reportReconOverridebyEmployeeId/{id}', [ReconAdminReportController::class, 'reportReconOverridebyEmployeeId']);
Route::get('/reportReconClawbackListbyEmployeeId/{id}', [ReconAdminReportController::class, 'reportReconClawbackListbyEmployeeId']);

Route::get('/reportReconAdjustementbyEmployeeId/{id}', [UserReconciliationController::class, 'reportReconAdjustementbyEmployeeId']);
Route::get('/exportReconciliationPayrollHistoriesList', [UserReconciliationController::class, 'exportReconciliationPayrollHistoriesList']);
Route::get('/user-commission-via-pid-in-report', [UserReconciliationController::class, 'userCommissionViaPIDInReport']);
Route::post('/exportPayrollReconciliationList', [UserReconciliationController::class, 'exportPayrollReconciliationList']);

// payroll reports
