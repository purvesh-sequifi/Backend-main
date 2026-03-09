<?php

use App\Http\Controllers\API\Sales\SalesPendingPayController;
use App\Http\Controllers\API\Sales\SalesProjectionsController;
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

Route::post('/sales_list', [SalesProjectionsController::class, 'index'])->name('saleslist_v1');
Route::post('/user_projection_summary', [SalesProjectionsController::class, 'userProjectionSummary']);
Route::post('/account_install_ratio_graph_api', [SalesProjectionsController::class, 'account_graph']);

Route::post('/pending_pay', [SalesPendingPayController::class, 'getPendingPay']);

Route::post('/commission_details', [SalesPendingPayController::class, 'commissionDetails']);
Route::post('/override_details', [SalesPendingPayController::class, 'overrideDetails']);
Route::post('/PayrollReconciliation', [SalesPendingPayController::class, 'PayrollReconciliation']);
Route::post('/adjustment_details', [SalesPendingPayController::class, 'adjustmentDetails']);
Route::post('/additional_value_details', [SalesPendingPayController::class, 'additionalValueDetails']);
Route::post('/wages_details', [SalesPendingPayController::class, 'wagesDetails']);

Route::post('/current_pay_stub_customer_info', [SalesPendingPayController::class, 'currentpaystubcustomerinfo']);
Route::post('/reimbursementDetails', [SalesPendingPayController::class, 'reimbursementDetails']);
Route::post('/payrollDeductions', [SalesPendingPayController::class, 'payrollDeductions']);

Route::post('/sync_projected_overrides_data/{pid?}', [SalesProjectionsController::class, 'syncProjectedOverridesData']);
Route::post('/sync_projected_commission_data/{pid?}', [SalesProjectionsController::class, 'syncProjectedCommissionData']);
