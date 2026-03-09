<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;

/**
 * Simple Performance Monitoring Controller
 * 
 * Provides basic performance monitoring by analyzing logs for multiple job types:
 * - ProcessRecalculatesOpenSales: Sales recalculation for backdated changes
 * - RecalculateSalesJob: Legacy sales recalculation jobs
 * - ApplyHistoryOnUsersV2Job: User history synchronization jobs
 * 
 * Tracks job completions, success/failure rates, throughput, and queue status
 */
class SimplePerformanceController extends Controller
{
    /**
     * Get current job processing status from logs
     * Supports multiple job types for comprehensive monitoring
     */
    public function getJobStatus()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            return response()->json([
                'status' => false,
                'message' => 'Log file not found'
            ], 404);
        }

        // Get recent log entries efficiently (avoid memory exhaustion)
        // Use tail command to get last 5000 lines instead of loading entire file
        $recentLines = [];
        if (function_exists('shell_exec')) {
            $output = shell_exec("tail -5000 {$logFile} 2>/dev/null");
            if ($output) {
                $recentLines = explode("\n", $output);
            }
        }
        
        // Fallback: if shell_exec failed, read file in chunks
        if (empty($recentLines)) {
            $handle = fopen($logFile, 'r');
            if ($handle) {
                // Seek to end and read backwards
                fseek($handle, -min(filesize($logFile), 1024 * 1024), SEEK_END); // Last 1MB
                $content = fread($handle, 1024 * 1024);
                fclose($handle);
                $recentLines = explode("\n", $content);
                $recentLines = array_slice($recentLines, -5000); // Last 5000 lines
            }
        }

        // Parse job completion data
        $completedJobs = [];
        $successfulPids = 0;
        $failedPids = 0;
        $lastJobTime = null;
        $recentProcessedPids = [];

        foreach ($recentLines as $line) {
            // Match: [ProcessRecalculatesOpenSales] Job completed
            if (strpos($line, '[ProcessRecalculatesOpenSales] Job completed') !== false) {
                if (preg_match('/\[(.*?)\].*?"batch_id":"([^"]*)".*?"processed_count":(\d+).*?"failed_count":(\d+).*?"duration_seconds":([\d.]+).*?"throughput_pids_per_sec":([\d.]+)/', $line, $matches)) {
                    $batchId = $matches[2];
                    $processedCount = (int)$matches[3];
                    $failedCount = (int)$matches[4];
                    $totalPids = $processedCount + $failedCount;
                    
                    $jobPids = $this->extractPidsForJobTime($matches[1], $recentLines, 'ProcessRecalculatesOpenSales');
                    
                    $completedJobs[] = [
                        'timestamp' => $matches[1],
                        'job_type' => 'ProcessRecalculatesOpenSales',
                        'total_pids' => $totalPids,
                        'success_count' => $processedCount,
                        'failed_count' => $failedCount,
                        'duration_ms' => (float)$matches[5] * 1000,
                        'throughput' => (float)$matches[6],
                        'batch_id' => $batchId,
                        'pids' => $jobPids
                    ];
                    $lastJobTime = $matches[1];
                }
            }
            
            // Match: RecalculateSalesJob completed
            if (strpos($line, 'RecalculateSalesJob completed') !== false) {
                if (preg_match('/\[(.*?)\].*?"batch_id":"([^"]*)".*?"total_pids":(\d+).*?"success_count":(\d+).*?"failed_count":(\d+).*?"duration_ms":([\d.]+).*?"throughput_pids_per_sec":([\d.]+)/', $line, $matches)) {
                    $batchId = $matches[2];
                    $jobPids = $this->extractPidsForJobTime($matches[1], $recentLines, 'RecalculateSalesJob');
                    
                    $completedJobs[] = [
                        'timestamp' => $matches[1],
                        'job_type' => 'RecalculateSalesJob',
                        'total_pids' => (int)$matches[3],
                        'success_count' => (int)$matches[4],
                        'failed_count' => (int)$matches[5],
                        'duration_ms' => (float)$matches[6],
                        'throughput' => (float)$matches[7],
                        'batch_id' => $batchId,
                        'pids' => $jobPids
                    ];
                    $lastJobTime = $matches[1];
                }
            }
            
            // Match: [ApplyHistoryOnUsersV2Job] Job completed
            if (strpos($line, '[ApplyHistoryOnUsersV2Job] Job completed') !== false) {
                if (preg_match('/\[(.*?)\].*?"batch_id":"([^"]*)".*?"processed_count":(\d+).*?"failed_count":(\d+)/', $line, $matches)) {
                    $completedJobs[] = [
                        'timestamp' => $matches[1],
                        'job_type' => 'ApplyHistoryOnUsersV2Job',
                        'total_pids' => (int)$matches[3] + (int)$matches[4],
                        'success_count' => (int)$matches[3],
                        'failed_count' => (int)$matches[4],
                        'duration_ms' => 0,
                        'throughput' => 0,
                        'batch_id' => $matches[2],
                        'pids' => []
                    ];
                    $lastJobTime = $matches[1];
                }
            }
            
            // Match: [ProcessRecalculatesOpenSales] PID recalculated successfully
            if (strpos($line, '[ProcessRecalculatesOpenSales] PID recalculated successfully') !== false) {
                $successfulPids++;
                if (preg_match('/"pid":"([^"]+)"/', $line, $pidMatches)) {
                    $recentProcessedPids[] = [
                        'pid' => $pidMatches[1],
                        'status' => 'success',
                        'job_type' => 'ProcessRecalculatesOpenSales',
                        'timestamp' => $this->extractTimestampFromLogLine($line)
                    ];
                }
            }
            
            // Match: Successfully recalculated PID (RecalculateSalesJob)
            if (strpos($line, 'Successfully recalculated PID') !== false && strpos($line, 'RecalculateSalesJob') !== false) {
                $successfulPids++;
                if (preg_match('/Successfully recalculated PID (\d+)/', $line, $pidMatches)) {
                    $recentProcessedPids[] = [
                        'pid' => $pidMatches[1],
                        'status' => 'success',
                        'job_type' => 'RecalculateSalesJob',
                        'timestamp' => $this->extractTimestampFromLogLine($line)
                    ];
                }
            }
            
            // Match: [ProcessRecalculatesOpenSales] PID recalculation failed
            if (strpos($line, '[ProcessRecalculatesOpenSales] PID recalculation failed') !== false) {
                $failedPids++;
                if (preg_match('/"pid":"([^"]+)"/', $line, $pidMatches)) {
                    $recentProcessedPids[] = [
                        'pid' => $pidMatches[1],
                        'status' => 'failed',
                        'job_type' => 'ProcessRecalculatesOpenSales',
                        'timestamp' => $this->extractTimestampFromLogLine($line)
                    ];
                }
            }
            
            // Match: Failed to recalculate PID (RecalculateSalesJob)
            if (strpos($line, 'Failed to recalculate PID') !== false && strpos($line, 'RecalculateSalesJob') !== false) {
                $failedPids++;
                if (preg_match('/Failed to recalculate PID (\d+)/', $line, $pidMatches)) {
                    $recentProcessedPids[] = [
                        'pid' => $pidMatches[1],
                        'status' => 'failed',
                        'job_type' => 'RecalculateSalesJob',
                        'timestamp' => $this->extractTimestampFromLogLine($line)
                    ];
                }
            }
        }

        // Calculate statistics
        $totalChunks = count($completedJobs);
        $totalProcessedPids = array_sum(array_column($completedJobs, 'total_pids'));
        $avgThroughput = $totalChunks > 0 ? array_sum(array_column($completedJobs, 'throughput')) / $totalChunks : 0;
        $avgDuration = $totalChunks > 0 ? array_sum(array_column($completedJobs, 'duration_ms')) / $totalChunks : 0;

        // Get queue status
        $queueStatus = $this->getQueueStatus();

        $data = [
            'summary' => [
                'completed_chunks' => $totalChunks,
                'total_processed_pids' => $totalProcessedPids,
                'successful_pids' => $successfulPids,
                'failed_pids' => $failedPids,
                'success_rate' => $totalProcessedPids > 0 ? round(($successfulPids / $totalProcessedPids) * 100, 2) : 0,
                'average_throughput' => round($avgThroughput, 2),
                'average_duration_seconds' => round($avgDuration / 1000, 2),
                'last_job_completed' => $lastJobTime,
                'estimated_total_pids' => 0,
                'estimated_remaining_pids' => 0,
                'progress_percentage' => 0
            ],
            'queue_status' => $queueStatus,
            'recent_jobs' => array_slice($completedJobs, -10), // Last 10 jobs
            'recent_processed_pids' => array_slice($recentProcessedPids, -50), // Last 50 individual PIDs
            'timestamp' => now()
        ];

        // Check if request wants JSON
        if (request()->wantsJson() || request()->has('json')) {
            return response()->json([
                'status' => true,
                'message' => 'Job status retrieved successfully',
                'data' => $data
            ]);
        }

        // Return beautiful HTML view
        return view('performance.job-status', [
            'data' => $data,
            'rawData' => [
                'status' => true,
                'message' => 'Job status retrieved successfully',
                'data' => $data
            ]
        ]);
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics()
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

        // Check if request wants JSON
        if (request()->wantsJson() || request()->has('json')) {
            return response()->json([
                'status' => true,
                'message' => 'System metrics retrieved successfully',
                'data' => $metrics
            ]);
        }

        // Return beautiful HTML view
        return view('performance.system-metrics', [
            'data' => $metrics,
            'rawData' => [
                'status' => true,
                'message' => 'System metrics retrieved successfully',
                'data' => $metrics
            ]
        ]);
    }

    /**
     * Get queue status
     */
    private function getQueueStatus(): array
    {
        $queues = ['sales-process', 'payroll', 'everee', 'default'];
        $status = [];

        foreach ($queues as $queue) {
            try {
                // Try multiple queue key formats
                $queueKeys = [
                    config('database.redis.options.prefix') . 'queues:' . $queue,
                    'solarstage_horizon:queue:' . $queue,
                    'laravel_database_queues:' . $queue
                ];
                
                $length = 0;
                $keyFound = false;
                
                foreach ($queueKeys as $queueKey) {
                    try {
                        $testLength = Redis::llen($queueKey);
                        if ($testLength !== false) {
                            $length = $testLength;
                            $keyFound = true;
                            break;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                // Check if there are recent jobs for this queue in logs to determine if it's active
                $recentActivity = $this->checkRecentQueueActivity($queue);
                
                $status[$queue] = [
                    'pending_jobs' => $length,
                    'status' => $recentActivity ? 'processing' : ($length > 0 ? 'active' : 'idle'),
                    'recent_activity' => $recentActivity
                ];
            } catch (\Exception $e) {
                $status[$queue] = ['error' => 'Unable to fetch queue status'];
            }
        }

        return $status;
    }
    
    /**
     * Check if there's recent activity for a queue
     */
    private function checkRecentQueueActivity(string $queue): bool
    {
        // For sales-process queue, check if there are recent job completions or individual PID processing
        if ($queue === 'sales-process') {
            $logFile = storage_path('logs/laravel.log');
            if (File::exists($logFile)) {
                // Check for any sales job activity in the last 50 lines (more recent)
                // Includes: ProcessRecalculatesOpenSales, RecalculateSalesJob, ApplyHistoryOnUsersV2Job
                $recentContent = shell_exec("tail -50 {$logFile} | grep -E '(ProcessRecalculatesOpenSales|RecalculateSalesJob|ApplyHistoryOnUsersV2Job|PID recalculated)' | wc -l");
                $activityCount = (int)trim($recentContent);
                
                // Also check if there are any horizon workers running for this queue
                $workersRunning = shell_exec("ps aux | grep 'horizon:work.*sales-process' | grep -v grep | wc -l");
                $workerCount = (int)trim($workersRunning);
                
                // If there's any recent activity OR workers running, consider it active
                return $activityCount > 0 || $workerCount > 0;
            }
        }
        
        return false;
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
     * Extract PIDs for a job by matching completion time with start time
     */
    private function extractPidsForJobTime(string $completionTime, array $logLines, string $jobType = 'ProcessRecalculatesOpenSales'): array
    {
        $pids = [];
        $completionTimestamp = strtotime($completionTime);
        
        // Define job-specific search patterns
        $startPatterns = [
            'ProcessRecalculatesOpenSales' => '[ProcessRecalculatesOpenSales] Starting job',
            'RecalculateSalesJob' => 'RecalculateSalesJob started',
            'ApplyHistoryOnUsersV2Job' => '[ApplyHistoryOnUsersV2Job] Starting job'
        ];
        
        $searchPattern = $startPatterns[$jobType] ?? $startPatterns['ProcessRecalculatesOpenSales'];
        
        // Look for job start entries within 30 minutes before completion
        foreach ($logLines as $line) {
            if (strpos($line, $searchPattern) !== false) {
                
                // Extract timestamp from this line
                if (preg_match('/\[(.*?)\]/', $line, $timeMatches)) {
                    $startTime = strtotime($timeMatches[1]);
                    
                    // If this start time is within 30 minutes before completion, it's likely our job
                    if ($startTime <= $completionTimestamp && ($completionTimestamp - $startTime) <= 1800) {
                        
                        // Extract PIDs array from the log line
                        if (preg_match('/"pids":\[(.*?)\]/', $line, $matches)) {
                            $pidsString = $matches[1];
                            // Remove quotes and split by comma
                            $pidsArray = array_map(function($pid) {
                                return trim($pid, '"');
                            }, explode(',', $pidsString));
                            
                            $pids = array_filter($pidsArray, function($pid) {
                                return !empty(trim($pid));
                            });
                            break; // Found our PIDs, stop looking
                        }
                    }
                }
            }
        }
        
        return array_values($pids);
    }

    /**
     * Extract PIDs for a specific batch from log lines (legacy method)
     */
    private function extractPidsForBatch(?string $batchId, array $logLines, string $jobType = 'ProcessRecalculatesOpenSales'): array
    {
        if (!$batchId) {
            return [];
        }

        $pids = [];
        
        // Define job-specific search patterns
        $startPatterns = [
            'ProcessRecalculatesOpenSales' => '[ProcessRecalculatesOpenSales] Starting job',
            'RecalculateSalesJob' => 'RecalculateSalesJob started',
            'ApplyHistoryOnUsersV2Job' => '[ApplyHistoryOnUsersV2Job] Starting job'
        ];
        
        $searchPattern = $startPatterns[$jobType] ?? $startPatterns['ProcessRecalculatesOpenSales'];
        
        // Look for job start log with this batch_id
        foreach ($logLines as $line) {
            if (strpos($line, $searchPattern) !== false && 
                strpos($line, $batchId) !== false) {
                
                // Extract PIDs array from the log line
                if (preg_match('/"pids":\[(.*?)\]/', $line, $matches)) {
                    $pidsString = $matches[1];
                    // Remove quotes and split by comma
                    $pidsArray = array_map(function($pid) {
                        return trim($pid, '"');
                    }, explode(',', $pidsString));
                    
                    $pids = array_filter($pidsArray, function($pid) {
                        return !empty(trim($pid));
                    });
                    break;
                }
            }
        }
        
        return array_values($pids);
    }

    /**
     * Extract timestamp from log line
     */
    private function extractTimestampFromLogLine(string $line): ?string
    {
        if (preg_match('/\[(.*?)\]/', $line, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
