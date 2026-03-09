<?php

namespace App\Http\Middleware;

use App\Http\Services\ExternalApiService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalApiToken
{
    /**
     * Handle an incoming request and verify external API token with scope validation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  $requiredScopes  Comma-separated list of required scopes
     */
    public function handle(Request $request, Closure $next, string $requiredScopes = ''): Response
    {
        $authorizationHeader = $request->header('Authorization');

        // Check for missing or malformed Authorization header
        if (empty($authorizationHeader) || ! str_starts_with($authorizationHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or malformed authorization token');
        }

        // Extract token from Bearer header
        $providedToken = substr($authorizationHeader, 7);

        // Parse and validate required scopes
        $requiredScopesArray = $this->parseRequiredScopes($requiredScopes);

        // Verify token and check scopes using the service
        $externalApiService = app()->make(ExternalApiService::class);
        $validatedTokenRecord = $externalApiService->verifyTokenAndScopes($providedToken, $requiredScopesArray);

        if (! $validatedTokenRecord) {
            return $this->unauthorizedResponse('Invalid, expired, or insufficient permissions');
        }

        // Add token information to request attributes for potential use in controllers
        $request->attributes->set('verified_external_api_token', $validatedTokenRecord);
        $request->attributes->set('external_api_token_scopes', $validatedTokenRecord->scopes);
        $request->attributes->set('external_api_token_name', $validatedTokenRecord->name);

        return $next($request);
    }

    /**
     * Parse comma-separated required scopes string into array
     */
    private function parseRequiredScopes(string $requiredScopes): array
    {
        if (empty($requiredScopes)) {
            return [];
        }

        $scopesArray = explode(',', $requiredScopes);

        return array_map('trim', $scopesArray);
    }

    /**
     * Return standardized unauthorized response
     */
    private function unauthorizedResponse(string $errorMessage): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized',
            'error' => $errorMessage,
            'error_code' => 401,
        ], 401);
    }
}
