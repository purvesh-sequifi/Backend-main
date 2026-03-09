<?php

use App\Http\Controllers\API\V2\management\EmployeeManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/my_overrides/{id}', [EmployeeManagementController::class, 'my_overrides']);
Route::post('/manual_overrides_from', [EmployeeManagementController::class, 'manual_overrides_from']);

Route::get('/mysale_overrides/{id}', [EmployeeManagementController::class, 'mysale_overrides']);
Route::post('/manual_overrides', [EmployeeManagementController::class, 'manual_overrides']);

Route::post('/edit_manual_overrides', [EmployeeManagementController::class, 'edit_manual_overrides']);
Route::get('/get_mysale_overrides/{id}', [EmployeeManagementController::class, 'get_mysale_overrides']);
Route::post('/get_mysale_overrides', [EmployeeManagementController::class, 'get_mysale_overrides']);
Route::put('/my_overrides_enable_disable', [EmployeeManagementController::class, 'OverridesEnableDisable']);
