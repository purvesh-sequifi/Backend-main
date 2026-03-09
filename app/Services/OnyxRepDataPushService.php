<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{
    User,
    Integration,
    InterigationTransactionLog,
    UserOrganizationHistory,
    UserManagerHistory
};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Onyx Rep Data Push Integration Service
 *
 * Handles pushing representative data to Onyx webhook for profile updates
 * and onboarding completion events.
 */
class OnyxRepDataPushService
{
    /**
     * Integration name in the database
     */
    private const INTEGRATION_NAME = 'OnyxRepDataPush';

    /**
     * Rate limiter key prefix
     */
    private const RATE_LIMITER_KEY = 'onyxrepdatapush_api';

    /**
     * Maximum API requests per minute
     */
    private const MAX_REQUESTS_PER_MINUTE = 60;

    /**
     * Maximum retry attempts for failed API calls
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Initial retry delay in milliseconds
     */
    private const INITIAL_RETRY_DELAY_MS = 1000;

    /**
     * Rep data types
     */
    private const TYPE_NEW_REP = 'new_rep';
    private const TYPE_REP_UPDATE = 'rep_update';

    /**
     * Get the active Onyx Rep Data Push integration configuration
     *
     * @return Integration|null
     */
    private function getIntegration(): ?Integration
    {
        return Integration::where(['name' => self::INTEGRATION_NAME, 'status' => 1])->first();
    }

    /**
     * Send user data to Onyx Rep Data Push webhook
     *
     * @param int $userId - The user ID to sync
     * @param string $eventType - The type: 'new_rep' or 'rep_update'
     * @param array|null $previousData - Previous user data for comparison (optional)
     * @param bool $isBulk - Whether this is from bulk sync (default: false)
     * @return array - Response array with status, message, and API response
     */
    public function sendUserData(int $userId, string $eventType = 'rep_update', ?array $previousData = null, bool $isBulk = false): array
    {
        try {
            $integration = $this->getIntegration();

            if (!$integration) {
                Log::error('OnyxRepDataPush Integration not found');
                return ['status' => false, 'message' => 'Integration not found'];
            }

            $user = User::where('id', $userId)->with('office')->first();

            if (!$user) {
                Log::error('User not found for user_id: ' . $userId);
                return ['status' => false, 'message' => 'User not found'];
            }

            // Build the payload
            $payload = $this->buildUserPayload($user, $eventType, $previousData);

            // Send the API request (includes rep_id)
            $url = trim($integration->value);
            $apiResponse = $this->sendApiRequest($url, $payload);

            // Log the transaction (includes rep_id for future lookups)
            $apiName = $isBulk
                ? 'Push Rep Data bulk push - ' . $eventType
                : 'Push Rep Data ' . $eventType;
            $this->logTransaction($apiName, $payload, $apiResponse, $url);

            return $apiResponse;

        } catch (\Exception $e) {
            Log::error('Error sending user data to OnyxRepDataPush', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => false,
                'message' => 'Error sending user data to OnyxRepDataPush: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send user data silently (fire and forget)
     *
     * NOTE: This is NOT truly async - it runs synchronously but doesn't throw exceptions.
     * It's called "silent" because it logs errors without affecting the main flow.
     *
     * For true async processing, dispatch a queue job instead.
     *
     * @param int $userId - The user ID to sync
     * @param string $eventType - The type: 'new_rep' or 'rep_update'
     * @param array|null $previousData - Previous user data for comparison (optional)
     * @return void
     */
    public function sendUserDataSilently(int $userId, string $eventType = 'rep_update', ?array $previousData = null): void
    {
        try {
            $this->sendUserData($userId, $eventType, $previousData);
        } catch (\Exception $e) {
            Log::error('Silent OnyxRepDataPush sync failed', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Build the user payload for Onyx Rep Data Push webhook
     *
     * Acceptance Logic:
     * - New rep (type = "new_rep"): All previous_* fields must be null, rep_* fields must be populated
     * - Rep update (type = "rep_update"): All fields must contain values (previous + current data)
     *
     * @param User $user - The user model
     * @param string $eventType - The type: 'new_rep' or 'rep_update'
     * @param array|null $previousData - Previous user data for comparison
     * @return array - Formatted payload
     */
    private function buildUserPayload(User $user, string $eventType, ?array $previousData = null): array
    {
        // Determine if this is a new rep or update
        $isNewRep = $this->isNewRep($user, $eventType);
        $type = $isNewRep ? self::TYPE_NEW_REP : self::TYPE_REP_UPDATE;

        // Get office name from user's office relationship (already loaded)
        $officeName = $user->office?->office_name;

        // Build current data
        $currentData = [
            'rep_id' => $user->id, // Store rep_id for reliable lookup
            'rep_name' => trim($user->first_name . ' ' . $user->last_name),
            'rep_phone_number' => $user->mobile_no ?? '',
            'rep_email' => $user->email ?? '',
            'team_name' => $officeName ?? ''
        ];

        // Get previous data if not provided and this is an update
        if ($previousData === null && !$isNewRep) {
            $previousData = $this->getPreviousUserData($user->id);
        }

        // For rep_update: If previous data is missing/incomplete, use current data as previous
        // This ensures all fields have values as per acceptance criteria
        if (!$isNewRep) {
            $previousData = [
                'rep_name' => $previousData['rep_name'] ?? $currentData['rep_name'],
                'rep_phone_number' => $previousData['rep_phone_number'] ?? $currentData['rep_phone_number'],
                'rep_email' => $previousData['rep_email'] ?? $currentData['rep_email'],
                'team_name' => $previousData['team_name'] ?? $currentData['team_name']
            ];

            // Log warning if we had to use current data as previous (indicates missing history)
            if (empty($previousData['rep_name']) && empty($previousData['rep_email'])) {
                Log::warning('OnyxRepDataPush: Missing previous data for rep_update, using current data as fallback', [
                    'rep_id' => $user->id,
                    'event_type' => $eventType
                ]);
            }
        }

        // Build the complete payload according to acceptance criteria
        $payload = [
            'rep_id' => $currentData['rep_id'], // Include rep_id for identification and future lookups
            'type' => $type,
            'previous_name' => $isNewRep ? null : $previousData['rep_name'],
            'previous_phone_number' => $isNewRep ? null : $previousData['rep_phone_number'],
            'previous_email' => $isNewRep ? null : $previousData['rep_email'],
            'previous_team_name' => $isNewRep ? null : $previousData['team_name'],
            'rep_name' => $currentData['rep_name'],
            'rep_phone_number' => $currentData['rep_phone_number'],
            'rep_email' => $currentData['rep_email'],
            'team_name' => $currentData['team_name']
        ];

        return $payload;
    }

    /**
     * Determine if this is a new rep based on event type
     *
     * @param User $user - The user model
     * @param string $eventType - The type: 'new_rep' or 'rep_update'
     * @return bool - True if new rep, false if update
     */
    private function isNewRep(User $user, string $eventType): bool
    {
        return $eventType === self::TYPE_NEW_REP;
    }

    /**
     * Get previous user data from the last successful sync
     *
     * Uses rep_id for reliable lookup (handles email/phone changes)
     *
     * @param int $userId - The user ID
     * @return array - Previous user data
     */
    private function getPreviousUserData(int $userId): array
    {
        try {
            // Query using JSON extraction for rep_id (reliable even if email changes)
            $lastLog = InterigationTransactionLog::where('interigation_name', 'OnyxRepDataPush')
                ->whereRaw('JSON_EXTRACT(payload, "$.rep_id") = ?', [$userId])
                ->orderBy('created_at', 'DESC')
                ->first();

            if (!$lastLog) {
                return [];
            }

            $payload = json_decode($lastLog->payload, true);

            return [
                'rep_name' => $payload['rep_name'] ?? null,
                'rep_phone_number' => $payload['rep_phone_number'] ?? null,
                'rep_email' => $payload['rep_email'] ?? null,
                'team_name' => $payload['team_name'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Error getting previous user data', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Send API request to Onyx webhook using cURL with rate limiting and retry logic
     *
     * @param string $url - API endpoint URL
     * @param array $fields - Payload data
     * @return array - Response array with status and API response
     */
    private function sendApiRequest(string $url, array $fields): array
    {
        // Check rate limit
        if (!$this->checkRateLimit()) {
            Log::warning('OnyxRepDataPush API rate limit exceeded', [
                'url' => $url
            ]);

            return [
                'status' => false,
                'message' => 'Rate limit exceeded',
                'statusCode' => 429,
                'response' => 'Too many requests. Please try again later.'
            ];
        }

        $headers = [
            "accept: application/json",
            "content-type: application/json"
        ];

        // Retry logic with exponential backoff
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            try {
                $response = curlRequestWithStatusCode($url, json_encode($fields), $headers, 'POST');

                // Check if response indicates success
                if ($response['statusCode'] >= 200 && $response['statusCode'] < 300) {
                    return [
                        'status' => true,
                        'message' => 'Success',
                        'statusCode' => $response['statusCode'],
                        'response' => $response['body'],
                        'attempts' => $attempt + 1
                    ];
                }

                // Non-2xx response - don't retry client errors (4xx)
                if ($response['statusCode'] >= 400 && $response['statusCode'] < 500) {
                    Log::warning('OnyxRepDataPush API client error (not retrying)', [
                        'status_code' => $response['statusCode'],
                        'attempt' => $attempt + 1
                    ]);

                    return [
                        'status' => false,
                        'message' => 'Client error',
                        'statusCode' => $response['statusCode'],
                        'response' => $response['body']
                    ];
                }

                // Server error (5xx) - retry
                Log::warning('OnyxRepDataPush API server error (will retry)', [
                    'status_code' => $response['statusCode'],
                    'attempt' => $attempt + 1
                ]);

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('OnyxRepDataPush API request failed (will retry)', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage()
                ]);
            }

            $attempt++;

            // Don't sleep after last attempt
            if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                // Exponential backoff: 1s, 2s, 4s
                $delayMs = self::INITIAL_RETRY_DELAY_MS * pow(2, $attempt - 1);
                usleep($delayMs * 1000); // Convert to microseconds
            }
        }

        // All retries failed
        $errorMessage = $lastException ? $lastException->getMessage() : 'Unknown error';

        Log::error('OnyxRepDataPush API request failed after all retries', [
            'attempts' => self::MAX_RETRY_ATTEMPTS,
            'error' => $errorMessage
        ]);

        return [
            'status' => false,
            'message' => 'Request failed after ' . self::MAX_RETRY_ATTEMPTS . ' attempts',
            'statusCode' => $lastException ? $lastException->getCode() : 0,
            'response' => $errorMessage
        ];
    }

    /**
     * Check if API rate limit allows request
     *
     * @return bool - True if request is allowed, false if rate limited
     */
    private function checkRateLimit(): bool
    {
        $key = self::RATE_LIMITER_KEY . ':' . now()->format('Y-m-d-H-i');

        return RateLimiter::attempt(
            $key,
            self::MAX_REQUESTS_PER_MINUTE,
            function() {
                // Request is allowed
            },
            60 // Decay time in seconds
        );
    }

    /**
     * Log transaction to database
     *
     * @param string $apiName - API operation name
     * @param array $payload - Request payload
     * @param array $response - API response
     * @param string $url - API endpoint
     * @return void
     */
    private function logTransaction(string $apiName, array $payload, array $response, string $url): void
    {
        InterigationTransactionLog::create([
            'interigation_name' => 'OnyxRepDataPush',
            'api_name' => $apiName,
            'payload' => json_encode($payload),
            'response' => json_encode($response),
            'url' => $url,
        ]);
    }
}
