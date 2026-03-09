<?php

use App\Http\Controllers\API\Hiring\OnboardingEmployeeBVController;
use App\Http\Controllers\API\SClearance\TurnAiController;
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

Route::post('/get_onboarding_employee_bv_status', [TurnAiController::class, 'get_onboarding_employee_bv_status']);

Route::name('v1.')->group(function () {
    Route::resource('/onboarding_employee', OnboardingEmployeeBVController::class);
});

Route::post('/onboarding_employee_agreement', [OnboardingEmployeeBVController::class, 'EmployeeAgreement']);
Route::get('/send_email_to_onboarding_employee/{id}', [OnboardingEmployeeBVController::class, 'sendEmailOnBoardingEmployee']);
