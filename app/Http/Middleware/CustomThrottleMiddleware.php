<?php

// app/Http/Middleware/CustomThrottleMiddleware.php

namespace App\Http\Middleware;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;
use Illuminate\Support\Facades\Log;

class CustomThrottleMiddleware extends BaseThrottleRequests
{
    /**
     * Create a 'too many attempts' exception.
     */
    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        return new ThrottleRequestsException(
            'To ensure system stability, recalculate action is limited to 5 requests per minute per user.',
            null,
            $headers
        );
    }

    /**
     * Get the user identifier for the request.
     */
    protected function resolveRequestSignature($request)
    {
        $token = $request->bearerToken();

        // If there's no Bearer token, use the IP address as the key.
        if (empty($token)) {
            $ipAddress = $request->ip();
            Log::info("Custom Throttle: Rate Limit by IP Address: {$ipAddress}");

            return sha1($ipAddress);
        }

        Log::info("Custom Throttle: Rate Limit by Token: {$token}");

        return sha1($token);
    }
}
