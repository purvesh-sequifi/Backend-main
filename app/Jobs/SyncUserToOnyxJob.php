<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\OnyxRepDataPushService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for syncing user data to Onyx webhook
 *
 * Used for background processing of user syncs to prevent timeouts
 * and enable parallel processing of large bulk operations.
 *
 * Supports batch dispatching for better tracking and management.
 */
class SyncUserToOnyxJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param int $userId - The user ID to sync
     * @param string $eventType - The event type: 'new_rep' or 'rep_update'
     */
    public function __construct(
        public int $userId,
        public string $eventType = 'rep_update'
    ) {}

    /**
     * Execute the job.
     *
     * @param OnyxRepDataPushService $onyxService
     * @return void
     */
    public function handle(OnyxRepDataPushService $onyxService): void
    {
        try {
            Log::info('SyncUserToOnyxJob: Starting sync', [
                'user_id' => $this->userId,
                'event_type' => $this->eventType,
                'attempt' => $this->attempts()
            ]);

            // Pass true for $isBulk to indicate this is from bulk sync
            $response = $onyxService->sendUserData($this->userId, $this->eventType, null, true);

            if ($response['status'] ?? false) {
                Log::info('SyncUserToOnyxJob: Sync successful', [
                    'user_id' => $this->userId,
                    'event_type' => $this->eventType
                ]);
            } else {
                Log::warning('SyncUserToOnyxJob: Sync failed', [
                    'user_id' => $this->userId,
                    'event_type' => $this->eventType,
                    'message' => $response['message'] ?? 'Unknown error'
                ]);

                // Release back to queue for retry if attempts remaining
                if ($this->attempts() < $this->tries) {
                    $this->release(60); // Retry after 60 seconds
                }
            }

        } catch (\Exception $e) {
            Log::error('SyncUserToOnyxJob: Exception occurred', [
                'user_id' => $this->userId,
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Release back to queue for retry if attempts remaining
            if ($this->attempts() < $this->tries) {
                $this->release(60); // Retry after 60 seconds
            } else {
                // Max attempts reached, fail the job
                $this->fail($e);
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncUserToOnyxJob: Job failed permanently', [
            'user_id' => $this->userId,
            'event_type' => $this->eventType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
