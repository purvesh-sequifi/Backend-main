<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiPerformanceMonitor
{
    protected $excludedPaths = [
        'api-performance',
        'health',
        'metrics',
        '_debugbar',
        'sign-and-store', // SSE endpoint - exclude from monitoring
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Skip monitoring for excluded paths or if disabled
        if (! config('api-performance.enabled', true) || $this->shouldSkipMonitoring($request)) {
            return $next($request);
        }

        // Capture start metrics with minimal overhead
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);

        // Get CPU usage (if available, minimal impact)
        $startCpu = $this->getCpuUsage();

        $response = $next($request);

        // Capture end metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        $endCpu = $this->getCpuUsage();

        // Direct database write (ultra-fast)
        $this->collectMetricsDirect([
            'endpoint' => $this->getEndpointIdentifier($request),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 4),
            'peak_memory_mb' => round($endPeakMemory / 1024 / 1024, 4),
            'cpu_usage_percent' => $this->calculateCpuUsage($startCpu, $endCpu, $endTime - $startTime),
            'request_size_kb' => round($request->header('Content-Length', 0) / 1024, 2),
            'response_size_kb' => $this->getResponseSize($response),
            'timestamp' => now(),
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent_hash' => md5($request->userAgent() ?? ''),
        ]);

        return $response;
    }

    protected function shouldSkipMonitoring(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedPaths as $excludedPath) {
            if (str_contains($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    protected function getEndpointIdentifier(Request $request): string
    {
        $route = $request->route();

        if ($route) {
            $routeName = $route->getName();

            // Skip Laravel's auto-generated route names (they start with "generated::")
            if ($routeName && ! str_starts_with($routeName, 'generated::')) {
                return $routeName;
            }

            // Use normalized actual request path for better grouping
            return $this->normalizeUri($request->path());
        }

        return $this->normalizeUri($request->path());
    }

    protected function normalizeUri(string $uri): string
    {
        // Convert dynamic parameters to placeholders for better aggregation
        // /api/users/123 -> /api/users/{id}
        // /api/v1/users/123/posts/456 -> /api/v1/users/{id}/posts/{id}
        // But preserve version numbers like v1, v2, etc.

        // Replace numeric IDs with {id} placeholder
        $normalized = preg_replace('/\/\d+(?=\/|$)/', '/{id}', $uri);

        // Replace UUIDs and long alphanumeric strings with {uuid} placeholder
        $normalized = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(?=\/|$)/i', '/{uuid}', $normalized);

        // Replace long random-looking alphanumeric strings (likely tokens/hashes) with {hash} placeholder
        // Only replace strings that are 20+ characters and purely alphanumeric (no underscores, hyphens, or common words)
        $normalized = preg_replace('/\/[a-zA-Z0-9]{20,}(?=\/|$)/', '/{hash}', $normalized);

        // Replace shorter mixed-case strings that contain both upper/lower case and numbers (likely tokens)
        $normalized = preg_replace('/\/(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])[a-zA-Z0-9]{12,}(?=\/|$)/', '/{hash}', $normalized);

        return $normalized;
    }

    protected function getCpuUsage()
    {
        // Lightweight CPU usage check (Linux only)
        if (PHP_OS_FAMILY === 'Linux' && function_exists('shell_exec')) {
            try {
                $load = shell_exec('ps -o %cpu -p '.getmypid().' | tail -1');

                return floatval(trim($load));
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    protected function calculateCpuUsage($startCpu, $endCpu, $duration)
    {
        if ($startCpu === null || $endCpu === null) {
            return null;
        }

        // Simple CPU usage calculation
        return round($endCpu - $startCpu, 2);
    }

    protected function getResponseSize(Response $response): float
    {
        // For streamed responses (like SSE), getContent() returns false
        $content = $response->getContent();
        if ($content === false || !is_string($content)) {
            // Streamed responses can't determine size easily
            return 0.0;
        }
        return round(strlen($content) / 1024, 2);
    }

    protected function collectMetricsDirect(array $metrics): void
    {
        // Check if ClickHouse metrics collection is enabled
        if (!config('api-performance.clickhouse.enabled', false)) {
            return; // Early return if disabled
        }
        
        // Add domain_name for multi-tenant support
        $metrics['domain_name'] = config('app.domain_name', 'unknown');
        
        try {
            // Synchronous write to ClickHouse
            $collector = app(\App\Services\ClickHouseApiMetricsCollector::class);
            $collector->collectDirect($metrics);
        } catch (\Throwable $e) {
            // Silent failure - metrics are non-critical analytics data
            if (config('app.debug')) {
                Log::debug('ClickHouse metrics collection failed', [
                    'error' => $e->getMessage(),
                    'endpoint' => $metrics['endpoint'] ?? 'unknown',
                ]);
            }
        }
    }
}
