<?php

use App\Http\Controllers\API\ManagerReport\ManagerReportsController;
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
// company
Route::post('/manager_report_office', [ManagerReportsController::class, 'officeReport'])->name('manager_report_office');

Route::post('/export_data_manager_report', [ManagerReportsController::class, 'exportManagerReportData'])->name('exportdatamanagerreport');

Route::post('/reconciliation', [ManagerReportsController::class, 'reconciliation'])->name('reconciliation');

Route::post('/report_sales_list', [ManagerReportsController::class, 'repotSalesList']);

Route::post('/report_sales_graph', [ManagerReportsController::class, 'mySalesGraph']);

Route::post('/report_account_install_ratio_graph', [ManagerReportsController::class, 'account_graph']);

Route::post('/past_pay_stub', [ManagerReportsController::class, 'pastPayStub']);
Route::post('/past_pay_stub_graph', [ManagerReportsController::class, 'pastPayStubGraph']);
Route::post('/past_pay_stub_detail', [ManagerReportsController::class, 'pastPayStubDetail']);
Route::post('/past_pay_stub_detail_list', [ManagerReportsController::class, 'pastPayStubDetailList']);

Route::post('/past_pay_stub_customer_info', [ManagerReportsController::class, 'pastpaystubcustomerinfo']);

Route::post('/current_pay_stub_detail_list', [ManagerReportsController::class, 'currentPayStubDetailList']);
Route::post('/current_pay_stub_customer_info', [ManagerReportsController::class, 'currentpaystubcustomerinfo']);

Route::post('/commission_details', [ManagerReportsController::class, 'payStubCommissionDetails']);
Route::post('/override_details', [ManagerReportsController::class, 'payStubOverrideDetails']);
Route::post('/payrollDeductionsByEmployeeId', [ManagerReportsController::class, 'payStubPayrollDeductionsByEmployeeId']);
Route::post('/adjustment_details', [ManagerReportsController::class, 'payStubAdjustmentDetails']);
Route::post('/reimbursement_details', [ManagerReportsController::class, 'payStubReimbursementDetails']);
Route::post('/deduction_details', [ManagerReportsController::class, 'payStubDeductionsDetails']);
Route::post('/wages_details', [ManagerReportsController::class, 'payStubWagesDetails']);

// override_details
