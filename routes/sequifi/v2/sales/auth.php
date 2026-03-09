<?php

use App\Http\Controllers\API\V2\Sales\SalesController;
use App\Http\Controllers\API\V2\Sales\SalesControllerForSalesExport;
use App\Http\Controllers\API\V2\Sales\SalesHistoryController;
use App\Http\Controllers\API\V2\Sales\SalesReportController;
use App\Http\Controllers\API\V2\Sales\SalesStandardController;
use App\Http\Controllers\API\V2\Sales\SalesStandardOverridesController;
use App\Http\Controllers\API\V2\SalesImportTemplate\SalesImportTemplateController;
use App\Http\Controllers\API\V2\Tiers\TierResetController;
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

// ADMIN => REPORTS => Sales
Route::get('/setterCloserListByEffectiveDate', [SalesController::class, 'setterCloserListByEffectiveDate']);
Route::get('/milestoneFromProduct', [SalesController::class, 'milestoneFromProduct']);

Route::post('/sales-list', [SalesController::class, 'salesList']);
Route::post('/sales-export', [SalesController::class, 'salesExport']); // Changed to use SalesExportJob with progress notifications
Route::post('/sales-by-pid', [SalesController::class, 'saleByPid']);

// Sale History Routes - Dedicated Controller
Route::prefix('sales-history')->group(function () {
    Route::get('/pid/{pid}', [SalesHistoryController::class, 'getHistoryByPid']);
    Route::get('/sale/{id}', [SalesHistoryController::class, 'getHistoryById']);
    Route::get('/field/{field}', [SalesHistoryController::class, 'getHistoryByField']);
    Route::get('/data-source/{sourceType}', [SalesHistoryController::class, 'getHistoryByDataSource']);
});

Route::post('/sale-edit', [SalesController::class, 'saleEdit']);
Route::post('/sale-product-triggers', [SalesController::class, 'saleProductTriggers']);
Route::post('/get-user-redline', [SalesController::class, 'getUserRedline']);
Route::post('/get-sale-redline', [SalesController::class, 'getSaleRedline']);

Route::post('/add-manual-sale', [SalesController::class, 'addManualSaleData']);
Route::post('/recalculate-sale', [SalesController::class, 'recalculateSale']);
Route::post('/recalculate-sale-all', [SalesController::class, 'recalculateSaleAll']);
Route::post('/resolve-sales-alert', [SalesController::class, 'resolveSalesAlert']);

// Performance Monitoring Routes
Route::prefix('performance')->group(function () {
    Route::get('/batch-status/{batch_id}', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getBatchStatus'])->name('api.v2.sales.batch-status');
    Route::get('/comparison', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getPerformanceComparison']);
    Route::get('/recent-batches', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getRecentBatches']);
    Route::get('/system-metrics', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getSystemMetrics']);
    Route::get('/dashboard', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getDashboardData']);
    Route::get('/active-batches', [\App\Http\Controllers\API\V2\Sales\SalesPerformanceController::class, 'getActiveBatches']);
});

// to import sales from legacy_api_raw_data_histories to sale_master
Route::post('/sales-import-pending', [SalesController::class, 'salesImportPending']);

Route::post('/account-summary', [SalesController::class, 'accountSummary']);
Route::post('/account-summary-by-position', [SalesController::class, 'accountSummaryByPosition']);
Route::post('/account-summary-projection', [SalesController::class, 'accountSummaryProjection']);
Route::post('/account-overrides', [SalesController::class, 'accountOverrides']);

// SALES REPORT
Route::post('/sales-contracts', [SalesReportController::class, 'salesContracts']);
Route::post('/sales-install-ratio', [SalesReportController::class, 'salesInstallRatio']);
Route::post('/sales-best-average', [SalesReportController::class, 'salesBestAverage']);
Route::post('/sales-account', [SalesReportController::class, 'salesAccount']);

// STANDARD => REPORTS => SALES
Route::post('/report-sales-list', [SalesStandardController::class, 'reportSalesList']);
Route::post('/report-sales-graph', [SalesStandardController::class, 'reportSalesGraph']); //
Route::post('/report-account-install-ratio-graph', [SalesStandardController::class, 'reportAccountInstallRatioGraph']);

// STANDARD => MU EARNING => MY SALES
Route::post('/my-sales-list', [SalesStandardController::class, 'mySalesList']);
Route::post('/my-sales-graph', [SalesStandardController::class, 'mySalesGraph']); //
Route::post('/my-account-install-ratio-graph', [SalesStandardController::class, 'myAccountInstallRatioGraph']); //
Route::post('/projected-sale-earnings', [SalesStandardController::class, 'projectedSaleEarnings']);

Route::post('/migrate-product-data', [SalesStandardController::class, 'migrateProductData']);
Route::post('/migrate-agreement-data', [SalesStandardController::class, 'migrateUserAgreementData']);
Route::post('/migrate-solar-data', [SalesStandardController::class, 'migrateSolarData']);
Route::post('/migrate-sale-data', [SalesStandardController::class, 'migrateSaleData']);

// STANDARD => MY EARNING => MY OVERRIDES
Route::post('/my-overrides-list', [SalesStandardOverridesController::class, 'myOverridesList']);
Route::post('/my-overrides-cards', [SalesStandardOverridesController::class, 'myOverridesCards']);
Route::post('/my-overrides-graph', [SalesStandardOverridesController::class, 'myOverridesGraph']);

Route::post('/tier-reset', [TierResetController::class, 'resetTiers']);
Route::post('/tier-sync', [TierResetController::class, 'tiersSync']);

Route::post('/recalculate_sales_from_commission', [SalesController::class, 'recalculateSalesFromCommission']);

// MANUAL WORKER
Route::get('/manualSetterCloserListByEffectiveDate', [SalesController::class, 'setterCloserListForManualWorker']);
Route::post('/add-manual-worker', [SalesController::class, 'addManualWorker']);
Route::delete('/remove-manual-worker/{workerId}/{pid}', [SalesController::class, 'removeManualWorker']);

// MANUAL OVERRIDES
Route::post('/add-manual-overrides', [SalesController::class, 'addManualOverrides']);
Route::delete('/remove-manual-overrides', [SalesController::class, 'removeManualOverrides']);
Route::post('/edit-manual-override', [SalesController::class, 'editManualOverride']);


// NEW V2 HARD DELETE OVERRIDE SYSTEM
Route::post('/delete-override-v2', [SalesController::class, 'deleteOverrideV2']);
Route::post('/restore-override-v2', [SalesController::class, 'restoreOverrideV2']);


// ADMIN SALES DELETION
Route::post('/delete-sale', [SalesController::class, 'deleteSale']);

Route::post('/import-template-list', [SalesImportTemplateController::class, 'templateList']);
Route::post('/import-template-save', [SalesImportTemplateController::class, 'createOrUpdate']);
Route::post('/import-template-delete', [SalesImportTemplateController::class, 'templateDelete']);
