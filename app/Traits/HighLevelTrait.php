<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

trait HighLevelTrait
{
    /**
     * Get HighLevel API client
     */
    protected function getHighLevelClient(): Client
    {
        $apiToken = config('services.highlevel.token');
        $apiVersion = config('services.highlevel.api_version');

        return new Client([
            'base_uri' => 'https://services.leadconnectorhq.com',
            'headers' => [
                'Authorization' => 'Bearer '.$apiToken,
                'Version' => $apiVersion,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Upsert a contact in HighLevel
     */
    protected function upsertHighLevelContact(array $contactData): ?array
    {
        try {
            // Ensure locationId is present in the contact data
            $contactData['locationId'] = $contactData['locationId'] ?? config('services.highlevel.location_id');

            $client = $this->getHighLevelClient();
            $response = $client->post('/contacts/upsert', [
                'json' => $contactData,
            ]);

            Log::info('HighLevel contact upserted successfully', [
                'contact_data' => $contactData,
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
     * Format user data into HighLevel contact format
     */
    protected function formatUserToHighLevelContact(object $user): array
    {
        $contactData = [
            'locationId' => config('services.highlevel.location_id'),
            'email' => $user->email ?? null,
            'firstName' => $user->first_name ?? null,
            'lastName' => $user->last_name ?? null,
            'phone' => $user->phone ?? null,
        ];

        // Add additional fields if available
        if (isset($user->home_address)) {
            $contactData['address'] = $user->home_address;
        }

        if (isset($user->city)) {
            $contactData['city'] = $user->city;
        }

        if (isset($user->state)) {
            $contactData['state'] = $user->state;
        }

        if (isset($user->zip)) {
            $contactData['postalCode'] = $user->zip;
        }

        return $contactData;
    }

    /**
     * Push user data to HighLevel
     */
    protected function pushUserToHighLevel(object $user): ?array
    {
        $contactData = $this->formatUserToHighLevelContact($user);

        return $this->upsertHighLevelContact($contactData);
    }
}
