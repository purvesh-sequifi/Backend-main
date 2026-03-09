<?php
//api
use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AwsLambdaApiController;
use App\Http\Controllers\API\ChatGPT\ChatGPTController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\CustomSalesFieldController;
use App\Http\Controllers\API\DataImportExportController;
use App\Http\Controllers\API\Dropdown\DropdownController;
use App\Http\Controllers\API\EmailConfigurationController;
use App\Http\Controllers\API\Everee\EvereeController;
use App\Http\Controllers\API\ExternalHiring\ExternalEmployeeHiringController;
use App\Http\Controllers\API\FeatureFlagController;
use App\Http\Controllers\API\HealthCheckController;
use App\Http\Controllers\API\Hiring\LeadsController;
use App\Http\Controllers\API\Hiring\OnboardingEmployeeController;
use App\Http\Controllers\API\HubSpotController;
use App\Http\Controllers\API\HubSpotCurrentEnergyController;
use App\Http\Controllers\API\IntegrationController;
use App\Http\Controllers\API\JobNotificationController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\Plaid\PlaidController;
use App\Http\Controllers\API\QuickBooks\QuickBooksController;
use App\Http\Controllers\API\SClearance\SClearanceController;
use App\Http\Controllers\API\SClearance\TurnAiController;
use App\Http\Controllers\API\StripeBillingController;
use App\Http\Controllers\API\SwaggerController;
use App\Http\Controllers\API\UserImportController;
use App\Http\Controllers\API\V2\CustomFields\CustomFieldsController;
use App\Http\Controllers\arcsiteController;
use App\Http\Controllers\supervisorTestController;
use App\Http\Controllers\TestDataController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Feature Flags (Read-Only for Frontend - to check feature status)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/v1/feature-flags', [FeatureFlagController::class, 'index']);
    Route::get('/v1/feature-flags/{feature}', [FeatureFlagController::class, 'check']);
});

// Custom Sales Fields (Protected by Feature Flag Middleware)
Route::prefix('v1/custom-sales-fields')
    ->middleware(['auth:sanctum', 'feature:custom-sales-fields'])
    ->group(function () {
        // CRUD for custom fields
        Route::get('/', [CustomSalesFieldController::class, 'index']);
        Route::post('/', [CustomSalesFieldController::class, 'store']);
        Route::post('/bulk', [CustomSalesFieldController::class, 'storeBulk']);
        Route::get('/archived', [CustomSalesFieldController::class, 'archivedList']);
        Route::get('/position-dropdown', [CustomSalesFieldController::class, 'positionDropdown']);
        Route::post('/sync-import-fields', [CustomSalesFieldController::class, 'syncImportFields']);
        
        // Sale field values - MUST be before /{id} routes to avoid conflicts
        // pid can be alphanumeric (letters, numbers, underscores, hyphens)
        Route::post('/save-values', [CustomSalesFieldController::class, 'saveValues']);
        Route::get('/values/{pid}', [CustomSalesFieldController::class, 'getValues'])->where('pid', '[a-zA-Z0-9_-]+');
        Route::get('/sale-details/{pid}', [CustomSalesFieldController::class, 'getSaleDetails'])->where('pid', '[a-zA-Z0-9_-]+');
        
        // Single resource routes (must be after specific routes like /values/{pid})
        Route::get('/{id}', [CustomSalesFieldController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [CustomSalesFieldController::class, 'update'])->where('id', '[0-9]+');
        Route::post('/{id}/archive', [CustomSalesFieldController::class, 'archive'])->where('id', '[0-9]+');
        Route::post('/{id}/unarchive', [CustomSalesFieldController::class, 'unarchive'])->where('id', '[0-9]+');
        Route::get('/{id}/check-usage', [CustomSalesFieldController::class, 'checkUsage'])->where('id', '[0-9]+');
    });

// Sale-specific custom fields route (for sale details page)
// Route: GET /v1/sales/{pid}/custom-fields
// Returns custom fields with calculated values for a specific sale
Route::middleware(['auth:sanctum', 'feature:custom-sales-fields'])
    ->get('/v1/sales/{pid}/custom-fields', [CustomSalesFieldController::class, 'getSaleCustomFields'])
    ->where('pid', '[a-zA-Z0-9_-]+');
