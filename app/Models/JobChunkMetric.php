<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Job Chunk Metrics Model
 * 
 * Tracks individual chunk performance within a batch job
 * for detailed performance analysis
 */
class JobChunkMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'chunk_number',
        'pids',
        'started_at',
        'completed_at',
        'duration_seconds',
        'success_count',
        'failed_count',
        'memory_usage',
        'cpu_usage',
        'error_details',
        'status'
    ];

    protected $casts = [
        'pids' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'decimal:3',
        'memory_usage' => 'integer',
        'cpu_usage' => 'decimal:2',
        'error_details' => 'json'
    ];

    /**
     * Parent job performance metric relationship
     */
    public function jobMetric(): BelongsTo
    {
        return $this->belongsTo(JobPerformanceMetric::class, 'batch_id', 'batch_id');
    }

    /**
     * Get chunk throughput (PIDs per second)
     */
    public function getThroughputAttribute(): float
    {
        $pidCount = is_array($this->pids) ? count($this->pids) : 0;
        if ($this->duration_seconds > 0 && $pidCount > 0) {
            return round($pidCount / $this->duration_seconds, 2);
        }
        return 0.0;
    }

    /**
     * Get chunk success rate
     */
    public function getSuccessRateAttribute(): float
    {
        $pidCount = is_array($this->pids) ? count($this->pids) : 0;
        if ($pidCount > 0) {
            return round(($this->success_count / $pidCount) * 100, 2);
        }
        return 0.0;
    }

    /**
     * Get formatted memory usage
     */
    public function getFormattedMemoryUsageAttribute(): string
    {
        return $this->formatBytes($this->memory_usage);
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
}
