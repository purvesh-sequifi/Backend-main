<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Check health of the application and its dependencies
     */
    public function check(): JsonResponse
    {
        $services = [
            'app' => true,
            'server' => $this->checkServer(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'redis' => $this->checkRedis(),
            'auth' => $this->checkAuth(),
        ];

        // Check if any service is explicitly false or contains a healthy:false value
        // Exclude Redis from health determination as requested
        $isUnhealthy = false;
        foreach ($services as $key => $service) {
            // Skip Redis check for overall health determination
            if ($key === 'redis') {
                continue;
            }

            if ($service === false) {
                $isUnhealthy = true;
                break;
            }
            if (is_array($service) && isset($service['healthy']) && $service['healthy'] === false) {
                $isUnhealthy = true;
                break;
            }
        }

        $status = ! $isUnhealthy ? 'healthy' : 'unhealthy';
        $statusCode = ! $isUnhealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
        ], $statusCode);
    }

    /**
     * Check server health (CPU, memory, disk space)
     *
     * @return array|bool
     */
    private function checkServer()
    {
        try {
            // Get system information
            $serverStats = [
                'disk_free' => $this->getDiskFreeSpace(),
                'memory' => $this->getMemoryUsage(),
                'cpu' => $this->getCpuLoad(),
            ];

            // Determine overall server health
            $diskHealthy = $serverStats['disk_free']['percent_free'] > 40; // More than 40% free space
            $memoryHealthy = isset($serverStats['memory']['free_percent']) ?
                $serverStats['memory']['free_percent'] > 10 : true; // More than 10% free memory
            $cpuHealthy = isset($serverStats['cpu']['load_average'][0]) ?
                $serverStats['cpu']['load_average'][0] < 80 : true; // Load average below 80%

            $allHealthy = $diskHealthy && $memoryHealthy && $cpuHealthy;

            // Return both status and detailed metrics
            return [
                'healthy' => $allHealthy,
                'metrics' => $serverStats,
            ];
        } catch (Exception $e) {
            Log::error('Server health check failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get disk free space
     */
    private function getDiskFreeSpace(): array
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
    private function getMemoryUsage(): ?array
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
    private function getCpuLoad(): ?array
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
    private function getMemoryLimit(): int
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
    private function formatBytes(int $bytes, int $precision = 2): string
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
    private function checkDatabase(): bool
    {
        try {
            // Test database connection by running simple query
            DB::connection()->getPdo();

            // Run a simple query to verify that database server is responsive
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $endTime = microtime(true);

            // Calculate query time in milliseconds
            $queryTime = ($endTime - $startTime) * 1000;

            // Log slow queries (over 500ms) but still return true
            if ($queryTime > 500) {
                Log::warning("Database health check took {$queryTime}ms, which is slow");
            }

            return true;
        } catch (Exception $e) {
            report($e); // Send to Sentry if configured

            return false;
        }
    }

    /**
     * Check cache
     */
    private function checkCache(): bool
    {
        try {
            $testKey = 'health_check_test_'.time();
            Cache::put($testKey, 'test', 1);
            $value = Cache::get($testKey);

            return $value === 'test';
        } catch (Exception $e) {
            report($e); // Send to Sentry if configured

            return false;
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis(): ?bool
    {
        try {
            // Skip Redis check in these cases:
            // 1. Redis host is not configured
            // 2. Environment is production and Redis isn't critical
            // 3. Redis is explicitly disabled for health checks
            if (! config('database.redis.default.host') ||
                (config('app.env') === 'production' && ! config('cache.default') === 'redis') ||
                config('app.skip_redis_health_check', false)) {

                Log::info('Skipping Redis health check on '.config('app.env').' environment');

                // Return null to indicate Redis check was skipped, not failed
                return null;
            }

            // Try to connect to Redis using the configured client (phpredis or predis)
            Redis::connection()->ping();

            return true;
        } catch (Exception $e) {
            // Only report serious Redis errors if Redis is actually needed
            if (config('cache.default') === 'redis') {
                report($e); // Send to Sentry if configured
                Log::error('Redis health check failed: '.$e->getMessage());

                return false;
            } else {
                // Redis error, but Redis isn't being used as primary cache
                Log::warning('Redis health check failed but Redis is not primary cache: '.$e->getMessage());

                return null; // Skip rather than fail
            }
        }
    }

    /**
     * Check authentication system
     */
    private function checkAuth(): bool
    {
        try {
            // Check if the user model and authentication middleware are accessible
            if (! class_exists(\App\Models\User::class)) {
                Log::error('User model not found during health check');

                return false;
            }

            // Count users to verify user table is accessible
            // This only verifies database access to the users table
            // without exposing sensitive data
            $userCount = \App\Models\User::count();

            // If we have no users at all, that's a problem
            if ($userCount === 0) {
                Log::warning('No users found in database during health check');

                return false;
            }

            return true;
        } catch (Exception $e) {
            report($e); // Send to Sentry if configured

            return false;
        }
    }
}
