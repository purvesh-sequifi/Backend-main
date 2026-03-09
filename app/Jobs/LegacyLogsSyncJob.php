<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LegacyLogsQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LegacyLogsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public int $userId,
        public array $emails = []
    ) {
        $this->onQueue(config('legacy_logs_sync.queue', 'default'));
        $this->tries = (int) config('legacy_logs_sync.job_attempts', 3);
        $this->backoff = (int) config('legacy_logs_sync.job_backoff', 60);
    }

    public function handle(LegacyLogsQueryService $service): void
    {
        if (! config('legacy_logs_sync.enabled')) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            Log::warning('[LegacyLogsSync] User not found for job', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        // Ensure emails include current known ones
        $emails = $service->collectUserEmails($user, $this->emails);

        $result = $service->queryForUser($user, $emails);

        Log::info('[LegacyLogsSync] Query results', $result);
    }
}
