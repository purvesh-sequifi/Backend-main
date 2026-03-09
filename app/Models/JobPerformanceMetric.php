<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Job Performance Metrics Model
 * 
 * Tracks performance metrics for sales recalculation jobs
 * to measure the impact of Octane+Swoole+Redis+Horizon optimization
 */
class JobPerformanceMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'job_type',
        'total_pids',
        'total_chunks',
        'started_at',
        'completed_at',
        'duration_seconds',
        'success_count',
        'failed_count',
        'average_chunk_time',
        'peak_memory_usage',
        'queue_name',
        'triggered_by',
        'request_params',
        'system_load_start',
        'system_load_end',
        'redis_ops_start',
        'redis_ops_end',
        'status'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'decimal:2',
        'average_chunk_time' => 'decimal:2',
        'peak_memory_usage' => 'integer',
        'request_params' => 'json',
        'system_load_start' => 'decimal:2',
        'system_load_end' => 'decimal:2',
        'redis_ops_start' => 'integer',
        'redis_ops_end' => 'integer'
    ];

    /**
     * Job chunk details relationship
     */
    public function chunkMetrics(): HasMany
    {
        return $this->hasMany(JobChunkMetric::class, 'batch_id', 'batch_id');
    }

    /**
     * Calculate throughput (PIDs per second)
     */
    public function getThroughputAttribute(): float
    {
        if ($this->duration_seconds > 0) {
            return round($this->total_pids / $this->duration_seconds, 2);
        }
        return 0.0;
    }

    /**
     * Calculate success rate percentage
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->total_pids > 0) {
            return round(($this->success_count / $this->total_pids) * 100, 2);
        }
        return 0.0;
    }

    /**
     * Get Redis operations per second during the job
     */
    public function getRedisOpsPerSecondAttribute(): float
    {
        $redisOps = $this->redis_ops_end - $this->redis_ops_start;
        if ($this->duration_seconds > 0 && $redisOps > 0) {
            return round($redisOps / $this->duration_seconds, 2);
        }
        return 0.0;
    }

    /**
     * Get formatted memory usage
     */
    public function getFormattedMemoryUsageAttribute(): string
    {
        return $this->formatBytes($this->peak_memory_usage);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Scope for recent metrics
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('started_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for completed jobs only
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for specific job type
     */
    public function scopeJobType($query, string $jobType)
    {
        return $query->where('job_type', $jobType);
    }
}
