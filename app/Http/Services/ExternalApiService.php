<?php

namespace App\Http\Services;

use App\Models\ExternalApiToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalApiService
{
    /**
     * Generate a new encrypted API token with specified scopes
     */
    public function generateApiToken(string $tokenName, array $requestedScopes = ['payroll:read'], ?int $expirationDays = null): string|false
    {
        // Validate scopes against available options
        $validatedScopes = $this->validateRequestedScopes($requestedScopes);
        if (! $validatedScopes) {
            Log::error('Invalid scopes provided for token generation', [
                'requested_scopes' => $requestedScopes,
                'available_scopes' => array_keys(ExternalApiToken::AVAILABLE_SCOPES),
            ]);

            return false;
        }

        // Generate secure random token
        $plainTextToken = Str::random(60);

        // Encrypt token before database storage
        $encryptedToken = dataEncrypt($plainTextToken);

        // Determine expiration date
        $expirationDays = $expirationDays ?? ExternalApiToken::DEFAULT_EXPIRATION_DAYS;
        $expirationDays = min($expirationDays, ExternalApiToken::MAXIMUM_EXPIRATION_DAYS);

        $tokenRecord = ExternalApiToken::create([
            'name' => $tokenName,
            'token' => $encryptedToken,
            'scopes' => $validatedScopes,
            'expires_at' => now()->addDays($expirationDays),
            'created_by_ip' => request()->ip(),
        ]);

        if ($tokenRecord instanceof ExternalApiToken) {
            // Log successful token generation for security audit
            Log::info('External API Token Generated Successfully', [
                'token_id' => $tokenRecord->id,
                'token_name' => $tokenName,
                'granted_scopes' => $validatedScopes,
                'expires_at' => $tokenRecord->expires_at,
                'expiration_days' => $expirationDays,
                'created_by_ip' => request()->ip() ?? 'CLI',
            ]);

            return $plainTextToken; // Return plain token to user (only shown once)
        }

        return false;
    }

    /**
     * Refresh an existing token with optional scope updates
     */
    public function refreshExistingToken(string $tokenName, ?string $oldToken = null, ?array $newScopes = null): string|false
    {
        if (! $oldToken) {
            // Expire all tokens with this name if no specific token provided
            $expiredCount = ExternalApiToken::where('name', $tokenName)->update([
                'expires_at' => now(),
            ]);

            Log::info('All tokens expired for refresh', [
                'token_name' => $tokenName,
                'expired_count' => $expiredCount,
            ]);
        } else {
            // Find and validate the specific old token
            $activeTokenRecords = ExternalApiToken::active()->where('name', $tokenName)->get();

            $validTokenRecord = null;
            foreach ($activeTokenRecords as $tokenRecord) {
                $decryptedToken = dataDecrypt($tokenRecord->token);
                if ($decryptedToken === $oldToken) {
                    $validTokenRecord = $tokenRecord;
                    break;
                }
            }

            if (! $validTokenRecord) {
                Log::warning('Invalid token provided for refresh', [
                    'token_name' => $tokenName,
                    'client_ip' => request()->ip() ?? 'CLI',
                ]);

                return false;
            }

            // Use existing scopes if new ones not provided, otherwise validate new scopes
            $scopesToUse = $newScopes ?? $validTokenRecord->scopes ?? ['payroll:read'];
            if ($newScopes) {
                $scopesToUse = $this->validateRequestedScopes($newScopes);
                if (! $scopesToUse) {
                    return false;
                }
            }

            // Expire the old token
            $validTokenRecord->revokeToken();

            Log::info('External API Token Refreshed Successfully', [
                'old_token_id' => $validTokenRecord->id,
                'token_name' => $tokenName,
                'previous_scopes' => $validTokenRecord->scopes,
                'new_scopes' => $scopesToUse,
                'client_ip' => request()->ip() ?? 'CLI',
            ]);
        }

        return $this->generateApiToken($tokenName, $newScopes ?? ['payroll:read']);
    }

    /**
     * Verify token and check required scopes
     */
    public function verifyTokenAndScopes(string $providedToken, array $requiredScopes = []): ExternalApiToken|false
    {
        $activeTokenRecords = ExternalApiToken::active()->get();

        foreach ($activeTokenRecords as $tokenRecord) {
            $decryptedToken = dataDecrypt($tokenRecord->token);
            if ($decryptedToken === $providedToken) {

                // Check if token needs rotation warning
                $this->logTokenRotationWarningIfNeeded($tokenRecord);

                // Verify scopes if required
                if (! empty($requiredScopes) && ! $tokenRecord->hasAnyRequiredScope($requiredScopes)) {
                    Log::warning('Token access denied due to insufficient scope permissions', [
                        'token_id' => $tokenRecord->id,
                        'required_scopes' => $requiredScopes,
                        'token_scopes' => $tokenRecord->scopes,
                        'client_ip' => request()->ip() ?? 'CLI',
                    ]);

                    return false;
                }

                // Record token usage for monitoring
                $tokenRecord->recordUsage();

                // Log successful token verification
                Log::info('External API Token Verified and Used Successfully', [
                    'token_id' => $tokenRecord->id,
                    'token_name' => $tokenRecord->name,
                    'scopes_validated' => $requiredScopes,
                    'client_ip' => request()->ip() ?? 'CLI',
                    'user_agent' => request()->userAgent() ?? 'Unknown',
                ]);

                return $tokenRecord;
            }
        }

        // Log failed token verification attempt
        Log::warning('Invalid or expired token verification attempt', [
            'client_ip' => request()->ip() ?? 'CLI',
            'user_agent' => request()->userAgent() ?? 'Unknown',
            'timestamp' => now(),
        ]);

        return false;
    }

    /**
     * Revoke a specific token by its plain text value
     */
    public function revokeSpecificToken(string $tokenToRevoke): bool
    {
        $activeTokenRecords = ExternalApiToken::active()->get();

        foreach ($activeTokenRecords as $tokenRecord) {
            $decryptedToken = dataDecrypt($tokenRecord->token);
            if ($decryptedToken === $tokenToRevoke) {
                $tokenRecord->revokeToken();

                Log::info('External API Token Revoked Successfully', [
                    'revoked_token_id' => $tokenRecord->id,
                    'token_name' => $tokenRecord->name,
                    'token_scopes' => $tokenRecord->scopes,
                    'revoked_by_ip' => request()->ip() ?? 'CLI',
                ]);

                return true;
            }
        }

        Log::warning('Token revocation failed - token not found', [
            'client_ip' => request()->ip() ?? 'CLI',
        ]);

        return false;
    }

    /**
     * Revoke all active tokens for a given name
     */
    public function revokeAllTokensByName(string $tokenName): int
    {
        $activeTokensToRevoke = ExternalApiToken::active()->where('name', $tokenName)->get();
        $revocationCount = $activeTokensToRevoke->count();

        ExternalApiToken::active()->where('name', $tokenName)->update(['expires_at' => now()]);

        Log::info('Bulk Token Revocation Completed', [
            'token_name' => $tokenName,
            'revoked_count' => $revocationCount,
            'revoked_token_ids' => $activeTokensToRevoke->pluck('id')->toArray(),
            'revoked_by_ip' => request()->ip() ?? 'CLI',
        ]);

        return $revocationCount;
    }

    /**
     * Log token rotation warning if token expires soon
     */
    private function logTokenRotationWarningIfNeeded(ExternalApiToken $tokenRecord): void
    {
        if ($tokenRecord->needsRotationSoon()) {
            Log::info('Token Rotation Recommended - Expires Soon', [
                'token_id' => $tokenRecord->id,
                'token_name' => $tokenRecord->name,
                'expires_at' => $tokenRecord->expires_at,
                'days_until_expiry' => $tokenRecord->getDaysUntilExpiration(),
                'warning_threshold_days' => ExternalApiToken::ROTATION_WARNING_DAYS,
                'client_ip' => request()->ip() ?? 'CLI',
            ]);
        }
    }

    /**
     * Validate requested scopes against available options
     */
    private function validateRequestedScopes(array $requestedScopes): array|false
    {
        $availableScopes = array_keys(ExternalApiToken::AVAILABLE_SCOPES);
        $validatedScopes = array_intersect($requestedScopes, $availableScopes);

        if (empty($validatedScopes)) {
            Log::error('No valid scopes found in request', [
                'requested_scopes' => $requestedScopes,
                'available_scopes' => $availableScopes,
            ]);

            return false;
        }

        return $validatedScopes;
    }

    /**
     * Get all available API scopes with descriptions
     */
    public function getAvailableScopesWithDescriptions(): array
    {
        return ExternalApiToken::AVAILABLE_SCOPES;
    }

    /**
     * Cleanup expired tokens (scheduled task)
     */
    public function cleanupExpiredTokens(): int
    {
        $expiredTokensCount = ExternalApiToken::expired()->count();
        ExternalApiToken::expired()->delete();

        Log::info('Expired Tokens Cleanup Completed', [
            'deleted_count' => $expiredTokensCount,
            'cleanup_timestamp' => now(),
        ]);

        return $expiredTokensCount;
    }

    /**
     * Get comprehensive token usage statistics
     */
    public function getTokenUsageStatistics(): array
    {
        return [
            'total_active_tokens' => ExternalApiToken::active()->count(),
            'total_expired_tokens' => ExternalApiToken::expired()->count(),
            'tokens_expiring_soon' => ExternalApiToken::expiringSoon()->count(),
            'tokens_used_last_24h' => ExternalApiToken::usedRecently(24)->count(),
            'tokens_used_last_7d' => ExternalApiToken::usedRecently(24 * 7)->count(),
            'tokens_never_used' => ExternalApiToken::active()->whereNull('last_used_at')->count(),
            'average_token_age_days' => $this->calculateAverageTokenAgeDays(),
        ];
    }

    /**
     * Calculate average age of active tokens in days
     */
    private function calculateAverageTokenAgeDays(): float
    {
        $activeTokens = ExternalApiToken::active()->get();

        if ($activeTokens->isEmpty()) {
            return 0.0;
        }

        $totalAgeDays = $activeTokens->sum(function ($token) {
            return $token->created_at->diffInDays(now());
        });

        return round($totalAgeDays / $activeTokens->count(), 2);
    }
}
