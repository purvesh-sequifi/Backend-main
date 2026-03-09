<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class JobProgressLog extends Model
{
    use SpatieLogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'job_id',
        'job_class',
        'queue',
        'status',
        'type',
        'progress_percentage',
        'total_records',
        'processed_records',
        'current_operation',
        'last_record_identifier',
        'message',
        'metadata',
        'error',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'error' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Create a new entry or update existing one for a job with improved handling for lock timeout issues
     */
    public static function updateProgress(string $jobId, array $data): ?JobProgressLog
    {
        $maxAttempts = 3;
        $attempt = 1;
        $backoff = 100; // milliseconds

        while ($attempt <= $maxAttempts) {
            try {
                // Use a separate database transaction with a shorter timeout
                return \Illuminate\Support\Facades\DB::transaction(function () use ($jobId, $data) {
                    return self::updateOrCreate(
                        ['job_id' => $jobId],
                        $data
                    );
                }, 3); // 3 retries at the transaction level
            } catch (\Exception $e) {
                // Only retry on lock timeout errors
                if (strpos($e->getMessage(), 'Lock wait timeout exceeded') !== false && $attempt < $maxAttempts) {
                    \Illuminate\Support\Facades\Log::warning(
                        "Lock wait timeout encountered in JobProgressLog::updateProgress. Retrying {$attempt}/{$maxAttempts}",
                        ['job_id' => $jobId, 'error' => $e->getMessage()]
                    );
                    $attempt++;
                    usleep($backoff * 1000 * $attempt); // Exponential backoff

                    continue;
                }

                // Log the error but don't fail the whole job
                \Illuminate\Support\Facades\Log::error(
                    'Error in JobProgressLog::updateProgress',
                    ['job_id' => $jobId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
                );

                // Return null instead of throwing to prevent job failure due to monitoring issues
                return null;
            }
        }

        // If we've exhausted all attempts
        \Illuminate\Support\Facades\Log::error(
            "Failed to update job progress after {$maxAttempts} attempts",
            ['job_id' => $jobId]
        );

        return null;
    }

    /**
     * Get all jobs with the specified status
     */
    public static function getByStatus(string $status): Collection
    {
        return self::where('status', $status)->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Get logs for a specific job type
     */
    public static function getByType(string $type): Collection
    {
        return self::where('type', $type)->orderBy('updated_at', 'desc')->get();
    }
}
