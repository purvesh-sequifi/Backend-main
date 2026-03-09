<?php

use App\Http\Controllers\API\PastAccountAlertController;
use App\Http\Controllers\API\SaleRecalculateController;
use App\Http\Controllers\API\Sales\MyOverridesController;
use App\Http\Controllers\API\Sales\SalesController;
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

Route::post('/sales_list', [SalesController::class, 'index'])->name('saleslist');

Route::get('/getPidByUser/{id}', [SalesController::class, 'getPidByUser']);

Route::post('/filter', [SalesController::class, 'filter'])->name('filter');

Route::post('/system_size', [SalesController::class, 'system_size']);

Route::post('/my_sales_graph', [SalesController::class, 'mySalesGraph']);

Route::post('/account_install_ratio_graph_api', [SalesController::class, 'account_graph']);

Route::post('/search', [SalesController::class, 'search']);

Route::get('/customerSaleTracking/{id}', [SalesController::class, 'customerSaleTracking']);

Route::get('/getOverrides', [MyOverridesController::class, 'getOverrides']);

Route::get('/pay_stub', [SalesController::class, 'payStub'])->name('pay_stub');

Route::get('/account_overrides/{pid}', [SalesController::class, 'accountOverride']);
Route::get('/get_user_redlines/{pid}', [SalesController::class, 'getUserRedlines']);
Route::post('/get_user_wise_redlines', [SalesController::class, 'getUserWiseRedlines']);

// Past Account Alert
Route::post('/move_alert_center', [PastAccountAlertController::class, 'moveAlertCenter']);

// Sale Recalculate
Route::post('/recalculate_sale_data', [SaleRecalculateController::class, 'recalculateSaleData']);
