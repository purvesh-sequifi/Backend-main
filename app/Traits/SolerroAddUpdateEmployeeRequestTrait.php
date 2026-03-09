<?php

namespace App\Traits;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait SolerroAddUpdateEmployeeRequestTrait
{
    /**
     * Send a request to the employee API.
     */
    public function SolerroSendEmployeeRequest(array $data): Response
    {
        $url = 'https://sequifi-employee-details-190496064751.us-central1.run.app/add-or-update-employee';

        // Bearer token for Authorization
        $token = '6SE9F8ax3IbKrS25jTFftrYLcU19Si6zEDrZklZjJjwJwVhqftFbF7Q8BvpxWO52';

        // Send the POST request using Laravel's HTTP Client
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Cookie' => 'JSESSIONID=1A841B775432C45F01829CB19AA1F3F8', // Cookie header
        ])
            ->post($url, $data); // POST request with raw data
    }
}
