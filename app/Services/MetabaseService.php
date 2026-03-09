<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MetabaseService
{
    /**
     * Generate a signed JWT token for Metabase embedded content
     *
     * @param  array  $payload  The payload for the JWT token
     * @return string The signed JWT token
     *
     * @throws InvalidArgumentException
     */
    public function generateEmbeddedToken(array $payload): string
    {
        try {
            $secretKey = Config::get('metabase.secret_key');

            if (empty($secretKey)) {
                throw new InvalidArgumentException('Metabase secret key is not configured');
            }

            // Set default expiration if not provided
            if (! isset($payload['exp'])) {
                $expirationMinutes = Config::get('metabase.token_expiration_minutes', 10);
                $payload['exp'] = time() + ($expirationMinutes * 60);
            }

            return JWT::encode($payload, $secretKey, 'HS256');
        } catch (\Exception $e) {
            Log::error('Failed to generate Metabase embedded token', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    /**
     * Generate embedded dashboard URL
     *
     * @param  int  $dashboardId  The Metabase dashboard ID
     * @param  array  $params  Optional parameters to pass to the dashboard
     * @param  array  $settings  Optional display settings (bordered, titled, theme)
     * @return string The complete embedded dashboard URL
     */
    public function generateDashboardUrl(int $dashboardId, array $params = [], array $settings = []): string
    {
        // Ensure params is always encoded as JSON object, not array
        $paramsObject = empty($params) ? new \stdClass : (object) $params;

        $payload = [
            'resource' => ['dashboard' => $dashboardId],
            'params' => $paramsObject,
        ];

        $token = $this->generateEmbeddedToken($payload);
        $baseUrl = Config::get('metabase.site_url');
        $defaultSettings = Config::get('metabase.default_settings', []);

        // Merge default settings with provided settings
        $finalSettings = array_merge($defaultSettings, $settings);

        // Build query string for settings
        $settingsQuery = $this->buildSettingsQuery($finalSettings);

        return "{$baseUrl}/embed/dashboard/{$token}{$settingsQuery}";
    }

    /**
     * Generate embedded question/card URL
     *
     * @param  int  $questionId  The Metabase question/card ID
     * @param  array  $params  Optional parameters to pass to the question
     * @param  array  $settings  Optional display settings
     * @return string The complete embedded question URL
     */
    public function generateQuestionUrl(int $questionId, array $params = [], array $settings = []): string
    {
        // Ensure params is always encoded as JSON object, not array
        $paramsObject = empty($params) ? new \stdClass : (object) $params;

        $payload = [
            'resource' => ['question' => $questionId],
            'params' => $paramsObject,
        ];

        $token = $this->generateEmbeddedToken($payload);
        $baseUrl = Config::get('metabase.site_url');
        $defaultSettings = Config::get('metabase.default_settings', []);

        // Merge default settings with provided settings
        $finalSettings = array_merge($defaultSettings, $settings);

        // Build query string for settings
        $settingsQuery = $this->buildSettingsQuery($finalSettings);

        return "{$baseUrl}/embed/question/{$token}{$settingsQuery}";
    }

    /**
     * Verify and decode a Metabase JWT token
     *
     * @param  string  $token  The JWT token to verify
     * @return array The decoded payload
     *
     * @throws InvalidArgumentException
     */
    public function verifyToken(string $token): array
    {
        try {
            $secretKey = Config::get('metabase.secret_key');

            if (empty($secretKey)) {
                throw new InvalidArgumentException('Metabase secret key is not configured');
            }

            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            return (array) $decoded;
        } catch (\Exception $e) {
            Log::error('Failed to verify Metabase token', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20).'...', // Log only first 20 chars for security
            ]);
            throw new InvalidArgumentException('Invalid or expired token');
        }
    }

    /**
     * Build query string for settings
     */
    private function buildSettingsQuery(array $settings): string
    {
        $queryParts = [];

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $queryParts[] = urlencode($key).'='.urlencode($value === true ? 'true' : ($value === false ? 'false' : $value));
            }
        }

        return empty($queryParts) ? '' : '#'.implode('&', $queryParts);
    }

    /**
     * Get Metabase site URL
     */
    public function getSiteUrl(): string
    {
        return Config::get('metabase.site_url');
    }

    /**
     * Check if Metabase is properly configured
     */
    public function isConfigured(): bool
    {
        $siteUrl = Config::get('metabase.site_url');
        $secretKey = Config::get('metabase.secret_key');

        return ! empty($siteUrl) && ! empty($secretKey);
    }
}
