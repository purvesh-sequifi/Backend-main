<?php

namespace App\Http\Controllers;

use App\Models\JobPerformanceLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class QueueDashboardController extends Controller
{
    /**
     * Display the main dashboard
     */
    public function index(): View
    {
        $stats = $this->getQueueStatistics();
        $recentFailedJobs = $this->getRecentFailedJobs();
        $queueHealth = $this->getQueueHealthStatus();
        $systemHealth = $this->getSystemHealthMetrics();

        return view('queue-dashboard.index', compact('stats', 'recentFailedJobs', 'queueHealth', 'systemHealth'));
    }

    /**
     * Get queue statistics for API/AJAX calls
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = $this->getQueueStatistics();
            $performance = $this->getPerformanceMetrics();
            $systemHealth = $this->getSystemHealthMetrics();
            $stuckJobsCount = $this->getStuckJobsCount();

            return response()->json([
                'stats' => $stats,
                'performance' => $performance,
                'system_health' => $systemHealth,
                'stuck_jobs_count' => $stuckJobsCount,
                'timestamp' => now()->format('H:i:s'),
            ]);
        } catch (Exception $e) {
            Log::error('Queue dashboard statistics failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard statistics',
                'debug' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'timestamp' => now()->format('H:i:s'),
            ], 500);
        }
    }

    /**
     * Get failed jobs with pagination
     */
    public function getFailedJobs(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $failedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $jobs = $failedJobs->map(function ($job) {
            $payload = json_decode($job->payload, true);

            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'payload' => $payload,
                'exception' => $job->exception,
                'failed_at' => Carbon::parse($job->failed_at)->format('Y-m-d H:i:s'),
                'failed_at_human' => Carbon::parse($job->failed_at)->diffForHumans(),
                'job_class' => $payload['displayName'] ?? 'Unknown',
            ];
        });

        return response()->json([
            'jobs' => $jobs,
            'pagination' => [
                'current_page' => $failedJobs->currentPage(),
                'last_page' => $failedJobs->lastPage(),
                'per_page' => $failedJobs->perPage(),
                'total' => $failedJobs->total(),
            ],
        ]);
    }

    /**
     * Get detailed failure information for a specific failed job
     */
    public function getFailedJobDetails($uuid): JsonResponse
    {
        try {
            // First, get the basic failed job info (this should always exist)
            $failedJob = DB::table('failed_jobs')
                ->where('uuid', $uuid)
                ->first();

            if (! $failedJob) {
                return response()->json([
                    'error' => 'Failed job not found',
                    'message' => 'The failed job record could not be found',
                ], 404);
            }

            // Parse the payload for additional context
            $payload = json_decode($failedJob->payload, true);

            // Try to get detailed information (this may not exist for older jobs)
            $failedJobDetails = \App\Models\FailedJobDetails::where('failed_job_uuid', $uuid)
                ->with(['jobPerformanceLog'])
                ->first();

            // Build basic info (always available)
            $data = [
                'basic_info' => [
                    'id' => $failedJob->id,
                    'uuid' => $failedJob->uuid,
                    'connection' => $failedJob->connection,
                    'queue' => $failedJob->queue,
                    'failed_at' => Carbon::parse($failedJob->failed_at)->format('Y-m-d H:i:s'),
                    'failed_at_human' => Carbon::parse($failedJob->failed_at)->diffForHumans(),
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'exception' => $failedJob->exception,
                    'payload' => $payload,
                ],
                'has_detailed_info' => $failedJobDetails !== null,
            ];

            // Add detailed info if available
            if ($failedJobDetails) {
                $data['detailed_info'] = [
                    'job_id' => $failedJobDetails->job_id,
                    'failure_reason' => $failedJobDetails->failure_reason,
                    'stack_trace' => $failedJobDetails->stack_trace,
                    'error_type' => $failedJobDetails->error_type,
                    'error_category' => $failedJobDetails->error_category,
                    'is_retryable' => $failedJobDetails->is_retryable,
                    'suggested_resolution' => $failedJobDetails->getSuggestedResolution(),
                    'attempts' => $failedJobDetails->attempts,
                    'max_tries' => $failedJobDetails->max_tries,
                    'timeout' => $failedJobDetails->timeout,
                    'first_failed_at' => $failedJobDetails->first_failed_at?->format('Y-m-d H:i:s'),
                    'last_failed_at' => $failedJobDetails->last_failed_at?->format('Y-m-d H:i:s'),
                ];

                $data['system_info'] = [
                    'memory_usage' => $failedJobDetails->formatted_memory_usage,
                    'peak_memory' => $failedJobDetails->formatted_peak_memory,
                    'execution_time' => $failedJobDetails->formatted_execution_time,
                    'worker_pid' => $failedJobDetails->worker_pid,
                    'php_version' => $failedJobDetails->php_version,
                    'server_info' => json_decode($failedJobDetails->server_info, true),
                ];

                $data['context_data'] = $failedJobDetails->context_data;
                $data['payload_data'] = $failedJobDetails->payload_data;
                $data['resolution_notes'] = $failedJobDetails->resolution_notes;
            } else {
                // Provide helpful message for older jobs
                $data['message'] = 'This is an older failed job. Basic information is available from the failed_jobs table, but enhanced details are not available as they were added after this job failed.';
            }

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error retrieving failed job details',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get failed jobs with enhanced information
     */
    public function getFailedJobsWithDetails(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $errorType = $request->get('error_type');
            $errorCategory = $request->get('error_category');
            $isRetryable = $request->get('is_retryable');
            $jobClass = $request->get('job_class');
            $queue = $request->get('queue');
            $search = $request->get('search');

            $query = DB::table('failed_jobs')
                ->leftJoin('failed_job_details', 'failed_jobs.uuid', '=', 'failed_job_details.failed_job_uuid')
                ->select([
                    'failed_jobs.*',
                    'failed_job_details.error_type',
                    'failed_job_details.error_category',
                    'failed_job_details.is_retryable',
                    'failed_job_details.failure_reason',
                    'failed_job_details.attempts',
                    'failed_job_details.memory_usage_mb',
                    'failed_job_details.execution_time_ms',
                    'failed_job_details.first_failed_at',
                    'failed_job_details.last_failed_at',
                ]);

            // Apply filters
            if ($errorType) {
                $query->where('failed_job_details.error_type', $errorType);
            }

            if ($errorCategory) {
                $query->where('failed_job_details.error_category', $errorCategory);
            }

            if ($isRetryable !== null) {
                $query->where('failed_job_details.is_retryable', $isRetryable);
            }

            if ($jobClass) {
                $query->where('failed_job_details.job_class', 'like', '%'.$jobClass.'%');
            }

            if ($queue) {
                $query->where('failed_jobs.queue', $queue);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('failed_job_details.failure_reason', 'like', '%'.$search.'%')
                        ->orWhere('failed_jobs.payload', 'like', '%'.$search.'%');
                });
            }

            $failedJobs = $query->orderBy('failed_jobs.failed_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $jobs = $failedJobs->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => $payload,
                    'exception' => $job->exception,
                    'failed_at' => Carbon::parse($job->failed_at)->format('Y-m-d H:i:s'),
                    'failed_at_human' => Carbon::parse($job->failed_at)->diffForHumans(),
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'enhanced_info' => [
                        'error_type' => $job->error_type,
                        'error_category' => $job->error_category,
                        'is_retryable' => $job->is_retryable,
                        'failure_reason' => $job->failure_reason ? (strlen($job->failure_reason) > 100 ? substr($job->failure_reason, 0, 100).'...' : $job->failure_reason) : null,
                        'attempts' => $job->attempts,
                        'memory_usage_mb' => $job->memory_usage_mb ? number_format($job->memory_usage_mb, 2) : null,
                        'execution_time_ms' => $job->execution_time_ms,
                        'first_failed_at' => $job->first_failed_at ? Carbon::parse($job->first_failed_at)->format('Y-m-d H:i:s') : null,
                        'last_failed_at' => $job->last_failed_at ? Carbon::parse($job->last_failed_at)->format('Y-m-d H:i:s') : null,
                    ],
                ];
            });

            return response()->json([
                'jobs' => $jobs,
                'pagination' => [
                    'current_page' => $failedJobs->currentPage(),
                    'last_page' => $failedJobs->lastPage(),
                    'per_page' => $failedJobs->perPage(),
                    'total' => $failedJobs->total(),
                ],
                'filters' => [
                    'error_types' => $this->getErrorTypes(),
                    'error_categories' => $this->getErrorCategories(),
                    'job_classes' => $this->getJobClasses(),
                    'queues' => $this->getQueues(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error retrieving failed jobs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available error types for filtering
     */
    private function getErrorTypes()
    {
        return \App\Models\FailedJobDetails::select('error_type')
            ->distinct()
            ->whereNotNull('error_type')
            ->pluck('error_type')
            ->toArray();
    }

    /**
     * Get available error categories for filtering
     */
    private function getErrorCategories()
    {
        return \App\Models\FailedJobDetails::select('error_category')
            ->distinct()
            ->whereNotNull('error_category')
            ->pluck('error_category')
            ->toArray();
    }

    /**
     * Get available job classes for filtering
     */
    private function getJobClasses()
    {
        return \App\Models\FailedJobDetails::select('job_class')
            ->distinct()
            ->whereNotNull('job_class')
            ->pluck('job_class')
            ->map(function ($class) {
                return class_basename($class);
            })
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get available queues for filtering
     */
    private function getQueues()
    {
        return DB::table('failed_jobs')
            ->select('queue')
            ->distinct()
            ->pluck('queue')
            ->toArray();
    }

    /**
     * Get failed jobs statistics
     */
    public function getFailedJobsStats()
    {
        try {
            $stats = [
                'total_failed_jobs' => DB::table('failed_jobs')->count(),
                'total_with_details' => \App\Models\FailedJobDetails::count(),
                'by_error_type' => \App\Models\FailedJobDetails::select('error_type', DB::raw('count(*) as count'))
                    ->groupBy('error_type')
                    ->pluck('count', 'error_type')
                    ->toArray(),
                'by_error_category' => \App\Models\FailedJobDetails::select('error_category', DB::raw('count(*) as count'))
                    ->groupBy('error_category')
                    ->pluck('count', 'error_category')
                    ->toArray(),
                'retryable_vs_non_retryable' => [
                    'retryable' => \App\Models\FailedJobDetails::retryable()->count(),
                    'non_retryable' => \App\Models\FailedJobDetails::nonRetryable()->count(),
                ],
                'recent_failures' => \App\Models\FailedJobDetails::recent()->count(),
                'top_failing_jobs' => \App\Models\FailedJobDetails::select('job_class', DB::raw('count(*) as count'))
                    ->groupBy('job_class')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'job_class' => class_basename($item->job_class),
                            'count' => $item->count,
                        ];
                    })
                    ->toArray(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error retrieving failed jobs statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed job
     */
    public function retryFailedJob($id): JsonResponse
    {
        try {
            // Check if the parameter is a UUID or database ID
            $jobIdentifier = $id;

            // If it's a numeric ID, we need to get the UUID
            if (is_numeric($id)) {
                $failedJob = DB::table('failed_jobs')->where('id', $id)->first();
                if (! $failedJob) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed job not found',
                    ], 404);
                }
                $jobIdentifier = $failedJob->uuid;
            }

            // Use Laravel's built-in queue:retry command for proper job restoration
            $exitCode = Artisan::call('queue:retry', ['id' => $jobIdentifier]);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Job has been queued for retry',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retry job - job may not exist or already processed',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a failed job
     */
    public function deleteFailedJob($id)
    {
        try {
            $deleted = $this->executeQueueOperation(function () use ($id) {
                return DB::table('failed_jobs')->where('id', $id)->delete();
            });

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed job not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Failed job deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry all failed jobs
     */
    public function retryAllFailedJobs(): JsonResponse
    {
        try {
            // Get count of failed jobs before retry
            $failedJobsCount = DB::table('failed_jobs')->count();

            if ($failedJobsCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No failed jobs to retry',
                ]);
            }

            // Use Laravel's built-in queue:retry command for all jobs
            $exitCode = Artisan::call('queue:retry', ['id' => 'all']);

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully retried {$failedJobsCount} failed job(s)",
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retry jobs - command execution failed',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry all jobs: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all failed jobs
     */
    public function clearAllFailedJobs()
    {
        try {
            // Clear failed jobs directly from database instead of using Artisan command
            // This avoids STDIN issues when called from web requests
            $deletedCount = $this->executeQueueOperation(function () {
                return DB::table('failed_jobs')->delete();
            });

            return response()->json([
                'success' => true,
                'message' => "All failed jobs have been cleared. Removed {$deletedCount} failed job(s).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear jobs: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear jobs from a specific queue
     */
    public function clearQueue(Request $request)
    {
        $queue = $request->input('queue');

        try {
            // Clear jobs directly from database instead of using Artisan command
            // This avoids STDIN issues when called from web requests
            // Use a fresh connection for this write operation to avoid connection pool issues
            $deletedCount = $this->executeQueueOperation(function () use ($queue) {
                return DB::table('jobs')->where('queue', $queue)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => "Queue '{$queue}' has been cleared. Removed {$deletedCount} job(s).",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear queue: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restart queue workers
     */
    public function restartWorkers(): JsonResponse
    {
        try {
            Artisan::call('queue:restart');

            return response()->json([
                'success' => true,
                'message' => 'Queue workers have been signaled to restart',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart workers: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get historical performance data
     */
    public function getPerformanceHistory(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);
        $startTime = now()->subHours($hours);

        // Get actual hourly statistics from performance logs
        $hourlyStats = JobPerformanceLog::getHourlyStats($startTime, now());

        // Create a complete timeline with 0 values for missing hours
        $data = [];
        for ($i = $hours; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $hourKey = $hour->format('Y-m-d H:00:00');

            // Find matching stat or create empty one
            $stat = $hourlyStats->firstWhere('hour', $hourKey);

            $data[] = [
                'hour' => $hour->format('H:i'),
                'timestamp' => $hour->timestamp,
                'processed' => $stat ? $stat->completed_jobs : 0,
                'failed' => $stat ? $stat->failed_jobs : 0,
                'pending' => $this->getJobsPendingAtHour($hour),
                'avg_processing_time' => $stat && $stat->avg_processing_time_ms
                    ? round($stat->avg_processing_time_ms / 1000, 2)
                    : 0,
                'total_jobs' => $stat ? $stat->total_jobs : 0,
            ];
        }

        return response()->json($data);
    }

    /**
     * Get detailed performance analytics
     */
    public function getPerformanceAnalytics(Request $request): JsonResponse
    {
        $days = $request->get('days', 7);
        $startDate = now()->subDays($days);

        // Queue performance breakdown
        $queueStats = JobPerformanceLog::getQueueStats($startDate, now());

        // Daily aggregation
        $dailyStats = JobPerformanceLog::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs,
                AVG(CASE WHEN status = "completed" THEN processing_time_ms ELSE NULL END) as avg_processing_time_ms,
                MAX(processing_time_ms) as max_processing_time_ms,
                AVG(memory_usage_mb) as avg_memory_usage_mb,
                MAX(memory_usage_mb) as max_memory_usage_mb
            ')
            ->whereBetween('created_at', [$startDate, now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Job class performance
        $jobClassStats = JobPerformanceLog::selectRaw('
                job_class,
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs,
                AVG(CASE WHEN status = "completed" THEN processing_time_ms ELSE NULL END) as avg_processing_time_ms,
                AVG(memory_usage_mb) as avg_memory_usage_mb
            ')
            ->whereBetween('created_at', [$startDate, now()])
            ->groupBy('job_class')
            ->orderByDesc('total_jobs')
            ->limit(10)
            ->get();

        return response()->json([
            'queue_stats' => $queueStats,
            'daily_stats' => $dailyStats,
            'job_class_stats' => $jobClassStats,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'days' => $days,
            ],
        ]);
    }

    /**
     * Get queue statistics
     */
    private function getQueueStatistics()
    {
        try {
            // Get actual queue names from database (jobs that have been dispatched)
            $databaseQueues = DB::table('jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue');

            // Get queue names from failed jobs table
            $failedJobQueues = DB::table('failed_jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue');

            // Get dynamically discovered queue names
            $discoveredQueues = $this->discoverConfiguredQueues();

            // Merge all queue sources and get unique names
            $queueNames = $databaseQueues
                ->merge($failedJobQueues)
                ->merge($discoveredQueues)
                ->unique()
                ->filter() // Remove empty/null values
                ->sort()
                ->values();

            $stats = [];

            foreach ($queueNames as $queue) {
                $pending = DB::table('jobs')->where('queue', $queue)->count();
                $processing = DB::table('jobs')
                    ->where('queue', $queue)
                    ->whereNotNull('reserved_at')
                    ->count();
                $failed24h = DB::table('failed_jobs')
                    ->where('queue', $queue)
                    ->where('failed_at', '>=', now()->subDay())
                    ->count();

                $stats[$queue] = [
                    'queue' => $queue,
                    'pending' => $pending,
                    'processing' => $processing,
                    'total' => $pending + $processing,
                    'failed_24h' => $failed24h,
                    'status' => $this->getQueueStatus($pending + $processing, $failed24h),
                ];
            }

            return $stats;
        } catch (Exception $e) {
            Log::error('Error getting queue statistics: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get queues that have running workers only
     */
    private function getWorkerOnlyQueues()
    {
        try {
            $runningWorkers = $this->getRunningWorkerProcesses();
            $workerQueues = collect($runningWorkers)
                ->pluck('queue')
                ->flatMap(function ($queueList) {
                    // Split comma-separated queue lists (e.g., "default,high,low" -> ["default", "high", "low"])
                    return array_map('trim', explode(',', $queueList));
                })
                ->unique()
                ->filter()
                ->sort()
                ->values();

            // If no workers are detected, fall back to active jobs queues
            if ($workerQueues->isEmpty()) {
                Log::info('No running workers detected, falling back to active jobs queues');

                return $this->getActiveJobsOnlyQueues();
            }

            Log::info('Worker-only queue detection', [
                'running_workers' => count($runningWorkers),
                'unique_queues' => $workerQueues->toArray(),
            ]);

            return $workerQueues;
        } catch (\Exception $e) {
            Log::error('Failed to detect worker queues: '.$e->getMessage());

            return $this->getActiveJobsOnlyQueues();
        }
    }

    /**
     * Get queues that have active jobs or recent failures only
     */
    private function getActiveJobsOnlyQueues()
    {
        try {
            // Get queue names that currently have jobs
            $databaseQueues = DB::connection('queue_monitoring')->table('jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue');

            // Get queue names from failed jobs in last 24 hours
            $recentFailedQueues = DB::connection('queue_monitoring')->table('failed_jobs')
                ->select('queue')
                ->where('failed_at', '>=', now()->subDay())
                ->distinct()
                ->pluck('queue');

            $activeQueues = $databaseQueues
                ->merge($recentFailedQueues)
                ->unique()
                ->filter()
                ->sort()
                ->values();

            Log::info('Active jobs queue detection', [
                'current_jobs_queues' => $databaseQueues->count(),
                'recent_failed_queues' => $recentFailedQueues->count(),
                'total_active_queues' => $activeQueues->toArray(),
            ]);

            return $activeQueues;
        } catch (\Exception $e) {
            Log::error('Failed to detect active job queues: '.$e->getMessage());

            return collect(['default']); // Fallback to default queue
        }
    }

    /**
     * Get all discovered queues from multiple sources (original behavior)
     */
    private function getAllDiscoveredQueues()
    {
        try {
            // Get actual queue names from database (jobs that have been dispatched)
            $databaseQueues = DB::connection('queue_monitoring')->table('jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue');

            // Get queue names from failed jobs table
            $failedJobQueues = DB::connection('queue_monitoring')->table('failed_jobs')
                ->select('queue')
                ->distinct()
                ->pluck('queue');

            // Get dynamically discovered queue names
            $discoveredQueues = $this->discoverConfiguredQueues();

            // Merge all queue sources and get unique names
            $allQueues = $databaseQueues
                ->merge($failedJobQueues)
                ->merge($discoveredQueues)
                ->unique()
                ->filter() // Remove empty/null values
                ->sort()
                ->values();

            Log::info('All discovered queues detection', [
                'database_queues' => $databaseQueues->count(),
                'failed_job_queues' => $failedJobQueues->count(),
                'discovered_queues' => $discoveredQueues->count(),
                'total_queues' => $allQueues->toArray(),
            ]);

            return $allQueues;
        } catch (\Exception $e) {
            Log::error('Failed to discover all queues: '.$e->getMessage());

            return collect(['default']); // Fallback to default queue
        }
    }

    /**
     * Get scheduled cron jobs information
     */
    public function getCronJobs(Request $request): JsonResponse
    {
        try {
            $cronJobs = $this->parseScheduledCommands();
            $cronSummary = $this->getCronJobSummary($cronJobs);

            return response()->json([
                'cron_jobs' => $cronJobs,
                'summary' => $cronSummary,
                'environment' => app()->environment(),
                'server_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
                'scan_timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get cron jobs: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to load cron jobs',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse scheduled commands from Kernel.php
     */
    private function parseScheduledCommands()
    {
        $cronJobs = [];
        $kernelPath = app_path('Console/Kernel.php');

        if (! file_exists($kernelPath)) {
            return $cronJobs;
        }

        try {
            $kernelContent = file_get_contents($kernelPath);

            // Create a temporary Schedule instance to analyze the scheduling
            $schedule = new \Illuminate\Console\Scheduling\Schedule;

            // Use reflection to call the protected schedule method
            $kernel = new \App\Console\Kernel(app(), app('events'));
            $reflection = new \ReflectionMethod($kernel, 'schedule');
            $reflection->setAccessible(true);
            $reflection->invoke($kernel, $schedule);

            // Get all scheduled events
            $events = $schedule->events();

            foreach ($events as $event) {
                $cronJob = $this->parseScheduledEvent($event);
                if ($cronJob) {
                    $cronJobs[] = $cronJob;
                }
            }

            // Also parse the source code for additional details
            $sourceCommands = $this->parseKernelSourceCode($kernelContent);
            $cronJobs = $this->enrichCronJobsWithSourceDetails($cronJobs, $sourceCommands);

        } catch (\Exception $e) {
            Log::warning('Failed to parse scheduled commands: '.$e->getMessage());

            // Fallback to source code parsing only
            try {
                $kernelContent = file_get_contents($kernelPath);
                $cronJobs = $this->parseKernelSourceCode($kernelContent);
            } catch (\Exception $fallbackError) {
                Log::error('Fallback cron parsing also failed: '.$fallbackError->getMessage());
            }
        }

        return $cronJobs;
    }

    /**
     * Parse a scheduled event object
     */
    private function parseScheduledEvent($event)
    {
        try {
            $command = '';
            $description = '';

            // Get command from event
            if (method_exists($event, 'command')) {
                $command = $event->command;
            } elseif (property_exists($event, 'command')) {
                $command = $event->command;
            }

            // Extract command name from full command
            if (preg_match('/artisan\s+([^\s]+)/', $command, $matches)) {
                $commandName = $matches[1];
            } else {
                $commandName = $command;
            }

            // Get cron expression
            $cronExpression = $event->getExpression();

            // Calculate next run time
            $nextRun = null;
            try {
                $cron = new \Cron\CronExpression($cronExpression);
                $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // If cron parsing fails, set a default
                $nextRun = 'Invalid cron expression';
            }

            // Get frequency description
            $frequency = $this->describeCronExpression($cronExpression);

            // Check if command has conditions
            $hasConditions = $this->eventHasConditions($event);

            return [
                'command' => $commandName,
                'full_command' => $command,
                'frequency' => $frequency,
                'cron_expression' => $cronExpression,
                'next_run' => $nextRun,
                'description' => $description,
                'has_conditions' => $hasConditions,
                'environment_specific' => $this->isEnvironmentSpecific($command),
                'overlapping_protection' => $this->hasOverlappingProtection($event),
                'runs_in_background' => $this->runsInBackground($event),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to parse scheduled event: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Parse Kernel.php source code for command details
     */
    private function parseKernelSourceCode($content)
    {
        $commands = [];

        // Look for schedule->command patterns
        $pattern = '/\$schedule->command\([\'"]([^\'\"]+)[\'"]\)(.*?)(?=\$schedule->|$)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $command = $match[1];
            $chainedMethods = $match[2] ?? '';

            // Parse frequency from chained methods
            $frequency = $this->parseFrequencyFromSource($chainedMethods);

            // Check for conditions
            $hasConditions = strpos($chainedMethods, '->when(') !== false;

            // Check for overlapping protection
            $hasOverlapping = strpos($chainedMethods, '->withoutOverlapping()') !== false;

            // Check for background execution
            $runsInBackground = strpos($chainedMethods, '->runInBackground()') !== false;

            // Extract comments (descriptions)
            $description = $this->extractCommentForCommand($content, $command);

            $commands[] = [
                'command' => $command,
                'frequency' => $frequency,
                'description' => $description,
                'has_conditions' => $hasConditions,
                'overlapping_protection' => $hasOverlapping,
                'runs_in_background' => $runsInBackground,
                'source_method_chain' => trim($chainedMethods),
            ];
        }

        return $commands;
    }

    /**
     * Parse frequency from source code method chain
     */
    private function parseFrequencyFromSource($methodChain)
    {
        // Common frequency patterns
        $frequencies = [
            'everyMinute()' => 'Every minute',
            'everyTwoMinutes()' => 'Every 2 minutes',
            'everyThreeMinutes()' => 'Every 3 minutes',
            'everyFourMinutes()' => 'Every 4 minutes',
            'everyFiveMinutes()' => 'Every 5 minutes',
            'everyTenMinutes()' => 'Every 10 minutes',
            'everyFifteenMinutes()' => 'Every 15 minutes',
            'everyThirtyMinutes()' => 'Every 30 minutes',
            'hourly()' => 'Hourly',
            'everyTwoHours()' => 'Every 2 hours',
            'everyThreeHours()' => 'Every 3 hours',
            'everyFourHours()' => 'Every 4 hours',
            'everySixHours()' => 'Every 6 hours',
            'daily()' => 'Daily',
            'weeklyOn(' => 'Weekly',
            'monthlyOn(' => 'Monthly',
            'yearlyOn(' => 'Yearly',
        ];

        foreach ($frequencies as $pattern => $description) {
            if (strpos($methodChain, $pattern) !== false) {
                // For specific time patterns
                if (preg_match('/dailyAt\([\'"]([^\'\"]+)[\'"]\)/', $methodChain, $matches)) {
                    return "Daily at {$matches[1]}";
                }
                if (preg_match('/weeklyOn\((\d+),\s*[\'"]([^\'\"]+)[\'"]\)/', $methodChain, $matches)) {
                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $dayName = $days[$matches[1]] ?? "Day {$matches[1]}";

                    return "Weekly on {$dayName} at {$matches[2]}";
                }
                if (preg_match('/monthlyOn\((\d+),\s*[\'"]([^\'\"]+)[\'"]\)/', $methodChain, $matches)) {
                    return "Monthly on day {$matches[1]} at {$matches[2]}";
                }

                return $description;
            }
        }

        return 'Unknown frequency';
    }

    /**
     * Extract comment/description for a command
     */
    private function extractCommentForCommand($content, $command)
    {
        $lines = explode("\n", $content);
        $commandPattern = preg_quote($command, '/');

        foreach ($lines as $index => $line) {
            if (preg_match("/schedule->command.*{$commandPattern}/", $line)) {
                // Look for comment in previous lines
                for ($i = $index - 1; $i >= 0; $i--) {
                    $prevLine = trim($lines[$i]);
                    if (strpos($prevLine, '//') === 0) {
                        return trim(substr($prevLine, 2));
                    } elseif (! empty($prevLine) && strpos($prevLine, '//') === false) {
                        break; // Stop if we hit non-comment code
                    }
                }
                break;
            }
        }

        return '';
    }

    /**
     * Enrich cron jobs with source code details
     */
    private function enrichCronJobsWithSourceDetails($cronJobs, $sourceCommands)
    {
        foreach ($cronJobs as &$cronJob) {
            $sourceCommand = collect($sourceCommands)->firstWhere('command', $cronJob['command']);
            if ($sourceCommand) {
                $cronJob['description'] = $sourceCommand['description'] ?: $cronJob['description'];
                $cronJob['source_details'] = $sourceCommand;
            }
        }

        return $cronJobs;
    }

    /**
     * Describe cron expression in human-readable format
     */
    private function describeCronExpression($expression)
    {
        try {
            // Common cron patterns
            $patterns = [
                '* * * * *' => 'Every minute',
                '0 * * * *' => 'Hourly',
                '0 0 * * *' => 'Daily at midnight',
                '0 12 * * *' => 'Daily at noon',
                '0 0 * * 0' => 'Weekly on Sunday',
                '0 0 1 * *' => 'Monthly on the 1st',
                '0 0 1 1 *' => 'Yearly on January 1st',
            ];

            if (isset($patterns[$expression])) {
                return $patterns[$expression];
            }

            // Try to parse with cron expression library
            if (class_exists('\Cron\CronExpression')) {
                $cron = new \Cron\CronExpression($expression);

                // This is a simplified description - you could use a more sophisticated library
                return "Custom schedule ({$expression})";
            }

            return "Custom schedule ({$expression})";
        } catch (\Exception $e) {
            return "Invalid cron ({$expression})";
        }
    }

    /**
     * Check if event has conditions
     */
    private function eventHasConditions($event)
    {
        try {
            // Check if there are any filters/conditions on the event
            $reflection = new \ReflectionObject($event);
            if ($reflection->hasProperty('filters')) {
                $filtersProperty = $reflection->getProperty('filters');
                $filtersProperty->setAccessible(true);
                $filters = $filtersProperty->getValue($event);

                return ! empty($filters);
            }
        } catch (\Exception $e) {
            // If we can't check, assume no conditions
        }

        return false;
    }

    /**
     * Check if command is environment specific
     */
    private function isEnvironmentSpecific($command)
    {
        $envKeywords = ['production', 'staging', 'local', 'testing', 'development'];
        foreach ($envKeywords as $keyword) {
            if (stripos($command, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if event has overlapping protection
     */
    private function hasOverlappingProtection($event)
    {
        try {
            $reflection = new \ReflectionObject($event);
            if ($reflection->hasProperty('withoutOverlapping')) {
                $property = $reflection->getProperty('withoutOverlapping');
                $property->setAccessible(true);

                return $property->getValue($event);
            }
        } catch (\Exception $e) {
            // If we can't check, assume no protection
        }

        return false;
    }

    /**
     * Check if event runs in background
     */
    private function runsInBackground($event)
    {
        try {
            $reflection = new \ReflectionObject($event);
            if ($reflection->hasProperty('runInBackground')) {
                $property = $reflection->getProperty('runInBackground');
                $property->setAccessible(true);

                return $property->getValue($event);
            }
        } catch (\Exception $e) {
            // If we can't check, assume foreground
        }

        return false;
    }

    /**
     * Get cron job summary statistics
     */
    private function getCronJobSummary($cronJobs)
    {
        $summary = [
            'total_jobs' => count($cronJobs),
            'frequency_breakdown' => [],
            'jobs_with_conditions' => 0,
            'jobs_with_overlapping_protection' => 0,
            'background_jobs' => 0,
            'environment_specific_jobs' => 0,
            'next_job' => null,
        ];

        $nextJobs = [];

        foreach ($cronJobs as $job) {
            // Count frequency types
            $freq = $job['frequency'];
            $summary['frequency_breakdown'][$freq] = ($summary['frequency_breakdown'][$freq] ?? 0) + 1;

            // Count features
            if ($job['has_conditions']) {
                $summary['jobs_with_conditions']++;
            }
            if ($job['overlapping_protection'] ?? false) {
                $summary['jobs_with_overlapping_protection']++;
            }
            if ($job['runs_in_background'] ?? false) {
                $summary['background_jobs']++;
            }
            if ($job['environment_specific'] ?? false) {
                $summary['environment_specific_jobs']++;
            }

            // Track next run times
            if ($job['next_run'] && $job['next_run'] !== 'Invalid cron expression') {
                $nextJobs[] = [
                    'command' => $job['command'],
                    'next_run' => $job['next_run'],
                    'frequency' => $job['frequency'],
                ];
            }
        }

        // Sort by next run time and get the earliest
        if (! empty($nextJobs)) {
            usort($nextJobs, function ($a, $b) {
                return strtotime($a['next_run']) - strtotime($b['next_run']);
            });
            $summary['next_job'] = $nextJobs[0];
        }

        return $summary;
    }

    /**
     * Dynamically discover configured queue names from multiple sources
     */
    private function discoverConfiguredQueues()
    {
        $discoveredQueues = collect();

        // 1. Get queues from Laravel configuration files
        $configQueues = $this->scanLaravelQueueConfig();
        $discoveredQueues = $discoveredQueues->merge($configQueues);

        // 2. Get queues from supervisor worker configurations
        $supervisorQueues = $this->extractQueuesFromSupervisorConfig();
        $discoveredQueues = $discoveredQueues->merge($supervisorQueues);

        // 3. Get queues from console commands (schedulers, etc.)
        $commandQueues = $this->scanConsoleCommandQueues();
        $discoveredQueues = $discoveredQueues->merge($commandQueues);

        // 4. Get queues from environment variables
        $envQueues = $this->scanEnvironmentVariableQueues();
        $discoveredQueues = $discoveredQueues->merge($envQueues);

        // 5. Get queues from Job classes (if possible)
        $jobQueues = $this->scanJobClassQueues();
        $discoveredQueues = $discoveredQueues->merge($jobQueues);

        return $discoveredQueues->unique()->filter();
    }

    /**
     * Scan Laravel queue configuration files
     */
    private function scanLaravelQueueConfig()
    {
        $queues = collect();

        try {
            // Default queue from database connection
            $defaultQueue = config('queue.connections.database.queue', 'default');
            if ($defaultQueue) {
                $queues->push($defaultQueue);
            }

            // All configured queue connections
            $connections = config('queue.connections', []);
            foreach ($connections as $connection) {
                if (isset($connection['queue'])) {
                    $queues->push($connection['queue']);
                }
            }

            // Custom queue configurations
            $customQueues = [
                config('queue.field_routes_queue'),
                config('queue.default_queue'),
                config('queue.sales_queue'),
                config('queue.import_queue'),
                config('queue.export_queue'),
                config('queue.automation_queue'),
                config('queue.notification_queue'),
            ];

            $queues = $queues->merge(array_filter($customQueues));

        } catch (Exception $e) {
            Log::warning('Failed to scan Laravel queue config: '.$e->getMessage());
        }

        return $queues;
    }

    /**
     * Extract queue names from supervisor worker configurations
     */
    private function extractQueuesFromSupervisorConfig()
    {
        $queues = collect();

        try {
            $supervisorConfigs = $this->getSupervisorWorkerConfig();

            foreach ($supervisorConfigs as $config) {
                if (isset($config['command'])) {
                    $queueName = $this->extractQueueFromCommand($config['command']);
                    if ($queueName && $queueName !== 'default') {
                        $queues->push($queueName);
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to extract queues from supervisor config: '.$e->getMessage());
        }

        return $queues;
    }

    /**
     * Scan console commands for queue usage
     */
    private function scanConsoleCommandQueues()
    {
        $queues = collect();

        try {
            // Check Kernel.php scheduler
            $kernelPath = app_path('Console/Kernel.php');
            if (file_exists($kernelPath)) {
                $kernelContent = file_get_contents($kernelPath);

                // Look for queue() method calls
                preg_match_all('/->queue\([\'"]([^\'\"]+)[\'"]\)/', $kernelContent, $matches);
                if (! empty($matches[1])) {
                    $queues = $queues->merge($matches[1]);
                }

                // Look for onQueue() method calls
                preg_match_all('/->onQueue\([\'"]([^\'\"]+)[\'"]\)/', $kernelContent, $matches);
                if (! empty($matches[1])) {
                    $queues = $queues->merge($matches[1]);
                }
            }

            // Scan Command classes for queue usage
            $commandsPath = app_path('Console/Commands');
            if (is_dir($commandsPath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($commandsPath)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());

                        // Look for dispatch() calls with queue specification
                        preg_match_all('/dispatch\([^)]+\)->onQueue\([\'"]([^\'\"]+)[\'"]\)/', $content, $matches);
                        if (! empty($matches[1])) {
                            $queues = $queues->merge($matches[1]);
                        }

                        // Look for queue() calls in commands
                        preg_match_all('/->queue\([\'"]([^\'\"]+)[\'"]\)/', $content, $matches);
                        if (! empty($matches[1])) {
                            $queues = $queues->merge($matches[1]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to scan console command queues: '.$e->getMessage());
        }

        return $queues;
    }

    /**
     * Scan environment variables for queue configurations
     */
    private function scanEnvironmentVariableQueues()
    {
        $queues = collect();

        try {
            // Common environment variable patterns for queues
            $envPatterns = [
                'QUEUE_CONNECTION',
                'QUEUE_DEFAULT',
                'FIELD_ROUTES_QUEUE',
                'SALES_QUEUE',
                'IMPORT_QUEUE',
                'EXPORT_QUEUE',
                'AUTOMATION_QUEUE',
                'NOTIFICATION_QUEUE',
                'HIGH_PRIORITY_QUEUE',
                'LOW_PRIORITY_QUEUE',
            ];

            foreach ($envPatterns as $pattern) {
                $value = env($pattern);
                if ($value && is_string($value)) {
                    $queues->push($value);
                }
            }

            // Scan all environment variables for queue-related patterns
            foreach ($_ENV as $key => $value) {
                if (str_contains(strtolower($key), 'queue') && is_string($value)) {
                    // Skip connection types and boolean values
                    if (! in_array(strtolower($value), ['database', 'redis', 'sync', 'true', 'false', '1', '0'])) {
                        $queues->push($value);
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to scan environment variable queues: '.$e->getMessage());
        }

        return $queues;
    }

    /**
     * Scan Job classes for queue specifications
     */
    private function scanJobClassQueues()
    {
        $queues = collect();

        try {
            $jobsPath = app_path('Jobs');
            if (is_dir($jobsPath)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($jobsPath)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $content = file_get_contents($file->getPathname());

                        // Look for $queue property
                        preg_match('/public\s+\$queue\s*=\s*[\'"]([^\'\"]+)[\'"]/', $content, $matches);
                        if (! empty($matches[1])) {
                            $queues->push($matches[1]);
                        }

                        // Look for queue() method implementations
                        preg_match('/public\s+function\s+queue\([^)]*\)\s*\{[^}]*return\s*[\'"]([^\'\"]+)[\'"]/', $content, $matches);
                        if (! empty($matches[1])) {
                            $queues->push($matches[1]);
                        }

                        // Look for onQueue() calls in constructor or methods
                        preg_match_all('/\$this->onQueue\([\'"]([^\'\"]+)[\'"]\)/', $content, $matches);
                        if (! empty($matches[1])) {
                            $queues = $queues->merge($matches[1]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to scan Job class queues: '.$e->getMessage());
        }

        return $queues;
    }

    /**
     * Get recent failed jobs
     */
    private function getRecentFailedJobs($limit = 10)
    {
        return DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'failed_at' => Carbon::parse($job->failed_at)->diffForHumans(),
                    'exception' => substr($job->exception, 0, 100).'...',
                ];
            });
    }

    /**
     * Get overall queue health status
     */
    private function getQueueHealthStatus()
    {
        $totalPending = DB::table('jobs')->count();
        $totalFailed24h = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        if ($totalFailed24h > 50) {
            return 'critical';
        }
        if ($totalPending > 500) {
            return 'warning';
        }
        if ($totalFailed24h > 10) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get queue status based on metrics
     */
    private function getQueueStatus($totalJobs, $failed24h)
    {
        if ($failed24h > 10) {
            return 'critical';
        }
        if ($totalJobs > 100) {
            return 'warning';
        }
        if ($failed24h > 5) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics()
    {
        $cacheKey = 'queue_performance_metrics';

        return Cache::remember($cacheKey, 60, function () {
            return [
                'total_jobs_today' => $this->getJobsProcessedToday(),
                'avg_processing_time' => $this->getAverageProcessingTime(),
                'success_rate' => $this->getSuccessRate(),
                'peak_hour_today' => $this->getPeakHourToday(),
            ];
        });
    }

    /**
     * Helper methods for performance metrics
     */
    private function getJobsProcessedToday()
    {
        return JobPerformanceLog::today()
            ->whereIn('status', ['completed', 'failed'])
            ->count();
    }

    private function getAverageProcessingTime()
    {
        $avgTimeMs = JobPerformanceLog::today()
            ->completed()
            ->whereNotNull('processing_time_ms')
            ->avg('processing_time_ms');

        return $avgTimeMs ? round($avgTimeMs / 1000, 2) : 0; // Convert to seconds
    }

    private function getSuccessRate()
    {
        $totalToday = JobPerformanceLog::today()
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        if ($totalToday == 0) {
            return 100;
        }

        $completedToday = JobPerformanceLog::today()
            ->completed()
            ->count();

        return round(($completedToday / $totalToday) * 100, 2);
    }

    private function getPeakHourToday()
    {
        $peakHour = JobPerformanceLog::selectRaw('HOUR(created_at) as hour, COUNT(*) as job_count')
            ->today()
            ->groupBy('hour')
            ->orderByDesc('job_count')
            ->first();

        return $peakHour ? sprintf('%02d:00', $peakHour->hour) : '--:--';
    }

    private function getJobsProcessedInHour($hour)
    {
        return JobPerformanceLog::whereBetween('created_at', [
            $hour->startOfHour(),
            $hour->copy()->endOfHour(),
        ])
            ->completed()
            ->count();
    }

    private function getJobsFailedInHour($hour)
    {
        return JobPerformanceLog::whereBetween('created_at', [
            $hour->startOfHour(),
            $hour->copy()->endOfHour(),
        ])
            ->failed()
            ->count();
    }

    private function getJobsPendingAtHour($hour)
    {
        // For pending jobs at a specific hour, we'll look at jobs that were created before that hour
        // but processed after, or jobs that are still pending
        return DB::table('jobs')
            ->where('created_at', '<=', $hour->endOfHour()->timestamp)
            ->where(function ($query) use ($hour) {
                $query->where('reserved_at', '>', $hour->endOfHour()->timestamp)
                    ->orWhereNull('reserved_at');
            })
            ->count();
    }

    /**
     * Get system health metrics using direct system calls
     */
    private function getSystemHealthMetrics()
    {
        $cacheKey = 'system_health_metrics';

        return Cache::remember($cacheKey, 30, function () {
            try {
                // Get server metrics directly
                $serverStats = [
                    'disk_free' => $this->getDiskFreeSpace(),
                    'memory' => $this->getMemoryUsage(),
                    'cpu' => $this->getCpuLoad(),
                ];

                // Determine overall server health
                $diskHealthy = $serverStats['disk_free']['percent_free'] > 20;
                $memoryHealthy = isset($serverStats['memory']['free_percent']) ?
                    $serverStats['memory']['free_percent'] > 10 : true;
                $cpuHealthy = isset($serverStats['cpu']['load_average'][0]) ?
                    $serverStats['cpu']['load_average'][0] < 5.0 : true;

                $serverHealthy = $diskHealthy && $memoryHealthy && $cpuHealthy;

                // Check other services
                $database = $this->checkDatabase();
                $cache = $this->checkCache();
                $redis = $this->checkRedis();

                // Determine overall status
                $overallHealthy = $serverHealthy && $database && $cache && ($redis !== false);

                return [
                    'status' => $overallHealthy ? 'healthy' : 'unhealthy',
                    'server' => [
                        'healthy' => $serverHealthy,
                        'cpu' => $this->formatCpuMetrics($serverStats['cpu']),
                        'memory' => $this->formatMemoryMetrics($serverStats['memory']),
                        'disk' => $this->formatDiskMetrics($serverStats['disk_free']),
                    ],
                    'database' => $database,
                    'cache' => $cache,
                    'redis' => $redis,
                    'workers' => null, // Could add worker monitoring here if needed
                    'last_updated' => now()->toIso8601String(),
                ];
            } catch (Exception $e) {
                Log::warning('Failed to fetch system health metrics: '.$e->getMessage());

                return [
                    'status' => 'unavailable',
                    'server' => [
                        'healthy' => false,
                        'cpu' => ['status' => 'unavailable'],
                        'memory' => ['status' => 'unavailable'],
                        'disk' => ['status' => 'unavailable'],
                    ],
                    'database' => false,
                    'cache' => false,
                    'redis' => null,
                    'workers' => null,
                    'last_updated' => now()->toIso8601String(),
                ];
            }
        });
    }

    /**
     * Get disk free space
     */
    private function getDiskFreeSpace()
    {
        $diskTotal = disk_total_space(base_path());
        $diskFree = disk_free_space(base_path());
        $percentFree = ($diskFree / $diskTotal) * 100;

        return [
            'total' => $this->formatBytes($diskTotal),
            'free' => $this->formatBytes($diskFree),
            'percent_free' => round($percentFree, 2),
        ];
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage()
    {
        // Check if we're on a Linux system where we can get memory info
        if (function_exists('shell_exec') && PHP_OS !== 'Windows') {
            try {
                $memInfo = shell_exec('free -b 2>/dev/null');
                if ($memInfo) {
                    $lines = explode("\n", $memInfo);
                    if (isset($lines[1])) {
                        $memoryInfo = preg_split('/\s+/', trim($lines[1]));
                        if (count($memoryInfo) >= 4) {
                            $total = $memoryInfo[1];
                            $used = $memoryInfo[2];
                            $free = $memoryInfo[3];
                            $freePercent = ($free / $total) * 100;

                            return [
                                'total' => $this->formatBytes($total),
                                'used' => $this->formatBytes($used),
                                'free' => $this->formatBytes($free),
                                'free_percent' => round($freePercent, 2),
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                // Silently fail if we can't get memory info
            }
        }

        // If we can't get detailed info, just return PHP memory limit and usage
        return [
            'php_memory_limit' => $this->formatBytes($this->getMemoryLimit()),
            'php_memory_usage' => $this->formatBytes(memory_get_usage(true)),
        ];
    }

    /**
     * Get CPU load
     */
    private function getCpuLoad()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();

            return [
                'load_average' => [
                    round($load[0], 2),  // 1 minute average
                    round($load[1], 2),  // 5 minute average
                    round($load[2], 2),   // 15 minute average
                ],
            ];
        }

        return null;
    }

    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1] * 1024 * 1024;
            } elseif ($matches[2] == 'K') {
                return $matches[1] * 1024;
            } elseif ($matches[2] == 'G') {
                return $matches[1] * 1024 * 1024 * 1024;
            }
        }

        return $memoryLimit;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Check database connection
     */
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check cache
     */
    private function checkCache()
    {
        try {
            $testKey = 'queue_dashboard_cache_test_'.time();
            Cache::put($testKey, 'test', 1);
            $value = Cache::get($testKey);

            return $value === 'test';
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis()
    {
        try {
            // Skip Redis check if not configured
            if (! config('database.redis.default.host')) {
                return null; // Skipped
            }

            Redis::connection()->ping();

            return true;
        } catch (Exception $e) {
            // If Redis isn't critical, just skip
            if (config('cache.default') !== 'redis') {
                return null; // Skipped
            }

            return false;
        }
    }

    /**
     * Format CPU metrics for dashboard display
     */
    private function formatCpuMetrics($cpuData)
    {
        if (! $cpuData || ! isset($cpuData['load_average'])) {
            return ['status' => 'unavailable'];
        }

        $loadAvg = $cpuData['load_average'];
        $currentLoad = $loadAvg[0]; // 1-minute load average

        return [
            'status' => 'available',
            'load_1min' => $currentLoad,
            'load_5min' => $loadAvg[1] ?? 0,
            'load_15min' => $loadAvg[2] ?? 0,
            'status_level' => $this->getCpuStatusLevel($currentLoad),
            'percentage' => min(100, round($currentLoad * 25, 1)), // Convert to rough percentage
        ];
    }

    /**
     * Format memory metrics for dashboard display
     */
    private function formatMemoryMetrics($memoryData)
    {
        if (! $memoryData) {
            return ['status' => 'unavailable'];
        }

        // Handle both detailed memory info and PHP-only memory info
        if (isset($memoryData['total'])) {
            return [
                'status' => 'available',
                'total' => $memoryData['total'],
                'used' => $memoryData['used'],
                'free' => $memoryData['free'],
                'free_percent' => $memoryData['free_percent'],
                'status_level' => $this->getMemoryStatusLevel($memoryData['free_percent']),
            ];
        } else {
            // PHP memory only
            return [
                'status' => 'php_only',
                'php_limit' => $memoryData['php_memory_limit'] ?? 'Unknown',
                'php_usage' => $memoryData['php_memory_usage'] ?? 'Unknown',
                'status_level' => 'info',
            ];
        }
    }

    /**
     * Format disk metrics for dashboard display
     */
    private function formatDiskMetrics($diskData)
    {
        if (! $diskData) {
            return ['status' => 'unavailable'];
        }

        return [
            'status' => 'available',
            'total' => $diskData['total'],
            'free' => $diskData['free'],
            'percent_free' => $diskData['percent_free'],
            'status_level' => $this->getDiskStatusLevel($diskData['percent_free']),
        ];
    }

    /**
     * Get CPU status level based on load average
     */
    private function getCpuStatusLevel($loadAvg)
    {
        if ($loadAvg < 1.0) {
            return 'success';
        } // Low load
        if ($loadAvg < 3.0) {
            return 'warning';
        } // Moderate load

        return 'danger'; // High load
    }

    /**
     * Get memory status level based on free percentage
     */
    private function getMemoryStatusLevel($freePercent)
    {
        if ($freePercent > 50) {
            return 'success';
        } // Plenty of memory
        if ($freePercent > 20) {
            return 'warning';
        } // Getting low

        return 'danger'; // Critical
    }

    /**
     * Get disk status level based on free percentage
     */
    private function getDiskStatusLevel($freePercent)
    {
        if ($freePercent > 50) {
            return 'success';
        } // Plenty of space
        if ($freePercent > 20) {
            return 'warning';
        } // Getting low

        return 'danger'; // Critical
    }

    /**
     * Get stuck jobs with pagination (includes both queue jobs and progress log jobs)
     */
    public function getStuckJobs(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $hoursThreshold = $request->get('hours', 4); // Default: jobs stuck for more than 4 hours

        // Get both types of stuck jobs
        $queueStuckJobs = $this->getQueueStuckJobs($hoursThreshold);
        $progressStuckJobs = $this->getProgressLogStuckJobs($hoursThreshold);

        // Combine and sort all stuck jobs
        $allStuckJobs = collect($queueStuckJobs)->merge($progressStuckJobs)
            ->sortBy('stuck_timestamp')
            ->values();

        // Manual pagination
        $total = $allStuckJobs->count();
        $offset = ($page - 1) * $perPage;
        $paginatedJobs = $allStuckJobs->slice($offset, $perPage)->values();

        return response()->json([
            'jobs' => $paginatedJobs,
            'pagination' => [
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'threshold_hours' => $hoursThreshold,
            'summary' => [
                'queue_stuck_jobs' => count($queueStuckJobs),
                'progress_stuck_jobs' => count($progressStuckJobs),
                'total_stuck_jobs' => $total,
            ],
        ]);
    }

    /**
     * Get stuck jobs from the jobs table (reserved but not processing)
     */
    private function getQueueStuckJobs($hoursThreshold)
    {
        $stuckThreshold = now()->subHours($hoursThreshold);

        $stuckJobs = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $stuckThreshold->timestamp)
            ->orderBy('reserved_at', 'asc')
            ->get();

        return $stuckJobs->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $reservedAt = Carbon::createFromTimestamp($job->reserved_at);

            return [
                'id' => $job->id,
                'type' => 'queue_job',
                'queue' => $job->queue,
                'payload' => $payload,
                'attempts' => $job->attempts,
                'reserved_at' => $reservedAt->format('Y-m-d H:i:s'),
                'reserved_at_human' => $reservedAt->diffForHumans(),
                'stuck_duration' => $reservedAt->diffInMinutes(now()),
                'stuck_duration_human' => $reservedAt->diffForHumans(now(), true),
                'stuck_timestamp' => $reservedAt->timestamp,
                'job_class' => $payload['displayName'] ?? 'Unknown',
                'available_at' => Carbon::createFromTimestamp($job->available_at)->format('Y-m-d H:i:s'),
                'status_message' => 'Reserved but not processing',
            ];
        })->toArray();
    }

    /**
     * Get stuck jobs from job_progress_logs table (processing but stalled)
     */
    private function getProgressLogStuckJobs($hoursThreshold)
    {
        try {
            // Check if JobProgressLog model exists
            if (! class_exists(\App\Models\JobProgressLog::class)) {
                return [];
            }

            $stuckThreshold = now()->subHours($hoursThreshold);

            $stuckJobs = \App\Models\JobProgressLog::where('status', 'processing')
                ->where('started_at', '<', $stuckThreshold)
                ->orderBy('started_at', 'asc')
                ->get();

            return $stuckJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'type' => 'progress_log',
                    'job_id' => $job->job_id,
                    'queue' => $job->queue,
                    'job_class' => $job->job_class,
                    'started_at' => $job->started_at->format('Y-m-d H:i:s'),
                    'started_at_human' => $job->started_at->diffForHumans(),
                    'stuck_duration' => $job->started_at->diffInMinutes(now()),
                    'stuck_duration_human' => $job->started_at->diffForHumans(now(), true),
                    'stuck_timestamp' => $job->started_at->timestamp,
                    'progress' => $job->progress_percentage ?? 0,
                    'message' => $job->message,
                    'current_operation' => $job->current_operation,
                    'status_message' => 'Processing but stalled',
                    'total_records' => $job->total_records,
                    'processed_records' => $job->processed_records,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Could not scan progress log stuck jobs: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Reset a stuck job (supports both queue jobs and progress log jobs)
     */
    public function resetStuckJob($id)
    {
        $request = request();
        $type = $request->get('type', 'queue_job'); // Default to queue_job for backward compatibility

        if ($type === 'progress_log') {
            return $this->resetProgressLogStuckJob($id);
        } else {
            return $this->resetQueueStuckJob($id);
        }
    }

    /**
     * Reset a stuck queue job (clear reserved_at to make it available again)
     */
    private function resetQueueStuckJob($id)
    {
        try {
            $job = DB::table('jobs')->where('id', $id)->first();

            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Queue job not found',
                ], 404);
            }

            if (is_null($job->reserved_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job is not currently reserved/stuck',
                ], 400);
            }

            // Reset the job by clearing reserved_at and resetting attempts
            DB::table('jobs')->where('id', $id)->update([
                'reserved_at' => null,
                'attempts' => 0,
                'available_at' => now()->timestamp,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stuck queue job has been reset and made available for processing',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset stuck queue job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset a stuck progress log job (mark as failed)
     */
    private function resetProgressLogStuckJob($id)
    {
        try {
            if (! class_exists(\App\Models\JobProgressLog::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JobProgressLog model not available',
                ], 400);
            }

            $job = \App\Models\JobProgressLog::where('id', $id)->first();

            if (! $job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Progress log job not found',
                ], 404);
            }

            if ($job->status !== 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => 'Job is not in processing status',
                ], 400);
            }

            // Mark the job as failed
            $job->status = 'failed';
            $job->completed_at = now();
            $job->message = 'Job marked as failed due to being stuck in processing state';

            // Add error information
            $error = $job->error ?? [];
            if (! is_array($error)) {
                $error = [];
            }
            $error['manual_cleanup'] = true;
            $error['reason'] = 'Job was stuck in processing state';
            $error['cleanup_time'] = now()->toDateTimeString();
            $job->error = $error;

            $job->save();

            return response()->json([
                'success' => true,
                'message' => 'Stuck progress log job has been marked as failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset stuck progress log job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a stuck job permanently (supports both queue jobs and progress log jobs)
     */
    public function deleteStuckJob($id)
    {
        $request = request();
        $type = $request->get('type', 'queue_job'); // Default to queue_job for backward compatibility

        if ($type === 'progress_log') {
            return $this->deleteProgressLogStuckJob($id);
        } else {
            return $this->deleteQueueStuckJob($id);
        }
    }

    /**
     * Delete a stuck queue job permanently
     */
    private function deleteQueueStuckJob($id)
    {
        try {
            $deleted = DB::table('jobs')->where('id', $id)->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Queue job not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stuck queue job deleted permanently',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stuck queue job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a stuck progress log job permanently
     */
    private function deleteProgressLogStuckJob($id)
    {
        try {
            if (! class_exists(\App\Models\JobProgressLog::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JobProgressLog model not available',
                ], 400);
            }

            $deleted = \App\Models\JobProgressLog::where('id', $id)->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Progress log job not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stuck progress log job deleted permanently',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stuck progress log job: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset all stuck jobs (both queue jobs and progress log jobs)
     */
    public function resetAllStuckJobs(Request $request): JsonResponse
    {
        $hoursThreshold = $request->get('hours', 4);
        $stuckThreshold = now()->subHours($hoursThreshold);

        try {
            $totalResetCount = 0;
            $queueResetCount = 0;
            $progressResetCount = 0;

            // Reset queue stuck jobs
            $queueResetCount = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<', $stuckThreshold->timestamp)
                ->update([
                    'reserved_at' => null,
                    'attempts' => 0,
                    'available_at' => now()->timestamp,
                ]);

            // Reset progress log stuck jobs
            if (class_exists(\App\Models\JobProgressLog::class)) {
                $stuckJobs = \App\Models\JobProgressLog::where('status', 'processing')
                    ->where('started_at', '<', $stuckThreshold)
                    ->get();

                foreach ($stuckJobs as $job) {
                    $job->status = 'failed';
                    $job->completed_at = now();
                    $job->message = 'Job marked as failed automatically due to being stuck in processing state';

                    // Add error information
                    $error = $job->error ?? [];
                    if (! is_array($error)) {
                        $error = [];
                    }
                    $error['bulk_cleanup'] = true;
                    $error['reason'] = 'Job was stuck in processing state';
                    $error['cleanup_time'] = now()->toDateTimeString();
                    $job->error = $error;

                    $job->save();
                    $progressResetCount++;
                }
            }

            $totalResetCount = $queueResetCount + $progressResetCount;

            return response()->json([
                'success' => true,
                'message' => "Successfully reset {$totalResetCount} stuck job(s) ({$queueResetCount} queue jobs, {$progressResetCount} progress log jobs)",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset stuck jobs: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all stuck jobs (delete them permanently - both queue jobs and progress log jobs)
     */
    public function clearAllStuckJobs(Request $request): JsonResponse
    {
        $hoursThreshold = $request->get('hours', 4);
        $stuckThreshold = now()->subHours($hoursThreshold);

        try {
            $totalDeletedCount = 0;
            $queueDeletedCount = 0;
            $progressDeletedCount = 0;

            // Delete queue stuck jobs
            $queueDeletedCount = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<', $stuckThreshold->timestamp)
                ->delete();

            // Delete progress log stuck jobs
            if (class_exists(\App\Models\JobProgressLog::class)) {
                $progressDeletedCount = \App\Models\JobProgressLog::where('status', 'processing')
                    ->where('started_at', '<', $stuckThreshold)
                    ->delete();
            }

            $totalDeletedCount = $queueDeletedCount + $progressDeletedCount;

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$totalDeletedCount} stuck job(s) ({$queueDeletedCount} queue jobs, {$progressDeletedCount} progress log jobs)",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear stuck jobs: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get stuck jobs count for dashboard stats (includes both types)
     */
    private function getStuckJobsCount($hoursThreshold = 4)
    {
        $stuckThreshold = now()->subHours($hoursThreshold);

        // Count queue stuck jobs
        $queueStuckCount = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $stuckThreshold->timestamp)
            ->count();

        // Count progress log stuck jobs
        $progressStuckCount = 0;
        try {
            if (class_exists(\App\Models\JobProgressLog::class)) {
                $progressStuckCount = \App\Models\JobProgressLog::where('status', 'processing')
                    ->where('started_at', '<', $stuckThreshold)
                    ->count();
            }
        } catch (\Exception $e) {
            Log::warning('Could not count progress log stuck jobs: '.$e->getMessage());
        }

        return $queueStuckCount + $progressStuckCount;
    }

    /**
     * Get comprehensive worker status by scanning supervisor configurations and active processes
     */
    public function getWorkerStatus(Request $request): JsonResponse
    {
        try {
            $scanTimestamp = now()->toIso8601String();

            // Get configured workers from supervisor
            $configuredWorkers = $this->getSupervisorWorkerConfig();

            // Get running worker processes
            $runningWorkers = $this->getRunningWorkerProcesses();

            // Analyze worker distribution and status
            $workerAnalysis = $this->analyzeWorkerStatus($configuredWorkers, $runningWorkers);

            // Get queue status
            $queueStatus = $this->getDetailedQueueStatus();

            // Calculate health metrics
            $totalConfigured = array_sum(array_column($configuredWorkers, 'numprocs'));
            $totalRunning = count($runningWorkers);
            $healthStatus = $this->calculateWorkerHealth($totalConfigured, $totalRunning, $workerAnalysis);

            return response()->json([
                'status' => $healthStatus['overall'],
                'scan_timestamp' => $scanTimestamp,
                'summary' => [
                    'total_configured' => $totalConfigured,
                    'total_running' => $totalRunning,
                    'health_score' => $healthStatus['score'],
                    'issues_detected' => count($healthStatus['issues']),
                ],
                'configured_workers' => $configuredWorkers,
                'running_processes' => $runningWorkers,
                'worker_analysis' => $workerAnalysis,
                'queue_status' => $queueStatus,
                'health_details' => $healthStatus,
                'system_info' => [
                    'supervisor_status' => $this->getSupervisorStatus(),
                    'queue_driver' => config('queue.default'),
                    'environment' => config('app.env'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Worker status check failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'scan_timestamp' => now()->toIso8601String(),
                'error' => 'Failed to retrieve worker status: '.$e->getMessage(),
                'fallback_data' => [
                    'running_processes' => $this->getRunningWorkerProcesses(),
                    'queue_status' => $this->getDetailedQueueStatus(),
                ],
            ], 500);
        }
    }

    /**
     * Scan supervisor configuration files to get worker configurations
     */
    private function getSupervisorWorkerConfig()
    {
        $configuredWorkers = [];
        $supervisorPaths = [
            '/etc/supervisor/conf.d/',
            '/etc/supervisord.d/',
            '/usr/local/etc/supervisor/conf.d/',
        ];

        foreach ($supervisorPaths as $path) {
            if (is_dir($path)) {
                $configuredWorkers = array_merge($configuredWorkers, $this->scanSupervisorDirectory($path));
            }
        }

        // Fallback: scan common configuration patterns
        if (empty($configuredWorkers)) {
            $configuredWorkers = $this->getFallbackWorkerConfig();
        }

        return $configuredWorkers;
    }

    /**
     * Scan a supervisor directory for Laravel worker configurations
     */
    private function scanSupervisorDirectory($path)
    {
        $workers = [];

        try {
            $files = glob($path.'*.conf');

            foreach ($files as $file) {
                // Look for Laravel worker configuration files
                if (strpos(basename($file), 'laravel') !== false ||
                    strpos(basename($file), 'worker') !== false ||
                    strpos(basename($file), 'queue') !== false) {

                    $workerConfig = $this->parseSupervisorConfig($file);
                    if ($workerConfig) {
                        $workers[] = $workerConfig;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to scan supervisor directory {$path}: ".$e->getMessage());
        }

        return $workers;
    }

    /**
     * Parse supervisor configuration file to extract worker details
     */
    private function parseSupervisorConfig($filePath)
    {
        try {
            $content = file_get_contents($filePath);
            $config = parse_ini_string($content, true);

            foreach ($config as $sectionName => $section) {
                // Look for program sections that contain queue:work commands
                if (strpos($sectionName, 'program:') === 0 &&
                    isset($section['command']) &&
                    strpos($section['command'], 'queue:work') !== false) {

                    return [
                        'name' => str_replace('program:', '', $sectionName),
                        'file' => basename($filePath),
                        'command' => $section['command'],
                        'numprocs' => intval($section['numprocs'] ?? 1),
                        'autostart' => $section['autostart'] ?? 'true',
                        'autorestart' => $section['autorestart'] ?? 'true',
                        'user' => $section['user'] ?? 'www-data',
                        'queue' => $this->extractQueueFromCommand($section['command']),
                        'timeout' => $this->extractTimeoutFromCommand($section['command']),
                        'log_file' => $section['stdout_logfile'] ?? null,
                        'configured' => true,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to parse supervisor config {$filePath}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Extract queue name from artisan command
     */
    private function extractQueueFromCommand($command)
    {
        if (preg_match('/--queue[=\s]+([^\s]+)/', $command, $matches)) {
            // Return the full queue list as found in command (will be split later if needed)
            return $matches[1];
        }

        return 'default';
    }

    /**
     * Extract timeout from artisan command
     */
    private function extractTimeoutFromCommand($command)
    {
        if (preg_match('/--timeout[=\s]+(\d+)/', $command, $matches)) {
            return intval($matches[1]);
        }

        return null;
    }

    /**
     * Get fallback worker configuration when supervisor configs are not accessible
     */
    private function getFallbackWorkerConfig()
    {
        // Try to infer workers from running processes or environment
        $workers = [];

        // Check if there are common worker patterns in environment or config
        $commonQueues = ['default', 'high', 'low', 'parlley', 'execute', 'finalize', 'onetimepayment'];

        foreach ($commonQueues as $queue) {
            // Check if there are jobs for this queue or it's mentioned in config
            $jobsCount = DB::table('jobs')->where('queue', $queue)->count();
            $failedCount = DB::table('failed_jobs')->where('queue', $queue)->count();

            if ($jobsCount > 0 || $failedCount > 0) {
                $workers[] = [
                    'name' => "laravel-{$queue}-worker",
                    'file' => 'inferred',
                    'command' => "php artisan queue:work --queue={$queue}",
                    'numprocs' => 1,
                    'queue' => $queue,
                    'configured' => false,
                    'inferred' => true,
                ];
            }
        }

        return $workers;
    }

    /**
     * Get currently running worker processes
     */
    private function getRunningWorkerProcesses()
    {
        $processes = [];

        try {
            // Primary method: scan running processes
            $processes = array_merge($processes, $this->scanRunningProcesses());

            // Secondary method: check supervisor status
            $supervisorProcesses = $this->getSupervisorRunningWorkers();
            $processes = array_merge($processes, $supervisorProcesses);

            // Remove duplicates based on PID
            $processes = $this->deduplicateProcesses($processes);

        } catch (\Exception $e) {
            Log::warning('Failed to get running worker processes: '.$e->getMessage());
        }

        return $processes;
    }

    /**
     * Scan for running queue:work processes
     */
    private function scanRunningProcesses()
    {
        $processes = [];

        try {
            // Scan for artisan queue:work processes (excluding Horizon)
            $command = 'ps aux | grep "artisan queue:work" | grep -v grep | grep -v horizon';
            $output = shell_exec($command);

            if ($output) {
                $lines = array_filter(explode("\n", trim($output)));

                foreach ($lines as $line) {
                    $process = $this->parseProcessLine($line);
                    if ($process) {
                        $processes[] = $process;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to scan running processes: '.$e->getMessage());
        }

        return $processes;
    }

    /**
     * Parse a process line from ps aux output
     */
    private function parseProcessLine($line)
    {
        $parts = preg_split('/\s+/', trim($line), 11);

        if (count($parts) < 11) {
            return null;
        }

        $pid = $parts[1];
        $cpuUsage = floatval($parts[2]);
        $memoryUsage = floatval($parts[3]);
        $startTime = $parts[8];
        $runTime = $parts[9];
        $command = $parts[10];

        // Extract queue name from command
        $queue = 'default';
        if (preg_match('/--queue[=\s]+([^\s]+)/', $command, $matches)) {
            $queue = $matches[1];
        }

        // Calculate uptime
        $uptime = $this->calculateProcessUptime($startTime, $runTime);

        return [
            'pid' => $pid,
            'queue' => $queue,
            'cpu_usage' => $cpuUsage,
            'memory_usage' => $memoryUsage,
            'start_time' => $startTime,
            'run_time' => $runTime,
            'uptime' => $uptime,
            'command' => $command,
            'status' => 'running',
            'managed_by' => 'direct',
            'user' => $parts[0],
        ];
    }

    /**
     * Get running workers from supervisor
     */
    private function getSupervisorRunningWorkers()
    {
        $workers = [];

        try {
            $command = 'supervisorctl status 2>/dev/null | grep -E "(laravel|worker|queue)" | grep RUNNING';
            $output = shell_exec($command);

            if ($output) {
                $lines = array_filter(explode("\n", trim($output)));

                foreach ($lines as $line) {
                    $worker = $this->parseSupervisorStatusLine($line);
                    if ($worker) {
                        $workers[] = $worker;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get supervisor worker status: '.$e->getMessage());
        }

        return $workers;
    }

    /**
     * Parse supervisor status line
     */
    private function parseSupervisorStatusLine($line)
    {
        // Format: "laravel-worker:laravel-worker_00   RUNNING   pid 1234, uptime 1:23:45"
        if (preg_match('/^(\S+)\s+RUNNING\s+pid\s+(\d+),\s+uptime\s+(.+)$/', $line, $matches)) {
            $name = $matches[1];
            $pid = $matches[2];
            $uptime = $matches[3];

            // Extract queue from name
            $queue = 'default';
            if (preg_match('/(?:laravel-)?(.+?)(?:-worker)?(?:_\d+)?$/', $name, $queueMatches)) {
                $queue = $queueMatches[1];
            }

            return [
                'pid' => $pid,
                'name' => $name,
                'queue' => $queue,
                'status' => 'running',
                'uptime' => $uptime,
                'managed_by' => 'supervisor',
                'source' => 'supervisorctl',
            ];
        }

        return null;
    }

    /**
     * Remove duplicate processes based on PID
     */
    private function deduplicateProcesses($processes)
    {
        $seen = [];
        $unique = [];

        foreach ($processes as $process) {
            $pid = $process['pid'];
            if (! isset($seen[$pid])) {
                $seen[$pid] = true;
                $unique[] = $process;
            }
        }

        return $unique;
    }

    /**
     * Analyze worker status and health
     */
    private function analyzeWorkerStatus($configuredWorkers, $runningWorkers)
    {
        $analysis = [
            'worker_distribution' => [],
            'queue_coverage' => [],
            'missing_workers' => [],
            'unexpected_workers' => [],
            'performance_metrics' => [],
        ];

        // Analyze distribution by queue
        $queueDistribution = [];
        foreach ($runningWorkers as $worker) {
            $queue = $worker['queue'];
            if (! isset($queueDistribution[$queue])) {
                $queueDistribution[$queue] = [];
            }
            $queueDistribution[$queue][] = $worker;
        }
        $analysis['worker_distribution'] = $queueDistribution;

        // Check queue coverage
        $configuredQueues = array_unique(array_column($configuredWorkers, 'queue'));
        $runningQueues = array_unique(array_column($runningWorkers, 'queue'));

        $analysis['queue_coverage'] = [
            'configured_queues' => $configuredQueues,
            'running_queues' => $runningQueues,
            'missing_queues' => array_diff($configuredQueues, $runningQueues),
            'unexpected_queues' => array_diff($runningQueues, $configuredQueues),
        ];

        // Find missing workers
        foreach ($configuredWorkers as $config) {
            $expectedCount = $config['numprocs'];
            $actualCount = count($queueDistribution[$config['queue']] ?? []);

            if ($actualCount < $expectedCount) {
                $analysis['missing_workers'][] = [
                    'name' => $config['name'],
                    'queue' => $config['queue'],
                    'expected' => $expectedCount,
                    'actual' => $actualCount,
                    'missing' => $expectedCount - $actualCount,
                ];
            }
        }

        // Calculate performance metrics
        $analysis['performance_metrics'] = $this->calculateWorkerPerformanceMetrics($runningWorkers);

        return $analysis;
    }

    /**
     * Calculate worker performance metrics
     */
    private function calculateWorkerPerformanceMetrics($workers)
    {
        if (empty($workers)) {
            return [
                'avg_cpu_usage' => 0,
                'avg_memory_usage' => 0,
                'total_memory_mb' => 0,
                'worker_count' => 0,
            ];
        }

        $totalCpu = 0;
        $totalMemory = 0;
        $count = 0;

        foreach ($workers as $worker) {
            if (isset($worker['cpu_usage'])) {
                $totalCpu += $worker['cpu_usage'];
                $count++;
            }
            if (isset($worker['memory_usage'])) {
                $totalMemory += $worker['memory_usage'];
            }
        }

        return [
            'avg_cpu_usage' => $count > 0 ? round($totalCpu / $count, 2) : 0,
            'avg_memory_usage' => $count > 0 ? round($totalMemory / $count, 2) : 0,
            'total_memory_mb' => round($totalMemory * 1024 / 100, 2), // Convert from % to approximate MB
            'worker_count' => count($workers),
        ];
    }

    /**
     * Get detailed queue status
     */
    private function getDetailedQueueStatus()
    {
        $queueStats = $this->getQueueStatistics();
        $queueStatus = [];

        foreach ($queueStats as $queue => $stats) {
            $queueStatus[$queue] = [
                'pending_jobs' => $stats['pending'],
                'processing_jobs' => $stats['processing'],
                'failed_24h' => $stats['failed_24h'],
                'status' => $stats['status'],
                'load_level' => $this->calculateQueueLoadLevel($stats),
            ];
        }

        return $queueStatus;
    }

    /**
     * Calculate queue load level
     */
    private function calculateQueueLoadLevel($stats)
    {
        $total = $stats['pending'] + $stats['processing'];

        if ($total == 0) {
            return 'idle';
        }
        if ($total < 10) {
            return 'low';
        }
        if ($total < 100) {
            return 'moderate';
        }
        if ($total < 1000) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * Calculate overall worker health
     */
    private function calculateWorkerHealth($totalConfigured, $totalRunning, $analysis)
    {
        $health = [
            'overall' => 'healthy',
            'score' => 100,
            'issues' => [],
            'recommendations' => [],
        ];

        // Check if workers are running
        if ($totalRunning == 0) {
            $health['overall'] = 'critical';
            $health['score'] = 0;
            $health['issues'][] = 'No queue workers are running';
            $health['recommendations'][] = 'Start queue workers immediately';

            return $health;
        }

        // Check worker count vs configured
        if ($totalConfigured > 0) {
            $coverageRatio = $totalRunning / $totalConfigured;

            if ($coverageRatio < 0.5) {
                $health['overall'] = 'critical';
                $health['score'] -= 40;
                $health['issues'][] = "Only {$totalRunning} of {$totalConfigured} configured workers are running";
                $health['recommendations'][] = 'Check supervisor status and restart workers';
            } elseif ($coverageRatio < 0.8) {
                $health['overall'] = 'warning';
                $health['score'] -= 20;
                $health['issues'][] = "Running {$totalRunning} of {$totalConfigured} configured workers";
                $health['recommendations'][] = 'Investigate why some workers are not running';
            }
        }

        // Check missing queues
        $missingQueues = $analysis['queue_coverage']['missing_queues'] ?? [];
        if (! empty($missingQueues)) {
            $health['overall'] = $health['overall'] === 'critical' ? 'critical' : 'warning';
            $health['score'] -= 15 * count($missingQueues);
            $health['issues'][] = 'Missing workers for queues: '.implode(', ', $missingQueues);
            $health['recommendations'][] = 'Start workers for missing queues';
        }

        // Check performance metrics
        $perfMetrics = $analysis['performance_metrics'] ?? [];
        if (isset($perfMetrics['avg_cpu_usage']) && $perfMetrics['avg_cpu_usage'] > 80) {
            $health['overall'] = $health['overall'] === 'critical' ? 'critical' : 'warning';
            $health['score'] -= 10;
            $health['issues'][] = 'High CPU usage detected in workers';
            $health['recommendations'][] = 'Monitor worker performance and consider scaling';
        }

        $health['score'] = max(0, $health['score']);

        return $health;
    }

    /**
     * Get supervisor daemon status
     */
    private function getSupervisorStatus()
    {
        try {
            $output = shell_exec('supervisorctl status 2>/dev/null | head -1');

            return $output ? 'running' : 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Calculate process uptime from ps output
     */
    private function calculateProcessUptime($startTime, $runTime)
    {
        // This is a simplified calculation - you might want to enhance this
        // based on your specific ps output format
        return $runTime;
    }

    /**
     * Execute a queue operation with proper connection management
     * This prevents connection pool exhaustion by ensuring connections are properly released
     */
    private function executeQueueOperation(callable $operation)
    {
        try {
            // Disable query log to reduce memory usage for bulk operations
            $originalLogSetting = DB::logging();
            DB::disableQueryLog();

            // Execute the operation
            $result = $operation();

            // Restore query log setting
            if ($originalLogSetting) {
                DB::enableQueryLog();
            }

            // Force connection cleanup by disconnecting after write operations
            DB::disconnect();

            return $result;
        } catch (\Exception $e) {
            // Restore query log setting on error
            if (isset($originalLogSetting) && $originalLogSetting) {
                DB::enableQueryLog();
            }

            // Ensure connection cleanup even on error
            DB::disconnect();

            throw $e;
        }
    }
}
