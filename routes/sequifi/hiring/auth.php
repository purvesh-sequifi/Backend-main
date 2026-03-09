<?php

use App\Http\Controllers\API\HiredUserController;
use App\Http\Controllers\API\Hiring\EmployeeProfileController;
use App\Http\Controllers\API\Hiring\Filter\OnboardingFilterController;
use App\Http\Controllers\API\Hiring\HiringProgressController;
use App\Http\Controllers\API\Hiring\LeadsController;
use App\Http\Controllers\API\Hiring\OnboardingEmployeeController;
use App\Http\Controllers\API\Hiring\PipelineCommentController;
use App\Http\Controllers\API\Hiring\PipelineSubTaskController;
use App\Http\Controllers\API\Hiring\ReferralsController;
// use App\Http\Controllers\API\Hiring\TemplateController; // Commented out - controller does not exist
use App\Http\Controllers\API\Hiring\TerminateEmployeeController;
use App\Http\Controllers\hiredEmployee_from_call_back;
use App\Http\Controllers\UsersAdditionalEmailController;
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

// onboarding
Route::resource('/onboarding_employee', OnboardingEmployeeController::class);
Route::get('/onboarding_employee_listing', [OnboardingEmployeeController::class, 'onboarding_employee_listing'])->name('onboarding_employee_listing');
Route::get('/onboarding_employee_details_by_id/{onboarding_employee_id}', [OnboardingEmployeeController::class, 'onboarding_employee_details_by_id'])->name('onboarding_employee_details_by_id');

// do not delete this route they are for aveyo data update start

// Route::get('/update_empid', [OnboardingEmployeeController::class, 'update_empid']);
// Route::get('/update_aveyoid_ondb', [OnboardingEmployeeController::class, 'update_aveyoid_ondb']);
// Route::get('/update_sequifiid_hubspot', [OnboardingEmployeeController::class, 'update_sequifiid_hubspot']);
// Route::get('/create_sequifiid_hubspot', [OnboardingEmployeeController::class, 'create_sequifiid_hubspot']);
Route::get('/status_update_hubspot', [OnboardingEmployeeController::class, 'status_update_hubspot']);

// do not delete this route they are for aveyo data update code end

Route::post('/onboarding_employee_details', [OnboardingEmployeeController::class, 'addUpdateOnboardingEmployee'])->name('onboarding_employee_details')->middleware('throttle.custom:5,1');
Route::put('/onboarding_employee_details', [OnboardingEmployeeController::class, 'addUpdateOnboardingEmployee']); // ->name('onboarding_employee_details');
Route::post('/onboarding_employee_originization', [OnboardingEmployeeController::class, 'EmployeeOriginization']);
Route::post('/onboarding_employee_compensation', [OnboardingEmployeeController::class, 'EmployeeCompencation']);
Route::post('/onboarding_employee_override', [OnboardingEmployeeController::class, 'EmployeeOverride']);
Route::post('/onboarding_employee_agreement', [OnboardingEmployeeController::class, 'EmployeeAgreement']);
Route::post('/onboarding_employee_wages', [OnboardingEmployeeController::class, 'wages']);
Route::post('/hiredEmployee', [OnboardingEmployeeController::class, 'hiredEmployee'])->middleware('throttle.custom:5,1');
Route::get('/delete_incomplete_hire_form/{id}', [OnboardingEmployeeController::class, 'deleteIncompleteHiredForm']);

Route::post('/directHiredEmployee', [OnboardingEmployeeController::class, 'directHiredEmployee'])->middleware('throttle.custom:5,1');

Route::post('/update_onboarding_employee', [OnboardingEmployeeController::class, 'UpdateOnboardingEmployee']);
Route::get('/get_onboarding_employee/{id}', [OnboardingEmployeeController::class, 'getOnboardingEmployee']);
// recruiter filter
Route::post('/recruiter_filter', [OnboardingFilterController::class, 'recruiterFilter']);

Route::post('/onboarding_configuration_employee', [OnboardingEmployeeController::class, 'OnboardingConfigurationSetting']);
Route::post('/onboarding_configuration_employee_add', [OnboardingEmployeeController::class, 'onboardingConfigurationSettingAdd']);
Route::post('/employee_additional_personal_details', [OnboardingEmployeeController::class, 'employee_additional_personal_details']);
Route::post('/onboarding_configuration_employee_delete', [OnboardingEmployeeController::class, 'onboardingConfigurationSettingDelete']);

Route::post('/get_onboarding_configuration_employee', [OnboardingEmployeeController::class, 'getOnboardingConfigurationSetting']);

Route::delete('/delete_employee_personal_detail/{id}', [OnboardingEmployeeController::class, 'deleteemployeepersonaldetail']);
Route::delete('/delete_additional_info/{id}', [OnboardingEmployeeController::class, 'deleteAdditionalInfo']);
Route::delete('/delete_document_upload/{id}', [OnboardingEmployeeController::class, 'deleteDocumentUpload']);
Route::post('/send_request_hiring', [OnboardingEmployeeController::class, 'sendRequestHiring']);
Route::post('/offer_review_to_onboarding_employee', [OnboardingEmployeeController::class, 'offer_review_to_onboarding_employee']);
Route::post('/offer_review_approved_or_reject', [OnboardingEmployeeController::class, 'offer_review_approved_or_reject']);
Route::post('/get_onboarding_configuration_employee_data', [OnboardingEmployeeController::class, 'getOnboardingConfigurationSettingData']);
Route::post('/special_review_offer_reject', [OnboardingEmployeeController::class, 'special_review_offer_reject']);

// Route::get('/send_email_to_onboarding_employee/{id}', [OnboardingEmployeeController::class, 'sendEmailOnBoardingEmployee']);

Route::post('/onBoardingChangeStatus', [OnboardingEmployeeController::class, 'onBoardingChangeStatus']);
Route::delete('/delete_onboarding_employee/{id}', [OnboardingEmployeeController::class, 'deleteOnboardingEmployee']);

// leads
Route::resource('/leads_list', LeadsController::class)->middleware('throttle.custom:10,1');
Route::post('/interview_reschedule/{id}', [LeadsController::class, 'interviewSchedule'])->name('interview_reschedule')->middleware('throttle.custom:5,1');
Route::post('/assign', [LeadsController::class, 'assign']);
Route::post('/changeStatus', [LeadsController::class, 'changeStatus']);
Route::get('/schedule_time_slot', [LeadsController::class, 'scheduleTimeSlot'])->name('scheduleTimeSlot');
Route::post('/user_schedule_time', [LeadsController::class, 'userScheduleTime'])->name('userScheduleTime');
Route::get('/get_user_schedule_time/{id}', [LeadsController::class, 'getUserScheduleTime'])->name('getUserScheduleTime');
Route::post('/schedule_interview', [LeadsController::class, 'scheduleInterview'])->name('scheduleInterview');
Route::post('/schedule_time', [LeadsController::class, 'scheduleTime'])->name('scheduleTime');
Route::post('/add_leads_comments', [LeadsController::class, 'leadsComments'])->name('leadsComments');
Route::post('/add_lead_comment_reaply', [LeadsController::class, 'leadsCommentsReaply'])->name('leadsCommentsReaply');
Route::get('/get_leads_comments/{id}', [LeadsController::class, 'getLeadsComments'])->name('getLeadsComments');
Route::get('/get_leadby_id/{id}', [LeadsController::class, 'getLeadById'])->name('getLeadById');
Route::get('/reporting_manager', [LeadsController::class, 'reportingManager']);

Route::get('/alertScheduleInterview/{id}', [LeadsController::class, 'alertScheduleInterview']);

Route::get('/alertScheduleInterviewStatusUpdate/{id}', [LeadsController::class, 'alertScheduleInterviewStatusUpdate']);

Route::post('/check_status_email_and_mobile', [LeadsController::class, 'checkStatusEmailAndMobile'])->name('checkStatusEmailAndMobile');
// Lead level rating...
Route::post('/update-lead-rating', [LeadsController::class, 'updateLeadRating'])->name('updateLeadRating');
// Lead sub task
Route::get('/pipeline/subtasks', [PipelineSubTaskController::class, 'index']);
Route::get('/pipeline/subtasks/{id}', [PipelineSubTaskController::class, 'show']);
Route::post('/pipeline/subtasks/save', [PipelineSubTaskController::class, 'store']);
Route::post('/pipeline/subtasks/delete', [PipelineSubTaskController::class, 'delete']);
Route::get('/subtasks/{pipeline_lead_status_id}', [PipelineSubTaskController::class, 'getSubTasksOfPipeline']);
Route::get('/lead-details/{id}', [LeadsController::class, 'leadDetails']);
Route::post('leaddocument', [LeadsController::class, 'saveLeadDocument']);
Route::post('/user_preference_update', [LeadsController::class, 'user_preference_update']);
Route::get('/getuserpreference', [LeadsController::class, 'getuserpreference']);

Route::post('/pipeline/comment/save', [PipelineCommentController::class, 'pipelineCommentSave']);
Route::post('pipeline/comment/delete', [PipelineCommentController::class, 'pipelineCommentDelete']);
Route::post('/pipeline/delete', [PipelineCommentController::class, 'deletePipeline']);
Route::post('/update-task-status', [LeadsController::class, 'updateTaskStatus']);
Route::get('sub-task-status', [LeadsController::class, 'subTaskStatus']);
// Hiring progress

Route::resource('/hiring_progress', HiringProgressController::class);
Route::post('/hiring_progress', [HiringProgressController::class, 'index'])->name('hiring_reports');
Route::post('/hiring_progress_filter', [HiringProgressController::class, 'filter'])->name('hiring_filter');
Route::get('/recent_hired', [HiringProgressController::class, 'recentHired'])->name('recent_hired');
Route::post('/hiring_graph', [HiringProgressController::class, 'graphForLead'])->name('hiring_graph');

// Route::post('/office-filter-graph', [HiringProgressController::class, 'officefilter']);

// employee profile
Route::resource('/employee-profile', EmployeeProfileController::class);
Route::get('/employee-personal-info', [EmployeeProfileController::class, 'EmployeePersonalinfo']);
Route::get('/employee-packege', [EmployeeProfileController::class, 'EmployeePackege']);
Route::get('/employee-tax-info', [EmployeeProfileController::class, 'EmployeeTaxInfo']);
Route::get('/employee-banking', [EmployeeProfileController::class, 'Employeebanking']);
Route::post('/update-commission', [EmployeeProfileController::class, 'updateCommission']);
Route::post('/update-user-profile', [EmployeeProfileController::class, 'updateUserProfile']);
Route::post('/update-user-account-status', [EmployeeProfileController::class, 'updateUserAccountStatus']);
Route::post('/welcome-mail', [EmployeeProfileController::class, 'welcomeMail']);
// user profile
Route::get('/user-profile/{id}', [EmployeeProfileController::class, 'userProfile']);
Route::get('/user-personal-info/{id}', [EmployeeProfileController::class, 'userPersonalinfo']);
Route::get('/user-packege/{id}', [EmployeeProfileController::class, 'userPackege']);
Route::get('/user-tax-info/{id}', [EmployeeProfileController::class, 'userTaxInfo']);
Route::get('/user-banking/{id}', [EmployeeProfileController::class, 'userBanking']);
// Route::post('/update-user-profile', [EmployeeProfileController::class, 'updateUserProfile']);
Route::get('/userRedlineHistory/{id}', [EmployeeProfileController::class, 'userRedlineHistory']);

// single person Activity log
Route::get('/get-audit-logs', [EmployeeProfileController::class, 'getAuditLog']);
/******** SequiDocs Routing ***************/
// Categories Routing

// Template and categories
// Route::get('/template-categories', [DropdownController::class, 'TemplateCategories'])->name('template-categories');
// Route::post('/category-dropdown-template', [TemplateController::class, 'categorydropdown']); // Controller does not exist
// Route::post('/add-template-assign', [TemplateController::class, 'assign']); // Controller does not exist
Route::post('/update-user-position', [EmployeeProfileController::class, 'updateUserPosition']);
Route::resource('/referrals_list', ReferralsController::class);

Route::post('/user_details', [HiredUserController::class, 'addUser']);
// Route::put('/user_details',[ HiredUserController::class,'addUpdateOnboardingEmployee']);
Route::post('/user_organization', [HiredUserController::class, 'UserOrganization']);
Route::post('/user_compensation', [HiredUserController::class, 'UserCompensation']);
Route::post('/user_override', [HiredUserController::class, 'UserOverrides']);
Route::post('/user_override_list', [HiredUserController::class, 'listUserOverrides']);
Route::post('/user_agreement', [HiredUserController::class, 'UserAgreement']);
Route::get('/user-detail-by-id/{id}', [HiredUserController::class, 'userDetailById']);
Route::post('/updateManagerByNewManerID', [HiredUserController::class, 'updateManagerByNewManerID']);
Route::post('/changeUserOfficeAndManager', [HiredUserController::class, 'changeUserOfficeAndManager']);
Route::post('/allocateManagerToUser', [HiredUserController::class, 'allocateManagerToUser']);
// Route::post('/user_compensations',[ HiredUserController::class,'UserCompensations']);
Route::post('/redline_subroutines', [HiredUserController::class, 'redlineSubroutines']);
Route::post('/user_compensation_self_gen', [HiredUserController::class, 'UserCompensationSelfGen']);
Route::post('/update_user_deduction', [HiredUserController::class, 'updateUserDeduction']);
Route::post('/addupdate_user_upcoming_overrides', [HiredUserController::class, 'addupdateUserUpcomingOverrides']);
Route::post('/list_user_upcoming_overrides', [HiredUserController::class, 'listUserUpcomingOverrides']);

Route::post('/delete_employment_package_history', [HiredUserController::class, 'deleteEmploymentPackageHistory']);
Route::post('/get_employment_package_history', [HiredUserController::class, 'get_employment_package_history']);
Route::post('/employee_transfer', [HiredUserController::class, 'employee_transfer']);

Route::post('/combine_redline_commission_upfront_history', [HiredUserController::class, 'combine_redline_commission_upfront_history']);
Route::post('/combine_override_history', [HiredUserController::class, 'combine_override_history']);
Route::post('/combine_transfer_history', [HiredUserController::class, 'combine_transfer_history']);
Route::post('/combine_organization_history', [HiredUserController::class, 'combine_organization_history']);
Route::post('/combine_commission_upfront_history_log', [HiredUserController::class, 'combine_commission_upfront_history_log']);
Route::post('/combine_deduction_history', [HiredUserController::class, 'combine_deduction_history']);

Route::post('/add_admin', [HiredUserController::class, 'addAdmin']);
Route::post('/hired_date_update', [HiredUserController::class, 'hireDateUpdate']);

// user wages routes
Route::post('/user_wages_update', [HiredUserController::class, 'userWagesUpdate']);
Route::post('/combine_wages_history', [HiredUserController::class, 'combine_wages_history']);

// Users additional email routes
Route::get('/users_additional_emails_list/{user_id}', [UsersAdditionalEmailController::class, 'users_additional_emails_list']);
Route::post('/add_users_additional_emails', [UsersAdditionalEmailController::class, 'add_users_additional_emails']);
Route::delete('/delete_users_additional_email/{id}', [UsersAdditionalEmailController::class, 'delete_users_additional_email']);

// new api as per risi sir requirment -
Route::post('/accept_decline_agreement ', [HiredUserController::class, 'accept_decline_agreement']);

Route::post('/leads_import', [LeadsController::class, 'leads_import'])->name('leads_import');
Route::get('/download_sample', [LeadsController::class, 'download_sample'])->name('download_leads_sample');
Route::get('/export_leads', [LeadsController::class, 'exportLeads'])->name('exportLeads');
Route::get('/export_onboarding', [OnboardingEmployeeController::class, 'exportOnboarding'])->name('exportOnboarding');

Route::post('/userProfileActivityLog', [EmployeeProfileController::class, 'userProfileActivityLog']);

Route::post('get-employee-data-by-date-type', [EmployeeProfileController::class, 'getEmployeeDataByDateType']);

Route::post('update-offer-expiry-date/{date?}', [hiredEmployee_from_call_back::class, 'updateOfferExpiryDate']);

Route::get('/getWorkerFilesListFromEveree', [OnboardingEmployeeController::class, 'getWorkerFilesListFromEveree']);
Route::post('delete-rejected-leads', [LeadsController::class, 'deleteRejectedLeads'])->name('deleteRejectedLeads');
Route::post('delete-expired-onboarding', [OnboardingEmployeeController::class, 'deleteExpiredOnboarding'])->name('deleteExpiredOnboarding');
Route::post('delete-rejected-onboarding', [OnboardingEmployeeController::class, 'deleteRejectedOnboarding'])->name('deleteRejectedOnboarding');

Route::post('/terminate-employee', [TerminateEmployeeController::class, 'terminateEmployee']);
Route::post('/dismiss-employee', [TerminateEmployeeController::class, 'dismissEmployee']);

Route::get('/everee-worker-tax-files/{userId}', [OnboardingEmployeeController::class, 'getWorkerTaxListFromEveree']);

Route::post('/update-user-arena-theme', [EmployeeProfileController::class, 'updateUserArenaTheme']);
// This is for employee admin only fields
Route::post('/employee_admin_only_fields', [OnboardingEmployeeController::class, 'employeeAdminOnlyFields']);
Route::get('/get_user_employee_admin_only_fields/{userId}', [OnboardingEmployeeController::class, 'getUserEmployeeAdminOnlyFields']);
Route::get('/get_all_employee_admin_only_fields', [OnboardingEmployeeController::class, 'getAllEmployeeAdminOnlyFields']);

//  this is for onboarding employees and user  admin only fields
Route::post('/update_employee_admin_only_fields', [OnboardingEmployeeController::class, 'updateEmployeeAdminOnlyFields']);
