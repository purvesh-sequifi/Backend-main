<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service class for managing position-related cache operations
 * 
 * Laravel 10 Redis Cache Tags Issue:
 * Cache::tags()->flush() does not reliably clear tagged cache entries in Redis.
 * This service implements cache versioning pattern as a robust alternative.
 */
class PositionCacheService
{
    /**
     * Clear positions cache when data is modified
     * 
     * For Redis: Uses cache versioning (increment version to invalidate all caches)
     * For File: Uses targeted key deletion as fallback
     *
     * @return bool True if cache was cleared successfully, false otherwise
     */
    public static function clear(): bool
    {
        try {
            // Use cache versioning for Redis (reliable and fast)
            if (config('cache.default') === 'redis') {
                // Change the cache version - this immediately invalidates ALL position caches
                // This is the standard cache invalidation pattern for production systems
                $newVersion = time();
                Cache::put('positions_cache_version', $newVersion, 86400); // 24 hour TTL
                
                // Position cache cleared using version invalidation
                return true;
            } else {
                // For file-based caching, we need to be more comprehensive
                // since we can't use cache tags like Redis

                $cacheKeysCleared = 0;

                // Get current version once (optimization - avoid 240+ calls in loop)
                $version = self::getCacheVersion();

                // Generate all common parameter combinations that match generateCacheKey logic
                $pageValues = range(1, 10); // Common pagination pages
                $perpageValues = [10, 15, 20, 25, 50, 100];

                // Clear the most common cache key combinations
                foreach ($pageValues as $page) {
                    foreach ($perpageValues as $perpage) {
                        // Try different combinations of empty/null filters
                        $paramCombinations = [
                            ['page' => $page, 'perpage' => $perpage], // Just pagination
                            ['page' => $page, 'perpage' => $perpage, 'search_filter' => ''], // With empty search
                            ['page' => $page, 'perpage' => $perpage, 'department' => ''], // With empty department
                            ['page' => $page, 'perpage' => $perpage, 'search_filter' => '', 'department' => ''], // Multiple empty
                        ];

                        foreach ($paramCombinations as $params) {
                            // Use pre-fetched version for consistency
                            $cacheKey = 'positions_optimized_v' . $version . '_' . md5(serialize($params));
                            if (Cache::has($cacheKey)) {
                                Cache::forget($cacheKey);
                                $cacheKeysCleared++;
                            }
                        }
                    }
                }

                // For file-based cache: Laravel hashes cache keys, so we can't scan by prefix
                // Instead, rely on targeted key deletion above or fallback to full clear
                // Alternative approach: Clear Laravel's entire cache if no keys were cleared
                if ($cacheKeysCleared === 0) {
                    // If we couldn't find specific keys, clear cache more aggressively
                    Artisan::call('cache:clear');
                    // Position cache cleared using aggressive cache:clear command
                } else {
                    // Position cache cleared using targeted key matching
                }

                return true;
            }
        } catch (\Exception $e) {
            // Position cache clearing failed, attempting fallback

            // Fallback: Clear entire cache as last resort
            try {
                Artisan::call('cache:clear');
                // Position cache cleared using fallback cache:clear command
                return true;
            } catch (\Exception $fallbackE) {
                Log::error('Position cache clearing completely failed', [
                    'original_error' => $e->getMessage(),
                    'fallback_error' => $fallbackE->getMessage(),
                ]);
                return false;
            }
        }
    }

    /**
     * Cache positions data with proper cache versioning support
     * 
     * IMPORTANT: Does NOT use Cache::tags() due to Laravel 10 Redis issues
     * Uses regular cache with version-based invalidation instead
     *
     * @param Request $request The request containing cache key parameters
     * @param callable $callback The function to execute if cache miss
     * @param int $ttl Cache time-to-live in seconds (default: 300 = 5 minutes)
     * @return mixed The cached or freshly retrieved data
     */
    public static function remember(Request $request, callable $callback, int $ttl = 300)
    {
        $cacheKey = self::generateCacheKey($request);

        // IMPORTANT: Don't use tagged cache - it's broken in Laravel Redis
        // Use regular cache with version-based invalidation instead
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Get the current cache version
     * When this version changes, all old cache keys become invalid
     *
     * @return string The current cache version
     */
    private static function getCacheVersion(): string
    {
        return Cache::remember('positions_cache_version', 86400, function () {
            return (string) time(); // Initial version is current timestamp
        });
    }

    /**
     * Generate cache key based on request parameters with versioning
     * Ensures consistent key generation for the same request parameters
     * Includes version so all caches auto-invalidate when version changes
     *
     * @param Request $request The request containing parameters
     * @return string The generated cache key
     */
    public static function generateCacheKey(Request $request): string
    {
        $params = $request->only([
            'page',
            'perpage',
            'search_filter',
            'pay_frequency_filter',
            'department',
            'override_settelement',
            'permission_group',
            'worker_type',
            'eligible_products',
        ]);

        // Include cache version in key - when version changes, all old keys become inaccessible
        $version = self::getCacheVersion();
        return 'positions_optimized_v' . $version . '_' . md5(serialize($params));
    }
}
