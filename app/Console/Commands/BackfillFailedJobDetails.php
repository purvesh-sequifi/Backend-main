<?php

namespace App\Console\Commands;

use App\Models\FailedJobDetails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFailedJobDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:backfill-failed-job-details {--limit=100 : Number of failed jobs to process} {--force : Force processing even if details already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill failed job details for existing failed jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit');
        $force = $this->option('force');

        $this->info('Starting backfill of failed job details...');

        // Get failed jobs that don't have details yet (or all if force is used)
        $query = DB::table('failed_jobs')
            ->select('id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at')
            ->orderBy('failed_at', 'desc')
            ->limit($limit);

        if (! $force) {
            $query->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('failed_job_details')
                    ->whereRaw('failed_job_details.failed_job_uuid = failed_jobs.uuid');
            });
        }

        $failedJobs = $query->get();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed jobs found to process.');

            return 0;
        }

        $this->info("Processing {$failedJobs->count()} failed jobs...");

        $processed = 0;
        $errors = 0;

        foreach ($failedJobs as $failedJob) {
            try {
                $this->info("Processing job {$failedJob->uuid}...");

                // Parse the payload to get job information
                $payload = json_decode($failedJob->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $jobId = $payload['id'] ?? null;

                // Create a mock exception from the stored exception
                $exception = new \Exception($this->extractExceptionMessage($failedJob->exception));

                // Prepare job data
                $jobData = [
                    'job_class' => $jobClass,
                    'queue' => $failedJob->queue,
                    'connection' => $failedJob->connection,
                    'job_id' => $jobId,
                    'payload' => $payload,
                    'attempts' => $payload['attempts'] ?? 1,
                    'max_tries' => $payload['maxTries'] ?? null,
                    'timeout' => $payload['timeout'] ?? null,
                ];

                // Create or update failed job details
                $failedJobDetails = FailedJobDetails::createOrUpdateFromFailure(
                    $failedJob->uuid,
                    $exception,
                    $jobData
                );

                if ($failedJobDetails) {
                    $this->info("✓ Created details for job {$failedJob->uuid}");
                    $processed++;
                } else {
                    $this->error("✗ Failed to create details for job {$failedJob->uuid}");
                    $errors++;
                }

            } catch (\Exception $e) {
                $this->error("✗ Error processing job {$failedJob->uuid}: ".$e->getMessage());
                $errors++;
            }
        }

        $this->info("\n=== Backfill Complete ===");
        $this->info("Processed: {$processed}");
        $this->info("Errors: {$errors}");

        return 0;
    }

    /**
     * Extract the main exception message from the stored exception string
     */
    private function extractExceptionMessage($exceptionString)
    {
        // The exception string usually starts with the exception class and message
        // Example: "App\Jobs\SomeJob: Some error message"
        $lines = explode("\n", $exceptionString);

        if (empty($lines)) {
            return 'Unknown error';
        }

        $firstLine = trim($lines[0]);

        // Try to extract just the message part
        if (strpos($firstLine, ':') !== false) {
            $parts = explode(':', $firstLine, 2);
            if (count($parts) > 1) {
                return trim($parts[1]);
            }
        }

        return $firstLine;
    }
}
