<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class QueueDashboardRateLimiter
{
    protected $rateLimiter;

    public function __construct(RateLimiter $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $tier = 'default'): BaseResponse
    {
        $key = $this->resolveRequestSignature($request, $tier);
        $limits = $this->getLimitsForTier($tier);

        if ($this->rateLimiter->tooManyAttempts($key, $limits['max_attempts'])) {
            $this->logRateLimitExceeded($request, $tier, $key);

            return $this->buildRateLimitResponse($key, $limits['max_attempts'], $limits['decay_minutes']);
        }

        $this->rateLimiter->hit($key, $limits['decay_minutes'] * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $limits['max_attempts'], $limits['decay_minutes']);
    }

    /**
     * Get rate limits for specific tier
     */
    protected function getLimitsForTier(string $tier): array
    {
        $configLimits = config('queue-dashboard.rate_limits', []);
        $environment = app()->environment();

        // Get base limits from config
        $limits = [];
        foreach ($configLimits as $tierName => $config) {
            $limits[$tierName] = [
                'max_attempts' => $config['max_attempts'],
                'decay_minutes' => $config['decay_minutes'],
            ];
        }

        // Apply environment-specific overrides
        $envOverrides = config("queue-dashboard.environment_overrides.{$environment}", []);
        if (isset($envOverrides[$tier])) {
            $limits[$tier] = array_merge($limits[$tier] ?? [], $envOverrides[$tier]);
        }

        // Fallback defaults if config is missing
        $fallbackLimits = [
            'dashboard' => ['max_attempts' => 60, 'decay_minutes' => 1],
            'read' => ['max_attempts' => 120, 'decay_minutes' => 1],
            'write' => ['max_attempts' => 30, 'decay_minutes' => 1],
            'bulk' => ['max_attempts' => 5, 'decay_minutes' => 5],
            'critical' => ['max_attempts' => 2, 'decay_minutes' => 10],
        ];

        return $limits[$tier] ?? $fallbackLimits[$tier] ?? $fallbackLimits['read'];
    }

    /**
     * Resolve the rate limiting signature for the request
     */
    protected function resolveRequestSignature(Request $request, string $tier): string
    {
        $identifier = $this->getClientIdentifier($request);
        $route = $request->route() ? $request->route()->getName() : $request->path();

        return "queue_dashboard_rate_limit:{$tier}:{$identifier}:{$route}";
    }

    /**
     * Get client identifier for rate limiting
     */
    protected function getClientIdentifier(Request $request): string
    {
        // Check configuration for whether to use user ID
        $useUserId = config('queue-dashboard.security.use_user_id', true);

        // Try to get authenticated user ID first (if enabled)
        if ($useUserId && $request->user()) {
            return 'user:'.$request->user()->id;
        }

        // Fall back to IP address
        return 'ip:'.$request->ip();
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $key, int $maxAttempts, int $decayMinutes): BaseResponse
    {
        $retryAfter = $this->rateLimiter->availableIn($key);

        $message = config('queue-dashboard.security.violation_message', 'Rate limit exceeded. Too many queue dashboard requests.');
        $details = [
            'message' => $message,
            'error' => 'rate_limit_exceeded',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
            'window' => $decayMinutes.' minutes',
        ];

        $response = response()->json($details, 429)
            ->header('Retry-After', $retryAfter);

        // Add headers if configured to do so
        if (config('queue-dashboard.security.include_headers', true)) {
            $response = $response
                ->header('X-RateLimit-Limit', $maxAttempts)
                ->header('X-RateLimit-Remaining', 0)
                ->header('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
        }

        return $response;
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(BaseResponse $response, string $key, int $maxAttempts, int $decayMinutes): BaseResponse
    {
        $remaining = $this->rateLimiter->remaining($key, $maxAttempts);
        $resetTime = $this->rateLimiter->availableIn($key);

        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', max(0, $remaining))
            ->header('X-RateLimit-Reset', now()->addSeconds($resetTime)->timestamp)
            ->header('X-RateLimit-Window', $decayMinutes.'m');
    }

    /**
     * Log rate limit exceeded attempts
     */
    protected function logRateLimitExceeded(Request $request, string $tier, string $key): void
    {
        // Only log if configured to do so
        if (! config('queue-dashboard.security.log_violations', true)) {
            return;
        }

        Log::warning('Queue Dashboard Rate Limit Exceeded', [
            'tier' => $tier,
            'tier_description' => config("queue-dashboard.rate_limits.{$tier}.description", 'Unknown operation'),
            'key' => $key,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route() ? $request->route()->getName() : null,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_id' => $request->user() ? $request->user()->id : null,
            'timestamp' => now()->toISOString(),
            'limit_exceeded' => $this->getLimitsForTier($tier),
        ]);
    }
}
