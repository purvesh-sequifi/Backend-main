<?php

namespace App\Observers;

use App\Models\AutomationActionLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AutomationActionLogObserver
{
    /**
     * Handle the AutomationActionLog "created" event.
     */
    public function created(AutomationActionLog $automationActionLog): void
    {
        // Safeguard 1: Only trigger for logs with emails to send and not already processed
        $shouldProcess = (
            ($automationActionLog->status == 0 || $automationActionLog->email_sent == false) &&
            ! empty($automationActionLog->email) &&
            ($automationActionLog->onboarding_id || $automationActionLog->lead_id)
        );

        if (! $shouldProcess) {
            Log::debug('AutomationActionLogObserver: Skipping automation log - does not meet processing criteria', [
                'log_id' => $automationActionLog->id,
                'status' => $automationActionLog->status,
                'email_sent' => $automationActionLog->email_sent,
                'has_email' => ! empty($automationActionLog->email),
                'has_target' => ($automationActionLog->onboarding_id || $automationActionLog->lead_id),
            ]);

            return;
        }

        // Safeguard 2: Rate limiting to prevent spam (max 10 logs per minute)
        $cacheKey = 'automation_log_processing_count_'.now()->format('Y-m-d_H-i');
        $currentCount = cache($cacheKey, 0);

        if ($currentCount >= 10) {
            Log::warning('AutomationActionLogObserver: Rate limit exceeded - deferring to cron processing', [
                'log_id' => $automationActionLog->id,
                'current_count' => $currentCount,
                'rate_limit' => 10,
            ]);

            return;
        }

        // Increment rate limit counter
        cache([$cacheKey => $currentCount + 1], now()->addMinutes(2));

        Log::info('AutomationActionLogObserver: New automation log created, triggering follow-up processing', [
            'log_id' => $automationActionLog->id,
            'automation_rule_id' => $automationActionLog->automation_rule_id,
            'onboarding_id' => $automationActionLog->onboarding_id,
            'lead_id' => $automationActionLog->lead_id,
            'status' => $automationActionLog->status,
            'email_sent' => $automationActionLog->email_sent,
            'processing_count' => $currentCount + 1,
            'trigger_reason' => 'Event-driven automation processing',
        ]);

        try {
            // Safeguard 3: Background processing to prevent blocking main thread
            Artisan::queue('automation:process-log', [
                'log_id' => $automationActionLog->id,
            ]);

            Log::info('AutomationActionLogObserver: Successfully queued automation processing', [
                'log_id' => $automationActionLog->id,
            ]);

        } catch (\Throwable $th) {
            Log::error('AutomationActionLogObserver: Failed to queue automation processing', [
                'log_id' => $automationActionLog->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            // Fallback: Try direct call if queue fails
            try {
                Artisan::call('automation:process-log', [
                    'log_id' => $automationActionLog->id,
                ]);

                Log::info('AutomationActionLogObserver: Fallback direct call succeeded', [
                    'log_id' => $automationActionLog->id,
                ]);

            } catch (\Throwable $fallbackException) {
                Log::error('AutomationActionLogObserver: Both queue and direct call failed', [
                    'log_id' => $automationActionLog->id,
                    'queue_error' => $th->getMessage(),
                    'direct_error' => $fallbackException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the AutomationActionLog "updated" event.
     */
    public function updated(AutomationActionLog $automationActionLog): void
    {
        // Log when email status changes for monitoring
        if ($automationActionLog->isDirty('email_sent')) {
            Log::info('AutomationActionLogObserver: Email status updated', [
                'log_id' => $automationActionLog->id,
                'email_sent' => $automationActionLog->email_sent,
                'email' => $automationActionLog->email,
                'previous_status' => $automationActionLog->getOriginal('email_sent'),
            ]);
        }
    }
}
