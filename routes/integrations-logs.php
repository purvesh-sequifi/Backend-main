<?php

use App\Http\Controllers\Logs\IntegrationsApiLogsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integration API Logs Routes
|--------------------------------------------------------------------------
|
| These routes are for the integration API logs monitoring interface
| They allow viewing logs for various API integrations like Pocomos
|
*/

// Define routes without authentication for easier development access
// Integration API Logs routes
Route::get('/admin/logs/integrations', [IntegrationsApiLogsController::class, 'index'])
    ->name('admin.logs.integrations');

Route::get('/admin/logs/integrations/{id}', [IntegrationsApiLogsController::class, 'show'])
    ->name('admin.logs.integrations.show');
