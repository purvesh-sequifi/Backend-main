<?php

use App\Http\Controllers\API\Reports\ReportsPayrollControllerV1;
use App\Http\Controllers\API\Reports\ReportsUsersController;
use Illuminate\Support\Facades\Route;

// External API Endpoints (Requires External API Token with payroll:read scope)

Route::post('/payroll-reports', [ReportsPayrollControllerV1::class, 'custom_payroll_report'])
    ->middleware('throttle:100,1')
    ->name('external-api.payroll-reports');

Route::post('/users-list', [ReportsUsersController::class, 'users_list_report'])
    ->middleware('throttle:100,1')
    ->name('external-api.users-list');

// Future endpoints can be added here following the same pattern:
// Route::post('/sales-reports', [SalesReportsController::class, 'generateSalesReport'])
//     ->middleware('throttle:50,1')
//     ->name('external-api.sales-reports');

// Note: The verify_external_api_token middleware with scope requirements
// is applied at the route group level in the main api.php file
