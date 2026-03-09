<?php

namespace App\Http\Controllers\API\JobNimbus;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobsControllerller extends Controller
{
    private $token;

    public function __construct(Request $request)
    {
        // $this->token = $request->header('Authorization'); // Assuming the token is provided in the Authorization header
        $this->token = 'loxxqh0l9el1swnn';
    }

    public static function index($token): JsonResponse
    {
        try {
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return response()->json(['success' => false, 'message' => 'Error retrieving contact', 'error' => 'Not allowed to fetch data!'], 500);
            }

            $data = self::makeCurlRequest($token, 'GET', 'https://app.jobnimbus.com/api1/jobs');
            $responseData = json_decode($data, true); // Assuming the response is in JSON format

            return response()->json(['success' => true, 'data' => $responseData, 'message' => 'Contacts retrieved successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error retrieving contacts', 'error' => $e->getMessage()], 500);
        }
    }

    public static function store(Request $request, $token): JsonResponse
    {
        try {
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return response()->json(['success' => false, 'message' => 'Error storing contact', 'error' => 'Not allowed to create data!'], 500);
            }

            $postData = $request->json()->all();
            $data = self::makeCurlRequest($token, 'POST', 'https://app.jobnimbus.com/api1/jobs', $postData);
            $responseData = json_decode($data, true); // Assuming the response is in JSON format

            return response()->json(['success' => true, 'data' => $responseData, 'message' => 'Contact stored successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error storing contact', 'error' => $e->getMessage()], 500);
        }
    }

    public static function update(Request $request, $token): JsonResponse
    {
        try {
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return response()->json(['success' => false, 'message' => 'Error updating contact', 'error' => 'Not allowed to update data!'], 500);
            }

            $url = 'https://app.jobnimbus.com/api1/jobs/'.$request->jnid;
            $postData = $request->json()->all();

            $data = self::makeCurlRequest($token, 'PUT', $url, $postData);
            $responseData = json_decode($data, true); // Assuming the response is in JSON format

            return response()->json(['success' => true, 'data' => $responseData, 'message' => 'Contact updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating contact', 'error' => $e->getMessage()], 500);
        }
    }

    public static function show(Request $request, $token): JsonResponse
    {
        try {
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return response()->json(['success' => false, 'message' => 'Error retrieving contact', 'error' => 'Not allowed to fetch data!'], 500);
            }

            $url = 'https://app.jobnimbus.com/api1/jobs/'.$request->jnid;
            $data = self::makeCurlRequest($token, 'GET', $url);
            $responseData = json_decode($data, true); // Assuming the response is in JSON format

            return response()->json(['success' => true, 'data' => $responseData, 'message' => 'Contact retrieved successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error retrieving contact', 'error' => $e->getMessage()], 500);
        }
    }

    private static function makeCurlRequest($token, $method, $url, $postData = [])
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
            throw new \Exception('Error No. '.$httpCode);
        }

        curl_close($curl);

        return $response;
    }
}
