<?php

namespace App\Core\Traits;

use App\Models\JobProgressLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

trait JobMonitoring
{
    /**
     * The unique identifier for this job instance
     */
    protected $jobMonitorId;

    /**
     * Initialize job monitoring
     */
    protected function initializeJobMonitoring()
    {
        $this->jobMonitorId = (string) Str::uuid();

        // Set initial status in database
        $this->logJobProgress([
            'job_class' => get_class($this),
            'queue' => $this->getQueueName(),
            'status' => 'queued',
            'type' => property_exists($this, 'type') ? $this->type : null,
            'progress_percentage' => 0,
            'message' => 'Job queued',
            'started_at' => now(), // Set started_at immediately to ensure it's captured
        ]);

        return $this->jobMonitorId;
    }

    /**
     * Mark the job as started
     */
    protected function markJobStarted($totalRecords = null)
    {
        $this->logJobProgress([
            'status' => 'processing',
            'total_records' => $totalRecords,
            'started_at' => now(),
            'message' => 'Job processing started',
        ]);
    }

    /**
     * Update job progress
     */
    protected function updateJobProgress($currentOperation, $processedRecords = null, $lastRecordId = null, $additionalMetadata = [])
    {
        $percentage = 0; // Default to 0 instead of null

        // Calculate percentage if we know total records
        $jobProgress = JobProgressLog::where('job_id', $this->jobMonitorId)->first();

        if ($jobProgress) {
            // If we have total records and processed records, calculate percentage
            if ($jobProgress->total_records && $processedRecords) {
                $percentage = min(99, round(($processedRecords / $jobProgress->total_records) * 100));
            }
            // If processedRecords is provided but total_records is not in the database yet
            elseif ($processedRecords && isset($additionalMetadata['total_records'])) {
                $percentage = min(99, round(($processedRecords / $additionalMetadata['total_records']) * 100));
            }
            // Use the previous percentage if not calculable
            elseif ($jobProgress->progress_percentage) {
                $percentage = $jobProgress->progress_percentage;
            }
        }

        $this->logJobProgress([
            'current_operation' => $currentOperation,
            'processed_records' => $processedRecords,
            'last_record_identifier' => $lastRecordId,
            'progress_percentage' => $percentage ?? $jobProgress->progress_percentage ?? 0,
            'metadata' => array_merge($jobProgress->metadata ?? [], $additionalMetadata),
        ]);

        // Log to file as well for real-time monitoring
        Log::info("Job {$this->jobMonitorId} progress: {$currentOperation}", [
            'job_id' => $this->jobMonitorId,
            'processed' => $processedRecords,
            'last_id' => $lastRecordId,
        ]);
    }

    /**
     * Mark job as completed
     */
    protected function markJobCompleted($message = 'Job completed successfully', $additionalMetadata = [])
    {
        $this->logJobProgress([
            'status' => 'completed',
            'progress_percentage' => 100,
            'message' => $message,
            'completed_at' => now(),
            'metadata' => $additionalMetadata,
        ]);
    }

    /**
     * Mark job as partially completed
     *
     * @param  int  $completedRecords  Number of records that were successfully processed
     * @param  string  $message  Message explaining partial completion
     * @param  array  $additionalMetadata  Additional metadata to store
     */
    protected function markJobPartiallyCompleted(int $completedRecords, string $message = 'Job partially completed', array $additionalMetadata = [])
    {
        // Calculate percentage based on completed vs total records
        $percentage = 0;
        $jobProgress = JobProgressLog::where('job_id', $this->jobMonitorId)->first();

        if ($jobProgress && $jobProgress->total_records && $completedRecords) {
            $percentage = min(99, round(($completedRecords / $jobProgress->total_records) * 100));
        }

        $this->logJobProgress([
            'status' => 'partially_completed',
            'progress_percentage' => $percentage,
            'completed_records' => $completedRecords,
            'processed_records' => $completedRecords,
            'message' => $message,
            'completed_at' => now(),
            'metadata' => $additionalMetadata,
        ]);
    }

    /**
     * Hide this job from monitoring
     *
     * @return bool Success indicator
     */
    protected function hideJobFromMonitoring(): bool
    {
        return $this->logJobProgress([
            'is_hidden' => true,
        ]);
    }

    /**
     * Mark job as failed
     */
    protected function markJobFailed(Throwable $exception)
    {
        $this->logJobProgress([
            'status' => 'failed',
            'message' => 'Job failed: '.$exception->getMessage(),
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the queue name this job is dispatched to
     */
    private function getQueueName()
    {
        // Check if queue property exists and is set
        if (property_exists($this, 'queue') && ! empty($this->queue)) {
            return $this->queue;
        }

        // Check connection configuration for default queue
        $connection = property_exists($this, 'connection') ? $this->connection : config('queue.default');
        $defaultQueue = config("queue.connections.{$connection}.queue", 'default');

        // If we're dispatched with onQueue but haven't stored it yet
        // We're returning 'parlley' as fallback since that's what we expect in this application
        return 'parlley';
    }

    /**
     * Log job progress to database with retry mechanism
     */
    private function logJobProgress(array $data)
    {
        if (! isset($this->jobMonitorId)) {
            $this->jobMonitorId = (string) Str::uuid();
        }

        $maxRetries = 3;
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                return JobProgressLog::updateProgress($this->jobMonitorId, $data);
            } catch (\Exception $e) {
                $lastException = $e;
                // Check if it's a lock timeout error
                if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false) {
                    $retryCount++;
                    // Log that we're retrying
                    \Illuminate\Support\Facades\Log::warning("Lock timeout encountered when logging job progress. Retrying ({$retryCount}/{$maxRetries})");
                    // Wait with exponential backoff before retrying
                    usleep(100000 * (2 ** $retryCount)); // 100ms, 200ms, 400ms

                    continue;
                }
                // For other exceptions, rethrow
                throw $e;
            }
        }

        // If we've exhausted retries, log and rethrow the last exception
        \Illuminate\Support\Facades\Log::error("Failed to log job progress after {$maxRetries} retries", [
            'job_id' => $this->jobMonitorId,
            'error' => $lastException->getMessage(),
        ]);

        // Return false instead of throwing to prevent job failure due to monitoring issues
        return false;
    }
}
