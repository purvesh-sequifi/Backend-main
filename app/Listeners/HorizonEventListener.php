<?php

namespace App\Listeners;

use App\Notifications\HorizonJobAlert;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Notification;

class HorizonEventListener
{
    /**
     * Handle job processing.
     */
    public function handleProcessing(JobProcessing $event)
    {
        // Monitor long-running jobs (if needed)
    }

    /**
     * Handle job processed.
     * 
     * Note: In Octane/Swoole, LARAVEL_START is per-worker, not per-job.
     * Using it would accumulate time across all jobs, causing false alerts.
     * The JobProcessed event doesn't provide individual job timing,
     * so we'll disable timing-based alerts for now to prevent spam.
     */
    public function handleProcessed(JobProcessed $event)
    {
        // Timing-based alerts disabled for Octane compatibility
        // LARAVEL_START represents worker start time, not job start time
        // This would cause false positives as time accumulates
        
        // If you need job timing alerts, use Horizon's built-in metrics instead:
        // - Configure 'waits' thresholds in config/horizon.php
        // - Monitor via Horizon dashboard
        // - Horizon tracks actual job execution time properly
    }

    /**
     * Handle job failure.
     */
    public function handleFailed(JobFailed $event)
    {
        $webhook = config('services.slack.webhook_url');

        if (! $webhook) {
            \Log::warning('Slack notifications disabled - no webhook configured');

            return;
        }

        try {
            Notification::route('slack', $webhook)
                ->notify(new HorizonJobAlert(
                    $event->job->resolveName(),
                    'failed',
                    $event->exception
                ));
        } catch (\Exception $e) {
            \Log::error('Slack notification failed', [
                'error' => $e->getMessage(),
                'job' => $event->job->resolveName(),
            ]);
        }
    }
}
