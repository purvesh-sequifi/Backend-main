<?php

use App\Http\Controllers\API\V2\EmploymentPackage\EmploymentPackageController;
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

Route::get('/user-detail-by-id/{id}', [EmploymentPackageController::class, 'userDetailById']);
Route::post('/user_organization', [EmploymentPackageController::class, 'userOrganization']);
Route::post('/hired_date_update', [EmploymentPackageController::class, 'hireDateUpdate']);
Route::post('/update_user_deduction', [EmploymentPackageController::class, 'updateUserDeduction']);

Route::post('/user_wages_update', [EmploymentPackageController::class, 'userWagesUpdate']);
Route::post('/user_override', [EmploymentPackageController::class, 'userOverrides']);
Route::post('/user_agreement_update', [EmploymentPackageController::class, 'userAgreemnetUpdate']);
Route::post('/user_compensation', [EmploymentPackageController::class, 'userCompensation']);

Route::post('/combine_organization_history', [EmploymentPackageController::class, 'combine_organization_history']);
Route::post('/combine_transfer_history', [EmploymentPackageController::class, 'combine_transfer_history']);
Route::post('/combine_redline_commission_upfront_history', [EmploymentPackageController::class, 'combine_redline_commission_upfront_history']);
Route::post('/combine_wages_history', [EmploymentPackageController::class, 'combine_wages_history']);
Route::post('/combine_agreement_history', [EmploymentPackageController::class, 'combine_agreement_history']);
Route::post('/combine_override_history', [EmploymentPackageController::class, 'combine_override_history']);
Route::post('/combine_deduction_history', [EmploymentPackageController::class, 'combine_deduction_history']);

Route::post('/employee_transfer', [EmploymentPackageController::class, 'employee_transfer']);

Route::get('/user-personal-details/{id}', [EmploymentPackageController::class, 'userPersonalDetails']);
Route::get('/user-organization-details/{id}', [EmploymentPackageController::class, 'userOrganizationDetails']);
Route::get('/user-compensation-details/{id}/{productId}', [EmploymentPackageController::class, 'userCompensationDetails']);

Route::post('/employment-package-details', [EmploymentPackageController::class, 'employmentPackageDetails']);
Route::post('/sales-count', [EmploymentPackageController::class, 'salesCount']);
Route::post('/last-effective-date', [EmploymentPackageController::class, 'lastEffectiveDate']);
Route::post('/check-conflicts', [EmploymentPackageController::class, 'checkConflicts']);
Route::post('/update-employment-package', [EmploymentPackageController::class, 'updateEmploymentPackage']);

// Hawxw2 routes
Route::post('/get-user-hire-date', [EmploymentPackageController::class, 'userw2hireStartDate']);
Route::post('/user-transfer-location', [EmploymentPackageController::class, 'userw2TransferLocation']);

// re-hire apis
Route::post('/re-hire-employment-package-details', [EmploymentPackageController::class, 'reHireEmploymentPackageDetails']);
