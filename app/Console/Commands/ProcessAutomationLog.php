<?php

namespace App\Console\Commands;

use App\Jobs\AutomationMail;
use App\Models\AutomationActionLog;
use App\Models\Lead;
use App\Models\OnboardingEmployees;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAutomationLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:process-log {log_id : The ID of the automation log to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a specific automation log entry and ensure email delivery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logId = $this->argument('log_id');

        Log::info('ProcessAutomationLog: Starting processing for specific log', [
            'log_id' => $logId,
        ]);

        $automationLog = AutomationActionLog::find($logId);

        if (! $automationLog) {
            Log::error('ProcessAutomationLog: Automation log not found', [
                'log_id' => $logId,
            ]);

            return Command::FAILURE;
        }

        // Safeguard: Only process logs that haven't sent emails yet
        if ($automationLog->email_sent) {
            Log::info('ProcessAutomationLog: Email already sent for this log', [
                'log_id' => $logId,
                'email_sent' => $automationLog->email_sent,
            ]);

            return Command::SUCCESS;
        }

        // Safeguard: Check if this log is already being processed (prevent duplicate processing)
        $processingKey = 'automation_log_processing_'.$logId;
        if (cache()->has($processingKey)) {
            Log::info('ProcessAutomationLog: Log is already being processed', [
                'log_id' => $logId,
                'processing_key' => $processingKey,
            ]);

            return Command::SUCCESS;
        }

        // Mark as processing for 5 minutes to prevent duplicates
        cache([$processingKey => true], now()->addMinutes(5));

        // Check if this log has email data to send
        if (empty($automationLog->email)) {
            Log::warning('ProcessAutomationLog: No email data found in log', [
                'log_id' => $logId,
                'email_field' => $automationLog->email,
            ]);

            return Command::SUCCESS;
        }

        try {
            // Prepare email data based on log type (onboarding vs lead)
            if ($automationLog->onboarding_id) {
                $this->processOnboardingEmail($automationLog);
            } elseif ($automationLog->lead_id) {
                $this->processLeadEmail($automationLog);
            } else {
                Log::warning('ProcessAutomationLog: Log has no onboarding_id or lead_id', [
                    'log_id' => $logId,
                ]);

                return Command::SUCCESS;
            }

            Log::info('ProcessAutomationLog: Successfully processed automation log', [
                'log_id' => $logId,
            ]);

            // Clean up processing lock
            cache()->forget($processingKey);

            return Command::SUCCESS;

        } catch (\Throwable $th) {
            Log::error('ProcessAutomationLog: Failed to process automation log', [
                'log_id' => $logId,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            // Clean up processing lock on error
            cache()->forget($processingKey);

            return Command::FAILURE;
        }
    }

    /**
     * Process email for onboarding automation log
     */
    private function processOnboardingEmail(AutomationActionLog $log)
    {
        $onboarding = OnboardingEmployees::find($log->onboarding_id);

        if (! $onboarding) {
            Log::warning('ProcessAutomationLog: Onboarding employee not found', [
                'log_id' => $log->id,
                'onboarding_id' => $log->onboarding_id,
            ]);

            return;
        }

        // Prepare email data for AutomationMail job
        $emailData = [
            'automation_log_id' => $log->id,
            'onboarding_name' => $onboarding->first_name.' '.$onboarding->last_name,
            'recipient_name' => $onboarding->first_name.' '.$onboarding->last_name,
            'send_to' => $log->email, // Email addresses from log
            'mailMessage' => 'Onboarding status update notification',
        ];

        Log::info('ProcessAutomationLog: Dispatching onboarding email', [
            'log_id' => $log->id,
            'onboarding_id' => $log->onboarding_id,
            'send_to' => $log->email,
        ]);

        // Dispatch email job with log ID for status tracking
        AutomationMail::dispatch($emailData);
    }

    /**
     * Process email for lead automation log
     */
    private function processLeadEmail(AutomationActionLog $log)
    {
        $lead = Lead::find($log->lead_id);

        if (! $lead) {
            Log::warning('ProcessAutomationLog: Lead not found', [
                'log_id' => $log->id,
                'lead_id' => $log->lead_id,
            ]);

            return;
        }

        // Prepare email data for AutomationMail job
        $emailData = [
            'automation_log_id' => $log->id,
            'lead_name' => $lead->first_name.' '.$lead->last_name,
            'recipient_name' => $lead->first_name.' '.$lead->last_name,
            'send_to' => $log->email, // Email addresses from log
            'mailMessage' => 'Lead status update notification',
        ];

        Log::info('ProcessAutomationLog: Dispatching lead email', [
            'log_id' => $log->id,
            'lead_id' => $log->lead_id,
            'send_to' => $log->email,
        ]);

        // Dispatch email job with log ID for status tracking
        AutomationMail::dispatch($emailData);
    }
}
