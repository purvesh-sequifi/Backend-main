<?php

use App\Http\Controllers\API\Everee\EvereeController;
use App\Http\Controllers\API\Payroll\ExportPayRollController;
use App\Http\Controllers\API\Payroll\OneTimePaymentController;
use App\Http\Controllers\API\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\API\Payroll\PayrollController;
use App\Http\Controllers\API\Payroll\PayrollSingleController;
use App\Http\Controllers\API\Payroll\UserReconciliationController;
use App\Http\Controllers\API\PayrollExport\PayrollExportController;
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

Route::post('/payroll_data', [PayrollController::class, 'getPayrollData']);
Route::get('/payroll_details_by_id/{id}', [PayrollController::class, 'getPayrollDetailsById'])->name('getPayrollDetailsById');
Route::post('/update_payroll', [PayrollController::class, 'updatePayroll'])->name('updatePayroll');
// Route::get('/override_details/{id}', [PayrollController::class, 'overrideDetails'])->name('overrideDetails');
// Route::get('/reimbursement_details/{id}', [PayrollController::class, 'reimbursementDetails'])->name('reimbursementDetails');
// Route::get('/adjustment_details/{id}', [PayrollController::class, 'adjustmentDetails'])->name('adjustmentDetails');
Route::post('/override_details', [PayrollController::class, 'overrideDetails'])->name('overrideDetails');
Route::post('/reimbursement_details', [PayrollController::class, 'reimbursementDetails'])->name('reimbursementDetails');
Route::post('/adjustment_details', [PayrollController::class, 'adjustmentDetails'])->name('adjustmentDetails');
Route::get('/payment_request', [PayrollController::class, 'paymentRequest'])->name('paymentRequest');
Route::get('/advance_negative_payment_request', [PayrollController::class, 'advanceNegativePaymentRequest']);
Route::post('/advance_repayment', [PayrollController::class, 'advanceRepay']);

Route::post('/paymentRequestPayNow', [PayrollController::class, 'paymentRequestPayNow']);
Route::get('/advance_payment_request', [PayrollController::class, 'AdvancepaymentRequest'])->name('AdvancepaymentRequest');
Route::post('/update_payment_request', [PayrollController::class, 'updatePaymentRequest'])->name('updatePaymentRequest');
// Route::get('/commission_details/{id}', [PayrollController::class, 'commissionDetails'])->name('commissionDetailsMain');
Route::post('/commission_details', [PayrollController::class, 'commissionDetails'])->name('commissionDetailsMain');
Route::post('/update_user_commission', [PayrollController::class, 'updateUserCommission'])->name('updateUserCommission');
Route::post('/reconciliation_details', [PayrollController::class, 'ReconciliationList'])->name('reconciliationDetails');
Route::post('/reconciliation_details_edit', [UserReconciliationController::class, 'ReconciliationListEdit']);
Route::post('/reconciliation_overrides_details_edit', [UserReconciliationController::class, 'ReconciliationOverridesListEdit']);
Route::post('/payroll_reconciliation_details', [UserReconciliationController::class, 'ReconciliationListPayRoll']);
Route::post('/reconciliationByUser', [UserReconciliationController::class, 'reconciliationByUser']);
Route::post('/checkUserClosePayroll', [UserReconciliationController::class, 'checkUserClosePayroll']);
Route::post('/sendToPayrollRecon', [UserReconciliationController::class, 'sendToPayrollRecon']);
Route::post('/ReconciliationListUserSkipped', [UserReconciliationController::class, 'ReconciliationListUserSkipped']);
Route::post('/ReconciliationListUserSkippedUndo', [UserReconciliationController::class, 'ReconciliationListUserSkippedUndo']);
Route::post('/reconciliationFinalizeDraft', [UserReconciliationController::class, 'reconciliationFinalizeDraft']);
Route::get('/reconciliationFinalizeDraftList', [UserReconciliationController::class, 'reconciliationFinalizeDraftList']);
Route::post('/finalizeReconciliationList', [UserReconciliationController::class, 'finalizeReconciliationList']);
Route::post('/sendTopayrollList', [UserReconciliationController::class, 'sendTopayrollList']);
Route::post('/payrollReconciliationHistory', [UserReconciliationController::class, 'payrollReconciliationHistory'])->name('payrollReconciliationHistory');
Route::post('/commissionAdjustmentComment', [UserReconciliationController::class, 'commissionAdjustmentComment']);
Route::post('/overrideAdjustmentComment', [UserReconciliationController::class, 'overrideAdjustmentComment']);
Route::get('/userRepotRecon/{id}', [UserReconciliationController::class, 'userRepotRecon']);
Route::post('/update_reconciliation_details', [PayrollController::class, 'updateUserReconciliation'])->name('updateUserReconciliation');
Route::post('/finalize_reconciliation', [PayrollController::class, 'finalizeReconciliation'])->name('finalizeReconciliation');
// Route::post('/finalizeReconciliation', [PayrollController::class, 'finalizeReconciliations']);
Route::post('/add_reconciliation_to_payroll', [PayrollController::class, 'addReconciliationToPayroll'])->name('addReconciliationToPayroll');
Route::get('/get_finalize_reconciliation', [PayrollController::class, 'getFinalizeReconciliation'])->name('getFinalizeReconciliation');
Route::post('/finalize_Payroll', [PayrollController::class, 'finalizePayroll'])->name('finalizePayroll');
Route::post('/finalize_status_Payroll', [PayrollController::class, 'finalizeStatusPayroll']);
// Route::post('/execute_Payroll', [PayrollController::class, 'executePayroll'])->name('executePayroll');
Route::get('/get_finalize_payroll', [PayrollController::class, 'getFinalizePayroll'])->name('getFinalizePayroll');
Route::post('/create_onetime_payment', [PayrollController::class, 'createOneTimePayment']);
Route::post('/reconciliations_adjustment', [PayrollController::class, 'reconciliationsAdjustment']);
Route::get('/get_reconciliations_adjustment', [PayrollController::class, 'getReconciliationsAdjustment']);

// Route::get('/get_summary_payroll', [PayrollController::class, 'getSummaryPayroll']);
Route::post('/get_summary_payroll', [PayrollController::class, 'getSummaryPayroll']);
// Route::post('/get_onetime_payment_history', [PayrollController::class, 'getOnetimePaymentHistory']);
Route::post('/get_onetime_payment_history', [OneTimePaymentController::class, 'getOnetimePaymentHistory']);
Route::post('/one_time_payment_pay_stub_list', [OneTimePaymentController::class, 'one_time_payment_pay_stub_list']);
Route::post('/one_time_payment_pay_stub_single', [OneTimePaymentController::class, 'one_time_payment_pay_stub_single']);
Route::get('/export_payment_history', [PayrollController::class, 'exportPaymentHistory']);
Route::get('/onetime_payment_total', [PayrollController::class, 'onetimePaymentTotal']);

Route::post('/export_payroll_report', [ExportPayRollController::class, 'exportPayrollReport']);

Route::post('/payroll_mark_as_paid', [PayrollController::class, 'payrollMarkAsPaid']);
Route::post('/payroll_mark_as_unpaid', [PayrollController::class, 'payroll_mark_as_unpaid']);
Route::post('/payroll_move_next_payroll', [PayrollController::class, 'payrollMoveToNextPayroll']);
Route::post('/payroll_undo_next_payroll', [PayrollController::class, 'payroll_undo_next_payroll']);
Route::post('/payroll_adjustment', [PayrollController::class, 'payrollAdjustment']);
Route::post('/moveToReconciliations', [PayrollController::class, 'moveToReconciliations']);
Route::post('/moveRunPayrollToReconStatus', [UserReconciliationController::class, 'moveRunPayrollToReconStatus']);
Route::post('/moveRunPayrollToReconciliations', [UserReconciliationController::class, 'moveRunPayrollToReconciliations']);
Route::post('/addAlertToPayroll', [PayrollController::class, 'addAlertToPayroll']);
Route::get('/payrollReconCommissionbyEmployeeId/{id}', [UserReconciliationController::class, 'payrollReconCommissionbyEmployeeId']);

Route::get('/payrollReconOverridebyEmployeeId/{id}', [UserReconciliationController::class, 'payrollReconOverridebyEmployeeId']);

Route::get('/payrollReconClawbackbyEmployeeId/{id}', [PayrollController::class, 'payrollReconClawbackbyEmployeeId']);
Route::get('/payrollReconAdjustementbyEmployeeId/{id}', [UserReconciliationController::class, 'payrollReconAdjustementbyEmployeeId']);
Route::get('/payrollReconAdjustementsbyEmployeeId/{id}', [PayrollController::class, 'payrollReconAdjustementsbyEmployeeId']);
Route::get('/payrollReconClawbackListbyEmployeeId/{id}', [UserReconciliationController::class, 'payrollReconClawbackListbyEmployeeId']);

// payroll Adjustment details route
Route::post('/payroll_commission_edit', [PayrollAdjustmentController::class, 'payrollCommission']);
Route::post('/payroll_overrides_edit', [PayrollAdjustmentController::class, 'updatePayrollOverrides']);
Route::post('/finalize_payroll_email', [PayrollAdjustmentController::class, 'finalisePayrollEmail']);
Route::post('/payroll_deduction_edit', [PayrollAdjustmentController::class, 'updatePayrollDeduction']);

Route::post('/payrollDeductionsByEmployeeId', [PayrollAdjustmentController::class, 'payrollDeductionsByEmployeeId']);
Route::post('/updatepayrollDeductionsByEmployeeId', [PayrollAdjustmentController::class, 'updatepayrollDeductionsByEmployeeId']);

Route::post('/delete_adjustement', [PayrollAdjustmentController::class, 'deleteAdjustement']);
Route::post('/payroll_reconciliation_rollback', [PayrollAdjustmentController::class, 'payrollReconciliationRollback']);
Route::post('/payrollsReconciliationRollback', [UserReconciliationController::class, 'payrollsReconciliationRollback']);

Route::post('/close_payroll', [PayrollController::class, 'close_payroll']);
Route::post('/get_everee_payables', [EvereeController::class, 'getEvereepayables']);
Route::get('/get_everee_missing_payables', [EvereeController::class, 'getEvereeMissingData']);

Route::post('/runPayrollReconciliationPopUp', [UserReconciliationController::class, 'runPayrollReconciliationPopUp']);
Route::post('/paystub_reconciliation_details', [UserReconciliationController::class, 'paystubReconciliationDetails']);

Route::post('/deleteReconAdjustement', [UserReconciliationController::class, 'deleteReconAdjustement']);

Route::post('/exportReconciliationPayrollHistoriesList', [ExportPayRollController::class, 'exportReconciliationPayrollHistoriesList']);

// export payroll
Route::post('/export_payroll_report', [ExportPayRollController::class, 'exportPayrollReport']);

Route::post('/one_time_payment', [OneTimePaymentController::class, 'create_payment']);
Route::post('/one_time_payment_request', [OneTimePaymentController::class, 'create_request_payment']);
Route::post('/one_time_adjustment_details', [OneTimePaymentController::class, 'one_timepayStubAdjustmentDetails']);
Route::post('/one_time_reimbursement_details', [OneTimePaymentController::class, 'one_timepayStubReimbursementDetails']);
Route::post('/one_time_user_override_details', [OneTimePaymentController::class, 'one_timepayStubUserOverrideDetails']);
Route::post('/one_time_commission_details', [OneTimePaymentController::class, 'one_timepayStubCommissionDetails']);
Route::post('/one_time_payroll_deductions_details', [OneTimePaymentController::class, 'one_timepayStubPayrollDeductionsDetails']);
Route::post('/one_time_reconciliation_details', [OneTimePaymentController::class, 'one_timepayStubReconciliationDetails']);
Route::post('/one_time_additional_details', [OneTimePaymentController::class, 'one_timepayStubAdditionalDetails']);
Route::post('/one_time_wages_details', [OneTimePaymentController::class, 'one_timepayStubWagesDetails']);
Route::post('/payment_request_pay_now', [OneTimePaymentController::class, 'adminPaymentRequestPayNow']);

Route::get('/user-commission-via-pid', [UserReconciliationController::class, 'userCommissionViaPID']);

// added by ash - event for each item move forward and revert back
Route::post('/single_payroll_mark_as_paid', [PayrollSingleController::class, 'singlePayrollMarkAsPaid']);
Route::post('/single_payroll_move_next_payroll', [PayrollSingleController::class, 'singlePayrollMoveToNextPayroll']);

Route::post('/payroll_mark_as_paid_new', [PayrollSingleController::class, 'payrollMarkAsPaid']);
Route::post('/payroll_mark_as_unpaid_new', [PayrollSingleController::class, 'payroll_mark_as_unpaid']);
Route::post('/payroll_move_next_payroll_new', [PayrollSingleController::class, 'payrollMoveToNextPayroll']);
Route::post('/payroll_undo_next_payroll_new', [PayrollSingleController::class, 'payroll_undo_next_payroll']);
Route::post('/moveToReconciliations_new', [PayrollSingleController::class, 'moveToReconciliations']);
Route::post('/commission_details_new', [PayrollSingleController::class, 'commissionDetails'])->name('commissionDetailsMainNew');
Route::post('/override_details_new', [PayrollSingleController::class, 'overrideDetails'])->name('overrideDetailsNew');
Route::post('/adjustment_details_new', [PayrollSingleController::class, 'adjustmentDetails'])->name('adjustmentDetailsNew');
Route::post('/reimbursement_details_new', [PayrollSingleController::class, 'reimbursementDetails'])->name('reimbursementDetailsNew');
Route::post('/payroll_data_new', [PayrollSingleController::class, 'getPayrollData']);

Route::post('/finalize_single_payroll', [PayrollSingleController::class, 'finalizePayroll']);
Route::post('/execute_Payroll', [PayrollSingleController::class, 'executePayroll']);

/* Route for the payroll export functionnality */
Route::post('/workker-basic', [PayrollExportController::class, 'workerBasicExport']);
Route::post('/workker-detail', [PayrollExportController::class, 'workerDetailExport']);
Route::post('/worker-all-details', [PayrollExportController::class, 'workerAllDetailsExport']);
Route::post('/pid-basic', [PayrollExportController::class, 'pidBasicExport']);
Route::post('/pid-detail', [PayrollExportController::class, 'pidDetailExport']);
Route::post('/repayment-export', [PayrollExportController::class, 'repaymentDetailExport']);
