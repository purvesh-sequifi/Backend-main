<?php

namespace App\Providers;

use App\Core\Adapters\Theme;
use App\Models\AutomationActionLog;
use App\Support\SalesExcelImportContext;
// use DB;
// use Log;
use App\Models\CompanyProfile;
use App\Models\LegacyApiRawDataHistory;
use App\Models\MilestoneProductAuditLog;
use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Models\observers\UserObserver;
use App\Models\observers\UsersAdditionalEmailObserver;
use App\Models\OnboardingEmployees;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\TiersLevel;
use App\Models\TiersSchema;
use App\Models\Timezone;
use App\Models\User;
use App\Models\UserOrganizationHistory;
use App\Models\UsersAdditionalEmail;
use App\Observers\AutomationActionLogObserver;
use App\Observers\HubSpotCurrentEnergyObserver;
use App\Observers\LegacyApiRawDataHistoryObserver;
use App\Observers\MilestoneSchemaObserver;
use App\Observers\MilestoneSchemaTriggerObserver;
use App\Observers\OnboardingEmployeesObserver;
use App\Observers\ProductMilestoneHistoriesObserver;
use App\Observers\ProductsObserver;
use App\Observers\TiersLevelsObserver;
use App\Observers\TiersSchemaObserver;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Sentry\State\Scope;  // Add this if not already present

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }

        // Register write-optimized database service provider
        $this->app->register(WriteOptimizedDatabaseServiceProvider::class);

        // Register AuditLogService as singleton for request-scoped audit logging (Octane compatibility)
        $this->app->singleton(\App\Services\AuditLogService::class);

        // Register SalesCustomFieldCalculator as singleton for Custom Sales Fields feature
        $this->app->singleton(\App\Services\SalesCustomFieldCalculator::class);

        // Sales import notification context (avoid $GLOBALS; safe for queue workers & Octane when scoped is reset)
        $this->app->scoped(SalesExcelImportContext::class, static fn () => new SalesExcelImportContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS for all generated URLs in production
        // This fixes issues with reverse proxies/load balancers where Laravel
        // doesn't detect the original HTTPS protocol
        if (App::environment('production') || App::environment('staging')) {
            URL::forceScheme('https');
        }

        $directory = public_path('template');
        if (! file_exists($directory)) {
            mkdir($directory, 0777, true);
            chmod($directory, 0777);
        }

        // Register Model Observers
        // LegacyApiRawDataHistory::observe(HubSpotCurrentEnergyObserver::class);
        AutomationActionLog::observe(AutomationActionLogObserver::class);
        $directory = public_path('template');
        if (! file_exists($directory)) {
            mkdir($directory, 0777, true);
            chmod($directory, 0777);
        }

        try {
            if (Schema::hasTable('company_profiles')) {
                $company = CompanyProfile::first();
                if (! empty($company)) {
                    $givenOffset = $company->time_zone;
                    $timeZone = Timezone::where('name', $givenOffset)->first();
                    if ($timeZone && ! empty($timeZone->timezone)) {
                        $timezone = $timeZone->timezone;
                        date_default_timezone_set($timezone);
                        config(['app.timezone' => $timezone]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available
            // This allows the application to start even without database connection
        }

        $theme = theme();

        // Share theme adapter class
        View::share('theme', $theme);

        // Set demo globally
        $theme->setDemo(request()->input('demo', 'demo1'));
        // $theme->setDemo('demo2');

        $theme->initConfig();

        bootstrap()->run();

        if (isRTL()) {
            // RTL html attributes
            Theme::addHtmlAttribute('html', 'dir', 'rtl');
            Theme::addHtmlAttribute('html', 'direction', 'rtl');
            Theme::addHtmlAttribute('html', 'style', 'direction:rtl;');
            Theme::addHtmlAttribute('body', 'direction', 'rtl');
        }

        MilestoneSchema::observe(MilestoneSchemaObserver::class);
        MilestoneSchemaTrigger::observe(MilestoneSchemaTriggerObserver::class);
        Products::observe(ProductsObserver::class);
        ProductMilestoneHistories::observe(ProductMilestoneHistoriesObserver::class);
        TiersSchema::observe(TiersSchemaObserver::class);
        TiersLevel::observe(TiersLevelsObserver::class);
        OnboardingEmployees::observe(OnboardingEmployeesObserver::class);
        LegacyApiRawDataHistory::observe(LegacyApiRawDataHistoryObserver::class);
        User::observe(UserObserver::class);
        UsersAdditionalEmail::observe(UsersAdditionalEmailObserver::class);

        \App\Models\PayrollHourlySalary::observe(\App\Observers\PayrollHourlySalaryObserver::class);
        \App\Models\PayrollOvertime::observe(\App\Observers\PayrollOvertimeObserver::class);
        \App\Models\UserCommission::observe(\App\Observers\UserCommissionObserver::class);
        \App\Models\UserOverrides::observe(\App\Observers\UserOverridesObserver::class);
        \App\Models\ClawbackSettlement::observe(\App\Observers\ClawbackSettlementObserver::class);
        \App\Models\PayrollAdjustmentDetail::observe(\App\Observers\PayrollAdjustmentDetailObserver::class);
        \App\Models\ApprovalsAndRequest::observe(\App\Observers\ApprovalsAndRequestObserver::class);
        \App\Models\CustomField::observe(\App\Observers\CustomFieldObserver::class);
        \App\Models\PayrollDeductions::observe(\App\Observers\PayrollDeductionsObserver::class);
        \App\Models\Payroll::observe(\App\Observers\PayrollObserver::class);

        // Pay Period Auto-Creation Observers - Automatically create next period when period is closed
        \App\Models\WeeklyPayFrequency::observe(\App\Observers\WeeklyPayFrequencyObserver::class);
        \App\Models\MonthlyPayFrequency::observe(\App\Observers\MonthlyPayFrequencyObserver::class);
        \App\Models\AdditionalPayFrequency::observe(\App\Observers\AdditionalPayFrequencyObserver::class);

        // Note: Cache clearing is handled in existing model observers (UserOverridesObserver, etc.)
        // to avoid conflicts with existing business logic

        App::terminating(function () {
            try {
                $auditLog = app(\App\Services\AuditLogService::class);
                
                // Only process if there are changes
                if (!$auditLog->hasChanges()) {
                    return;
                }

                $group = MilestoneProductAuditLog::orderBy('group', 'DESC')->first()?->group + 1;
                
                foreach ($auditLog->getChanges() as $change) {
                    $change['group'] = $group;
                    MilestoneProductAuditLog::create($change);
                }

                Log::debug('Audit log saved', [
                    'group' => $group,
                    'count' => $auditLog->count(),
                ]);

            } catch (\Exception $e) {
                Log::error('Error in App::terminating - Audit log not saved', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        // Database query performance monitoring with Sentry
        // Only enabled if Sentry tracing is enabled
        if (config('sentry.tracing.enabled') && config('sentry.tracing.sql_queries')) {
            DB::listen(function ($query) {
                // Get current transaction from Sentry if it exists
                $transaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();

                if ($transaction) {
                    // Create a child span for the database query
                    $context = new \Sentry\Tracing\SpanContext;
                    $context->setOp('db.query');
                    $context->setDescription($query->sql);

                    // Add data about the query
                    $span = $transaction->startChild($context);
                    $span->setData([
                        'db.system' => config('database.default', 'mysql'),
                        'db.query_time_ms' => $query->time,
                    ]);

                    // Add query bindings if enabled
                    if (config('sentry.tracing.sql_bindings')) {
                        $span->setData([
                            'db.bindings' => $query->bindings,
                        ]);
                    }

                    // Finish the span
                    $span->finish();
                }
            });
        }

        /* This is only for  sentry tags  start from here */

        \Sentry\configureScope(function (Scope $scope): void {
            $requstUri = $this->app->request->getRequestUri();
            $requstUri = strtolower($requstUri);

            $tagName = 'Not Specified';

            // Sentry will track 'OnBoarding' Module tag for errors passes through below matched URLs
            $onboardingUrls = ['onboarding', 'onboarding', 'hiredEmployee', 'HiringEmployee', 'delete_incomplete_hire_form', 'delete_additional_info', 'delete_document_upload', 'send_request_hiring', 'offer_review_', 'special_review_offer_reject', 'recruiter_filter', 'employee_additional_personal_details', 'getWorkerFilesListFromEveree', 'everee-worker-tax-files'];
            foreach ($onboardingUrls as $snglUrl) {
                $snglUrl = strtolower($snglUrl);
                if (str_contains($requstUri, $snglUrl)) {
                    $tagName = 'OnBoarding';
                    break;
                }
            }
            // Sentry will track 'OnBoarding' Module tag for errors passes through above matched URLs

            // Sentry will track 'Hiring Progress' Module tag for errors passes through below matched URLs
            $hiringprogressUrls = ['hiring_progress', 'recent_hired', 'hiring_graph'];
            foreach ($hiringprogressUrls as $snglUrl) {
                $snglUrl = strtolower($snglUrl);
                if (str_contains($requstUri, $snglUrl)) {
                    $tagName = 'Hiring Progress';
                    break;
                }
            }
            // Sentry will track 'Hiring Progress' Module tag for errors passes through above matched URLs

            // Sentry will track 'Leads' Module tag for errors passes through below matched URLs
            /*
              $leadsUrls = array('leads_list', 'interview_reschedule', 'assign', 'changeStatus', 'schedule_time', 'schedule_interview', 'add_lead', 'get_lead', 'reporting_manager', 'alertScheduleInterview', 'check_status_email_and_mobile', 'lead-details', 'leaddocument', 'user_preference_update', 'getuserpreference', 'task-status');
              foreach($leadsUrls as $snglUrl)
              {
                  $snglUrl = strtolower($snglUrl);
                  if( str_contains($requstUri, $snglUrl) )
                  {
                      $tagName = 'Leads';
                      break;
                  }
              }
                  */
            // Sentry will track 'Leads' Module tag for errors passes through above matched URLs

            // Sentry will track 'Pipeline' Module tag for errors passes through below matched URLs
            /*
              $pipelineUrls = array('/subtasks', '/pipeline');
              foreach($pipelineUrls as $snglUrl)
              {
                  $snglUrl = strtolower($snglUrl);
                  if( str_contains($requstUri, $snglUrl) )
                  {
                      $tagName = 'Pipeline';
                      break;
                  }
              }
                  */
            // Sentry will track 'Pipeline' Module tag for errors passes through above matched URLs

            // Sentry will track 'Sequidoc' Module tag for errors passes through below matched URLs
            $sequidocsUrls = ['SequiDoc', 'sequdoc', 'get_template', 'update_template', 'delete_template', 'employee_search_for_send_document', 'resend_document_individually', 'employee_document_comment', 'other_template', 'custom_doc_for_sign', 'sequi_doc', 'new_template', 'save_onboarding_employee', 'offer_letter', 'remove_and_reassign_position_to_template', 'upload_dcoument_pdf', 'template_category_dropdown', '_list_dropdown', 'office_and_position_wise_user_list', 'smart_text_template', 'other_blank_template', 'other_pdf_template', 'offer_letter_category', 'send_offer_letter_to_onboarding_employee', 'send_document_to_upload_files', 'category_wise_document_list_with_user_count', 'get_signed_documents_user_details', 'users_post_hiring_document_list', 'document_version_list', 'send_document_to_external_recipient', 'test_and_send_document_to_upload_files_to_external_recipient', 'document_list_for_external_recipient', 'add_comment_on_user_document', 'get_comments_on_users_documents', 'send-reminder', 'create-default-documents', 'user_comment_new', 'document_accepted_declined_process', 'category_id_wise_template_list_dropdown', 'use_smart_text_template'];
            foreach ($sequidocsUrls as $snglUrl) {
                $snglUrl = strtolower($snglUrl);
                if (str_contains($requstUri, $snglUrl)) {
                    $tagName = 'Sequidoc';
                    break;
                }
            }
            // Sentry will track 'Sequidoc' Module tag for errors passes through above matched URLs

            // Sentry will track 'Payroll' Module tag for errors passes through below matched URLs
            $payrollUrls = ['payroll', 'override_details', 'reimbursement_details', 'adjustment_details', 'payment_request', 'advance_repayment', 'paymentRequestPayNow', 'commission_details', 'update_user_commission', 'reconciliation_details', 'reconciliation_overrides_details_edit', 'reconciliationByUser', 'ReconciliationListUser', 'reconciliationFinalize', 'finalizeReconciliationList', 'AdjustmentComment', 'userRepotRecon', 'update_reconciliation_details', 'finalize_reconciliation', 'create_onetime_payment', 'reconciliations_adjustment', 'get_onetime_payment_history', 'one_time_payment', 'export_payment_history', 'onetime_payment_total', 'moveToReconciliation', 'delete_adjustement', 'get_everee_payables', 'get_everee_missing_payables', 'paystub_reconciliation_details', 'deleteReconAdjustement', 'one_time_payment', 'one_time_adjustment_details', 'one_time_reimbursement_details', 'user-commission-via-pid', 'moveToReconciliation', 'commission_details', 'override_details', 'adjustment_details', 'reimbursement_details', 'workker-basic', 'workker-detail', 'worker-all-details', 'pid-basic', 'pid-detail', 'hourly_salary_details', 'overtime_details', 'move_to_recon'];
            foreach ($payrollUrls as $snglUrl) {
                $snglUrl = strtolower($snglUrl);
                if (str_contains($requstUri, $snglUrl)) {
                    $tagName = 'Payroll';
                    break;
                }
            }
            // Sentry will track 'Payroll' Module tag for errors passes through above matched URLs

            // Sentry will track 'OnBoarding' Module tag for errors passes through below matched URLs
            $onboardingUrls = ['login'];
            foreach ($onboardingUrls as $snglUrl) {
                $snglUrl = strtolower($snglUrl);
                if (str_contains($requstUri, $snglUrl)) {
                    $tagName = 'Login';
                    break;
                }
            }

            $scope->setTag('Module', $tagName);
            $scope->setTag('WebURL', url()->full());
        });

        /* End sentry tags here */

        if (app()->bound('sentry') && Auth::check()) {
            app('sentry')->configureScope(function (Scope $scope) {
                $scope->setUser([
                    'id' => Auth::id(),
                    'email' => 'gorakh@sequifi.com',
                ]);
            });
        }

        /*
        ** Register model observers to automatically handle model events
        ** UserObserver will handle events like created, updated, deleted for the User model
        */
        User::observe(\App\Observers\UserObserver::class);

        // UserOrganizationHistoryObserver will handle events for the UserOrganizationHistory model
        UserOrganizationHistory::observe(\App\Observers\UserOrganizationHistoryObserver::class);

        // Laravel Pennant Feature Flag Configuration
        // Auto-discover feature classes in app/Features
        Feature::discover();

        // Resolve feature scope to company (not user)
        // This allows feature flags to be enabled/disabled per company
        // Note: This is a single-tenant application where CompanyProfile::first()
        // returns the company for the current deployment
        Feature::resolveScopeUsing(function ($driver) {
            $user = auth()->user();
            
            if (!$user) {
                return null;
            }
            
            // Single-tenant: Get the company profile for this deployment
            return CompanyProfile::first();
        });
    }
}
