<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MetabaseService;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @group Metabase
 *
 * APIs for Metabase embedded dashboards and reports
 */
class MetabaseController extends Controller
{
    protected MetabaseService $metabaseService;

    public function __construct(MetabaseService $metabaseService)
    {
        $this->metabaseService = $metabaseService;
    }

    /**
     * Generate Embedded Dashboard URL
     *
     * Generate a signed URL for embedding a Metabase dashboard
     *
     * @bodyParam dashboard_id int required The Metabase dashboard ID. Example: 2
     * @bodyParam params object Optional parameters to pass to the dashboard. Example: {"user_id": 123}
     * @bodyParam settings object Optional display settings. Example: {"bordered": true, "titled": true, "theme": "night"}
     * @bodyParam expiration_minutes int Optional token expiration in minutes (default: 10). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "url": "http://localhost:3000/embed/dashboard/eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXNvdXJjZSI6eyJkYXNoYm9hcmQiOjJ9LCJwYXJhbXMiOnt9LCJleHAiOjE2MzQxMjM0NTZ9.abc123#bordered=true&titled=true",
     *     "dashboard_id": 2,
     *     "expires_at": "2023-10-15T10:30:56Z"
     *   },
     *   "message": "Dashboard URL generated successfully"
     * }
     * @response 400 {
     *   "success": false,
     *   "message": "Validation failed",
     *   "errors": {
     *     "dashboard_id": ["The dashboard id field is required."]
     *   }
     * }
     * @response 500 {
     *   "success": false,
     *   "message": "Failed to generate dashboard URL"
     * }
     */
    public function generateDashboardUrl(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_id' => 'required|integer|min:1',
                'params' => 'sometimes|array',
                'settings' => 'sometimes|array',
                'expiration_minutes' => 'sometimes|integer|min:1|max:1440', // Max 24 hours
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if (! $this->metabaseService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metabase is not properly configured',
                ], 500);
            }

            $dashboardId = $request->input('dashboard_id');
            $params = $request->input('params', []);
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes');

            // If custom expiration is provided, create a custom payload
            if ($expirationMinutes) {
                $payload = [
                    'resource' => ['dashboard' => $dashboardId],
                    'params' => empty($params) ? new \stdClass : (object) $params, // Fix: ensure object
                    'exp' => time() + ($expirationMinutes * 60),
                ];
                $token = $this->metabaseService->generateEmbeddedToken($payload);
                $baseUrl = $this->metabaseService->getSiteUrl();
                $settingsQuery = $this->buildSettingsQuery($settings);
                $url = "{$baseUrl}/embed/dashboard/{$token}{$settingsQuery}";
            } else {
                $url = $this->metabaseService->generateDashboardUrl($dashboardId, $params, $settings);
                $expirationMinutes = config('metabase.token_expiration_minutes', 10);
            }

            $expiresAt = now()->addMinutes($expirationMinutes);

            Log::info('Metabase dashboard URL generated', [
                'dashboard_id' => $dashboardId,
                'user_id' => auth()->id(),
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'dashboard_id' => $dashboardId,
                    'expires_at' => $expiresAt->toISOString(),
                ],
                'message' => 'Dashboard URL generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Metabase dashboard URL', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard URL',
            ], 500);
        }
    }

    /**
     * Generate Embedded Question URL
     *
     * Generate a signed URL for embedding a Metabase question/card
     *
     * @bodyParam question_id int required The Metabase question/card ID. Example: 5
     * @bodyParam params object Optional parameters to pass to the question. Example: {"user_id": 123}
     * @bodyParam settings object Optional display settings. Example: {"bordered": true, "titled": true}
     * @bodyParam expiration_minutes int Optional token expiration in minutes (default: 10). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "url": "http://localhost:3000/embed/question/eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXNvdXJjZSI6eyJxdWVzdGlvbiI6NX0sInBhcmFtcyI6e30sImV4cCI6MTYzNDEyMzQ1Nn0.def456#bordered=true&titled=true",
     *     "question_id": 5,
     *     "expires_at": "2023-10-15T10:30:56Z"
     *   },
     *   "message": "Question URL generated successfully"
     * }
     */
    public function generateQuestionUrl(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'question_id' => 'required|integer|min:1',
                'params' => 'sometimes|array',
                'settings' => 'sometimes|array',
                'expiration_minutes' => 'sometimes|integer|min:1|max:1440',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if (! $this->metabaseService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metabase is not properly configured',
                ], 500);
            }

            $questionId = $request->input('question_id');
            $params = $request->input('params', []);
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes');

            if ($expirationMinutes) {
                $payload = [
                    'resource' => ['question' => $questionId],
                    'params' => empty($params) ? new \stdClass : (object) $params, // Fix: ensure object
                    'exp' => time() + ($expirationMinutes * 60),
                ];
                $token = $this->metabaseService->generateEmbeddedToken($payload);
                $baseUrl = $this->metabaseService->getSiteUrl();
                $settingsQuery = $this->buildSettingsQuery($settings);
                $url = "{$baseUrl}/embed/question/{$token}{$settingsQuery}";
            } else {
                $url = $this->metabaseService->generateQuestionUrl($questionId, $params, $settings);
                $expirationMinutes = config('metabase.token_expiration_minutes', 10);
            }

            $expiresAt = now()->addMinutes($expirationMinutes);

            Log::info('Metabase question URL generated', [
                'question_id' => $questionId,
                'user_id' => auth()->id(),
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'question_id' => $questionId,
                    'expires_at' => $expiresAt->toISOString(),
                ],
                'message' => 'Question URL generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Metabase question URL', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate question URL',
            ], 500);
        }
    }

    /**
     * Verify Metabase Token
     *
     * Verify and decode a Metabase JWT token
     *
     * @bodyParam token string required The JWT token to verify. Example: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "resource": {"dashboard": 2},
     *     "params": {},
     *     "exp": 1634123456,
     *     "valid": true
     *   },
     *   "message": "Token verified successfully"
     * }
     */
    public function verifyToken(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $token = $request->input('token');
            $payload = $this->metabaseService->verifyToken($token);

            return response()->json([
                'success' => true,
                'data' => array_merge($payload, ['valid' => true]),
                'message' => 'Token verified successfully',
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to verify Metabase token', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 400);
        }
    }

    /**
     * Get Metabase Configuration Status
     *
     * Check if Metabase is properly configured
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "configured": true,
     *     "site_url": "http://localhost:3000",
     *     "default_expiration_minutes": 10
     *   },
     *   "message": "Configuration status retrieved successfully"
     * }
     */
    public function getConfigurationStatus(): JsonResponse
    {
        try {
            $isConfigured = $this->metabaseService->isConfigured();
            $siteUrl = $this->metabaseService->getSiteUrl();
            $defaultExpiration = config('metabase.token_expiration_minutes', 10);

            return response()->json([
                'success' => true,
                'data' => [
                    'configured' => $isConfigured,
                    'site_url' => $siteUrl,
                    'default_expiration_minutes' => $defaultExpiration,
                ],
                'message' => 'Configuration status retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Metabase configuration status', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve configuration status',
            ], 500);
        }
    }

    /**
     * Build query string for settings (helper method)
     */
    private function buildSettingsQuery(array $settings): string
    {
        $defaultSettings = config('metabase.default_settings', []);
        $finalSettings = array_merge($defaultSettings, $settings);

        $queryParts = [];

        foreach ($finalSettings as $key => $value) {
            if ($value !== null) {
                $queryParts[] = urlencode($key).'='.urlencode($value === true ? 'true' : ($value === false ? 'false' : $value));
            }
        }

        return empty($queryParts) ? '' : '#'.implode('&', $queryParts);
    }

    /**
     * Embed dashboard directly - returns HTML content with iframe
     */
    public function embedDashboard(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $request->validate([
                'dashboard_id' => 'required|integer|min:1',
                'params' => 'array',
                'settings' => 'array',
                'expiration_minutes' => 'integer|min:1|max:1440',
                'width' => 'string',
                'height' => 'string',
            ]);

            $dashboardId = $request->input('dashboard_id');
            $params = $request->input('params', []);
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes');
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Add user context to params
            $params['user_id'] = $user->id;

            // Generate the URL
            if ($expirationMinutes) {
                $payload = [
                    'resource' => ['dashboard' => $dashboardId],
                    'params' => empty($params) ? new \stdClass : (object) $params,
                    'exp' => time() + ($expirationMinutes * 60),
                ];

                $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                $url = config('metabase.site_url').'/embed/dashboard/'.$token.$this->buildSettingsQuery($settings);
            } else {
                $url = $this->metabaseService->generateDashboardUrl($dashboardId, $params, $settings);
            }

            // Generate HTML content
            $html = $this->generateEmbedHtml($url, $width, $height, "Metabase Dashboard {$dashboardId}");

            return response()->json([
                'success' => true,
                'data' => [
                    'dashboard_id' => $dashboardId,
                    'user_id' => $user->id,
                    'url' => $url,
                    'html' => $html,
                    'expires_at' => $expirationMinutes ?
                        now()->addMinutes($expirationMinutes)->toISOString() :
                        now()->addHour()->toISOString(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Metabase embed dashboard error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to embed dashboard: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Embed question directly - returns HTML content with iframe
     */
    public function embedQuestion(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $request->validate([
                'question_id' => 'required|integer|min:1',
                'params' => 'array',
                'settings' => 'array',
                'expiration_minutes' => 'integer|min:1|max:1440',
                'width' => 'string',
                'height' => 'string',
            ]);

            $questionId = $request->input('question_id');
            $params = $request->input('params', []);
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes');
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Add user context to params
            $params['user_id'] = $user->id;

            // Generate the URL
            if ($expirationMinutes) {
                $payload = [
                    'resource' => ['question' => $questionId],
                    'params' => empty($params) ? new \stdClass : (object) $params,
                    'exp' => time() + ($expirationMinutes * 60),
                ];

                $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                $url = config('metabase.site_url').'/embed/question/'.$token.$this->buildSettingsQuery($settings);
            } else {
                $url = $this->metabaseService->generateQuestionUrl($questionId, $params, $settings);
            }

            // Generate HTML content
            $html = $this->generateEmbedHtml($url, $width, $height, "Metabase Question {$questionId}");

            return response()->json([
                'success' => true,
                'data' => [
                    'question_id' => $questionId,
                    'user_id' => $user->id,
                    'url' => $url,
                    'html' => $html,
                    'expires_at' => $expirationMinutes ?
                        now()->addMinutes($expirationMinutes)->toISOString() :
                        now()->addHour()->toISOString(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Metabase embed question error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to embed question: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate HTML content for embedding
     */
    private function generateEmbedHtml(string $url, string $width, string $height, string $title): string
    {
        return "
<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$title}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .metabase-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .metabase-header {
            background: #509ee3;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 500;
        }
        .metabase-iframe {
            flex: 1;
            border: none;
            width: 100%;
            min-height: {$height};
        }
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class=\"metabase-container\">
        <div class=\"metabase-header\">
            📊 {$title}
        </div>
        <div class=\"loading\" id=\"loading\">Loading dashboard...</div>
        <iframe 
            class=\"metabase-iframe\" 
            src=\"{$url}\"
            style=\"display: none;\"
            onload=\"document.getElementById('loading').style.display='none'; this.style.display='block';\">
        </iframe>
    </div>
</body>
</html>";
    }

    /**
     * Convert a regular Metabase URL to embeddable format
     */
    public function convertMetabaseUrl(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $request->validate([
                'url' => 'required|string',
                'settings' => 'array',
                'expiration_minutes' => 'integer|min:1|max:1440',
                'width' => 'string',
                'height' => 'string',
            ]);

            $url = $request->input('url');
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes');
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Parse the Metabase URL
            $parsed = $this->parseMetabaseUrl($url);

            if (! $parsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Metabase URL format',
                ], 400);
            }

            // Add user context to params
            $parsed['params']['user_id'] = $user->id;

            // Generate embeddable URL
            if ($parsed['type'] === 'question') {
                if ($expirationMinutes) {
                    $payload = [
                        'resource' => ['question' => $parsed['id']],
                        'params' => empty($parsed['params']) ? new \stdClass : (object) $parsed['params'],
                        'exp' => time() + ($expirationMinutes * 60),
                    ];

                    $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                    $embedUrl = config('metabase.site_url').'/embed/question/'.$token.$this->buildSettingsQuery($settings);
                } else {
                    $embedUrl = $this->metabaseService->generateQuestionUrl($parsed['id'], $parsed['params'], $settings);
                }

                $html = $this->generateEmbedHtml($embedUrl, $width, $height, "Metabase Question {$parsed['id']}");

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'question',
                        'question_id' => $parsed['id'],
                        'user_id' => $user->id,
                        'original_url' => $url,
                        'embed_url' => $embedUrl,
                        'html' => $html,
                        'params' => $parsed['params'],
                        'expires_at' => $expirationMinutes ?
                            now()->addMinutes($expirationMinutes)->toISOString() :
                            now()->addHour()->toISOString(),
                    ],
                ]);
            } elseif ($parsed['type'] === 'dashboard') {
                if ($expirationMinutes) {
                    $payload = [
                        'resource' => ['dashboard' => $parsed['id']],
                        'params' => empty($parsed['params']) ? new \stdClass : (object) $parsed['params'],
                        'exp' => time() + ($expirationMinutes * 60),
                    ];

                    $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                    $embedUrl = config('metabase.site_url').'/embed/dashboard/'.$token.$this->buildSettingsQuery($settings);
                } else {
                    $embedUrl = $this->metabaseService->generateDashboardUrl($parsed['id'], $parsed['params'], $settings);
                }

                $html = $this->generateEmbedHtml($embedUrl, $width, $height, "Metabase Dashboard {$parsed['id']}");

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'dashboard',
                        'dashboard_id' => $parsed['id'],
                        'user_id' => $user->id,
                        'original_url' => $url,
                        'embed_url' => $embedUrl,
                        'html' => $html,
                        'params' => $parsed['params'],
                        'expires_at' => $expirationMinutes ?
                            now()->addMinutes($expirationMinutes)->toISOString() :
                            now()->addHour()->toISOString(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unsupported Metabase URL type',
            ], 400);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Metabase URL conversion error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to convert URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate embeddable question from Metabase URL (shorthand method)
     */
    public function questionFromUrl(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $request->validate([
                'url' => 'required|string',
                'settings' => 'array',
                'expiration_minutes' => 'integer|min:1|max:1440',
                'width' => 'string',
                'height' => 'string',
            ]);

            $url = $request->input('url');
            $settings = $request->input('settings', []);
            $expirationMinutes = $request->input('expiration_minutes', 30);
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Parse the Metabase URL
            $parsed = $this->parseMetabaseUrl($url);

            if (! $parsed || $parsed['type'] !== 'question') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Metabase question URL format',
                ], 400);
            }

            // Add user context to params
            $parsed['params']['user_id'] = $user->id;

            // Generate embeddable URL
            if ($expirationMinutes) {
                $payload = [
                    'resource' => ['question' => $parsed['id']],
                    'params' => empty($parsed['params']) ? new \stdClass : (object) $parsed['params'],
                    'exp' => time() + ($expirationMinutes * 60),
                ];

                $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                $embedUrl = config('metabase.site_url').'/embed/question/'.$token.$this->buildSettingsQuery($settings);
            } else {
                $embedUrl = $this->metabaseService->generateQuestionUrl($parsed['id'], $parsed['params'], $settings);
            }

            $html = $this->generateEmbedHtml($embedUrl, $width, $height, "Metabase Question {$parsed['id']}");

            return response()->json([
                'success' => true,
                'data' => [
                    'question_id' => $parsed['id'],
                    'user_id' => $user->id,
                    'original_url' => $url,
                    'embed_url' => $embedUrl,
                    'html' => $html,
                    'params' => $parsed['params'],
                    'expires_at' => now()->addMinutes($expirationMinutes)->toISOString(),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Metabase question from URL error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate question from URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse a Metabase URL and extract ID and parameters
     */
    private function parseMetabaseUrl(string $url): ?array
    {
        // Remove any trailing slashes and normalize the URL
        $url = rtrim($url, '/');

        // Parse the URL
        $parsed = parse_url($url);
        if (! $parsed) {
            return null;
        }

        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        // Parse query parameters
        $params = [];
        if ($query) {
            parse_str($query, $params);
        }

        // Check for question URL pattern: /question/ID-slug or /question/ID
        if (preg_match('/\/question\/(\d+)(?:-[^\/]*)?/', $path, $matches)) {
            return [
                'type' => 'question',
                'id' => (int) $matches[1],
                'params' => $params,
                'path' => $path,
                'query' => $query,
            ];
        }

        // Check for dashboard URL pattern: /dashboard/ID-slug or /dashboard/ID
        if (preg_match('/\/dashboard\/(\d+)(?:-[^\/]*)?/', $path, $matches)) {
            return [
                'type' => 'dashboard',
                'id' => (int) $matches[1],
                'params' => $params,
                'path' => $path,
                'query' => $query,
            ];
        }

        return null;
    }

    /**
     * Convert a regular Metabase URL to embeddable format (GET version)
     */
    public function convertMetabaseUrlGet(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $request->validate([
                'url' => 'required|string',
                'bordered' => 'in:true,false,1,0',
                'titled' => 'in:true,false,1,0',
                'theme' => 'string',
                'expiration_minutes' => 'integer|min:1|max:1440',
                'width' => 'string',
                'height' => 'string',
            ]);

            $url = $request->input('url');
            $settings = [];

            // Build settings from query parameters
            if ($request->has('bordered')) {
                $settings['bordered'] = in_array($request->input('bordered'), ['true', '1', 1, true], true);
            }
            if ($request->has('titled')) {
                $settings['titled'] = in_array($request->input('titled'), ['true', '1', 1, true], true);
            }
            if ($request->has('theme')) {
                $settings['theme'] = $request->input('theme');
            }

            $expirationMinutes = $request->input('expiration_minutes', 30);
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Parse the Metabase URL
            $parsed = $this->parseMetabaseUrl($url);

            if (! $parsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Metabase URL format',
                ], 400);
            }

            // Add user context to params
            $parsed['params']['user_id'] = $user->id;

            // Generate embeddable URL
            if ($parsed['type'] === 'question') {
                if ($expirationMinutes) {
                    $payload = [
                        'resource' => ['question' => $parsed['id']],
                        'params' => empty($parsed['params']) ? new \stdClass : (object) $parsed['params'],
                        'exp' => time() + ($expirationMinutes * 60),
                    ];

                    $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                    $embedUrl = config('metabase.site_url').'/embed/question/'.$token.$this->buildSettingsQuery($settings);
                } else {
                    $embedUrl = $this->metabaseService->generateQuestionUrl($parsed['id'], $parsed['params'], $settings);
                }

                $html = $this->generateEmbedHtml($embedUrl, $width, $height, "Metabase Question {$parsed['id']}");

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'question',
                        'question_id' => $parsed['id'],
                        'user_id' => $user->id,
                        'original_url' => $url,
                        'embed_url' => $embedUrl,
                        'html' => $html,
                        'params' => $parsed['params'],
                        'expires_at' => $expirationMinutes ?
                            now()->addMinutes($expirationMinutes)->toISOString() :
                            now()->addHour()->toISOString(),
                    ],
                ]);
            } elseif ($parsed['type'] === 'dashboard') {
                if ($expirationMinutes) {
                    $payload = [
                        'resource' => ['dashboard' => $parsed['id']],
                        'params' => empty($parsed['params']) ? new \stdClass : (object) $parsed['params'],
                        'exp' => time() + ($expirationMinutes * 60),
                    ];

                    $token = JWT::encode($payload, config('metabase.secret_key'), 'HS256');
                    $embedUrl = config('metabase.site_url').'/embed/dashboard/'.$token.$this->buildSettingsQuery($settings);
                } else {
                    $embedUrl = $this->metabaseService->generateDashboardUrl($parsed['id'], $parsed['params'], $settings);
                }

                $html = $this->generateEmbedHtml($embedUrl, $width, $height, "Metabase Dashboard {$parsed['id']}");

                return response()->json([
                    'success' => true,
                    'data' => [
                        'type' => 'dashboard',
                        'dashboard_id' => $parsed['id'],
                        'user_id' => $user->id,
                        'original_url' => $url,
                        'embed_url' => $embedUrl,
                        'html' => $html,
                        'params' => $parsed['params'],
                        'expires_at' => $expirationMinutes ?
                            now()->addMinutes($expirationMinutes)->toISOString() :
                            now()->addHour()->toISOString(),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unsupported Metabase URL type',
            ], 400);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Metabase URL conversion (GET) error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to convert URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate Question URL with Parameters (API-only)
     *
     * Enhanced API endpoint for generating question URLs with better parameter handling
     *
     * @urlParam questionId int required The Metabase question ID. Example: 45
     *
     * @bodyParam params object Optional parameters for the question. Example: {"Start Date": "2025-04-01", "End Date": "2025-09-30"}
     * @bodyParam settings object Optional display settings. Example: {"bordered": true, "titled": true}
     * @bodyParam expiration_minutes int Optional token expiration in minutes. Example: 10
     */
    public function generateQuestionUrlWithParams(Request $request, $questionId): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['question_id' => $questionId]), [
                'question_id' => 'required|integer|min:1',
                'params' => 'sometimes|array',
                'settings' => 'sometimes|array',
                'expiration_minutes' => 'sometimes|integer|min:1|max:1440',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            if (! $this->metabaseService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Metabase is not properly configured',
                ], 500);
            }

            $params = $request->input('params', []);
            $settings = array_merge(config('metabase.default_settings'), $request->input('settings', []));
            $expirationMinutes = $request->input('expiration_minutes', config('metabase.token_expiration_minutes'));

            // Generate the embedded URL
            $payload = [
                'resource' => ['question' => (int) $questionId],
                'params' => empty($params) ? new \stdClass : (object) $params,
                'exp' => time() + ($expirationMinutes * 60),
            ];

            $token = $this->metabaseService->generateEmbeddedToken($payload);
            $baseUrl = $this->metabaseService->getSiteUrl();
            $settingsQuery = $this->buildSettingsQuery($settings);
            $embedUrl = $baseUrl.'/embed/question/'.$token.$settingsQuery;

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $embedUrl,
                    'question_id' => (int) $questionId,
                    'params' => $params,
                    'settings' => $settings,
                    'expires_at' => date('c', time() + ($expirationMinutes * 60)),
                    'token' => $token,
                ],
                'message' => 'Question URL generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating question URL: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate question URL',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Question Iframe HTML (API-only)
     *
     * Returns ready-to-use HTML iframe code for embedding
     *
     * @urlParam questionId int required The Metabase question ID. Example: 45
     *
     * @queryParam params array Optional URL-encoded parameters
     * @queryParam width string Optional iframe width. Example: 100%
     * @queryParam height string Optional iframe height. Example: 600px
     */
    public function getQuestionIframeHtml(Request $request, $questionId): JsonResponse
    {
        try {
            $params = [];

            // Parse URL parameters
            foreach ($request->all() as $key => $value) {
                if (! in_array($key, ['width', 'height', 'bordered', 'titled', 'theme'])) {
                    $params[$key] = $value;
                }
            }

            $settings = [
                'bordered' => $request->input('bordered', true),
                'titled' => $request->input('titled', true),
            ];

            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Generate the embedded URL
            $payload = [
                'resource' => ['question' => (int) $questionId],
                'params' => empty($params) ? new \stdClass : (object) $params,
                'exp' => time() + (10 * 60), // 10 minutes
            ];

            $token = $this->metabaseService->generateEmbeddedToken($payload);
            $baseUrl = $this->metabaseService->getSiteUrl();
            $settingsQuery = $this->buildSettingsQuery($settings);
            $embedUrl = $baseUrl.'/embed/question/'.$token.$settingsQuery;

            // Generate iframe HTML
            $iframeHtml = sprintf(
                '<iframe src="%s" width="%s" height="%s" frameborder="0" title="Metabase Question %d"></iframe>',
                htmlspecialchars($embedUrl),
                htmlspecialchars($width),
                htmlspecialchars($height),
                (int) $questionId
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'iframe_html' => $iframeHtml,
                    'embed_url' => $embedUrl,
                    'question_id' => (int) $questionId,
                    'params' => $params,
                    'settings' => $settings,
                    'dimensions' => [
                        'width' => $width,
                        'height' => $height,
                    ],
                ],
                'message' => 'Iframe HTML generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating iframe HTML: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate iframe HTML',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Embed Question with Parameters (API-only)
     *
     * Enhanced embedding with better parameter handling and response format
     *
     * @urlParam questionId int required The Metabase question ID. Example: 45
     *
     * @bodyParam params object Optional parameters for the question
     * @bodyParam settings object Optional display settings
     * @bodyParam response_format string Optional response format (url|iframe|both). Default: both
     */
    public function embedQuestionWithParams(Request $request, $questionId): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['question_id' => $questionId]), [
                'question_id' => 'required|integer|min:1',
                'params' => 'sometimes|array',
                'settings' => 'sometimes|array',
                'response_format' => 'sometimes|string|in:url,iframe,both',
                'width' => 'sometimes|string',
                'height' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $params = $request->input('params', []);
            $settings = array_merge(config('metabase.default_settings'), $request->input('settings', []));
            $responseFormat = $request->input('response_format', 'both');
            $width = $request->input('width', '100%');
            $height = $request->input('height', '600px');

            // Generate the embedded URL
            $payload = [
                'resource' => ['question' => (int) $questionId],
                'params' => empty($params) ? new \stdClass : (object) $params,
                'exp' => time() + (10 * 60),
            ];

            $token = $this->metabaseService->generateEmbeddedToken($payload);
            $baseUrl = $this->metabaseService->getSiteUrl();
            $settingsQuery = $this->buildSettingsQuery($settings);
            $embedUrl = $baseUrl.'/embed/question/'.$token.$settingsQuery;

            $responseData = [
                'question_id' => (int) $questionId,
                'params' => $params,
                'settings' => $settings,
                'expires_at' => date('c', time() + (10 * 60)),
            ];

            // Add URL if requested
            if (in_array($responseFormat, ['url', 'both'])) {
                $responseData['embed_url'] = $embedUrl;
                $responseData['token'] = $token;
            }

            // Add iframe HTML if requested
            if (in_array($responseFormat, ['iframe', 'both'])) {
                $responseData['iframe_html'] = sprintf(
                    '<iframe src="%s" width="%s" height="%s" frameborder="0" title="Metabase Question %d"></iframe>',
                    htmlspecialchars($embedUrl),
                    htmlspecialchars($width),
                    htmlspecialchars($height),
                    (int) $questionId
                );
                $responseData['dimensions'] = [
                    'width' => $width,
                    'height' => $height,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Question embedded successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error embedding question: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to embed question',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Complete Embed Response (API-only)
     *
     * Returns everything needed for frontend embedding
     *
     * @urlParam questionId int required The Metabase question ID. Example: 45
     *
     * @bodyParam params object Optional parameters for the question
     * @bodyParam settings object Optional display settings
     */
    public function getEmbedResponse(Request $request, $questionId): JsonResponse
    {
        try {
            $params = $request->input('params', []);
            $settings = array_merge(config('metabase.default_settings'), $request->input('settings', []));

            // Generate the embedded URL
            $payload = [
                'resource' => ['question' => (int) $questionId],
                'params' => empty($params) ? new \stdClass : (object) $params,
                'exp' => time() + (10 * 60),
            ];

            $token = $this->metabaseService->generateEmbeddedToken($payload);
            $baseUrl = $this->metabaseService->getSiteUrl();
            $settingsQuery = $this->buildSettingsQuery($settings);
            $embedUrl = $baseUrl.'/embed/question/'.$token.$settingsQuery;

            return response()->json([
                'success' => true,
                'embed' => [
                    'url' => $embedUrl,
                    'token' => $token,
                    'question_id' => (int) $questionId,
                    'params' => $params,
                    'settings' => $settings,
                    'expires_at' => date('c', time() + (10 * 60)),
                    'iframe_html' => sprintf(
                        '<iframe src="%s" width="100%%" height="600px" frameborder="0" title="Metabase Question %d"></iframe>',
                        htmlspecialchars($embedUrl),
                        (int) $questionId
                    ),
                    'usage' => [
                        'direct_access' => 'Use embed.url in an iframe - DO NOT access directly in browser',
                        'iframe_html' => 'Use embed.iframe_html to render directly in your frontend',
                        'javascript' => 'document.getElementById("container").innerHTML = response.embed.iframe_html',
                    ],
                ],
                'message' => 'Embed data generated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating embed response: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate embed response',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
