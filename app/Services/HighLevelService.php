<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class HighLevelService
{
    /**
     * The base URL for the HighLevel API
     *
     * @var string
     */
    protected $baseUrl = 'https://services.leadconnectorhq.com';

    /**
     * The API token for HighLevel
     *
     * @var string
     */
    protected $apiToken;

    /**
     * The location ID for HighLevel
     *
     * @var string
     */
    protected $locationId;

    /**
     * The API version for HighLevel
     *
     * @var string
     */
    protected $apiVersion;

    /**
     * The HTTP client instance
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Create a new HighLevelService instance.
     *
     * @return void
     */
    public function __construct(?string $apiToken = null, ?string $locationId = null, $apiVersion = null)
    {
        $this->apiToken = $apiToken ?: config('services.highlevel.api_token');
        $this->locationId = $locationId ?: config('services.highlevel.location_id');
        $this->apiVersion = $apiVersion ?: config('services.highlevel.api_version', '2021-07-28');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiToken,
                'Version' => $this->apiVersion,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Upsert a contact in HighLevel
     */
    public function upsertContact(array $contactData): ?array
    {
        try {
            // Ensure locationId is present in the contact data
            $contactData['locationId'] = $contactData['locationId'] ?? $this->locationId;

            $response = $this->client->post('/contacts/upsert', [
                'json' => $contactData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('HighLevel API Error: '.$e->getMessage(), [
                'contact_data' => $contactData,
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return null;
        }
    }

    /**
     * Search for a contact in HighLevel by email
     */
    public function searchContactByEmail(string $email): ?array
    {
        try {
            $response = $this->client->get('/contacts/search', [
                'query' => [
                    'email' => $email,
                    'locationId' => $this->locationId,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('HighLevel API Error: '.$e->getMessage(), [
                'email' => $email,
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return null;
        }
    }

    /**
     * Search for a contact in HighLevel by phone
     */
    public function searchContactByPhone(string $phone): ?array
    {
        try {
            $response = $this->client->get('/contacts/search', [
                'query' => [
                    'phone' => $phone,
                    'locationId' => $this->locationId,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('HighLevel API Error: '.$e->getMessage(), [
                'phone' => $phone,
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            return null;
        }
    }

    /**
     * Format user data into HighLevel contact format
     */
    public function formatUserToContact(object $user): array
    {
        return [
            'locationId' => $this->locationId,
            'email' => $user->email ?? null,
            'firstName' => $user->first_name ?? null,
            'lastName' => $user->last_name ?? null,
            'phone' => $user->phone ?? null,
            // Add additional fields as needed
        ];
    }
}
