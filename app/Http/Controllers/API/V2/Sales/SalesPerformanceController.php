<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2\Sales;

use App\Http\Controllers\Controller;
use App\Models\JobPerformanceMetric;
use App\Services\JobPerformanceTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Sales Performance Monitoring Controller
 * 
 * Provides APIs to monitor and track sales recalculation performance
 * for measuring the impact of Octane+Swoole+Redis+Horizon optimization
 */
class SalesPerformanceController extends Controller
{
    protected JobPerformanceTracker $tracker;

    public function __construct()
    {
        $this->tracker = new JobPerformanceTracker();
    }

    /**
     * Get batch status and performance metrics
     */
    public function getBatchStatus(Request $request, string $batchId): JsonResponse
    {
        $summary = $this->tracker->getBatchSummary($batchId);
        
        if (!$summary) {
            return response()->json([
                'status' => false,
                'message' => 'Batch not found',
                'batch_id' => $batchId
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Batch status retrieved successfully',
            'data' => $summary
        ]);
    }

    /**
     * Get performance comparison between time periods
     */
    public function getPerformanceComparison(Request $request): JsonResponse
    {
        $hoursBack = $request->input('hours', 24);
        $comparison = $this->tracker->getPerformanceComparison($hoursBack);

        return response()->json([
            'status' => true,
            'message' => 'Performance comparison retrieved successfully',
            'data' => $comparison
        ]);
    }

    /**
     * Get recent batch performance metrics
     */
    public function getRecentBatches(Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        $limit = $request->input('limit', 50);
        
        $batches = JobPerformanceMetric::with('chunkMetrics')
            ->recent($hours)
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($batch) {
                return [
                    'batch_id' => $batch->batch_id,
                    'job_type' => $batch->job_type,
                    'status' => $batch->status,
                    'total_pids' => $batch->total_pids,
                    'total_chunks' => $batch->total_chunks,
                    'duration_seconds' => $batch->duration_seconds,
                    'throughput' => $batch->throughput,
                    'success_rate' => $batch->success_rate,
                    'peak_memory_usage' => $batch->formatted_memory_usage,
                    'redis_ops_per_second' => $batch->redis_ops_per_second,
                    'started_at' => $batch->started_at,
                    'completed_at' => $batch->completed_at,
                    'triggered_by' => $batch->triggered_by
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Recent batches retrieved successfully',
            'data' => [
                'batches' => $batches,
                'total_count' => $batches->count(),
                'time_range_hours' => $hours
            ]
        ]);
    }

    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(): JsonResponse
    {
        $metrics = [
            'timestamp' => now(),
            'memory' => [
                'current_usage' => memory_get_usage(true),
                'peak_usage' => memory_get_peak_usage(true),
                'formatted_current' => $this->formatBytes(memory_get_usage(true)),
                'formatted_peak' => $this->formatBytes(memory_get_peak_usage(true))
            ],
            'system' => [
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
                'cpu_count' => function_exists('swoole_cpu_num') ? swoole_cpu_num() : null
            ]
        ];

        // Redis metrics
        try {
            $redisInfo = Redis::info();
            $metrics['redis'] = [
                'memory_usage' => $redisInfo['used_memory_human'] ?? 'N/A',
                'memory_peak' => $redisInfo['used_memory_peak_human'] ?? 'N/A',
                'ops_per_sec' => $redisInfo['instantaneous_ops_per_sec'] ?? 0,
                'total_commands' => $redisInfo['total_commands_processed'] ?? 0,
                'connected_clients' => $redisInfo['connected_clients'] ?? 0,
                'keyspace_hits' => $redisInfo['keyspace_hits'] ?? 0,
                'keyspace_misses' => $redisInfo['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($redisInfo)
            ];
        } catch (\Exception $e) {
            $metrics['redis'] = ['error' => 'Unable to fetch Redis metrics'];
        }

        // Queue metrics (from Horizon)
        try {
            $queueMetrics = $this->getQueueMetrics();
            $metrics['queues'] = $queueMetrics;
        } catch (\Exception $e) {
            $metrics['queues'] = ['error' => 'Unable to fetch queue metrics'];
        }

        return response()->json([
            'status' => true,
            'message' => 'System metrics retrieved successfully',
            'data' => $metrics
        ]);
    }

    /**
     * Get performance dashboard data
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        $hours = $request->input('hours', 24);
        
        // Get recent performance metrics
        $recentBatches = JobPerformanceMetric::completed()
            ->recent($hours)
            ->get();

        // Calculate aggregate statistics
        $stats = [
            'total_batches' => $recentBatches->count(),
            'total_pids_processed' => $recentBatches->sum('total_pids'),
            'average_duration' => round($recentBatches->avg('duration_seconds'), 2),
            'average_throughput' => round($recentBatches->avg(function ($batch) {
                return $batch->throughput;
            }), 2),
            'average_success_rate' => round($recentBatches->avg(function ($batch) {
                return $batch->success_rate;
            }), 2),
            'total_successes' => $recentBatches->sum('success_count'),
            'total_failures' => $recentBatches->sum('failed_count')
        ];

        // Get hourly breakdown
        $hourlyStats = $recentBatches->groupBy(function ($batch) {
            return $batch->started_at->format('Y-m-d H:00');
        })->map(function ($hourBatches) {
            return [
                'batches_count' => $hourBatches->count(),
                'total_pids' => $hourBatches->sum('total_pids'),
                'average_duration' => round($hourBatches->avg('duration_seconds'), 2),
                'average_throughput' => round($hourBatches->avg(function ($batch) {
                    return $batch->throughput;
                }), 2)
            ];
        });

        // Get performance comparison
        $comparison = $this->tracker->getPerformanceComparison($hours);

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'summary_stats' => $stats,
                'hourly_breakdown' => $hourlyStats,
                'performance_comparison' => $comparison,
                'time_range_hours' => $hours,
                'last_updated' => now()
            ]
        ]);
    }

    /**
     * Get active batches (currently running)
     */
    public function getActiveBatches(): JsonResponse
    {
        $activeBatches = JobPerformanceMetric::whereIn('status', ['started', 'processing'])
            ->with('chunkMetrics')
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($batch) {
                $completedChunks = $batch->chunkMetrics->whereIn('status', ['completed', 'failed'])->count();
                $progress = $batch->total_chunks > 0 ? round(($completedChunks / $batch->total_chunks) * 100, 2) : 0;
                
                return [
                    'batch_id' => $batch->batch_id,
                    'job_type' => $batch->job_type,
                    'status' => $batch->status,
                    'total_pids' => $batch->total_pids,
                    'total_chunks' => $batch->total_chunks,
                    'completed_chunks' => $completedChunks,
                    'progress_percentage' => $progress,
                    'elapsed_time' => $batch->started_at->diffInSeconds(now()),
                    'started_at' => $batch->started_at,
                    'triggered_by' => $batch->triggered_by
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Active batches retrieved successfully',
            'data' => [
                'active_batches' => $activeBatches,
                'total_active' => $activeBatches->count()
            ]
        ]);
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
     * Calculate Redis hit rate
     */
    private function calculateHitRate(array $redisInfo): float
    {
        $hits = $redisInfo['keyspace_hits'] ?? 0;
        $misses = $redisInfo['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Get queue metrics from Redis
     */
    private function getQueueMetrics(): array
    {
        $queues = ['sales-process', 'payroll', 'everee', 'default'];
        $metrics = [];

        foreach ($queues as $queue) {
            try {
                $queueKey = config('database.redis.options.prefix') . 'queues:' . $queue;
                $length = Redis::llen($queueKey);
                $metrics[$queue] = [
                    'pending_jobs' => $length,
                    'queue_key' => $queueKey
                ];
            } catch (\Exception $e) {
                $metrics[$queue] = ['error' => 'Unable to fetch queue length'];
            }
        }

        return $metrics;
    }
}
