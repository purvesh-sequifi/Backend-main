<?php

use App\Http\Controllers\API\V2\Hiring\OnboardingEmployeeController;
use App\Http\Controllers\API\V2\Hiring\PositionHirePermissionController;
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

Route::get('/onboarding_employee_listing', [OnboardingEmployeeController::class, 'onBoardingEmployeeListing']); // Main Page Listing
Route::get('/get_onboarding_employee/{id}', [OnboardingEmployeeController::class, 'getOnboardingEmployee']); // Every step
Route::post('/onboarding_employee_details', [OnboardingEmployeeController::class, 'onboardingEmployeeDetails']); // Details Step
Route::post('/onboarding_employee_originization', [OnboardingEmployeeController::class, 'employeeOrganization']); // Organization Step
Route::delete('/delete_onboarding_location/{id}', [OnboardingEmployeeController::class, 'deleteOnboardingLocation']); // Delete Additional Location
Route::post('/onboarding_employee_wages', [OnboardingEmployeeController::class, 'wages']); // Wages Step
Route::post('/onboarding_employee_readline', [OnboardingEmployeeController::class, 'employeeReadline']); // Redline Step
Route::post('/onboarding_employee_compensation', [OnboardingEmployeeController::class, 'employeeCompensation']); // Commission Step
Route::post('/onboarding_employee_upfronts', [OnboardingEmployeeController::class, 'employeeUpFronts']); // Upfront Step
Route::post('/onboarding_employee_override', [OnboardingEmployeeController::class, 'employeeOverride']); // Override Step
Route::post('/onboarding_employee_agreement', [OnboardingEmployeeController::class, 'employeeAgreement']); // Agreement Step
Route::post('/directHiredEmployee', [OnboardingEmployeeController::class, 'directHiredEmployee']); // Review & Finish Step
Route::post('/hiredEmployee', [OnboardingEmployeeController::class, 'hiredEmployee']); // Employee List Button
Route::post('/HiringEmployeeCompensation', [OnboardingEmployeeController::class, 'hiringEmployeeCompensation']); // Employment Package
Route::post('/onboarding_employee_withheld', [OnboardingEmployeeController::class, 'employeeWithheld']); // Settlement Step
Route::delete('/delete_onboarding_employee/{id}', [OnboardingEmployeeController::class, 'deleteOnboardingEmployee']); // Delete Option

Route::post('/re-hiredEmployee', [OnboardingEmployeeController::class, 'reHiredEmployee']); // Employee List Button

// New Contract/Rehire Enhancement Routes (Admin Only)
Route::post('/initiate-new-contract', [OnboardingEmployeeController::class, 'initiateNewContract']); // Start new contract wizard
Route::post('/complete-new-contract', [OnboardingEmployeeController::class, 'completeNewContract']); // Complete new contract
Route::post('/skip-documents-contract', [OnboardingEmployeeController::class, 'skipDocumentsContract']); // Skip documents option
Route::post('/activate-contract-after-documents/{onboardingEmployeeId}', [OnboardingEmployeeController::class, 'activateContractAfterDocuments']); // Activate contract after document completion
Route::post('/check-contract-override', [OnboardingEmployeeController::class, 'checkContractOverride']); // Check if contract date will override existing

// Hire Without Offer Letter Permissions Routes
Route::prefix('hire-permissions')->group(function () {
    Route::get('/unassigned-positions', [PositionHirePermissionController::class, 'getUnassignedPositionsWithPermissions']);
    Route::post('/update', [PositionHirePermissionController::class, 'updatePositionPermissions']);
    Route::get('/positions', [PositionHirePermissionController::class, 'getAllPositionsWithPermissions']);
});
