<?php

use App\Http\Controllers\API\V2\EmploymentCompensationHistoryController;
use App\Http\Controllers\API\V2\Useremploymentpackage\HiredUserController;
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

Route::get('/user-detail-by-id/{id}', [HiredUserController::class, 'userDetailById']);
Route::post('/user_organization', [HiredUserController::class, 'userOrganization']);
Route::post('/hired_date_update', [HiredUserController::class, 'hireDateUpdate']);
Route::post('/update_user_deduction', [HiredUserController::class, 'updateUserDeduction']);

Route::post('/user_wages_update', [HiredUserController::class, 'userWagesUpdate']);
Route::post('/user_override', [HiredUserController::class, 'userOverrides']);
Route::post('/user_agreement_update', [HiredUserController::class, 'userAgreemnetUpdate']);
Route::post('/user_compensation', [HiredUserController::class, 'userCompensation']);

Route::post('/combine_organization_history', [HiredUserController::class, 'combine_organization_history']);
Route::post('/combine_transfer_history', [HiredUserController::class, 'combine_transfer_history']);
Route::post('/combine_redline_commission_upfront_history', [HiredUserController::class, 'combine_redline_commission_upfront_history']);
Route::post('/combine_wages_history', [HiredUserController::class, 'combine_wages_history']);
Route::post('/combine_agreement_history', [HiredUserController::class, 'combine_agreement_history']);
Route::post('/combine_override_history', [HiredUserController::class, 'combine_override_history']);
Route::post('/combine_deduction_history', [HiredUserController::class, 'combine_deduction_history']);

Route::post('/employee_transfer', [HiredUserController::class, 'employee_transfer']);

// Employment Compensation Audit History Tracking API
Route::prefix('combine_employment_compensation_history_tracking')->group(function () {
    Route::post('/legacy', [EmploymentCompensationHistoryController::class, 'legacyFormat']); // Frontend compatible format
    Route::get('/{user_id}', [EmploymentCompensationHistoryController::class, 'index']);
    Route::get('/{user_id}/category/{category}', [EmploymentCompensationHistoryController::class, 'byCategory']);
    Route::get('/{user_id}/stats', [EmploymentCompensationHistoryController::class, 'stats']);
});
