<?php

namespace App\Models\observers;

use App\Jobs\LegacyLogsSyncJob;
use App\Jobs\SyncLegacyLogsOnUserChangeJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if (! config('legacy_logs_sync.enabled')) {
            return;
        }

        $emails = array_filter([
            strtolower((string) $user->email),
            strtolower((string) $user->work_email),
        ]);

        Log::info('[LegacyLogsSync] User created trigger', [
            'user_id' => $user->id,
            'emails' => $emails,
        ]);

        LegacyLogsSyncJob::dispatch($user->id, $emails)
            ->onQueue(config('legacy_logs_sync.queue'));

        // Also dispatch backfill job to update closer1_id and enqueue SaleMasterJob
        $payload = [
            'new_email' => $user->email,
            'new_work_email' => $user->work_email,
        ];
        if (! empty($user->created_at)) {
            $payload['new_hire_at'] = $user->created_at;
        }
        SyncLegacyLogsOnUserChangeJob::dispatch($user->id, 'user_created', $payload);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if (! config('legacy_logs_sync.enabled')) {
            return;
        }

        $watched = (array) config('legacy_logs_sync.trigger_fields', []);
        $dirty = [];
        foreach ($watched as $field) {
            if ($user->wasChanged($field)) {
                $dirty[] = $field;
            }
        }

        if (empty($dirty)) {
            return; // nothing relevant changed
        }

        // Collect current and previous emails for matching legacy rows
        $emails = [];
        foreach (['email', 'work_email'] as $k) {
            $cur = strtolower((string) ($user->{$k} ?? ''));
            $old = strtolower((string) ($user->getOriginal($k) ?? ''));
            if ($cur) {
                $emails[] = $cur;
            }
            if ($old) {
                $emails[] = $old;
            }
        }
        $emails = array_values(array_filter(array_unique($emails)));

        Log::info('[LegacyLogsSync] User updated trigger', [
            'user_id' => $user->id,
            'dirty_fields' => $dirty,
            'emails' => $emails,
        ]);

        LegacyLogsSyncJob::dispatch($user->id, $emails)
            ->onQueue(config('legacy_logs_sync.queue'));

        // Dispatch backfill job with specific payloads
        $payload = [
            'new_email' => $user->email,
            'old_email' => $user->getOriginal('email'),
            'new_work_email' => $user->work_email,
            'old_work_email' => $user->getOriginal('work_email'),
        ];
        // If created_at changed, include new hire_at
        if ($user->wasChanged('created_at') && ! empty($user->created_at)) {
            $payload['new_hire_at'] = $user->created_at;
        }
        SyncLegacyLogsOnUserChangeJob::dispatch($user->id, 'user_updated', $payload);
    }
}
