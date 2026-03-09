<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPerformanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'job_class',
        'queue',
        'connection',
        'payload',
        'status',
        'started_at',
        'completed_at',
        'failed_at',
        'processing_time_ms',
        'memory_usage_mb',
        'attempts',
        'error_message',
        'worker_pid',
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Scope to get jobs from today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get jobs within date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get jobs by queue
     */
    public function scopeByQueue($query, $queue)
    {
        return $query->where('queue', $queue);
    }

    /**
     * Get processing time in seconds
     */
    public function getProcessingTimeSecondsAttribute()
    {
        return $this->processing_time_ms ? round($this->processing_time_ms / 1000, 2) : null;
    }

    /**
     * Calculate processing time if not already set
     */
    public function calculateProcessingTime()
    {
        if ($this->started_at && $this->completed_at) {
            $this->processing_time_ms = $this->started_at->diffInMilliseconds($this->completed_at);

            return $this->processing_time_ms;
        }

        return null;
    }

    /**
     * Get hourly stats for a given date range
     */
    public static function getHourlyStats($startDate, $endDate)
    {
        return self::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs,
                AVG(CASE WHEN status = "completed" THEN processing_time_ms ELSE NULL END) as avg_processing_time_ms,
                MAX(processing_time_ms) as max_processing_time_ms,
                MIN(processing_time_ms) as min_processing_time_ms
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    /**
     * Get queue performance stats
     */
    public static function getQueueStats($startDate = null, $endDate = null)
    {
        $query = self::selectRaw('
                queue,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs,
                AVG(CASE WHEN status = "completed" THEN processing_time_ms ELSE NULL END) as avg_processing_time_ms,
                AVG(memory_usage_mb) as avg_memory_usage_mb
            ');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->groupBy('queue')->get();
    }

    /**
     * Log job start
     */
    public static function logJobStart($jobId, $jobClass, $queue, $connection, $payload = null)
    {
        return self::create([
            'job_id' => $jobId,
            'job_class' => $jobClass,
            'queue' => $queue,
            'connection' => $connection,
            'payload' => $payload,
            'status' => 'started',
            'started_at' => now(),
            'worker_pid' => getmypid(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
    }

    /**
     * Log job completion
     */
    public function logJobCompletion()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $this->calculateProcessingTime();
        $this->save();
    }

    /**
     * Log job failure
     */
    public function logJobFailure($errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        if ($this->started_at) {
            $this->calculateProcessingTime();
            $this->save();
        }
    }
}
