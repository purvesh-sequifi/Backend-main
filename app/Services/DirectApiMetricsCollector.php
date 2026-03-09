<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DirectApiMetricsCollector
{
    protected $enabled;

    protected $samplingRate;

    public function __construct()
    {
        $this->enabled = config('api-performance.enabled', true);
        $this->samplingRate = config('api-performance.collection.sampling_rate', 100);
    }

    public function collectDirect(array $metrics): void
    {
        if (! $this->enabled || ! $this->shouldSample()) {
            return;
        }

        try {
            // Direct SQLite write - optimized for speed
            DB::connection('api_metrics')->table('api_requests')->insert([
                'endpoint' => $metrics['endpoint'],
                'method' => $metrics['method'],
                'status_code' => $metrics['status_code'],
                'response_time_ms' => $metrics['response_time_ms'],
                'memory_usage_mb' => $metrics['memory_usage_mb'] ?? 0,
                'peak_memory_mb' => $metrics['peak_memory_mb'] ?? 0,
                'cpu_usage_percent' => $metrics['cpu_usage_percent'],
                'request_size_kb' => $metrics['request_size_kb'] ?? 0,
                'response_size_kb' => $metrics['response_size_kb'] ?? 0,
                'timestamp' => $metrics['timestamp'],
                'user_id' => $metrics['user_id'],
                'ip_address' => $metrics['ip_address'],
                'user_agent_hash' => $metrics['user_agent_hash'],
            ]);

        } catch (\Exception $e) {
            // Silent failure to protect API performance
            // Log only in debug mode to avoid log bloat
            if (config('app.debug')) {
                Log::debug('API metrics write failed: '.$e->getMessage());
            }
        }
    }

    protected function shouldSample(): bool
    {
        // Convert rate to percentage if needed (1.0 = 100%)
        $rate = $this->samplingRate > 1 ? $this->samplingRate : $this->samplingRate * 100;

        // Check if adaptive sampling is enabled
        if (config('api-performance.collection.adaptive_sampling.enabled', false)) {
            $currentLoad = $this->getCurrentRequestLoad();
            $threshold = config('api-performance.collection.adaptive_sampling.request_threshold_per_minute', 1000);

            if ($currentLoad > $threshold) {
                $reducedRate = config('api-performance.collection.adaptive_sampling.reduced_sampling_rate', 0.25);
                $rate = $reducedRate * 100;
            }
        }

        return rand(1, 100) <= $rate;
    }

    protected function getCurrentRequestLoad(): int
    {
        // Simple load estimation based on recent requests
        try {
            $recentCount = DB::connection('api_metrics')
                ->table('api_requests')
                ->where('timestamp', '>=', now()->subMinute())
                ->count();

            return $recentCount;
        } catch (\Exception $e) {
            return 0; // If we can't check, assume low load
        }
    }
}
