<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Octane Response Cache Middleware
 * 
 * Caches GET request responses to improve performance in Octane environment.
 * Only caches successful GET requests without authentication.
 */
class OctaneResponseCache
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  $ttl  Time to live in seconds (default: 60)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, int $ttl = 60): Response
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Skip caching for authenticated requests (security)
        if ($request->user()) {
            return $next($request);
        }

        // Skip caching if cache is disabled
        if (!config('cache.default') || config('cache.default') === 'array') {
            return $next($request);
        }

        // Generate cache key based on full URL and query parameters
        $cacheKey = $this->getCacheKey($request);

        // Try to get cached response
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse !== null) {
            // Return cached response with cache hit header
            return response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders(array_merge($cachedResponse['headers'], [
                    'X-Cache' => 'HIT',
                    'X-Cache-Key' => $cacheKey,
                ]));
        }

        // Process request
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
            ], $ttl);

            // Add cache miss header
            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-Key', $cacheKey);
        }

        return $response;
    }

    /**
     * Generate a cache key for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getCacheKey(Request $request): string
    {
        return 'octane_cache:' . md5(
            $request->fullUrl() . 
            '|' . $request->getMethod() . 
            '|' . json_encode($request->headers->get('Accept'))
        );
    }

    /**
     * Get headers that should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return array
     */
    protected function getCacheableHeaders(Response $response): array
    {
        $cacheableHeaders = [
            'Content-Type',
            'Content-Encoding',
            'Content-Language',
            'Vary',
        ];

        $headers = [];
        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}

