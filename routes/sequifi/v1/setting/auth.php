<?php

use App\Http\Controllers\API\CRMController;
use App\Http\Controllers\API\SClearance\SClearanceController;
use App\Http\Controllers\API\SClearance\TurnAiController;
use App\Http\Controllers\API\Setting\SetupPayrollController;
use Illuminate\Support\Facades\Route;

//  CRM Setting
Route::get('/crm_setting_list', [CRMController::class, 'crmSettingList']);
Route::get('/get_crm_setting_by_id/{id}', [CRMController::class, 'getCrmSettingById']);
Route::post('/crm_setting', [CRMController::class, 'crmSetting']);
Route::post('/crm_setting_update', [CRMController::class, 'crmSettingUpdates']);
Route::post('/crm_setting_disconnect', [CRMController::class, 'crmSettingActiveInactive']);
Route::post('/sequai_crm_setting_update', [CRMController::class, 'sequiAiCrmSettingUpdates']);

// S Clearance Transunioun
Route::post('/s_clearance/update_employer', [SClearanceController::class, 'update_employer']);
Route::get('/s_clearance/get_employer/{employer_id}', [SClearanceController::class, 'get_employer']);
Route::post('/s_clearance/add_configurations', [SClearanceController::class, 'configure_setting']);
Route::get('/s_clearance/get_configurations', [SClearanceController::class, 'get_configurations']);
Route::get('/s_clearance/get_plans', [SClearanceController::class, 'getPlanLists']);
Route::get('/s_clearance/get_statuses', [SClearanceController::class, 'getStatusLists']);
Route::post('/s_clearance/new_clearance_external', [SClearanceController::class, 'new_clearance_external']);
Route::post('/s_clearance/new_clearance_internal', [SClearanceController::class, 'new_clearance_internal']);
Route::post('/s_clearance/office_and_position_wise_user_list', [SClearanceController::class, 'SClearance_office_and_position_wise_user_list']);
Route::post('/s_clearance/cancel_screening_requests', [SClearanceController::class, 'cancel_screening_request']);
Route::post('/s_clearance/get_all_screening_requests_list', [SClearanceController::class, 'get_all_screening_requests_list']);
Route::post('/s_clearance/approve_decline_bv_report', [SClearanceController::class, 'approve_decline_bv_report']);
Route::post('/s_clearance/view_report_details', [SClearanceController::class, 'view_report_details']);
Route::post('/s_clearance/get_configurations_position_based', [SClearanceController::class, 'get_configurations_position_based']);

// S Clearance Turn
Route::get('/s_clearance_turn/get_child_partner_agreement', [TurnAiController::class, 'get_child_partner_agreement']);
Route::get('/s_clearance_turn/get_package_configurations', [TurnAiController::class, 'get_package_configurations']);
Route::get('/s_clearance_turn/get_configurations', [TurnAiController::class, 'get_configurations']);
Route::post('/s_clearance_turn/get_configurations_position_based', [TurnAiController::class, 'get_configurations_position_based']);
Route::post('/s_clearance_turn/add_configurations', [TurnAiController::class, 'configure_setting']);
Route::post('/s_clearance_turn/new_clearance_internal', [TurnAiController::class, 'new_clearance_internal']);
Route::post('/s_clearance_turn/new_clearance_onboarding', [TurnAiController::class, 'new_clearance_onboarding']);
Route::post('/s_clearance_turn/new_clearance_external', [TurnAiController::class, 'new_clearance_external']);
Route::post('/s_clearance_turn/office_and_position_wise_user_list', [TurnAiController::class, 'SClearance_office_and_position_wise_user_list']);
Route::post('/s_clearance_turn/approve_decline_bv_report', [TurnAiController::class, 'approve_decline_bv_report']);
Route::post('/s_clearance_turn/resend_sclearance_request', [TurnAiController::class, 'resend_sclearance_request']);
Route::post('/s_clearance_turn/get_all_screening_requests_list', [TurnAiController::class, 'get_all_screening_requests_list']);
Route::post('/s_clearance_turn/withdraw_screening_request', [TurnAiController::class, 'withdraw_screening_request']);
Route::post('/s_clearance_turn/view_report_details', [TurnAiController::class, 'view_report_details']);
Route::get('/s_clearance_turn/get_statuses', [TurnAiController::class, 'getTurnStatusLists']);
Route::get('/s_clearance_turn/get_packages', [TurnAiController::class, 'getPackageLists']);
Route::post('/s_clearance_turn/add_child_partner', [TurnAiController::class, 'add_child_partner']); // for testing
Route::get('/s_clearance_turn/get_token', [TurnAiController::class, 'get_token']); // for testing
Route::post('/s_clearance_turn/get_screening_report', [TurnAiController::class, 'get_screening_report']);
Route::get('/s_clearance_turn/screening_results/{turn_id}', [TurnAiController::class, 'screening_results']);
Route::post('/s_clearance_turn/send_review_background_email', [TurnAiController::class, 'send_review_background_email']);

Route::post('/add_payroll_setting', [SetupPayrollController::class, 'addPayrollSetting']);
Route::post('/update_payroll_setting', [SetupPayrollController::class, 'updatePayrollSetting']);
Route::get('/get_payroll_setting', [SetupPayrollController::class, 'getPayrollSetting']);
Route::delete('/delete_payroll_setting/{id}', [SetupPayrollController::class, 'deletePayrollSetting']);
Route::post('/s_clearance/resend_sclearance_request', [SClearanceController::class, 'resend_sclearance_request']);
Route::post('/s_clearance/add_new_sclearance', [SClearanceController::class, 'add_new_sclearance']);
