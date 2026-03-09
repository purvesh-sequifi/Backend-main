<?php

namespace App\Models\observers;

use App\Jobs\SyncLegacyLogsOnUserChangeJob;
use App\Models\UsersAdditionalEmail;
use Illuminate\Support\Facades\Log;

class UsersAdditionalEmailObserver
{
    public function created(UsersAdditionalEmail $record): void
    {
        $this->dispatchJob($record, 'additional_email_created', [
            'new_email' => $record->email,
        ]);
    }

    public function updated(UsersAdditionalEmail $record): void
    {
        $payload = [];
        if ($record->wasChanged('email')) {
            $payload['old_email'] = $record->getOriginal('email');
            $payload['new_email'] = $record->email;
        }
        if (! empty($payload)) {
            $this->dispatchJob($record, 'additional_email_updated', $payload);
        }
    }

    // public function deleted(UsersAdditionalEmail $record): void
    // {
    //     $this->dispatchJob($record, 'additional_email_deleted', [
    //         'old_email' => $record->email,
    //     ]);
    // }

    protected function dispatchJob(UsersAdditionalEmail $record, string $event, array $payload = []): void
    {
        try {
            if ($record->user_id) {
                SyncLegacyLogsOnUserChangeJob::dispatch($record->user_id, $event, $payload);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed dispatching SyncLegacyLogsOnUserChangeJob from UsersAdditionalEmailObserver', [
                'user_id' => $record->user_id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
