<?php

namespace App\Console\Commands;

use App\Models\AutomationActionLog;
use App\Models\AutomationRule;
use App\Models\Lead;
use App\Models\OnboardingEmployees;
use App\Models\SystemSetting;
use App\Services\Automation;
use App\Services\AutomationOnboarding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunAutomation extends Command
{
    /**
     * SystemSetting key for last run timestamp
     */
    const LAST_RUN_TIMESTAMP_KEY = 'automation_last_run_timestamp';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:run 
                            {--onboarding-id= : Process automation for specific onboarding candidate}
                            {--optimize-memory : Optimize memory usage during processing}
                            {--batch-size=100 : Number of records to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Automation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        // Handle memory optimization option
        if ($this->option('optimize-memory')) {
            // Increase memory limit for large batch processing
            ini_set('memory_limit', '512M');
            Log::info('RunAutomation: Memory optimization enabled', [
                'memory_limit' => ini_get('memory_limit'),
            ]);
        }

        // Get batch size option (default: 100)
        $batchSize = (int) $this->option('batch-size');
        if ($batchSize < 1) {
            $batchSize = 100; // Default to 100 if invalid value provided
        }

        Log::info('RunAutomation: Command options', [
            'optimize_memory' => $this->option('optimize-memory'),
            'batch_size' => $batchSize,
        ]);

        // Check if specific onboarding candidate was requested
        $specificOnboardingId = $this->option('onboarding-id');

        if ($specificOnboardingId) {
            Log::info('RunAutomation: Starting real-time automation for specific candidate', [
                'onboarding_id' => $specificOnboardingId,
                'trigger_type' => 'Real-time status change',
            ]);

            return $this->processSpecificOnboardingCandidate($specificOnboardingId);
        }

        // Get the last automation run time from SystemSetting (default to 10 minutes ago to catch any missed changes)
        $lastRunTimestamp = SystemSetting::getValue(self::LAST_RUN_TIMESTAMP_KEY);
        $lastRunTime = $lastRunTimestamp ? \Carbon\Carbon::parse($lastRunTimestamp) : now()->subMinutes(10);

        Log::info('RunAutomation: Starting optimized automation run', [
            'last_run_time' => $lastRunTime,
            'checking_changes_since' => $lastRunTime,
        ]);

        // Process Lead Automations (only recent changes)
        $automationRules = AutomationRule::where('category', 'Lead')->where('status', 1)->get();
        $totalLeadsProcessed = 0;

        foreach ($automationRules as $automationRule) {
            // Check if this rule has "Lead Stays" conditions and get the minimum required days
            $hasLeadStaysRule = false;
            $minStayDays = 0;

            if (isset($automationRule->rule[0]['when'])) {
                foreach ($automationRule->rule[0]['when'] as $condition) {
                    if (isset($condition['event_type']) && $condition['event_type'] == 'Lead Stays') {
                        $hasLeadStaysRule = true;
                        if (isset($condition['for_days'])) {
                            $minStayDays = max($minStayDays, (int) $condition['for_days']);
                        }
                    }
                }
            }

            if ($hasLeadStaysRule && $minStayDays > 0) {
                // For "Lead Stays" rules: Get leads created at least X days ago
                // BUT exclude those who already have successful automation logs for their CURRENT status
                // This prevents sending duplicate emails while lead remains in same status
                $leads = Lead::whereNotIn('status', [Lead::STATUS_HIRED, Lead::STATUS_REJECTED])
                    ->whereRaw('DATEDIFF(NOW(), created_at) >= ?', [$minStayDays])
                    ->whereNotExists(function ($query) use ($automationRule) {
                        $query->select(DB::raw(1))
                            ->from('automation_action_logs')
                            ->whereColumn('automation_action_logs.lead_id', 'leads.id')
                            ->where('automation_action_logs.automation_rule_id', $automationRule->id)
                            ->whereColumn('automation_action_logs.new_pipeline_lead_status', 'leads.pipeline_status_id')
                            ->where('automation_action_logs.email_sent', 1);
                    })
                    ->get();
            } else {
                // For "Lead Moves" rules: Use the optimized approach (recent updates only)
                // OPTIMIZATION: Only get leads with recent status changes or updates
                $leads = Lead::whereNotIn('status', [Lead::STATUS_HIRED, Lead::STATUS_REJECTED])
                    ->where('updated_at', '>', $lastRunTime)
                    ->get();
            }

            foreach ($leads as $lead) {
                $totalLeadsProcessed++;

                // check if for this lead this automation already run
                $isAutomationExecuted = $this->isAutomationExecuted($automationRule->id, $lead->id);

                if (! $isAutomationExecuted) {

                    // execute this automation rule
                    $data = [
                        'lead_id' => $lead->id,
                        'automation_rule_id' => $automationRule->id,
                    ];

                    try {
                        $automation = new Automation($data);
                        $automation->trigger();

                    } catch (\Throwable $th) {
                        $log = AutomationActionLog::createSafely([
                            'lead_id' => $lead->id,
                            'automation_rule_id' => $automationRule->id,
                            'status' => 0,
                            'trace_log' => $th,
                            'category' => 'ERROR',
                            'event' => 'Automation Exception',
                        ]);
                    }
                }
            }
        }

        // Process Onboarding Automations (only recent changes)
        $automationOnboarding = AutomationRule::where('category', 'Onboarding')->where('status', 1)->get();
        $totalOnboardingProcessed = 0;

        if (count($automationOnboarding) > 0) {
            Log::info('RunAutomation: Processing onboarding automations (OPTIMIZED)', [
                'automation_rules_count' => count($automationOnboarding),
                'checking_changes_since' => $lastRunTime,
            ]);

            foreach ($automationOnboarding as $automationRule) {
                // Check if this rule has "Candidate Stays" conditions and get the minimum required days
                $hasCandidateStaysRule = false;
                $minStayDays = 0;

                if (isset($automationRule->rule[0]['when'])) {
                    foreach ($automationRule->rule[0]['when'] as $condition) {
                        if (isset($condition['event_type']) && $condition['event_type'] == 'Candidate Stays') {
                            $hasCandidateStaysRule = true;
                            if (isset($condition['for_days'])) {
                                $minStayDays = max($minStayDays, (int) $condition['for_days']);
                            }
                        }
                    }
                }

                if ($hasCandidateStaysRule && $minStayDays > 0) {
                    // For "Candidate Stays" rules: Get employees created at least X days ago
                    // BUT exclude those who already have successful automation logs for their CURRENT status
                    // This prevents sending duplicate emails while employee remains in same status
                    $onboardings = OnboardingEmployees::whereNotIn('status_id', [11, 15])
                        ->whereRaw('DATEDIFF(NOW(), created_at) >= ?', [$minStayDays])
                        ->whereNotExists(function ($query) use ($automationRule) {
                            $query->select(DB::raw(1))
                                ->from('automation_action_logs')
                                ->whereColumn('automation_action_logs.onboarding_id', 'onboarding_employees.id')
                                ->where('automation_action_logs.automation_rule_id', $automationRule->id)
                                ->whereColumn('automation_action_logs.to_status_id', 'onboarding_employees.status_id')
                                ->where('automation_action_logs.email_sent', 1);
                        })
                        ->get();
                } else {
                    // For "Candidate Moves" rules: Use the optimized approach (recent updates only)
                    // OPTIMIZATION: Only get candidates with recent status changes
                    // Priority 1: Candidates with old_status_id (recent status change)
                    // Priority 2: Candidates updated since last run
                    $onboardingsWithStatusChange = OnboardingEmployees::whereNotIn('status_id', [11, 15])
                        ->whereNotNull('old_status_id')
                        ->where('updated_at', '>', $lastRunTime)
                        ->get();

                    // Also check for any other recent updates that might need processing
                    $onboardingsRecentlyUpdated = OnboardingEmployees::whereNotIn('status_id', [11, 15])
                        ->whereNull('old_status_id')
                        ->where('updated_at', '>', $lastRunTime)
                        ->get();

                    $onboardings = $onboardingsWithStatusChange->merge($onboardingsRecentlyUpdated)->unique('id');
                }

                foreach ($onboardings as $onboarding) {
                    $totalOnboardingProcessed++;

                    // Log if no user_id but continue processing (automation can work without user_id)
                    if (! $onboarding->user_id) {
                        Log::debug('RunAutomation: Processing candidate with NULL user_id', [
                            'onboarding_id' => $onboarding->id,
                            'note' => 'Automation can still process status changes without user_id',
                        ]);
                    }

                    // check if for this candidate this automation already run (for logging purposes only)
                    $isAutomationExecuted = $this->isAutomationOnboardingExecuted($automationRule->id, $onboarding->id);

                    // execute this automation rule (multiple executions allowed)
                    $data = [
                        'onboarding_id' => $onboarding->id,
                        'automation_rule_id' => $automationRule->id,
                    ];

                    try {
                        $automation = new AutomationOnboarding($data);
                        $automation->trigger();

                    } catch (\Throwable $th) {
                        $log = AutomationActionLog::createSafely([
                            'onboarding_id' => $onboarding->id,
                            'automation_rule_id' => $automationRule->id,
                            'status' => 0,
                            'trace_log' => $th,
                            'category' => 'ERROR',
                            'event' => 'Onboarding Automation Exception',
                        ]);
                    }
                }
            }
        }

        // Update the last run time in SystemSetting
        $currentTimestamp = now()->toDateTimeString();
        SystemSetting::setValue(
            self::LAST_RUN_TIMESTAMP_KEY,
            $currentTimestamp,
            'automation',
            'Last successful run timestamp for automation processing'
        );

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('RunAutomation: Optimized automation run completed', [
            'execution_time_ms' => $executionTime,
            'leads_processed' => $totalLeadsProcessed,
            'onboarding_processed' => $totalOnboardingProcessed,
            'total_processed' => $totalLeadsProcessed + $totalOnboardingProcessed,
            'last_run_timestamp_saved' => $currentTimestamp,
            'optimization_benefit' => 'Only processed candidates with recent changes',
        ]);

        // Process failed automation logs (status = 0, email_sent = false)
        $this->processFailedAutomationLogs();

        return Command::SUCCESS;
    }

    /**
     * Process failed automation logs that still need email delivery
     */
    protected function processFailedAutomationLogs()
    {
        $startTime = microtime(true);

        // Get failed logs with rate limiting (max 50 per run to prevent overwhelming the system)
        $failedLogs = AutomationActionLog::where('status', 0)
            ->where('email_sent', false)
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNotNull('onboarding_id')->orWhereNotNull('lead_id');
            })
            ->orderBy('created_at', 'asc') // Process oldest first
            ->limit(50)
            ->get();

        if ($failedLogs->isEmpty()) {
            Log::info('RunAutomation: No failed automation logs to process');

            return;
        }

        Log::info('RunAutomation: Processing failed automation logs', [
            'failed_logs_count' => $failedLogs->count(),
            'max_logs_per_run' => 50,
        ]);

        $successCount = 0;
        $failureCount = 0;

        foreach ($failedLogs as $log) {
            try {
                // Use the ProcessAutomationLog command to handle individual log processing
                Artisan::call('automation:process-log', [
                    'log_id' => $log->id,
                ]);

                $successCount++;

                Log::debug('RunAutomation: Successfully processed failed log', [
                    'log_id' => $log->id,
                    'onboarding_id' => $log->onboarding_id,
                    'lead_id' => $log->lead_id,
                ]);

            } catch (\Throwable $th) {
                $failureCount++;

                Log::error('RunAutomation: Failed to process automation log', [
                    'log_id' => $log->id,
                    'onboarding_id' => $log->onboarding_id,
                    'lead_id' => $log->lead_id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('RunAutomation: Failed automation logs processing completed', [
            'total_logs_processed' => $failedLogs->count(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'execution_time_ms' => $executionTime,
        ]);
    }

    /**
     * Process automation for a specific onboarding candidate (real-time trigger)
     */
    protected function processSpecificOnboardingCandidate($onboardingId)
    {
        $startTime = microtime(true);

        try {
            // Get the specific candidate
            $candidate = OnboardingEmployees::find($onboardingId);

            if (! $candidate) {
                Log::warning('RunAutomation: Specific onboarding candidate not found', [
                    'onboarding_id' => $onboardingId,
                ]);

                return Command::FAILURE;
            }

            Log::info('RunAutomation: Processing real-time automation for specific candidate', [
                'onboarding_id' => $onboardingId,
                'candidate_name' => $candidate->first_name.' '.$candidate->last_name,
                'status_id' => $candidate->status_id,
                'old_status_id' => $candidate->old_status_id,
            ]);

            // Get all active onboarding automation rules
            $automationRules = AutomationRule::where('category', 'Onboarding')->where('status', 1)->get();
            $totalProcessed = 0;

            foreach ($automationRules as $automationRule) {
                // Check if this automation has already been executed for this candidate
                $isAutomationExecuted = $this->isAutomationOnboardingExecuted($automationRule->id, $candidate->id);

                // Execute this automation rule for the specific candidate (multiple executions allowed)
                $data = [
                    'onboarding_id' => $candidate->id,
                    'automation_rule_id' => $automationRule->id,
                ];

                try {
                    Log::debug('RunAutomation: Triggering real-time automation for candidate', [
                        'onboarding_id' => $candidate->id,
                        'candidate_name' => $candidate->first_name.' '.$candidate->last_name,
                        'status_id' => $candidate->status_id,
                        'old_status_id' => $candidate->old_status_id,
                        'automation_rule_id' => $automationRule->id,
                        'trigger_type' => 'Real-time specific candidate',
                        'previous_executions_exist' => $isAutomationExecuted,
                        'multiple_executions_allowed' => true,
                    ]);

                    $automation = new AutomationOnboarding($data);
                    $automation->trigger();
                    $totalProcessed++;

                } catch (\Throwable $th) {
                    Log::error('RunAutomation: Real-time automation failed for candidate', [
                        'onboarding_id' => $candidate->id,
                        'automation_rule_id' => $automationRule->id,
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString(),
                    ]);

                    $log = AutomationActionLog::createSafely([
                        'onboarding_id' => $candidate->id,
                        'automation_rule_id' => $automationRule->id,
                        'status' => 0,
                        'trace_log' => $th,
                        'category' => 'ERROR',
                        'event' => 'Candidate Automation Exception',
                    ]);
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('RunAutomation: Real-time automation completed for specific candidate', [
                'onboarding_id' => $onboardingId,
                'automation_rules_processed' => $totalProcessed,
                'execution_time_ms' => $executionTime,
                'trigger_type' => 'Real-time specific candidate',
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('RunAutomation: Failed to process real-time automation for specific candidate', [
                'onboarding_id' => $onboardingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    public function isAutomationExecuted($automationRuleId, $leadId)
    {

        $log = AutomationActionLog::where([
            'automation_rule_id' => $automationRuleId,
            'lead_id' => $leadId,
            'status' => 1,
        ])->first();

        if ($log) {

            return true; // yes executed
        }

        return false; // no not executed

    }

    public function isAutomationOnboardingExecuted($automationRuleId, $onboardingId)
    {

        $log = AutomationActionLog::where([
            'automation_rule_id' => $automationRuleId,
            'onboarding_id' => $onboardingId,
            'status' => 1,
        ])->first();

        if ($log) {

            return true; // yes executed
        }

        return false; // no not executed

    }
}
