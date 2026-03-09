<?php

use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AwsLambdaApiController;
use App\Http\Controllers\API\ChatGPT\ChatGPTController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\DataImportExportController;
use App\Http\Controllers\API\Dropdown\DropdownController;
// use App\Http\Controllers\SentryTestController;
use App\Http\Controllers\API\EmailConfigurationController;
use App\Http\Controllers\API\Everee\EvereeController;
use App\Http\Controllers\API\ExternalHiring\ExternalEmployeeHiringController;
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

// API route for register new user
// Route::post('/register', [AuthController::class, 'register']);
// API route for login user

Route::post('/encrypt_decrypt_key', [EvereeController::class, 'encrypt_decrypt_key']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle.custom:5,1')->name('login');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::post('/send_email_to_review_personal_information_taxes', [AuthController::class, 'review_personal_information_taxes']);

Route::post('/reset-password', [AuthController::class, 'resetPass']);

Route::get('/reset-password/{id}', [AuthController::class, 'resetPassword']);

Route::post('/update-Password', [AuthController::class, 'updatePassword']);

Route::get('/accepted_declined_requested_change_hiring_process/{id}/{status}', [AuthController::class, 'updateHiringProcessStatusByUser']);

Route::get('/requested_change_hiring_process/{id}/{status}', [AuthController::class, 'updateHiringProcessChangeRequestByUser']);

Route::post('/user_comment', [AuthController::class, 'userComment']);

Route::get('/export_sales_data', [AuthController::class, 'exportSalesData'])->name('exportclawback');

Route::get('/export_clawback', [AuthController::class, 'exportClawbackData'])->name('exportsalesdata');

Route::get('/export_user', [AuthController::class, 'exportUsersData'])->name('exportUserData');

Route::get('/export_pending_install', [AuthController::class, 'exportPendingData'])->name('exportpendinginstall');

Route::post('/add_lead_without_auth', [LeadsController::class, 'addLeadWithoutAuth']);
Route::post('/check_status_email_and_mobile_without_auth', [LeadsController::class, 'checkStatusEmailAndMobileWithoutAuth']);
Route::get('/city_by_state', [DropdownController::class, 'stateCity']);

// stripe billing
Route::post('/get_billing', [StripeBillingController::class, 'index']);
Route::get('/get_invoice_data/{bill_id}', [StripeBillingController::class, 'get_invoice_data']);
// Route::get('/get_subscriptions',[StripeBillingController::class,'getSubscriptions']);
Route::post('/get_subscriptions', [StripeBillingController::class, 'get_monthly_subscriptions']);
Route::get('/create_billing_history', [StripeBillingController::class, 'create_billing_history']);
Route::post('/manage_subscription', [StripeBillingController::class, 'manageSubscription']);
Route::post('/add_subscription', [StripeBillingController::class, 'addSubscription']);
Route::post('/get_pidsdata', [StripeBillingController::class, 'getPidsdata']);
Route::post('/get_users_data', [StripeBillingController::class, 'getUsersData']);

Route::post('/get_m2data', [StripeBillingController::class, 'getm2data']);
Route::post('/getadjustedkwdata', [StripeBillingController::class, 'getadjustedkwdata']);
// everee billing detail route
Route::post('/stripe_webhook', [StripeBillingController::class, 'stripeWebhookUrl']);
/* commom webhook url rouute */
Route::post('/stripe_webhook_url', [StripeBillingController::class, 'redirectWebhookUrl']);
Route::post('/stripe_redirect_webhook_url', [StripeBillingController::class, 'stripeWebhookUrlMultipleServer']);

Route::post('/get_billing_payroll_data', [StripeBillingController::class, 'getPayrolldata']);
Route::post('/get_billing_one_time_payment_data', [StripeBillingController::class, 'getOneTimePaymentData']);

Route::post('/get_invoice_payroll_data', [StripeBillingController::class, 'get_Payroll_Invoice_data']);
Route::post('/get_invoice_one_time_payment_data', [StripeBillingController::class, 'get_OneTimePayment_Invoice_data']);

Route::post('/get_Sales_Invoice_pids', [StripeBillingController::class, 'get_Sales_Invoice_pids']);
Route::post('/get_Sales_Invoice_m2_data', [StripeBillingController::class, 'get_Sales_Invoice_m2_data']);
Route::post('/get_Sales_Invoice_adjusted_kw_data', [StripeBillingController::class, 'get_Sales_Invoice_adjusted_kw_data']);
Route::post('/add_billinghistory', [StripeBillingController::class, 'addBilingHistory']);
Route::post('/hubspotImportData', [AuthController::class, 'hubspotImportData']);
Route::post('/addLead', [AuthController::class, 'addLead']);
Route::post('/updateLead', [AuthController::class, 'updateLead']);
Route::post('/hubspotSyncData', [AuthController::class, 'hubspotSyncData'])->middleware('throttle.custom:5,1');

Route::post('/update_everee_worker_id_null', [EvereeController::class, 'updateEvereeWorkerIdNull']);
Route::post('/everee_webhook', [EvereeController::class, 'handle']);
Route::post('/everee_webhook_w2', [EvereeController::class, 'handle']);
Route::get('/download_user_separate_sheet', [DataImportExportController::class, 'download_user_separate_sheet']);
Route::post('/arcsiteWebhook', [arcsiteController::class, 'handle']);
Route::post('/syncDataFromLog', [arcsiteController::class, 'syncDataFromLog']);
Route::post('/arcsiteLasVegasWebhook', [arcsiteController::class, 'arcsiteLasVegas']);
Route::post('/arcsiteStGeorgeWebhook', [arcsiteController::class, 'arcsiteStGeorge']);
Route::post('/arcsiteDenverWebhook', [arcsiteController::class, 'arcsiteDenver']);

// Quickbooks route
Route::get('/quickbooks_callback', [QuickBooksController::class, 'handle']);
Route::get('/quickbooks_refresh_token', [QuickBooksController::class, 'refreshToken']);
Route::get('/get_quickbooks_journal_entry_by_id/{id}', [QuickBooksController::class, 'getJournalEntryById'])->where('id', '[0-9]+');
Route::post('/quickbooks_journal_report', [QuickBooksController::class, 'getJournalReport']);
Route::post('/quickbooks_connect', [QuickBooksController::class, 'connect']);

// Unprotected hiring routes
Route::get('/merge_all_employeeFields', [OnboardingEmployeeController::class, 'mergeAllEmployeeFieldsReversed']);
Route::post('/create_journal_entry', [QuickBooksController::class, 'createJournalEntry']);
Route::post('/create_quickbook_account', [QuickBooksController::class, 'createQuickbookAccounts']);

// webhook for hubspotCurrentEnergy
Route::post('/hubspot_current_energy_webhook', [HubSpotCurrentEnergyController::class, 'hubspotCurrentEnergyWebhook']);

// hubspot start

// Route::post('/get_contact_of_hubspot', [HubSpotController::class, 'get_contact_of_hubspot']);

// hubspot end 

Route::prefix('supervisor')->middleware('auth:sanctum')->group(function () {
    Route::post('/test', [supervisorTestController::class, 'testSupervisor']);
});
// Route::group(['middleware' => ['changeTimeZone']], function () {

// Route::post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    // Route::post('/user-import', [UserImportController::class, 'fileImport']);
    Route::get('/get_userdata', [AuthController::class, 'get_userdata']);
    Route::post('/change_password', [AuthController::class, 'change_password']);
    Route::post('/update_device_token', [NotificationController::class, 'update_device_token']);
    Route::post('/add_payment', [StripeBillingController::class, 'addpaymentdata']);
    Route::post('/autopayinvoice', [StripeBillingController::class, 'autopayinvoice']);
    Route::get('/listpaymentmethods', [StripeBillingController::class, 'listpaymentmethods']);
    Route::post('/updatepaymentmethod', [StripeBillingController::class, 'updatepaymentmethod']);
    Route::post('/updateautopayment', [StripeBillingController::class, 'updateautopayment']);
    Route::post('/deletepaymentmethod', [StripeBillingController::class, 'deletepaymentmethod']);
    Route::get('/setup_intents', [StripeBillingController::class, 'setup_intents']);
    Route::post('/stripe_callback_url', [StripeBillingController::class, 'stripeCallbackUrl']);
    Route::post('/updateinvoice', [StripeBillingController::class, 'updateinvoice']);
    Route::post('/invoice/pay', [StripeBillingController::class, 'stripePayInvoice']);
    Route::post('/runBillingCommand', [StripeBillingController::class, 'runBillingCommand']);
    Route::post('/checkDuplicateAndAttachToCustomer/{paymentMethodId}', [StripeBillingController::class, 'checkDuplicateAndAttachToCustomer']);
});
// Protecting Routes

// MongoDB routes (Optimized & Fast) - Secured with Authentication
Route::prefix('arena')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/test', [App\Http\Controllers\MongoDBController::class, 'testConnection']);
    Route::get('/collections', [App\Http\Controllers\MongoDBController::class, 'listCollections']);
    Route::get('/collection/{collection}', [App\Http\Controllers\MongoDBController::class, 'getDocuments']);
    Route::post('/collection/{collection}/find-one', [App\Http\Controllers\MongoDBController::class, 'findOne']);
    Route::get('/collection/{collection}/count', [App\Http\Controllers\MongoDBController::class, 'countDocuments']);
    Route::post('/collection/{collection}/insert', [App\Http\Controllers\MongoDBController::class, 'insertDocument']);
    Route::post('/collection/{collection}/insert-many', [App\Http\Controllers\MongoDBController::class, 'insertMany']);
    Route::put('/collection/{collection}/update', [App\Http\Controllers\MongoDBController::class, 'updateDocuments']);
    Route::post('/collection/{collection}/aggregate', [App\Http\Controllers\MongoDBController::class, 'aggregate']);
    Route::get('/stats', [App\Http\Controllers\MongoDBController::class, 'getStats']);
    Route::delete('/cache', [App\Http\Controllers\MongoDBController::class, 'clearCache']);
});

Route::prefix('setting')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/setting/auth.php';
});

Route::prefix('hiring')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/hiring/auth.php';
});

Route::prefix('office')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/office/auth.php';
});

Route::prefix('excel')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/excel/auth.php';
});

Route::prefix('plaid')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/plaid/auth.php';
});

Route::prefix('sales')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/sales/auth.php';
});

Route::prefix('excel/sheet/import')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/import/auth.php';
});

Route::prefix('permission')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/permission/auth.php';
});
Route::prefix('management')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/management/auth.php';
});
Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/reports/auth.php';
});
Route::prefix('RequestApproval')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/RequestApproval/auth.php';
});
Route::prefix('CalenderEvent')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/CalenderEvent/auth.php';
});

Route::prefix('managerreports')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/managerreports/auth.php';
});

Route::prefix('payroll')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/payroll/auth.php';
});

Route::prefix('everee')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/everee/auth.php';
});
Route::prefix('dashboard')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/Dashboard/auth.php';
});

Route::prefix('ticket')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/Ticket/auth.php';
});
// routes for scheduling module
Route::prefix('scheduling')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/scheduling/auth.php';
});

// });

// route for Arena
Route::prefix('arena')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/arena/auth.php';
});

Route::get('sales-data-process/{id}', [PlaidController::class, 'salesdataprocess']);
Route::get('sales-data/{id}', [PlaidController::class, 'salesdata']);
Route::get('sales-raw-data/{id}', [PlaidController::class, 'salerawdata']);
Route::get('sales-raw-data1/{id}/{pid}', [PlaidController::class, 'salerawdata1']);

// without auth company profile
Route::get('/get-company-profile-without-auth', [CompanyController::class, 'getCompanyProfileWithoutAuth'])
    ->middleware('guest');

Route::post('/third-party-api', [AuthController::class, 'thirdParty']);

// Email config
Route::post('/email_configuration', [EmailConfigurationController::class, 'emailConfiguration']);
Route::get('/email_configuration_list', [EmailConfigurationController::class, 'emailConfigurationList']);

Route::post('/email_configuration_check', [EmailConfigurationController::class, 'emailConfigurationCheck']);

Route::post('/get_all_notifications', [NotificationController::class, 'get_all_notifications']);
Route::post('/get_notification', [NotificationController::class, 'get_notification_detail']);
Route::post('/check_payroll_deta', [NotificationController::class, 'check_payroll_deta']);

// ============================================
// GENERIC NOTIFICATIONS (Redis-based)
// Supports: position updates, payroll, sales exports, reports, etc.
// ============================================
Route::middleware('auth:sanctum')->prefix('v2/notifications')->group(function () {
    // Get all active notifications (or filter by type with ?type=position_update)
    Route::get('/active', [NotificationController::class, 'getActiveNotifications']);
    
    // Dismiss specific notification by type and unique key
    Route::delete('/{type}/{uniqueKey}', [NotificationController::class, 'dismissNotification']);
    
    // BACKWARD COMPATIBLE: Old frontend expects /notifications/{uniqueKey}
    // Assumes type is 'position_update' for now
    Route::delete('/{uniqueKey}', function(string $uniqueKey) {
        return app(\App\Http\Controllers\API\NotificationController::class)
            ->dismissNotification('position_update', $uniqueKey);
    })->where('uniqueKey', '[^/]+');  // Ensure it doesn't match /{type}/{uniqueKey}
    
    // Mark all as read (frontend compatibility)
    Route::post('/mark-all-read', [NotificationController::class, 'markAllNotificationsAsRead']);
});

Route::get('/activityLog', [ActivityLogController::class, 'activityLog'])
    ->middleware(['auth:sanctum', 'admin']);
Route::get('/archive-activity-logs', [ActivityLogController::class, 'clickhouseActivityLog'])
    ->middleware(['auth:sanctum', 'admin', 'throttle:clickhouse']);
Route::get('/userActivityLog', [ActivityLogController::class, 'userActivityLog'])
    ->middleware(['auth:sanctum', 'admin']);

// Route::get('/userActivityLog', [EmailConfigurationController::class, 'userActivityLog']);
Route::get('/test_api', [EvereeController::class, 'test_api']);
Route::post('/get_locations_api', [EvereeController::class, 'add_locations']);
Route::post('/add_contractors', [EvereeController::class, 'add_contractors']);
Route::post('/delete_payables', [EvereeController::class, 'delete_payables']);

Route::prefix('reset-app')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\ResetAppController::class, 'reset']);
});

/* S-Clearance (Transunion Sharable for Hires API) */
Route::prefix('v1')->group(function () {
    // Job Notifications API (Pusher fallback polling)
    Route::middleware('auth:sanctum')->prefix('job-notifications')->group(function () {
        Route::get('/recent', [JobNotificationController::class, 'recent']);
        Route::get('/active', [JobNotificationController::class, 'active']);
        Route::get('/status/{jobId}', [JobNotificationController::class, 'status']);
        Route::delete('/clear', [JobNotificationController::class, 'clear']);
    });

    Route::post('s-clearance/callback-url/report', [SClearanceController::class, 'reportStatusCallbackURLForSharable']);
    Route::post('s-clearance/callback-url/manualauthentication', [SClearanceController::class, 'manualAuthCallbackURLForSharable']);

    Route::get('/s_clearance/get_user_details/{request_id}', [SClearanceController::class, 'get_user_details']);
    Route::get('/s_clearance/validate_request/{request_id}', [SClearanceController::class, 'validate_request']);
    Route::post('/s_clearance/add_screening_request', [SClearanceController::class, 'add_screening_request']);
    Route::post('/s_clearance/background_verification_exam', [SClearanceController::class, 'background_verification_exam']);
    Route::post('/s_clearance/background_verification_exam_answers', [SClearanceController::class, 'background_verification_exam_answers']);
    Route::post('/s_clearance/users_billing_report', [SClearanceController::class, 'users_billing_report']);
    Route::post('/s_clearance/getapplicantreport', [SClearanceController::class, 'getapplicantreport']);
    Route::get('/s_clearance/get_plan', [SClearanceController::class, 'getPlan']);

    // turn ai
    Route::get('/s_clearance_turn/get_user_details/{request_id}', [TurnAiController::class, 'get_user_details']);
    Route::post('/s_clearance_turn/update_user_zipcode/{request_id}', [TurnAiController::class, 'update_user_zipcode']);
    Route::get('/s_clearance_turn/get_form_data/{request_id}', [TurnAiController::class, 'get_form_data']);
    Route::post('/s_clearance_turn/add_screening_request', [TurnAiController::class, 'add_screening_request']);
    Route::post('/s_clearance_turn/turn_result_webhook', [TurnAiController::class, 'turn_result_webhook']);
    Route::post('/s_clearance_turn/turn_status_webhook', [TurnAiController::class, 'turn_status_webhook']);
    Route::post('/s_clearance_turn/users_billing_report', [TurnAiController::class, 'users_billing_report']);
    Route::get('/s_clearance_turn/sclearance_transunion_data', [TurnAiController::class, 'sclearance_transunion_data']);
    Route::get('/s_clearance_turn/screening_results/{turn_id}', [TurnAiController::class, 'screening_results']);
    Route::post('/s_clearance_turn/get_post_consent_url', [TurnAiController::class, 'get_post_consent_url']);

    Route::post('/sequiai/users_billing_report', [ChatGPTController::class, 'users_billing_report']);
    Route::get('/sequiai/get_plans', [ChatGPTController::class, 'get_plans']);
});

Route::get('/s-clearance/generated-reports-count', [SClearanceController::class, 'generated_reports_count']); // don't remove function
Route::post('/s_clearance/post_applicant_report', [SClearanceController::class, 'post_applicant_report']); // don't remove function

// v1 api
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::prefix('payroll')->group(function () {
        include 'sequifi/v1/payroll/auth.php';
    });
    Route::prefix('reports')->group(function () {
        include 'sequifi/v1/reports/auth.php';
    });
    Route::prefix('sales')->group(function () {
        include 'sequifi/v1/sales/auth.php';
    });
    Route::prefix('management')->group(function () {
        include 'sequifi/v1/management/auth.php';
    });
    Route::prefix('managerreports')->group(function () {
        include 'sequifi/v1/managerreports/auth.php';
    });
    Route::prefix('setting')->group(function () {
        include 'sequifi/v1/setting/auth.php';
    });
    Route::prefix('pipeline')->group(function () {
        include 'sequifi/v1/pipeline/auth.php';
    });
    Route::prefix('hiring')->group(function () {
        include 'sequifi/v1/hiring/auth.php';
    });
    Route::prefix('sequiai')->group(function () {
        include 'sequifi/v1/sequigpt/auth.php';
    });
    Route::prefix('bucket')->group(function () {
        include 'sequifi/v1/bucket/auth.php';
    });
    Route::prefix('crmsale')->group(function () {
        include 'sequifi/v1/crmsale/auth.php';
    });

    Route::prefix('recon')->group(function () {
        include 'sequifi/v1/recon/auth.php';
    });
    Route::prefix('leaderboard')->group(function () {
        include 'sequifi/v1/leaderboard/auth.php';
    });
    Route::prefix('emptimercard')->group(function () {
        include 'sequifi/v1/emptimercard/auth.php';
    });
    // Route::prefix('jobnimbus')->group(function () {
    //     include 'sequifi/jobnimbus/auth.php';
    // });
});
Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::prefix('milestoneschema')->group(function () {
        include 'sequifi/v2/milestoneschema/auth.php';
    });
    Route::prefix('management')->group(function () {
        include 'sequifi/v2/management/auth.php';
    });
    Route::prefix('products')->group(function () {
        include 'sequifi/v2/products/auth.php';
    });
    Route::prefix('hiring')->group(function () {
        include 'sequifi/v2/hiring/auth.php';
    });
    Route::prefix('position')->group(function () {
        include 'sequifi/v2/position/auth.php';
    });
    Route::prefix('positionwages')->group(function () {
        include 'sequifi/v2/position_wages/auth.php';
    });
    Route::prefix('positioncommision')->group(function () {
        include 'sequifi/v2/positioncommission/auth.php';
    });
    Route::prefix('positionupfront')->group(function () {
        include 'sequifi/v2/positionupfront/auth.php';
    });
    Route::prefix('positiondeduction')->group(function () {
        include 'sequifi/v2/positiondeduction/auth.php';
    });
    Route::prefix('positionoverride')->group(function () {
        include 'sequifi/v2/positionoverride/auth.php';
    });
    Route::prefix('positionsettlement')->group(function () {
        include 'sequifi/v2/positionsettlement/auth.php';
    });
    Route::prefix('useremploymentpackage')->group(function () {
        include 'sequifi/v2/useremploymentpackage/auth.php';
    });
    Route::prefix('custom_fields')->group(function () {
        include 'sequifi/v2/custom_fields/auth.php';
    });
    Route::prefix('sales')->group(function () {
        include 'sequifi/v2/sales/auth.php';
    });
    Route::prefix('tiers')->group(function () {
        include 'sequifi/v2/tiers/auth.php';
    });
    Route::prefix('employment-package')->group(function () {
        include 'sequifi/v2/employmentPackage/auth.php';
    });
    Route::prefix('recon')->group(function () {
        include 'sequifi/v2/recon/auth.php';
    });
    Route::prefix('reports')->group(function () {
        include 'sequifi/v2/reports/auth.php';
    });
    Route::prefix('setting')->group(function () {
        include 'sequifi/v2/setting/auth.php';
    });
    Route::prefix('payroll')->group(function () {
        include 'sequifi/v2/payroll/auth.php';
    });
    Route::prefix('request-approval')->group(function () {
        include 'sequifi/v2/request-approval/auth.php';
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('user-import')->group(function () {
        include 'sequifi/userImport/auth.php';
    });
});

Route::prefix('v2')->group(function () {
    Route::prefix('sequidocs')->group(function () {
        include 'sequifi/v2/sequidocs/auth.php';
    });
});

Route::prefix('v2')->group(function () {
    Route::prefix('custom_fields')->group(function () {
        Route::get('/get_custom_fields_setting', [CustomFieldsController::class, 'getCustomFieldsSetting']);
        Route::get('/get_custom_fields_setting_without_auth', [CustomFieldsController::class, 'getCustomFieldsSettingWithoutAuth']);
        Route::middleware('auth:sanctum')->group(function () {
            include 'sequifi/v2/custom_fields/auth.php';
        });
    });

    // arena dashboard API route
    Route::prefix('dashboard')->group(function () {
        Route::middleware('arena_static_token')->group(function () {
            include 'sequifi/v2/dashboard/auth.php';
        });
    });
});

Route::prefix('v2')->middleware('auth:sanctum')->group(function () {
    Route::prefix('arena')->group(function () {
        include 'sequifi/v2/arena/auth.php';
    });
    Route::prefix('arena/RequestApproval')->group(function () {
        include 'sequifi/v2/requestapproval/auth.php';
    });
    // Route::prefix('arena/RequestApproval')->group(function () {
    //     include 'sequifi/v2/requestapproval/auth.php';
    // });
});
// Swagger API
Route::middleware('swaggerAuth')->group(function () {
    Route::post('create-sale', [ApiMissingDataController::class, 'addSaleDataBySwagger']);

    // External Hiring APIs
    //    Route::get('all-state-with-offices', [ExternalEmployeeHiringController::class, 'allStateWithOffices']);
    //    Route::get('commissions-by-positions', [ExternalEmployeeHiringController::class, 'commissionsByPositions']);
    //    Route::get('override-settings', [ExternalEmployeeHiringController::class, 'overrideSettings']);
    //    Route::get('office-with-red-line', [ExternalEmployeeHiringController::class, 'officeWithRedLine']);
    //    Route::get('office-team-list', [ExternalEmployeeHiringController::class, 'officeTeamList']);
    //    Route::get('manager-list-by-office-effective-date', [ExternalEmployeeHiringController::class, 'managerListByOfficeEffectiveDate']);

    Route::get('departments-list', [ExternalEmployeeHiringController::class, 'departmentsList']);
    Route::get('positions-list', [ExternalEmployeeHiringController::class, 'positionsList']);
    Route::get('manager-list', [ExternalEmployeeHiringController::class, 'managerList']);
    Route::get('recruiter-list', [ExternalEmployeeHiringController::class, 'recruiterList']);
    Route::post('create-employee', [ExternalEmployeeHiringController::class, 'createEmployee']);
});

// Route::get('importOldSequidocData/t', [\App\Http\Controllers\ImportOldSequidocData::class, 'importTemplates']); // Controller does not exist
// Route::get('importOldSequidocData/d', [\App\Http\Controllers\ImportOldSequidocData::class, 'importDocs']); // Controller does not exist
// Route::get('importOldSequidocData/md', [\App\Http\Controllers\ImportOldSequidocData::class, 'importManualDoc']); // Controller does not exist
// Route::get('importOldSequidocData/od', [\App\Http\Controllers\ImportOldSequidocData::class, 'importOtherDoc']); // Controller does not exist

Route::get('/get-encrypt-data', function () {
    $sql = 'SELECT * FROM users';
    $userData = DB::select($sql);

    $final = [];
    foreach ($userData as $user) {
        $fieldsToEncrypt = [
            'social_sequrity_no',
            'business_ein',
            'account_no',
            'confirm_account_no',
            'routing_no',
        ];

        foreach ($fieldsToEncrypt as $field) {
            if (! empty($user->$field)) {
                $final[] = processPaystubEncryption($user, $field, 'users');
            }
        }
    }

    /* paystub encryption data */
    $paystubQuery = $sql = 'SELECT * FROM paystub_employee';
    $paystubEmployeeData = DB::select($paystubQuery);

    foreach ($paystubEmployeeData as $value) {
        $fieldsToEncrypt = [
            'user_social_sequrity_no',
            'user_business_ein',
            'user_account_no',
            'user_routing_no',
            'company_business_ein',
        ];
        foreach ($fieldsToEncrypt as $field) {
            if (! empty($value->$field)) {
                $final[] = processPaystubEncryption($value, $field, 'paystub_employee');
            }
        }
    }

    /* company profile encrypt data */
    $companyProfileQuery = $sql = 'SELECT * FROM company_profiles';
    $companyProfileData = DB::select($companyProfileQuery);

    foreach ($companyProfileData as $value) {
        $fieldsToEncrypt = [
            'business_ein',
        ];
        foreach ($fieldsToEncrypt as $field) {
            if (! empty($value->$field)) {
                $final[] = processPaystubEncryption($value, $field, 'company_profiles');
            }
        }
    }

    /* company_billing_address encrypt data */
    $companyBillingQuery = $sql = 'SELECT * FROM company_billing_addresses';
    $companyBillingData = DB::select($companyBillingQuery);

    foreach ($companyBillingData as $value) {
        $fieldsToEncrypt = [
            'business_ein',
        ];
        foreach ($fieldsToEncrypt as $field) {
            if (! empty($value->$field)) {
                $final[] = processPaystubEncryption($value, $field, 'company_billing_addresses');
            }
        }
    }

    return $final;
    // echo "done";
});

/* paystub encryptioin data */
if (! function_exists('processPaystubEncryption')) {
    function processPaystubEncryption($user, $field, $tableName)
    {
        $newKey = env('ENCRYPTION_KEY');
        $newIv = env('ENCRYPTION_IV');
        $newAlgo = env('ENCRYPTION_CIPHER_ALGO');

        $method = 'AES-256-CBC';
        $key = 'encryptionKey123';
        $iv = '1234567891011121';

        $dyn = 'None';
        $decryptedData = 'No';
        $decryptedData1 = dataDecrypt1($user->$field, $key, $iv, $method);
        if ($decryptedData1 === false) {
            $method = 'AES-256-CBC';
            $key = 0;
            $iv = '1234567891011121';
            $decryptedData2 = dataDecrypt1($user->$field, $key, $iv, $method);
            if ($decryptedData2 === false) {
                $dyn = 'No Encrypted Data';
                $decryptedData = $user->$field;
            } else {
                $dyn = 'Second';
                $decryptedData = $decryptedData2;
            }
        } else {
            $decryptedData11 = dataDecrypt1($decryptedData1, $key, $iv, $method);
            if ($decryptedData11 === false) {
                $dyn = 'First';
                $decryptedData = $decryptedData1;
            } else {
                $dyn = 'First In First';
                $decryptedData = $decryptedData11;
            }
        }

        $encodedValue = null;
        if (! empty($decryptedData)) {
            $encryptedValue = openssl_encrypt($decryptedData, $newAlgo, $newKey, 0, $newIv);
            $encodedValue = base64_encode($encryptedValue);
        }

        DB::update("UPDATE {$tableName} SET ".$field.' = ? WHERE id = ?', [$encodedValue, $user->id]);

        return [$decryptedData, $user->$field, $dyn, $encodedValue, $field, $user->id];
    }
}

if (! function_exists('dataDecrypt1')) {
    function dataDecrypt1($encryptedData, $key, $iv, $method)
    {
        $decryptData = base64_decode($encryptedData);

        return openssl_decrypt($decryptData, $method, $key, 0, $iv);
    }
}

Route::get('default-data-create', [TestDataController::class, 'defaultDataCreate']);
Route::get('sales-template', [TestDataController::class, 'salesTemplate']);
Route::get('users-template', [TestDataController::class, 'usersTemplate']);
Route::get('leads-template', [TestDataController::class, 'leadsTemplate']);

// Database read/write split test routes
// These should be removed in production
require __DIR__.'/api_test_routes.php';
// Sentry Test Routes
// Route::get('sentry-test', [SentryTestController::class, 'testSentry']);

// Route::get('sentry-test-queries', [SentryTestController::class, 'testQueries']);

// API Documentation route
Route::get('documentation', [SwaggerController::class, 'index']);
Route::get('jobs-template', [TestDataController::class, 'jobsTemplate']);
Route::get('change-data', [TestDataController::class, 'changeData']);
Route::get('change-user-import', [TestDataController::class, 'changeUserImport']);
Route::get('branches', [TestDataController::class, 'branches']);
Route::post('default-first-Coast-webhook', [TestDataController::class, 'firstCoastWebhook']);

Route::get('desc', function () {
    return App\Models\User::select('id', 'social_sequrity_no',
        'business_ein',
        'account_no',
        'confirm_account_no',
        'routing_no')->get();
});

Route::prefix('automation')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/automation/automation_routes.php';
});

Route::post('processSaleData', [AuthController::class, 'processSaleData']);
Route::get('batch-status/{id}', [AuthController::class, 'getBatchStatus']);
Route::post('/processSaleDataKinWebhook', [AuthController::class, 'processSaleDataKinWebhook']);
Route::post('/processSaleDataLGCY', [AuthController::class, 'processSaleDataLGCY']);
Route::post('/update_aveyo_hs_id', [IntegrationController::class, 'updateAveyoHsIdForUser']);
Route::post('/pushUserData', [IntegrationController::class, 'pushUserData']); // Unified endpoint for all integrations
Route::post('/dispatchSaleMasterJob', [AuthController::class, 'dispatchSaleMasterJob']);
Route::post('/dispatchJobForSaleProcessFromAwsLambda', [AwsLambdaApiController::class, 'dispatchJobForSaleProcessFromAwsLambda']);

Route::prefix('v3')->group(function () {
    // v3 arena dashboard API route
    Route::prefix('dashboard')->group(function () {
        Route::middleware('arena_static_token')->group(function () {
            include 'sequifi/v3/dashboard/auth.php';
        });
    });
});

// Health Check Endpoint (public, no auth required for Sentry monitoring)
Route::get('/health', [HealthCheckController::class, 'check']);

// Current user endpoint (for web sessions)
Route::get('/user', function () {
    return response()->json(auth()->user());
})->middleware('auth:sanctum,web');

/*
|--------------------------------------------------------------------------
| Metabase Embedded Routes
|--------------------------------------------------------------------------
*/
Route::prefix('metabase')->middleware('auth:sanctum,web')->group(function () {
    Route::post('/dashboard/url', [App\Http\Controllers\API\MetabaseController::class, 'generateDashboardUrl']);
    Route::post('/question/url', [App\Http\Controllers\API\MetabaseController::class, 'generateQuestionUrl']);
    Route::post('/verify-token', [App\Http\Controllers\API\MetabaseController::class, 'verifyToken']);
    Route::get('/config', [App\Http\Controllers\API\MetabaseController::class, 'getConfigurationStatus']);
});

// Pure API endpoints for Bearer token access only
Route::prefix('metabase/api')->middleware('auth:sanctum')->group(function () {
    Route::post('/dashboard/embed', [App\Http\Controllers\API\MetabaseController::class, 'embedDashboard']);
    Route::post('/question/embed', [App\Http\Controllers\API\MetabaseController::class, 'embedQuestion']);
    Route::post('/dashboard/url', [App\Http\Controllers\API\MetabaseController::class, 'generateDashboardUrl']);
    Route::post('/question/url', [App\Http\Controllers\API\MetabaseController::class, 'generateQuestionUrl']);

    // New enhanced API endpoints for better parameter handling and iframe support
    Route::post('/question/{questionId}/embed-with-params', [App\Http\Controllers\API\MetabaseController::class, 'embedQuestionWithParams']);
    Route::get('/question/{questionId}/iframe-html', [App\Http\Controllers\API\MetabaseController::class, 'getQuestionIframeHtml']);
    Route::post('/question/{questionId}/generate-url', [App\Http\Controllers\API\MetabaseController::class, 'generateQuestionUrlWithParams']);
    Route::post('/question/{questionId}/embed-response', [App\Http\Controllers\API\MetabaseController::class, 'getEmbedResponse']);

    // Convert regular Metabase URLs to embeddable format
    Route::post('/convert-url', [App\Http\Controllers\API\MetabaseController::class, 'convertMetabaseUrl']);
    Route::get('/convert-url', [App\Http\Controllers\API\MetabaseController::class, 'convertMetabaseUrlGet']);
    Route::post('/question/from-url', [App\Http\Controllers\API\MetabaseController::class, 'questionFromUrl']);
});

// Public Metabase test endpoint (no auth required)
Route::get('/metabase/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Metabase API is reachable',
        'timestamp' => now()->toISOString(),
        'endpoints' => [
            'config' => '/api/metabase/config (requires auth)',
            'dashboard' => '/api/metabase/dashboard/url (requires auth)',
            'question' => '/api/metabase/question/url (requires auth)',
        ],
    ]);
});

if (strtolower(env('DOMAIN_NAME')) === 'moxie') {

    // Only administrators can generate API tokens
    Route::prefix('public/v2')->middleware('auth:sanctum', 'admin')->group(function () {
        include 'sequifi/v2/external_api_token/api.php';
    });

    // External API endpoints using existing payroll tokens
    Route::prefix('public/v2')->middleware('verify_external_api_token:payroll:read')->group(function () {
        include 'sequifi/v2/external_api/api.php';
    });
}

/*
|--------------------------------------------------------------------------
| Subscription Mapping Routes
|--------------------------------------------------------------------------
*/

Route::prefix('subscription-mapping')->group(function () {
    Route::post('/map', [App\Http\Controllers\API\SubscriptionMappingController::class, 'mapSubscription']);
    Route::post('/batch', [App\Http\Controllers\API\SubscriptionMappingController::class, 'batchMap']);
    Route::get('/config', [App\Http\Controllers\API\SubscriptionMappingController::class, 'getConfiguration']);
    Route::put('/config', [App\Http\Controllers\API\SubscriptionMappingController::class, 'updateConfiguration']);
    Route::post('/config/field', [App\Http\Controllers\API\SubscriptionMappingController::class, 'addFieldMapping']);
});

// Debugging route for queue dashboard investigation
Route::get('/debug/supervisor-paths', function () {
    $paths = [
        '/etc/supervisor/conf.d/',
        '/etc/supervisord.d/',
        '/usr/local/etc/supervisor/conf.d/',
        '/etc/supervisor/',
        '/etc/supervisord/',
        '/usr/local/etc/supervisor/',
    ];

    $results = [];

    foreach ($paths as $path) {
        $pathInfo = [
            'path' => $path,
            'exists' => is_dir($path),
            'readable' => is_readable($path),
            'files' => [],
            'error' => null,
        ];

        if (is_dir($path)) {
            try {
                $files = glob($path.'*');
                $pathInfo['files'] = array_map('basename', $files ?: []);
                $pathInfo['conf_files'] = array_filter($pathInfo['files'], function ($file) {
                    return str_ends_with($file, '.conf');
                });
            } catch (Exception $e) {
                $pathInfo['error'] = $e->getMessage();
            }
        }

        $results[$path] = $pathInfo;
    }

    // Also check if supervisorctl is available
    $supervisorctl = [
        'command_available' => false,
        'status_output' => null,
        'error' => null,
    ];

    try {
        $output = shell_exec('which supervisorctl 2>/dev/null');
        if ($output) {
            $supervisorctl['command_available'] = true;
            $statusOutput = shell_exec('supervisorctl status 2>&1');
            $supervisorctl['status_output'] = $statusOutput;
        }
    } catch (Exception $e) {
        $supervisorctl['error'] = $e->getMessage();
    }

    return response()->json([
        'supervisor_paths' => $results,
        'supervisorctl' => $supervisorctl,
        'current_user' => get_current_user(),
        'running_as' => exec('whoami 2>/dev/null'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Custom Sales Fields Feature Routes
|--------------------------------------------------------------------------
|
| Feature flags and custom sales fields endpoints.
| Protected by Sanctum authentication and feature flag middleware.
|
*/

use App\Http\Controllers\API\CustomSalesFieldController;
use App\Http\Controllers\API\FeatureFlagController;

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
