<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\HubspotTrait;
use App\Http\Controllers\Controller;
use App\Models\HubspotTransectionLog;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubSpotCurrentEnergyController extends Controller
{
    use HubspotTrait;

    public function hubspotCurrentEnergyWebhook(Request $request): JsonResponse
    {
        try {
            $responseData = $request->all();
            $dealId = (isset($responseData) && ! empty($responseData['objectId'])) ? $responseData['objectId'] : null;
            $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
            $tokens = config('services.hubspot_current_energy.api_key');

            if (! $integration) {
                return response()->json([
                    'ApiName' => 'hubspot_webhook_response',
                    'status' => false,
                    'message' => 'Hubspot CRM is not active',
                ], 400);
            }
            if (! $tokens) {
                return response()->json([
                    'ApiName' => 'hubspot_webhook_response',
                    'status' => false,
                    'message' => 'Hubspot token not available',
                ], 400);
            }

            if (! $dealId) {
                return response()->json([
                    'ApiName' => 'hubspot_webhook_response',
                    'status' => false,
                    'message' => 'objectId not available',
                ], 400);
            }

            $url = 'https://api.hubapi.com/crm/v3/objects/deals/'.$dealId.'?properties=dealname%2Cproject_id%2Cfinancing_type%2Camount%2Csystem_size__kw_%2Cgross_epc%2Cnet_epc%2Cdealer_fee%2Chs_tcv%2Cclosedate%2Cpayment_approved_date%2Csales_representative%2Cnum_associated_contacts%2Chs_acv%2Cdealstage%2Cadders_total_amount%2Ccancel_date%2Cadders_total_amount%2Csales_rep_id_sales%2Ccontract_date%2Cfinancing_type&archived=false&associations=contacts';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'content-type: application/json',
                    "Authorization:Bearer $tokens",
                ],
            ]);
            $response = curl_exec($ch);
            $res = (object) json_decode($response);
            $contactIds = isset($res->associations->contacts->results) ? array_column($res->associations->contacts->results, 'id') : null;

            $this->hubspotSubroutineForCurrentEnergy($res, $contactIds);
            $responseData = $request->all();
            HubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_response',
                'response' => json_encode($responseData),
            ]);

            return response()->json([
                'ApiName' => 'hubspot_webhook_response',
                'status' => true,
                'message' => 'success',
            ]);
        } catch (\Exception $e) {
            $responseData = $request->all();
            hubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_response',
                'response' => json_encode($responseData),
            ]);
            hubspotTransectionLog::create([
                'api_name' => 'hubspot_webhook_error',
                'response' => $e->getMessage(),
            ]);

            return response()->json([
                'ApiName' => 'hubspot_webhook_response',
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 400);
        }
    }
}
