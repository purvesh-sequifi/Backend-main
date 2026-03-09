<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\JobPerformanceMetric;
use App\Models\JobChunkMetric;
use App\Services\JobPerformanceTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchCompletionCheckerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 10; // Retry up to 10 times

    protected string $batchId;

    public function __construct(string $batchId)
    {
        $this->batchId = $batchId;
    }

    public function handle(): void
    {
        $jobMetric = JobPerformanceMetric::where('batch_id', $this->batchId)->first();
        
        if (!$jobMetric) {
            Log::warning("BatchCompletionChecker: Job metric not found for batch {$this->batchId}");
            return;
        }

        // Check if batch is already completed
        if (in_array($jobMetric->status, ['completed', 'failed'])) {
            Log::info("BatchCompletionChecker: Batch {$this->batchId} already completed with status: {$jobMetric->status}");
            return;
        }

        // Get all chunks for this batch
        $chunks = JobChunkMetric::where('batch_id', $this->batchId)->get();
        $totalChunks = $jobMetric->total_chunks;
        $completedChunks = $chunks->whereIn('status', ['completed', 'failed'])->count();

        Log::info("BatchCompletionChecker: Checking batch {$this->batchId}", [
            'total_chunks' => $totalChunks,
            'completed_chunks' => $completedChunks,
            'chunks_found' => $chunks->count()
        ]);

        // If all chunks are completed, mark the batch as complete
        if ($completedChunks >= $totalChunks && $chunks->count() >= $totalChunks) {
            $tracker = new JobPerformanceTracker();
            $tracker->completeBatch($this->batchId);
            
            Log::info("BatchCompletionChecker: Batch {$this->batchId} marked as completed", [
                'total_chunks' => $totalChunks,
                'completed_chunks' => $completedChunks
            ]);
        } else {
            // If not all chunks are completed, reschedule this job to check again
            $delay = min($this->attempts() * 30, 300); // Exponential backoff, max 5 minutes
            
            self::dispatch($this->batchId)
                ->delay(now()->addSeconds($delay))
                ->onQueue('default');
                
            Log::info("BatchCompletionChecker: Rescheduling check for batch {$this->batchId}", [
                'delay_seconds' => $delay,
                'attempt' => $this->attempts(),
                'completed_chunks' => $completedChunks,
                'total_chunks' => $totalChunks
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("BatchCompletionChecker failed for batch {$this->batchId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
