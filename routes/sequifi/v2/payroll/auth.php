<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V2\Payroll\PayrollController;
use App\Http\Controllers\API\V2\Payroll\PayrollSetUpController;
use App\Http\Controllers\API\V2\Payroll\PayrollReportController;
use App\Http\Controllers\API\V2\Payroll\PayrollExportController;
use App\Http\Controllers\API\V2\Payroll\OneTimePaymentController;
use App\Http\Controllers\API\V2\Payroll\PayrollMarkPaidController;
use App\Http\Controllers\API\V2\Payroll\PayrollRePaymentController;
use App\Http\Controllers\API\V2\Payroll\PayrollBreakdownController;
use App\Http\Controllers\API\V2\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\API\V2\Payroll\PayrollMoveToNextController;
use App\Http\Controllers\API\V2\Payroll\PayrollOneTimePaymentController;

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

// MIGRATE worker_type and pay_frequency DATA ON OLD TABLE - DONE
// ONE TIME PAYMENT DATA MIGRATION = one_time_payments, paystub_employee - DONE
// DAILY PAY PAYROLL NEED TO MAKE SURE SINGLE USER PAYROLL DATA - DONE
// NEED TO CREATE API WHICH RE-CALCULATES PAYROLL IF NEED ARISE - DONE
// NEED TO CHECK RECON & IMPLEMENT - IGNORE FOR NOW SINCE THERE ARE MANY CHANGES NEEDED FOR RECON MODULE
// ApprovalsAndRequest - REMOVE 7,8,9 FROM PAYROLL ADJUSTMENT MODEL - DONE
// WHEN MOVED TO NEXT PAYROLL SAVE ref_id UPON EXECUTING PAYROLL - DONE
// CHECK ALL OBSERVER FOR AMOUNT UPDATE - DONE
// ADD is_onetime_payment, one_time_payment_id to W2PayrollTaxDeduction::create - NOT NEEDED ANYMORE
// SALARY & OVERTIME ADJUSTMENT NEEDED TO ADD DATE FOR SEPARATION - DONE
// TWO API FOR PENDING PAY // WAGES & ADDITIONAL FIELDS SAME AS OTP - DONE
// ADD worker_type and pay_frequency TO ALL THE PAYROLL RELATED TABLE - DONE
// NEED TO CHECK payFrequencyNew FUNCTION AND IT"S USE THROUGH OUT THE PROJECT FOR FREQUENCY TYPE - DONE
// NEED TO ADD HISTORY RESPONSE FOR EXECUTED PAYROLL - DONE
// NEED TO SAVE position_id IN PAYROLL TABLE WHERE IT IS NOT BEING SAVED - DONE
// LOG OBSERVER ANY ERROR INTO TABLE - DONE

// NEED TO APPLY COMMON SOLUTION FOR OBSERVER TRIGGER
// NEED TO MANAGE PAID & NEXT PAYROLL STATUS DURING PAY NOW OPTION
// NEED TO IMPLEMENT RECON MODULE
// CHECK TO SEE IF PAYROLL API CAN BE OPTIMIZED ANY FURTHER
// NEED TO ADD INDEXING FOR PAYROLL


Route::post('/get-payroll-data', [PayrollController::class, 'getPayrollData']);
Route::post('/re-calculate-payroll-data', [PayrollController::class, 'reCalculatePayrollData']);
Route::post('/check-payroll-status', [PayrollController::class, 'checkPayrollStatus']);
Route::post('/get-payroll-summary', [PayrollController::class, 'getSummaryPayroll']); // v1/payroll/get_summary_payroll
Route::post('/get-payroll-summary-report', [PayrollController::class, 'getSummaryPayrollReport']); // reports/get_report_summary_payroll
Route::post('/close-payroll', [PayrollController::class, 'closePayroll']); // payroll/close_payroll
Route::post('/finalize-payroll', [PayrollController::class, 'finalizePayroll']); // v1/payroll/finalize_Payroll
Route::post('/execute-payroll', [PayrollController::class, 'executePayroll']); // v1/payroll/execute_Payroll

// PAYROLL BREAKDOWN
Route::post('/salary-breakdown', [PayrollBreakdownController::class, 'salaryBreakdown']);
Route::post('/overtime-breakdown', [PayrollBreakdownController::class, 'overtimeBreakdown']);
Route::post('/commission-breakdown', [PayrollBreakdownController::class, 'commissionBreakdown']);
Route::post('/override-breakdown', [PayrollBreakdownController::class, 'overrideBreakdown']);
Route::post('/adjustment-breakdown', [PayrollBreakdownController::class, 'adjustmentBreakdown']);
Route::post('/reimbursement-breakdown', [PayrollBreakdownController::class, 'reimbursementBreakdown']);
Route::post('/deduction-breakdown', [PayrollBreakdownController::class, 'deductionBreakdown']);
Route::post('/reconciliation-breakdown', [PayrollBreakdownController::class, 'reconciliationBreakdown']);
Route::post('/wages-breakdown', [PayrollBreakdownController::class, 'wagesBreakdown']);
Route::post('/additional-fields-breakdown', [PayrollBreakdownController::class, 'additionalFieldsBreakdown']);
Route::get('/get-custom-field', [PayrollBreakdownController::class, 'getCustomField']); // v1/payroll/get_custom_filed
Route::post('/update-payroll-custom-field', [PayrollBreakdownController::class, 'updatePayrollCustomField']); // v1/payroll/update_payroll_custom_filed
Route::get('/export-custom-field', [PayrollBreakdownController::class, 'exportCustomField']); // v1/payroll/export_custom
Route::post('/import-custom-field', [PayrollBreakdownController::class, 'importCustomField']); // v1/payroll/import_custom

// PAYROLL RE-PAYMENT & PAYROLL REQUESTS
Route::get('/payment-request-list', [PayrollRePaymentController::class, 'paymentRequestList']); // payroll/payment_request //
Route::get('/negative-payment-request-list', [PayrollRePaymentController::class, 'negativePaymentRequestList']); // payroll/advance_negative_payment_request //
Route::post('/payment-request-update', [PayrollRePaymentController::class, 'paymentRequestUpdate']); // v1/payroll/update_payment_request //
Route::post('/undo-payment-request', [PayrollRePaymentController::class, 'undoPaymentRequest']); // v1/payroll/undo_payroll_approval_request //
Route::post('/advance-repayment', [PayrollRePaymentController::class, 'advanceRepayment']); // payroll/advance_repayment //

// PAYROLL ADJUSTMENTS
Route::post('/salary-adjustment', [PayrollAdjustmentController::class, 'salaryAdjustment']); // v1/payroll/payroll_hourly_salary_edit
Route::post('/overtime-adjustment', [PayrollAdjustmentController::class, 'overtimeAdjustment']); // v1/payroll/payroll_overtime_edit
Route::post('/commission-adjustment', [PayrollAdjustmentController::class, 'commissionAdjustment']); // payroll/payroll_commission_edit
Route::post('/override-adjustment', [PayrollAdjustmentController::class, 'overrideAdjustment']); // payroll/payroll_overrides_edit
Route::post('/deduction-adjustment', [PayrollAdjustmentController::class, 'deductionAdjustment']); // payroll/payroll_deduction_edit
Route::post('/delete-adjustment', [PayrollAdjustmentController::class, 'deleteAdjustment']); // v1/payroll/single_payroll_adjustment_delete

// PAYROLL MARK AS PAID
Route::post('/user-mark-as-paid', [PayrollMarkPaidController::class, 'userMarkAsPaid']); // v1/payroll/payroll_mark_as_paid //
Route::post('/user-mark-as-unpaid', [PayrollMarkPaidController::class, 'userMarkAsUnpaid']); // v1/payroll/payroll_mark_as_unpaid
Route::post('/single-mark-as-paid', [PayrollMarkPaidController::class, 'singleMarkAsPaid']); // v1/payroll/single_payroll_mark_as_paid
Route::post('/single-mark-as-unpaid', [PayrollMarkPaidController::class, 'singleMarkAsUnpaid']); // v1/payroll/single_payroll_mark_as_paid - NEW
Route::post('/undo-all-status', [PayrollMarkPaidController::class, 'undoAllStatus']);

// PAYROLL MOVE TOO NEXT
Route::post('/user-move-to-next', [PayrollMoveToNextController::class, 'userMoveToNext']); // v1/payroll/payroll_move_next_payroll
Route::post('/user-move-to-next-undo', [PayrollMoveToNextController::class, 'userMoveToNextUndo']); // v1/payroll/payroll_undo_next_payroll
Route::post('/single-move-to-next', [PayrollMoveToNextController::class, 'singleMoveToNext']); // v1/payroll/single_payroll_move_next_payroll
Route::post('/single-move-to-next-undo', [PayrollMoveToNextController::class, 'singleMoveToNextUndo']); // v1/payroll/single_payroll_move_next_payroll - NEW

// PAYROLL MOVE TO RECON
Route::post('/single-move-to-recon', [PayrollMoveToNextController::class, 'singleMoveToRecon']); // v1/payroll/move_to_recon

// PAYROLL ONE TIME PAYMENT & PAY NOW
Route::post('/user-one-time-payment', [PayrollOneTimePaymentController::class, 'userOneTimePayment']); // v1/payroll/one_time_payment_pay_now
Route::post('/single-one-time-payment', [PayrollOneTimePaymentController::class, 'singleOneTimePayment']); // v1/payroll/single_one_time_payment_pay_now

// ONE-TIME PAYMENT
Route::post('/one-time-history-list', [OneTimePaymentController::class, 'oneTimeHistoryList']); // payroll/get_onetime_payment_history // ENDPOINT
Route::get('/total-one-time-payment', [OneTimePaymentController::class, 'totalOneTimePayment']); // payroll/onetime_payment_total // ENDPOINT
Route::post('/one-time-payment', [OneTimePaymentController::class, 'oneTimePayment']); // payroll/one_time_payment // ENDPOINT
Route::post('/one-time-payment-pay-stub', [OneTimePaymentController::class, 'oneTimePaymentPayStub']); // payroll/one_time_payment_pay_stub_single // ENDPOINT
Route::post('/one-time-payment-commission-details', [OneTimePaymentController::class, 'oneTimePaymentCommissionDetails']); // payroll/one_time_commission_details
Route::post('/one-time-payment-override-details', [OneTimePaymentController::class, 'oneTimePaymentOverrideDetails']); // payroll/one_time_user_override_details
Route::post('/one-time-payment-adjustment-details', [OneTimePaymentController::class, 'oneTimePaymentAdjustmentDetails']); // payroll/one_time_adjustment_details
Route::post('/one-time-payment-reimbursement-details', [OneTimePaymentController::class, 'oneTimePaymentReimbursementDetails']); // payroll/one_time_reimbursement_details
Route::post('/one-time-payment-deduction-details', [OneTimePaymentController::class, 'oneTimePaymentDeductionDetails']); // NEW
Route::post('/one-time-payment-reconciliation-details', [OneTimePaymentController::class, 'oneTimePaymentReconciliationDetails']); // payroll/one_time_reconciliation_details
Route::post('/one-time-payment-additional-details', [OneTimePaymentController::class, 'oneTimePaymentAdditionalDetails']); // payroll/one_time_additional_details
Route::post('/one-time-payment-wages-details', [OneTimePaymentController::class, 'oneTimePaymentWagesDetails']); // payroll/one_time_wages_details
Route::post('/payment-request-add-payroll', [OneTimePaymentController::class, 'paymentRequestAddPayroll']); // v1/payroll/payment_request_add_payroll
Route::post('/payment-request-pay-now', [OneTimePaymentController::class, 'paymentRequestPayNow']); // payroll/payment_request_pay_now

// PAYROLL EXPORT
Route::post("/pid-basic", [PayrollExportController::class, "pidBasic"]);
Route::post("/pid-detail", [PayrollExportController::class, "pidDetail"]);
Route::post("/worker-basic", [PayrollExportController::class, "workerBasic"]);
Route::post("/worker-detail", [PayrollExportController::class, "workerDetail"]);
Route::post("/worker-all-details", [PayrollExportController::class, "workerAllDetails"]);
Route::post("/repayment-export", [PayrollExportController::class, "repaymentDetail"]); // NO CHANGES ARE MADE AS OF YET, HAVE TO MAKE ONCE RE-PAYMENT IS IMPLEMENTED

// PAYROLL SETUP
Route::get("/get-advance-payment-setting", [PayrollSetUpController::class, "getAdvancePaymentSetting"]); // setting/advance-payment-setting
Route::post("/advance-payment-setting", [PayrollSetUpController::class, "advancePaymentSetting"]); // setting/advance-payment-setting

// PAYROLL REPORT
Route::post("/payroll-report", [PayrollReportController::class, "payrollReport"]); // v1/payroll/get_report_year_month_frequency_wise
Route::post("/payroll-report-data", [PayrollReportController::class, "payrollReportData"]); // v1/reports/payroll_report
Route::post("/pid-basic-report", [PayrollReportController::class, "pidBasic"]); // v1/reports/pid-basic
Route::post("/pid-detail-report", [PayrollReportController::class, "pidDetail"]); // v1/reports/pid-detail
Route::post("/worker-basic-report", [PayrollReportController::class, "workerBasic"]); // v1/reports/workker-basic
Route::post("/worker-detail-report", [PayrollReportController::class, "workerDetail"]); // v1/reports/workker-detail
Route::post("/worker-all-details-report", [PayrollReportController::class, "workerAllDetails"]); // v1/reports/worker-all-details

// PENDING PAY
Route::post("/pending-pay", [PayrollReportController::class, "pendingPay"]); // v1/sales/pending_pay
Route::post("/past-pay-stub", [PayrollReportController::class, "pastPayStub"]); // managerreports/past_pay_stub
Route::post("/past-pay-stub-details", [PayrollReportController::class, "pastPayStubDetails"]); // v1/managerreports/past_pay_stub_detail_list
Route::post("/one-time-payment-pay-stub-list", [PayrollReportController::class, "oneTimePaymentPayStubList"]); // payroll/one_time_payment_pay_stub_list

// PAY STUB BREAKDOWN
Route::post('/pay-stub-commission-breakdown', [PayrollReportController::class, 'commissionBreakdown']);
Route::post('/pay-stub-override-breakdown', [PayrollReportController::class, 'overrideBreakdown']);
Route::post('/pay-stub-adjustment-breakdown', [PayrollReportController::class, 'adjustmentBreakdown']);
Route::post('/pay-stub-reimbursement-breakdown', [PayrollReportController::class, 'reimbursementBreakdown']);
Route::post('/pay-stub-deduction-breakdown', [PayrollReportController::class, 'deductionBreakdown']);
Route::post('/pay-stub-reconciliation-breakdown', [PayrollReportController::class, 'reconciliationBreakdown']);
Route::post('/pay-stub-wages-breakdown', [PayrollReportController::class, 'wagesBreakdown']);
Route::post('/pay-stub-additional-fields-breakdown', [PayrollReportController::class, 'additionalFieldsBreakdown']);


// managerreports/past_pay_stub_graph