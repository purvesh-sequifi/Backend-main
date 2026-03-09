<?php

use App\Http\Controllers\API\Payroll\CustomFiledController;
use App\Http\Controllers\API\Payroll\PayrollSingleController;
use App\Http\Controllers\API\V1\PayrollMoveToReconController;
use App\Http\Controllers\API\V1\PayrollStatusCheckController;
use App\Http\Controllers\API\V1\PayrollUnFinalizeController;
use Illuminate\Support\Facades\Route;

Route::post('/single_payroll_mark_as_paid', [PayrollSingleController::class, 'singlePayrollMarkAsPaid']);
Route::post('/single_payroll_move_next_payroll', [PayrollSingleController::class, 'singlePayrollMoveToNextPayroll']);
Route::post('/single_payroll_adjustment_delete', [PayrollSingleController::class, 'singlePayrollAdjustmentdelete']);
Route::post('/undo_payroll_approval_request', [PayrollSingleController::class, 'undoPayrollAApprovalRequest']);

Route::post('/payroll_mark_as_paid', [PayrollSingleController::class, 'payrollMarkAsPaid']);
Route::post('/one_time_payment_pay_now', [PayrollSingleController::class, 'create_payment_for_pay_now']);
Route::post('/single_one_time_payment_pay_now', [PayrollSingleController::class, 'single_one_time_payment_pay_now']);
Route::post('/payroll_mark_as_unpaid', [PayrollSingleController::class, 'payroll_mark_as_unpaid']);
Route::post('/payroll_move_next_payroll', [PayrollSingleController::class, 'payrollMoveToNextPayroll']);
Route::post('/payroll_undo_next_payroll', [PayrollSingleController::class, 'payroll_undo_next_payroll']);
Route::post('/moveToReconciliations', [PayrollSingleController::class, 'moveToReconciliations']);
Route::post('/commission_details', [PayrollSingleController::class, 'commissionDetails'])->name('commissionDetailsV1');
Route::post('/override_details', [PayrollSingleController::class, 'overrideDetails'])->name('overrideDetailsV1');
Route::post('/adjustment_details', [PayrollSingleController::class, 'adjustmentDetails'])->name('adjustmentDetailsV1');
Route::post('/reimbursement_details', [PayrollSingleController::class, 'reimbursementDetails'])->name('reimbursementDetailsV1');
Route::post('/payroll_data', [PayrollSingleController::class, 'getPayrollData']);
Route::post('/get_payroll_workers', [PayrollSingleController::class, 'getPayrollWorkers']);
Route::post('/add_worker_custom_field', [CustomFiledController::class, 'addWorkerCustomField']);
Route::post('/finalize_Payroll', [PayrollSingleController::class, 'finalizePayroll']);
Route::post('/execute_Payroll', [PayrollSingleController::class, 'executePayroll']);

Route::post('/update_payment_request', [PayrollSingleController::class, 'updatePaymentRequest'])->name('updatePaymentRequestV1');
Route::post('/payment_request_add_payroll', [PayrollSingleController::class, 'adminPaymentRequestAddPayroll'])->name('adminPaymentRequestAddPayrollV1');

Route::post('/update_payroll_custom_filed', [CustomFiledController::class, 'update_payroll_custom_filed'])->name('update_payroll_custom_filed_v1');

Route::get('/export_custom', [CustomFiledController::class, 'export_custom'])->name('export_custom_v1');
Route::post('/get_report_year_month_frequency_wise', [CustomFiledController::class, 'reportYearMonthFrequencyWise']);

Route::get('/get_custom_filed', [CustomFiledController::class, 'getPayrollSetting']);
Route::post('/get_summary_payroll', [PayrollSingleController::class, 'getSummaryPayroll']);
Route::post('/import_custom', [CustomFiledController::class, 'import_custom'])->name('import_custom_v1');
Route::post('/payroll_data_contactor', [PayrollSingleController::class, 'getPayrollDataForContactor']);
Route::post('/payroll_data_employees', [PayrollSingleController::class, 'getPayrollDataForEmployees']);
Route::post('/hourly_salary_details', [PayrollSingleController::class, 'hourlySalaryDetails'])->name('hourlySalaryDetailsV1');
Route::post('/overtime_details', [PayrollSingleController::class, 'overtimeDetails'])->name('overtimeDetailsV1');
Route::post('/payroll_hourly_salary_edit', [PayrollSingleController::class, 'payrollhourlysalaryedit']);
Route::post('/payroll_overtime_edit', [PayrollSingleController::class, 'payrollovertimeedit']);
Route::post('/move_to_recon', [PayrollMoveToReconController::class, 'moveToRecon']);

Route::post('/un-finalize-payroll', [PayrollUnFinalizeController::class, 'unFinalizePayroll']);
Route::post('/check-finalize-status', [PayrollStatusCheckController::class, 'checkFinalizeStatus']);
