<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\EspQuickBaseService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for syncing user data to ESP QuickBase
 *
 * Used for background processing of user syncs to prevent timeouts
 * and enable parallel processing of large bulk operations.
 *
 * Supports batch dispatching for better tracking and management.
 */
class SyncUserToEspJob implements ShouldQueue
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
     */
    public function __construct(
        public int $userId
    ) {}

    /**
     * Execute the job.
     *
     * @param EspQuickBaseService $espService
     * @return void
     */
    public function handle(EspQuickBaseService $espService): void
    {
        try {
            Log::info('SyncUserToEspJob: Starting sync', [
                'user_id' => $this->userId,
                'attempt' => $this->attempts()
            ]);

            $response = $espService->sendUserData($this->userId, 'from_job_bulk');

            if ($response['status'] ?? false) {
                Log::info('SyncUserToEspJob: Sync successful', [
                    'user_id' => $this->userId
                ]);
            } else {
                Log::warning('SyncUserToEspJob: Sync failed', [
                    'user_id' => $this->userId,
                    'message' => $response['message'] ?? 'Unknown error'
                ]);

                // Release back to queue for retry if attempts remaining
                if ($this->attempts() < $this->tries) {
                    $this->release(60); // Retry after 60 seconds
                }
            }

        } catch (\Exception $e) {
            Log::error('SyncUserToEspJob: Exception occurred', [
                'user_id' => $this->userId,
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
        Log::error('SyncUserToEspJob: Job failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
