<?php

use App\Http\Controllers\API\PayrollExport\PayrollReportExportController;
use App\Http\Controllers\API\Reports\ReportsPayrollControllerV1;
use App\Http\Controllers\API\Reports\ReportsProjectionController;
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
// Sales
Route::post('/sales_report', [ReportsProjectionController::class, 'sales_report'])->name('sales_report_v1');
Route::post('/sales_graph', [ReportsProjectionController::class, 'sales_graph'])->name('sales_graph_v1');
Route::post('/sales_account', [ReportsProjectionController::class, 'sales_account'])->name('sales_account_v1');
Route::post('/sales_best_avg', [ReportsProjectionController::class, 'sales_best_avg'])->name('sales_best_avg_v1');
Route::post('/sales_contracts', [ReportsProjectionController::class, 'sales_contracts'])->name('sales_contracts_v1');
Route::post('/sales_install_ratio', [ReportsProjectionController::class, 'sales_install_ratio'])->name('sales_install_ratio_v1');

// Payroll Reports
Route::post('/commission_details', [ReportsPayrollControllerV1::class, 'commissionDetails']);
Route::post('/override_details', [ReportsPayrollControllerV1::class, 'overrideDetails']);
Route::post('/reimbursement_details', [ReportsPayrollControllerV1::class, 'reimbursementDetails']);
Route::post('/adjustment_details', [ReportsPayrollControllerV1::class, 'adjustmentDetails']);
Route::post('/payroll_report', [ReportsPayrollControllerV1::class, 'payroll_report']);
Route::post('/payroll_report_employees', [ReportsPayrollControllerV1::class, 'payroll_report_employees']);
Route::post('/hourly_salary_details', [ReportsPayrollControllerV1::class, 'hourlySalaryDetails']);
Route::post('/overtime_details', [ReportsPayrollControllerV1::class, 'overtimeDetails']);

Route::post('/additional_value_details', [ReportsPayrollControllerV1::class, 'additionalValueDetails']);

/* Route for the payroll export functionnality */
Route::post('/workker-basic', [PayrollReportExportController::class, 'workerBasicExport']);
Route::post('/workker-detail', [PayrollReportExportController::class, 'workerDetailExport']);
Route::post('/worker-all-details', [PayrollReportExportController::class, 'workerAllDetailsExport']);
Route::post('/pid-basic', [PayrollReportExportController::class, 'pidBasicExport']);
Route::post('/pid-detail', [PayrollReportExportController::class, 'pidDetailExport']);
