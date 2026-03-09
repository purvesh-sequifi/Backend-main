<?php

use App\Http\Controllers\API\V1\PayrollMoveToReconController;
use App\Http\Controllers\API\V1\ReconController;
use App\Http\Controllers\API\V1\ReconFinalizeController;
use App\Http\Controllers\API\V1\ReconPayrollController;
use App\Http\Controllers\API\V1\ReconPopUpController;
use App\Http\Controllers\API\V1\ReconReportController;
use App\Http\Controllers\API\V1\ReconStandardReportController;
use App\Http\Controllers\API\V1\ReconStandardUserReportController;
use App\Http\Controllers\API\V1\ReconViewReports\ViewReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('/payroll')->name('payroll.')->group(function () {
    Route::post('/recon-send-to-payroll', [ReconController::class, 'reconSendToPayroll']);
    Route::post('/reconciliation-list-user-skipped', [ReconController::class, 'reconciliationListUserSkipped']);
    Route::post('/reconciliation-list-user-skipped-undo', [ReconController::class, 'reconciliationListUserSkippedUndo']);
    Route::get('/recon-finalize-draft-list', [ReconController::class, 'finalizeReconDraftList']);
    Route::post('/finalizeReconciliationList', [ReconController::class, 'finalizeReconciliationList']);
    Route::post('/start-recon', [ReconPayrollController::class, 'startReconList']);
    // /* Route::get("/run-payroll-recon-pop-up/{id}", [ReconPayrollController::class, "runPayrollReconciliationPopUp"]); */
    Route::get('/run-payroll-recon-pop-up/{id}', [ReconPopUpController::class, 'reconCommissionPopup']);

    /* move to recon api from payroll */
    Route::post('/move-to-recon', [PayrollMoveToReconController::class, 'moveToRecon']);
    Route::post('/reconciliationFinalizeDraft', [ReconFinalizeController::class, 'reconciliationFinalizeDraft']);

    Route::post('/recon-clawback-adjustment', [ReconPopUpController::class, 'updateReconClawbackDetails']);
    Route::post('/recon-adjustment-popup', [ReconPopUpController::class, 'reconAdjustmentPopup']);
    Route::post('/report-recon-adjustment-popup', [ReconPopUpController::class, 'reportReconAdjustmentPopup']);
    // /* Route::post("/over-ride-move-to-recon", [PayrollMoveToReconController::class, "overrideMoveToRecon"]); */

    // /* Route::post("/move-to-recon", [ReconPayrollController::class, "moveToReconciliations"]); */ test
    Route::resource('/', ReconPayrollController::class);
    Route::post('/recon-user-commission-account-summary/{userId}', [ReconPopUpController::class, 'reconUserCommissionAccountSummary']);
    Route::post('/report-recon-user-commission-account-summary', [ReconPopUpController::class, 'reportReconUserCommissionAccountSummary']);
});
Route::prefix('/reports')->name('payroll.')->group(function () {
    Route::get('/', [ReconReportController::class, 'mainReport']);
    Route::get('/main-report-export', [ReconReportController::class, 'mainReportExport']);
    Route::post('/user-recon-report-export', [ReconReportController::class, 'userReconReportExport']);
    Route::post('/standard-report-past-recon', [ReconReportController::class, 'standardReportPastReconciliation']);
    // /* Route::post("/standard-report-deduction-list", [ReconReportController::class, "standardReportDeductionList"]); */

    /* standard report api */
    Route::post('/recon-standard-commission-breakdown-graph', [ReconStandardReportController::class, 'commissionBreakdownGraph']);
    Route::post('/recon-standard-outstanding-recon-values', [ReconStandardReportController::class, 'outstandingReconValues']);
    Route::post('/recon-standard-outstanding-recon-graph', [ReconStandardReportController::class, 'outstandingReconGraph']);
    // /* Route::post("/recon-commission-report-by-userid", [ReconStandardReportController::class, "getCommissionReconReport"]); */
    Route::post('/recon-commission-report-list/{id}', [ReconStandardReportController::class, 'getCommissionReportList']);

    Route::post('/top-header-recon-values', [ReconStandardUserReportController::class, 'topHeaderReportData']);
    Route::post('/recon-breakdown-recon-graph', [ReconStandardUserReportController::class, 'reconBreakDownReconGraph']);
    Route::post('/recon-commission-report-by-userid', [ReconStandardUserReportController::class, 'getCommissionReconReport']);
    Route::post('/recon-clawback-report-list', [ReconStandardUserReportController::class, 'getClawbackReportList']);
    Route::post('/get-overrides-card-details', [ReconStandardUserReportController::class, 'getOverridesCardDetails']);
    Route::post('/standard-report-deduction-list', [ReconStandardUserReportController::class, 'standardReportDeductionList']);
    Route::post('/standard-report-adjustment-list', [ReconStandardUserReportController::class, 'adjustmentReconReport']);

    Route::post('/get-override-reports-list', [ReconStandardReportController::class, 'getOverrideReportsList']);
    Route::post('/recon-commission-overrides-card-detail', [ReconStandardReportController::class, 'getCommissionOverridesCardDetails']);

});
Route::resource('/recon', ReconController::class);
Route::post('setup-active-inactive', [ReconController::class, 'reconCompanySettingStatus']);
Route::get('position-setup-active-inactive', [ReconController::class, 'reconPositionStatus']);

/* Standard view report */
Route::prefix('view-reports')->group(function () {
    Route::post('/', [ViewReportController::class, 'index'])->name('view-reports');
    Route::post('/view-clawback-reports', [ViewReportController::class, 'clawbackReportList'])->name('view-reports.clawback');
    Route::post('/view-commission-reports', [ViewReportController::class, 'commissionReportList'])->name('view-reports.commission');
    Route::post('/view-overrides-reports', [ViewReportController::class, 'overridesReportList'])->name('view-reports.overrides');
    Route::post('/view-recon-adjustment-reports', [ViewReportController::class, 'reconAdjustmentReportList'])->name('view-reports.adjustment');
});
