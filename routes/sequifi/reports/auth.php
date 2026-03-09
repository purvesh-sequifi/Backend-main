<?php

use App\Http\Controllers\API\Payroll\UserReconciliationController;
use App\Http\Controllers\API\Reports\ReportsAdminController;
use App\Http\Controllers\API\Reports\ReportsClawBackController;
use App\Http\Controllers\API\Reports\ReportsPayrollController;
use App\Http\Controllers\APIReportsCustomImportAPIController;
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
Route::post('/company_report', [ReportsAdminController::class, 'company_report'])->name('company_report');
Route::post('/company_graph', [ReportsAdminController::class, 'company_graph'])->name('company_graph');
Route::post('/company_graph_new', [ReportsAdminController::class, 'company_graph_new'])->name('company_graph_new');
Route::post('/company_export', [ReportsAdminController::class, 'company_export'])->name('company_export');
Route::post('/personnel_summary', [ReportsAdminController::class, 'personnel_summary'])->name('personnel_summary');
Route::post('/company_snapshot_new', [ReportsAdminController::class, 'company_snapshot_new'])->name('company_snapshot_new');
Route::post('/company_report_new', [ReportsAdminController::class, 'company_report_new'])->name('company_report_new');
Route::post('/company_projected_payouts', [ReportsAdminController::class, 'company_projected_payouts'])->name('company_projected_payouts');
// Route::resource('/claw_back', ReportsClawBackController::class);

// sales
Route::post('/sales_report', [ReportsAdminController::class, 'sales_report'])->name('sales_report');
Route::post('/global_search', [ReportsAdminController::class, 'global_search']);
Route::post('/sales_by_pid', [ReportsAdminController::class, 'sales_by_pid'])->name('sales_by_pid');
Route::post('/customer_payment_by_pid', [ReportsAdminController::class, 'customer_payment_by_pid']);
Route::post('/sales_graph', [ReportsAdminController::class, 'sales_graph'])->name('sales_graph');
Route::post('/sales_export', [ReportsAdminController::class, 'sales_export'])->name('sales_export');
Route::post('/salesAccountSummary', [ReportsAdminController::class, 'salesAccountSummary']);
Route::post('/salesAccountSummaryByPosition', [ReportsAdminController::class, 'salesAccountSummaryByPosition']);
Route::post('/company_margin_summary', [ReportsAdminController::class, 'companyMarginSummary']);
Route::post('/sales_import', [ReportsAdminController::class, 'sales_import'])->name('sales_import');
Route::post('/sales_import_validation', [ReportsAdminController::class, 'sales_import_validation']);
Route::get('/download_sample', [ReportsAdminController::class, 'download_sample'])->name('download_reports_sample');

// reconciliation
Route::post('/reconciliation_report', [ReportsAdminController::class, 'reconciliation_report'])->name('reconciliation_report');
Route::post('/reconciliation_export', [ReportsAdminController::class, 'reconciliation_export'])->name('reconciliation_export');

// costs
Route::post('/costs_report', [ReportsAdminController::class, 'costs_report'])->name('costs_report');
Route::post('/costs_graph', [ReportsAdminController::class, 'costs_graph'])->name('costs_graph');
Route::post('/costs_export', [ReportsAdminController::class, 'costs_export'])->name('costs_export');

// report
Route::post('/claw_back', [ReportsClawBackController::class, 'clawbackInstalls']);
Route::post('/pending_install', [ReportsClawBackController::class, 'pendingInstalls']);
Route::post('/export_clawback', [ReportsClawBackController::class, 'exportClawbackData'])->name('exportsalesdatas');
Route::post('/export_pending_install', [ReportsClawBackController::class, 'exportPendingData'])->name('exportpendinginstalls');
Route::post('/get_report_year_month_frequency_wise', [ReportsPayrollController::class, 'reportYearMonthFrequencyWise']);

Route::get('/reconciliationPayrollHistoriesList', [UserReconciliationController::class, 'reconciliationPayrollHistoriesList']);
Route::post('/payrollReconciliationList', [UserReconciliationController::class, 'payrollReconciliationList']);
Route::get('/reportReconClawbackListbyEmployeeId/{id}', [UserReconciliationController::class, 'reportReconClawbackListbyEmployeeId']);
Route::get('/reportReconOverridebyEmployeeId/{id}', [UserReconciliationController::class, 'reportReconOverridebyEmployeeId']);
Route::get('/reportReconCommissionbyEmployeeId/{id}', [UserReconciliationController::class, 'reportReconCommissionbyEmployeeId']);
Route::get('/reportReconAdjustementbyEmployeeId/{id}', [UserReconciliationController::class, 'reportReconAdjustementbyEmployeeId']);
Route::get('/exportReconciliationPayrollHistoriesList', [UserReconciliationController::class, 'exportReconciliationPayrollHistoriesList']);
Route::get('/user-commission-via-pid-in-report', [UserReconciliationController::class, 'userCommissionViaPIDInReport']);
Route::post('/exportPayrollReconciliationList', [UserReconciliationController::class, 'exportPayrollReconciliationList']);

// payroll reports
Route::post('/payroll_report', [ReportsPayrollController::class, 'payroll_report']);
Route::post('/get_report_summary_payroll', [ReportsPayrollController::class, 'getReportSummaryPayroll']);
//  Route::get('/commission_details/{id}', [ReportsPayrollController::class, 'commissionDetails']);
//  Route::get('/override_details/{id}', [ReportsPayrollController::class, 'overrideDetails']);
//  Route::get('/reimbursement_details/{id}', [ReportsPayrollController::class, 'reimbursementDetails']);
//  Route::get('/adjustment_details/{id}', [ReportsPayrollController::class, 'adjustmentDetails']);
Route::post('/commission_details', [ReportsPayrollController::class, 'commissionDetails']);
Route::post('/override_details', [ReportsPayrollController::class, 'overrideDetails']);
Route::post('/reimbursement_details', [ReportsPayrollController::class, 'reimbursementDetails']);
Route::post('/adjustment_details', [ReportsPayrollController::class, 'adjustmentDetails']);
Route::post('/api_log_report', [ReportsAdminController::class, 'api_log_report']);

// Custom Import API's
Route::post('/import-template-list', [APIReportsCustomImportAPIController::class, 'templateList']);
Route::post('/import-template-save', [APIReportsCustomImportAPIController::class, 'createOrUpdate']);
Route::post('/import-template-delete', [APIReportsCustomImportAPIController::class, 'templateDelete']);
Route::get('/import-template-category-dropdown', [APIReportsCustomImportAPIController::class, 'templateCategoryDropdown']);
Route::get('/import-template-dropdown', [APIReportsCustomImportAPIController::class, 'templateDropdown']);
