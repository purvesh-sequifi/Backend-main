<?php

namespace App\Core\Traits;

use App\Http\Controllers\API\JobNimbus\ContactsController;
use App\Http\Controllers\API\JobNimbus\JobsControllerller;

trait JobNimbusTrait
{
    public function getJobNimbuscontats($token = '')
    {
        return ContactsController::index($token);
    }

    public function storeJobNimbuscontats($data, $token = '')
    {
        try {
            $response = $this->makeCurlRequest($token, 'POST', 'https://app.jobnimbus.com/api1/contacts', $data);
            $responseData = json_decode($response, true);

            return ['status' => true, 'message' => 'Store Contact Successfully', 'data' => $responseData];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'error' => $e->getMessage()];
        }
    }

    public function updateJobNimbuscontats($data, $jobnimbus_jnid, $token = '')
    {
        try {
            $response = $this->makeCurlRequest($token, 'PUT', 'https://app.jobnimbus.com/api1/contacts/'.$jobnimbus_jnid, $data);
            $responseData = json_decode($response, true);

            return ['status' => true, 'message' => 'Update Contact Successfully', 'data' => $responseData];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage(), 'error' => $e->getMessage()];
        }
    }

    public function getJobNimbuscontat($id, $token = '')
    {
        return ContactsController::show($id, $token);
    }

    public function getJobNimbusJobs($token = '')
    {
        try {
            $data = $this->makeCurlRequest($token, 'GET', 'https://app.jobnimbus.com/api1/jobs');
            $responseData = json_decode($data, true);

            return ['status' => true, 'message' => 'Store Job Successfully', 'data' => $responseData];
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'error' => $e->getMessage()]);
        }
    }

    public function storeJobNimbusJobs($data, $token = '')
    {
        return JobsControllerller::store($data, $token);
    }

    public function updateJobNimbusJobs($data, $token = '')
    {
        return JobsControllerller::update($data, $token);
    }

    public function getJobNimbusJob($id, $token = '')
    {
        return JobsControllerller::show($id, $token);
    }

    public function makeCurlRequest($token, $method, $url, $postData = [])
    {
        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$token,
                'Content-Type: application/json',
            ],
        ];
        if ($method === 'POST' || $method === 'PUT') {
            $options[CURLOPT_POSTFIELDS] = json_encode($postData);
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            // Handle cURL error
            throw new \Exception('cURL error: '.curl_error($curl));
        }
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            // Handle HTTP error
            throw new \Exception($response);
        }
        curl_close($curl);

        return $response;
    }
}
