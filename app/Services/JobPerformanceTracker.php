<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobPerformanceMetric;
use App\Models\JobChunkMetric;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Job Performance Tracker Service
 * 
 * Comprehensive performance tracking for sales recalculation jobs
 * to measure the impact of Octane+Swoole+Redis+Horizon optimization
 */
class JobPerformanceTracker
{
    private string $batchId;
    private array $systemMetrics;

    public function __construct()
    {
        $this->batchId = Str::uuid()->toString();
        $this->systemMetrics = [];
    }

    /**
     * Start tracking a new job batch
     */
    public function startBatch(
        string $jobType,
        int $totalPids,
        int $totalChunks,
        string $queueName,
        ?string $triggeredBy = null,
        ?array $requestParams = null
    ): string {
        $this->captureSystemMetrics('start');

        JobPerformanceMetric::create([
            'batch_id' => $this->batchId,
            'job_type' => $jobType,
            'total_pids' => $totalPids,
            'total_chunks' => $totalChunks,
            'started_at' => now(),
            'queue_name' => $queueName,
            'triggered_by' => $triggeredBy,
            'request_params' => $requestParams,
            'system_load_start' => $this->systemMetrics['cpu_load'] ?? null,
            'redis_ops_start' => $this->systemMetrics['redis_ops'] ?? null,
            'status' => 'started'
        ]);

        return $this->batchId;
    }

    /**
     * Start tracking a chunk within the batch
     */
    public function startChunk(string $batchId, int $chunkNumber, array $pids): void
    {
        // Ensure the batch exists in job_performance_metrics before creating chunk metric
        // This prevents foreign key constraint violations
        // This can happen when jobs are dispatched with a batchId that wasn't registered
        // Using firstOrCreate() is atomic and handles race conditions safely
        JobPerformanceMetric::firstOrCreate(
            ['batch_id' => $batchId],
            [
                'job_type' => 'RecalculateSalesJob',
                'total_pids' => 0, // Will be updated when batch completes
                'total_chunks' => 0, // Will be updated when batch completes
                'started_at' => now(),
                'queue_name' => config('queue.default', 'default'),
                'status' => 'started'
            ]
        );

        JobChunkMetric::create([
            'batch_id' => $batchId,
            'chunk_number' => $chunkNumber,
            'pids' => $pids,
            'started_at' => now(),
            'memory_usage' => memory_get_usage(true),
            'status' => 'started'
        ]);
    }

    /**
     * Complete tracking for a chunk
     */
    public function completeChunk(
        string $batchId,
        int $chunkNumber,
        int $successCount,
        int $failedCount,
        ?array $errorDetails = null
    ): void {
        $chunk = JobChunkMetric::where('batch_id', $batchId)
            ->where('chunk_number', $chunkNumber)
            ->first();

        if ($chunk) {
            $completedAt = now();
            $duration = $completedAt->diffInSeconds($chunk->started_at, true);

            $chunk->update([
                'completed_at' => $completedAt,
                'duration_seconds' => $duration,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'memory_usage' => memory_get_usage(true),
                'cpu_usage' => $this->getCpuUsage(),
                'error_details' => $errorDetails,
                'status' => $failedCount > 0 ? 'failed' : 'completed'
            ]);
        }
    }

    /**
     * Complete tracking for the entire batch
     */
    public function completeBatch(string $batchId): void
    {
        $this->captureSystemMetrics('end');

        $jobMetric = JobPerformanceMetric::where('batch_id', $batchId)->first();
        if (!$jobMetric) {
            return;
        }

        $completedAt = now();
        $duration = $completedAt->diffInSeconds($jobMetric->started_at, true);

        // Calculate aggregate metrics from chunks
        $chunkMetrics = JobChunkMetric::where('batch_id', $batchId)->get();
        $totalSuccess = $chunkMetrics->sum('success_count');
        $totalFailed = $chunkMetrics->sum('failed_count');
        $averageChunkTime = $chunkMetrics->avg('duration_seconds');
        $peakMemory = $chunkMetrics->max('memory_usage');

        $jobMetric->update([
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'success_count' => $totalSuccess,
            'failed_count' => $totalFailed,
            'average_chunk_time' => $averageChunkTime,
            'peak_memory_usage' => $peakMemory,
            'system_load_end' => $this->systemMetrics['cpu_load'] ?? null,
            'redis_ops_end' => $this->systemMetrics['redis_ops'] ?? null,
            'status' => $totalFailed > 0 ? 'failed' : 'completed'
        ]);
    }

    /**
     * Mark batch as failed
     */
    public function failBatch(string $batchId, string $reason): void
    {
        JobPerformanceMetric::where('batch_id', $batchId)->update([
            'completed_at' => now(),
            'status' => 'failed',
            'request_params' => ['error_reason' => $reason]
        ]);
    }

    /**
     * Get performance summary for a batch
     */
    public function getBatchSummary(string $batchId): ?array
    {
        $jobMetric = JobPerformanceMetric::with('chunkMetrics')
            ->where('batch_id', $batchId)
            ->first();

        if (!$jobMetric) {
            return null;
        }

        return [
            'batch_id' => $batchId,
            'job_type' => $jobMetric->job_type,
            'status' => $jobMetric->status,
            'total_pids' => $jobMetric->total_pids,
            'total_chunks' => $jobMetric->total_chunks,
            'duration_seconds' => $jobMetric->duration_seconds,
            'throughput' => $jobMetric->throughput,
            'success_rate' => $jobMetric->success_rate,
            'success_count' => $jobMetric->success_count,
            'failed_count' => $jobMetric->failed_count,
            'average_chunk_time' => $jobMetric->average_chunk_time,
            'peak_memory_usage' => $jobMetric->formatted_memory_usage,
            'redis_ops_per_second' => $jobMetric->redis_ops_per_second,
            'system_load_change' => $this->calculateSystemLoadChange($jobMetric),
            'started_at' => $jobMetric->started_at,
            'completed_at' => $jobMetric->completed_at,
            'chunk_details' => $jobMetric->chunkMetrics->map(function ($chunk) {
                return [
                    'chunk_number' => $chunk->chunk_number,
                    'pids_count' => count($chunk->pids),
                    'duration_seconds' => $chunk->duration_seconds,
                    'throughput' => $chunk->throughput,
                    'success_rate' => $chunk->success_rate,
                    'memory_usage' => $chunk->formatted_memory_usage,
                    'status' => $chunk->status
                ];
            })
        ];
    }

    /**
     * Get performance comparison between time periods
     */
    public function getPerformanceComparison(int $hoursBack = 24): array
    {
        $recent = JobPerformanceMetric::completed()
            ->where('started_at', '>=', now()->subHours($hoursBack))
            ->get();

        $previous = JobPerformanceMetric::completed()
            ->where('started_at', '>=', now()->subHours($hoursBack * 2))
            ->where('started_at', '<', now()->subHours($hoursBack))
            ->get();

        return [
            'recent_period' => $this->calculateAggregateMetrics($recent),
            'previous_period' => $this->calculateAggregateMetrics($previous),
            'improvement' => $this->calculateImprovement($recent, $previous)
        ];
    }

    /**
     * Capture system metrics
     */
    private function captureSystemMetrics(string $phase): void
    {
        // CPU Load Average
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->systemMetrics['cpu_load'] = $load[0] ?? 0;
        }

        // Redis operations count
        try {
            $redisInfo = Redis::info('stats');
            $this->systemMetrics['redis_ops'] = $redisInfo['total_commands_processed'] ?? 0;
        } catch (\Exception $e) {
            $this->systemMetrics['redis_ops'] = 0;
        }
    }

    /**
     * Get current CPU usage percentage
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        }
        return 0.0;
    }

    /**
     * Calculate system load change
     */
    private function calculateSystemLoadChange(JobPerformanceMetric $jobMetric): float
    {
        if ($jobMetric->system_load_start && $jobMetric->system_load_end) {
            return round($jobMetric->system_load_end - $jobMetric->system_load_start, 2);
        }
        return 0.0;
    }

    /**
     * Calculate aggregate metrics for a collection
     */
    private function calculateAggregateMetrics($jobs): array
    {
        if ($jobs->isEmpty()) {
            return [
                'total_jobs' => 0,
                'total_pids' => 0,
                'average_duration' => 0,
                'average_throughput' => 0,
                'average_success_rate' => 0,
                'average_memory_usage' => 0
            ];
        }

        return [
            'total_jobs' => $jobs->count(),
            'total_pids' => $jobs->sum('total_pids'),
            'average_duration' => round($jobs->avg('duration_seconds'), 2),
            'average_throughput' => round($jobs->avg(function ($job) {
                return $job->throughput;
            }), 2),
            'average_success_rate' => round($jobs->avg(function ($job) {
                return $job->success_rate;
            }), 2),
            'average_memory_usage' => round($jobs->avg('peak_memory_usage') / 1024 / 1024, 2) // MB
        ];
    }

    /**
     * Calculate improvement percentage
     */
    private function calculateImprovement($recent, $previous): array
    {
        $recentMetrics = $this->calculateAggregateMetrics($recent);
        $previousMetrics = $this->calculateAggregateMetrics($previous);

        $improvements = [];
        foreach (['average_duration', 'average_throughput', 'average_success_rate'] as $metric) {
            if ($previousMetrics[$metric] > 0) {
                $change = (($recentMetrics[$metric] - $previousMetrics[$metric]) / $previousMetrics[$metric]) * 100;
                $improvements[$metric] = round($change, 2);
            } else {
                $improvements[$metric] = 0;
            }
        }

        return $improvements;
    }

    /**
     * Get current batch ID
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Set batch ID (for existing batches)
     */
    public function setBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
    }
}
