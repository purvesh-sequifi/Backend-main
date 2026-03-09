<?php

use App\Http\Controllers\API\V2\SequiDocs\EmailTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Email Tracking Routes
|--------------------------------------------------------------------------
|
| Routes for tracking email opens and managing email tracking statistics
| for SequiDocs documents, especially offer letters.
|
*/

// Public route for email tracking pixel (no authentication required)
Route::get('/track/{token}', [EmailTrackingController::class, 'trackEmailOpen'])
    ->name('email.track')
    ->where('token', '[A-Za-z0-9_]+');

// Authenticated routes for email tracking statistics and management
Route::middleware(['auth:sanctum'])->group(function () {

    // Get tracking statistics for a specific document
    Route::get('/stats', [EmailTrackingController::class, 'getTrackingStats'])
        ->name('email.tracking.stats');

    // Get bulk tracking statistics for multiple documents
    Route::get('/bulk-stats', [EmailTrackingController::class, 'getBulkTrackingStats'])
        ->name('email.tracking.bulk-stats');

    // Status transition endpoints
    Route::get('/status-transition-eligibility', [EmailTrackingController::class, 'checkStatusTransitionEligibility'])
        ->name('email.tracking.status-eligibility');

    Route::get('/available-status-transitions', [EmailTrackingController::class, 'getAvailableStatusTransitions'])
        ->name('email.tracking.available-transitions');

    Route::get('/document-eligibility', [EmailTrackingController::class, 'checkDocumentEligibility'])
        ->name('email.tracking.document-eligibility');
});
