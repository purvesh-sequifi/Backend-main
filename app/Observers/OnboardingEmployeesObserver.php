<?php

namespace App\Observers;

use App\Models\OnboardingEmployees;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingEmployeesObserver
{
    /**
     * Handle the OnboardingEmployees "created" event.
     */
    public function created(OnboardingEmployees $onboardingEmployees): void
    {
        //
    }

    /**
     * Handle the OnboardingEmployees "updating" event.
     */
    public function updating(OnboardingEmployees $onboardingEmployees): void
    {
        if ($onboardingEmployees->isDirty('status_id')) {
            // Save old value before update
            $onboardingEmployees->old_status_id = $onboardingEmployees->getOriginal('status_id');

            Log::info('OnboardingEmployeesObserver: Status change tracked', [
                'onboarding_id' => $onboardingEmployees->id,
                'old_status_id' => $onboardingEmployees->getOriginal('status_id'),
                'new_status_id' => $onboardingEmployees->status_id,
            ]);
        }
    }

    /**
     * Handle the OnboardingEmployees "updated" event.
     */
    public function updated(OnboardingEmployees $onboardingEmployees): void
    {
        if ($onboardingEmployees->wasChanged('status_id')) {
            // Trigger automation immediately after status change is saved
            $this->triggerAutomationForStatusChange($onboardingEmployees);
        }
    }

    /**
     * Handle the OnboardingEmployees "deleted" event.
     */
    public function deleted(OnboardingEmployees $onboardingEmployees): void
    {
        //
    }

    /**
     * Handle the OnboardingEmployees "restored" event.
     */
    public function restored(OnboardingEmployees $onboardingEmployees): void
    {
        //
    }

    /**
     * Handle the OnboardingEmployees "force deleted" event.
     */
    public function forceDeleted(OnboardingEmployees $onboardingEmployees): void
    {
        //
    }

    /**
     * Trigger automation for status change with safeguards
     */
    protected function triggerAutomationForStatusChange($model)
    {
        try {
            // Safeguard 1: Rate limiting (max 5 automation triggers per minute)
            $rateLimitKey = 'automation_trigger_count_'.now()->format('Y-m-d_H:i');
            $currentCount = cache($rateLimitKey, 0);

            if ($currentCount >= 5) {
                Log::warning('OnboardingEmployeesObserver: Automation rate limit exceeded - deferring to cron', [
                    'onboarding_id' => $model->id,
                    'current_count' => $currentCount,
                    'rate_limit' => 5,
                ]);

                return;
            }

            // Increment rate limit counter
            cache([$rateLimitKey => $currentCount + 1], now()->addMinutes(2));

            // Safeguard 2: Only trigger for important status changes (avoid draft/temp statuses)
            $importantStatuses = [1, 4, 7, 12, 14, 16, 17, 22, 23, 24]; // Accepted, Offer Letter Sent, Onboarding, Offer Letter Resent, Active, Document Review, Offer Review, Offer Letter Accepted Rest Pending, Offer Letter Pending Rest Completed, Offer Letter Sent Pending

            if (! in_array($model->status_id, $importantStatuses)) {
                Log::debug('OnboardingEmployeesObserver: Skipping automation for non-important status', [
                    'onboarding_id' => $model->id,
                    'status_id' => $model->status_id,
                    'important_statuses' => $importantStatuses,
                ]);

                return;
            }

            // Safeguard 3: Avoid triggering for bulk operations (check if this is part of a larger transaction)
            // if (DB::transactionLevel() > 0) {
            //     Log::debug('OnboardingEmployeesObserver: Skipping automation during database transaction', [
            //         'onboarding_id' => $model->id,
            //         'transaction_level' => DB::transactionLevel()
            //     ]);
            //     return;
            // }

            Log::info('OnboardingEmployeesObserver: Triggering real-time automation for status change', [
                'onboarding_id' => $model->id,
                'old_status_id' => $model->getOriginal('status_id'),
                'new_status_id' => $model->status_id,
                'trigger_count' => $currentCount + 1,
                'trigger_type' => 'Real-time status change',
            ]);

            // Safeguard 4: Use queue for background processing to avoid blocking the main request
            try {
                Artisan::queue('automation:run', [
                    '--onboarding-id' => $model->id,
                ]);

                Log::info('OnboardingEmployeesObserver: Automation queued successfully', [
                    'onboarding_id' => $model->id,
                ]);

            } catch (\Throwable $queueException) {
                // Fallback: Direct call if queue fails
                Log::warning('OnboardingEmployeesObserver: Queue failed, trying direct automation call', [
                    'onboarding_id' => $model->id,
                    'queue_error' => $queueException->getMessage(),
                ]);

                try {
                    Artisan::call('automation:run');

                    Log::info('OnboardingEmployeesObserver: Direct automation call succeeded', [
                        'onboarding_id' => $model->id,
                    ]);

                } catch (\Throwable $directException) {
                    Log::error('OnboardingEmployeesObserver: Both queue and direct automation calls failed', [
                        'onboarding_id' => $model->id,
                        'queue_error' => $queueException->getMessage(),
                        'direct_error' => $directException->getMessage(),
                    ]);
                }
            }

        } catch (\Throwable $e) {
            Log::error('OnboardingEmployeesObserver: Failed to trigger automation for status change', [
                'onboarding_id' => $model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
