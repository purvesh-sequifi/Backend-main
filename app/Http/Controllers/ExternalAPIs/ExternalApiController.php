<?php

namespace App\Http\Controllers\ExternalAPIs;

use App\Http\Controllers\Controller;
use App\Http\Services\ExternalApiService;
use App\Models\ExternalApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalApiController extends Controller
{
    private ExternalApiService $externalApiService;

    public function __construct(ExternalApiService $externalApiService)
    {
        $this->externalApiService = $externalApiService;
    }

    /**
     * Generate a new API token with specified scopes and expiration
     */
    public function generateApiToken(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'token_name' => 'string|max:255',
                'requested_scopes' => 'array',
                'requested_scopes.*' => 'string|in:'.implode(',', array_keys(ExternalApiToken::AVAILABLE_SCOPES)),
                'expiration_days' => 'integer|min:1|max:'.ExternalApiToken::MAXIMUM_EXPIRATION_DAYS,
            ]);

            $tokenName = $validatedData['token_name'] ?? 'Public API Token';
            $requestedScopes = $validatedData['requested_scopes'] ?? ['payroll:read'];
            $expirationDays = $validatedData['expiration_days'] ?? null;

            $generatedToken = $this->externalApiService->generateApiToken($tokenName, $requestedScopes, $expirationDays);

            if ($generatedToken) {
                return response()->json([
                    'status' => true,
                    'message' => 'API token generated successfully',
                    'data' => [
                        'token' => $generatedToken,
                        'token_name' => $tokenName,
                        'granted_scopes' => $requestedScopes,
                        'expires_in_days' => $expirationDays ?? ExternalApiToken::DEFAULT_EXPIRATION_DAYS,
                    ],
                    'security_warning' => 'Save this token securely. You will not be able to view it again.',
                ]);
            }

            throw new \Exception('Failed to generate API token');
        } catch (\Illuminate\Validation\ValidationException $validationException) {
            return $this->validationErrorResponse($validationException->errors());
        } catch (\Exception $exception) {
            Log::error('Token generation failed', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Refresh an existing API token with optional scope updates
     */
    public function refreshApiToken(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'token_name' => 'required|string|max:255',
                'current_token' => 'string',
                'new_scopes' => 'array',
                'new_scopes.*' => 'string|in:'.implode(',', array_keys(ExternalApiToken::AVAILABLE_SCOPES)),
            ]);

            $tokenName = $validatedData['token_name'];
            $currentToken = $validatedData['current_token'] ?? null;
            $newScopes = $validatedData['new_scopes'] ?? null;

            $refreshedToken = $this->externalApiService->refreshExistingToken($tokenName, $currentToken, $newScopes);

            if ($refreshedToken) {
                return response()->json([
                    'status' => true,
                    'message' => 'API token refreshed successfully',
                    'data' => [
                        'token' => $refreshedToken,
                        'token_name' => $tokenName,
                        'updated_scopes' => $newScopes,
                    ],
                    'security_warning' => 'Save this new token securely. You will not be able to view it again.',
                ]);
            }

            throw new \Exception('Failed to refresh API token');
        } catch (\Illuminate\Validation\ValidationException $validationException) {
            return $this->validationErrorResponse($validationException->errors());
        } catch (\Exception $exception) {
            Log::error('Token refresh failed', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Revoke a specific API token
     */
    public function revokeApiToken(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'token_to_revoke' => 'required|string',
            ]);

            $tokenToRevoke = $validatedData['token_to_revoke'];
            $revocationSuccessful = $this->externalApiService->revokeSpecificToken($tokenToRevoke);

            if ($revocationSuccessful) {
                return response()->json([
                    'status' => true,
                    'message' => 'API token revoked successfully',
                ]);
            }

            return response()->json([
                'status' => false,
                'message' => 'Token not found or already expired',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $validationException) {
            return $this->validationErrorResponse($validationException->errors());
        } catch (\Exception $exception) {
            Log::error('Token revocation failed', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Revoke all tokens for a specific token name
     */
    public function revokeAllTokensByName(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'token_name' => 'required|string|max:255',
            ]);

            $tokenName = $validatedData['token_name'];
            $revokedTokensCount = $this->externalApiService->revokeAllTokensByName($tokenName);

            return response()->json([
                'status' => true,
                'message' => "Successfully revoked {$revokedTokensCount} token(s)",
                'data' => [
                    'revoked_tokens_count' => $revokedTokensCount,
                    'token_name' => $tokenName,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $validationException) {
            return $this->validationErrorResponse($validationException->errors());
        } catch (\Exception $exception) {
            Log::error('Bulk token revocation failed', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Get all available API scopes with descriptions
     */
    public function getAvailableApiScopes()
    {
        try {
            $availableScopes = $this->externalApiService->getAvailableScopesWithDescriptions();

            return response()->json([
                'status' => true,
                'message' => 'Available API scopes retrieved successfully',
                'data' => [
                    'available_scopes' => $availableScopes,
                    'total_scopes_count' => count($availableScopes),
                ],
            ]);

        } catch (\Exception $exception) {
            Log::error('Failed to retrieve available scopes', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Get comprehensive token usage statistics
     */
    public function getTokenUsageStatistics()
    {
        try {
            $usageStatistics = $this->externalApiService->getTokenUsageStatistics();

            return response()->json([
                'status' => true,
                'message' => 'Token usage statistics retrieved successfully',
                'data' => [
                    'statistics' => $usageStatistics,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $exception) {
            Log::error('Failed to retrieve token statistics', [
                'error_message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Format validation error response
     */
    private function validationErrorResponse(array $validationErrors): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $validationErrors,
        ], 422);
    }

    /**
     * Format general error response
     */
    private function errorResponse(string $errorMessage, int $statusCode = 500): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $errorMessage,
            'error_code' => $statusCode,
        ], $statusCode);
    }
}
