<?php

use App\Http\Controllers\API\V2\Recon\PayrollMoveToReconController;
use App\Http\Controllers\API\V2\Recon\ReconController;
use App\Http\Controllers\API\V2\Recon\ReconFinalizeController;
use App\Http\Controllers\API\V2\Recon\ReconPayrollController;
use App\Http\Controllers\API\V2\Recon\ReconPopUpController;
use App\Http\Controllers\API\V2\Recon\ReconReportController;
use App\Http\Controllers\API\V2\Recon\ReconStandardReportController;
use App\Http\Controllers\API\V2\Recon\ReconStandardUserReportController;
use App\Http\Controllers\API\V2\ReconViewReports\ViewReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('/payroll')->name('v2.payroll.')->group(function () {
    Route::post('/recon-send-to-payroll', [ReconController::class, 'reconSendToPayroll']);
    Route::post('/reconciliation-list-user-skipped', [ReconController::class, 'reconciliationListUserSkipped']);
    Route::post('/reconciliation-list-user-skipped-undo', [ReconController::class, 'reconciliationListUserSkippedUndo']);
    Route::get('/recon-finalize-draft-list', [ReconController::class, 'finalizeReconDraftList']);
    Route::post('/finalizeReconciliationList', [ReconController::class, 'finalizeReconciliationList']);

    Route::get('/run-payroll-recon-pop-up/{id}', [ReconPopUpController::class, 'reconCommissionPopup']);
    Route::get('/run-payroll-recon-override-pop-up/{id}', [ReconPopUpController::class, 'reconOverridePop']);
    Route::get('/run-payroll-recon-clawback-pop-up/{id}', [ReconPopUpController::class, 'reconClawbackPopup']);
    Route::post('/reconciliation_details_edit', [ReconPopUpController::class, 'reconAdjustmentEdit']);
    Route::post('/reconciliation_overrides_details_edit', [ReconPopUpController::class, 'overrideAdjustmentEdit']);
    Route::post('/recon-clawback-adjustment', [ReconPopUpController::class, 'updateReconClawbackDetails']);
    Route::post('/recon-adjustment-popup', [ReconPopUpController::class, 'reconAdjustmentPopup']);
    Route::post('/report-recon-adjustment-popup', [ReconPopUpController::class, 'reportReconAdjustmentPopup']);
    Route::post('/recon-user-commission-account-summary/{userId}', [ReconPopUpController::class, 'reconUserCommissionAccountSummary']);
    Route::post('/report-recon-user-commission-account-summary', [ReconPopUpController::class, 'reportReconUserCommissionAccountSummary']);

    /* start recon payroll */
    Route::resource('/', ReconPayrollController::class);
    Route::post('/start-recon', [ReconPayrollController::class, 'startReconList']);
    // Route::post("/move-to-recon", [ReconPayrollController::class, "moveToReconciliations"]);

    Route::post('/reconciliationFinalizeDraft', [ReconFinalizeController::class, 'reconciliationFinalizeDraft']);

    /* move to recon api from payroll */
    Route::post('/move-to-recon', [PayrollMoveToReconController::class, 'moveToRecon']);
    // Route::post("/over-ride-move-to-recon", [PayrollMoveToReconController::class, "overrideMoveToRecon"]);

});

Route::prefix('/reports')->name('v2.payroll.')->group(function () {
    Route::get('/', [ReconReportController::class, 'mainReport']);
    Route::get('/main-report-export', [ReconReportController::class, 'mainReportExport']);
    Route::post('/user-recon-report-export', [ReconReportController::class, 'userReconReportExport']);
    // Route::post("/standard-report-past-recon", [ReconReportController::class, "standardReportPastReconciliation"]);
    // Route::post("/standard-report-deduction-list", [ReconReportController::class, "standardReportDeductionList"]);

    /* standard report api */
    Route::post('/standard-report-past-recon', [ReconStandardReportController::class, 'standardReportPastReconciliation']);
    Route::post('/recon-standard-commission-breakdown-graph', [ReconStandardReportController::class, 'commissionBreakdownGraph']);
    Route::post('/recon-standard-outstanding-recon-values', [ReconStandardReportController::class, 'outstandingReconValues']);
    Route::post('/recon-standard-outstanding-recon-graph', [ReconStandardReportController::class, 'outstandingReconGraph']);
    Route::post('/recon-commission-report-list/{id}', [ReconStandardReportController::class, 'getCommissionReportList']);
    Route::post('/get-override-reports-list', [ReconStandardReportController::class, 'getOverrideReportsList']);
    // Route::post("/recon-commission-overrides-card-detail", [ReconStandardReportController::class, "getCommissionOverridesCardDetails"]);
    // Route::post("/recon-commission-report-by-userid", [ReconStandardReportController::class, "getCommissionReconReport"]);

    Route::post('/top-header-recon-values', [ReconStandardUserReportController::class, 'topHeaderReportData']);
    Route::post('/recon-breakdown-recon-graph', [ReconStandardUserReportController::class, 'reconBreakDownReconGraph']);
    Route::post('/recon-commission-overrides-card-detail', [ReconStandardUserReportController::class, 'getCommissionOverridesCardDetails']);
    Route::post('/recon-commission-report-by-userid', [ReconStandardUserReportController::class, 'getCommissionReconReport']);
    Route::post('/recon-clawback-report-list', [ReconStandardUserReportController::class, 'getClawbackReportList']);
    Route::post('/get-overrides-card-details', [ReconStandardUserReportController::class, 'getOverridesCardDetails']);
    Route::post('/standard-report-deduction-list', [ReconStandardUserReportController::class, 'standardReportDeductionList']);
    Route::post('/standard-report-adjustment-list', [ReconStandardUserReportController::class, 'adjustmentReconReport']);

});

Route::name('v2.')->group(function () {
    Route::resource('/recon', ReconController::class);
});
Route::post('setup-active-inactive', [ReconController::class, 'reconCompanySettingStatus']);
Route::get('position-setup-active-inactive', [ReconController::class, 'reconPositionStatus']);

/* Standard view report */
Route::prefix('view-reports')->name('view-reports-v2.')->group(function () {
    Route::post('/', [ViewReportController::class, 'index'])->name('index');
    Route::post('/view-clawback-reports', [ViewReportController::class, 'clawbackReportList'])->name('clawback');
    Route::post('/view-commission-reports', [ViewReportController::class, 'commissionReportList'])->name('commission');
    Route::post('/view-overrides-reports', [ViewReportController::class, 'overridesReportList'])->name('overrides');
    Route::post('/view-recon-adjustment-reports', [ViewReportController::class, 'reconAdjustmentReportList'])->name('adjustment');
    Route::post('/view-recon-duction-reports', [ViewReportController::class, 'reconductionreports'])->name('reconduction');
});
