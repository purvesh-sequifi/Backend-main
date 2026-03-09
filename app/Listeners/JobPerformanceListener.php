<?php

namespace App\Listeners;

use App\Models\JobPerformanceLog;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobPerformanceListener
{
    /**
     * Store performance logs temporarily
     */
    private static $performanceLogs = [];

    /**
     * Handle job processing (when job starts)
     */
    public function handleJobProcessing(JobProcessing $event)
    {
        try {
            $payload = json_decode($event->job->getRawBody(), true);
            $jobClass = $payload['displayName'] ?? 'Unknown';
            $jobId = $payload['id'] ?? Str::uuid();

            $performanceLog = JobPerformanceLog::logJobStart(
                $jobId,
                $jobClass,
                $event->job->getQueue(),
                $event->connectionName,
                $payload
            );

            // Store the log ID for later reference
            self::$performanceLogs[$event->job->getJobId()] = $performanceLog->id;

        } catch (\Exception $e) {
            Log::error('Failed to log job start: '.$e->getMessage(), [
                'job_id' => $event->job->getJobId(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
            ]);
        }
    }

    /**
     * Handle job processed (when job completes successfully)
     */
    public function handleJobProcessed(JobProcessed $event)
    {
        try {
            $jobId = $event->job->getJobId();

            if (isset(self::$performanceLogs[$jobId])) {
                $performanceLog = JobPerformanceLog::find(self::$performanceLogs[$jobId]);

                if ($performanceLog) {
                    $performanceLog->logJobCompletion();
                }

                // Clean up temporary storage
                unset(self::$performanceLogs[$jobId]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log job completion: '.$e->getMessage(), [
                'job_id' => $event->job->getJobId(),
            ]);
        }
    }

    /**
     * Handle job failed (when job fails)
     */
    public function handleJobFailed(JobFailed $event)
    {
        try {
            $jobId = $event->job->getJobId();
            $errorMessage = $event->exception ? $event->exception->getMessage() : 'Unknown error';
            $performanceLogId = null;

            if (isset(self::$performanceLogs[$jobId])) {
                $performanceLog = JobPerformanceLog::find(self::$performanceLogs[$jobId]);

                if ($performanceLog) {
                    $performanceLog->logJobFailure($errorMessage);
                    $performanceLogId = $performanceLog->id;
                }

                // Clean up temporary storage
                unset(self::$performanceLogs[$jobId]);
            } else {
                // If we don't have a started log, create a failed log
                $payload = json_decode($event->job->getRawBody(), true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $jobUuid = $payload['id'] ?? Str::uuid();

                $performanceLog = JobPerformanceLog::create([
                    'job_id' => $jobUuid,
                    'job_class' => $jobClass,
                    'queue' => $event->job->getQueue(),
                    'connection' => $event->connectionName,
                    'payload' => $payload,
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $errorMessage,
                    'worker_pid' => getmypid(),
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                $performanceLogId = $performanceLog->id;
            }

            // Create detailed failure information
            $this->createDetailedFailureLog($event, $performanceLogId);

        } catch (\Exception $e) {
            Log::error('Failed to log job failure: '.$e->getMessage(), [
                'job_id' => $event->job->getJobId(),
                'error' => $event->exception ? $event->exception->getMessage() : 'Unknown',
            ]);
        }
    }

    /**
     * Create detailed failure log for enhanced monitoring
     */
    private function createDetailedFailureLog(JobFailed $event, $performanceLogId = null)
    {
        try {
            // Get the failed job UUID from the database
            $failedJobUuid = $this->getFailedJobUuid($event->job);

            if (! $failedJobUuid) {
                Log::warning('Could not find failed job UUID for detailed logging', [
                    'job_id' => $event->job->getJobId(),
                    'queue' => $event->job->getQueue(),
                ]);

                return;
            }

            // Parse job payload
            $payload = json_decode($event->job->getRawBody(), true);
            $jobClass = $payload['displayName'] ?? 'Unknown';

            // Prepare job data for detailed logging
            $jobData = [
                'job_class' => $jobClass,
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'job_id' => $payload['id'] ?? null,
                'payload' => $payload,
                'attempts' => $event->job->attempts(),
                'max_tries' => $this->getMaxTries($event->job),
                'timeout' => $this->getTimeout($event->job),
            ];

            // Create or update detailed failure record
            \App\Models\FailedJobDetails::createOrUpdateFromFailure(
                $failedJobUuid,
                $event->exception,
                $jobData,
                $performanceLogId
            );

        } catch (\Exception $e) {
            Log::error('Failed to create detailed failure log: '.$e->getMessage(), [
                'job_id' => $event->job->getJobId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get the failed job UUID from the database
     */
    private function getFailedJobUuid($job)
    {
        try {
            $payload = json_decode($job->getRawBody(), true);
            $jobId = $payload['id'] ?? null;

            if (! $jobId) {
                return null;
            }

            // Wait a moment for the failed job to be inserted into the database
            // Laravel inserts failed jobs after the JobFailed event is fired
            sleep(1);

            // First try to find by job ID in the payload
            $failedJob = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('payload', 'like', '%"id":"'.$jobId.'"%')
                ->orderBy('failed_at', 'desc')
                ->first();

            if ($failedJob) {
                return $failedJob->uuid;
            }

            // If not found by ID, try to find by job class and queue
            $jobClass = $payload['displayName'] ?? null;
            $queue = $job->getQueue();

            if ($jobClass && $queue) {
                $failedJob = \Illuminate\Support\Facades\DB::table('failed_jobs')
                    ->where('queue', $queue)
                    ->where('payload', 'like', '%"displayName":"'.$jobClass.'"%')
                    ->orderBy('failed_at', 'desc')
                    ->first();

                if ($failedJob) {
                    return $failedJob->uuid;
                }
            }

            // Last resort: get the most recent failed job on this queue
            $failedJob = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->where('queue', $queue)
                ->orderBy('failed_at', 'desc')
                ->first();

            return $failedJob ? $failedJob->uuid : null;

        } catch (\Exception $e) {
            Log::error('Error getting failed job UUID: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get max tries for a job
     */
    private function getMaxTries($job)
    {
        try {
            $payload = json_decode($job->getRawBody(), true);

            return $payload['maxTries'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get timeout for a job
     */
    private function getTimeout($job)
    {
        try {
            $payload = json_decode($job->getRawBody(), true);

            return $payload['timeout'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
