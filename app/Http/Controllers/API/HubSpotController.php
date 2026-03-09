<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HubspotProject;
use App\Models\HubspotSale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HubSpotController extends Controller
{
    public function createObjectSales(Request $request)
    {

        $url = 'https://api.hubapi.com/crm/v3/objects/p_sales?properties=last_name%2Cfirst_name%2Csales_name%2Chubspot_owner_id%2Chs_created_by_user_id';

        $headers = [
            'content-type: application/json',
            'Authorization: Bearer pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641',
        ];

        $curl_response = curlRequest($url, '', $headers, 'GET');
        $resp = json_decode($curl_response);
        // return $resp;

        foreach ($resp as $key => $data) {
            foreach ($data as $key => $value) {
                $hubspotCreateSale = HubspotSale::create([

                    'first_name' => isset($value->properties->first_name) ? $value->properties->first_name : null,
                    'last_name' => isset($value->properties->last_name) ? $value->properties->last_name : null,
                    'sales_name' => isset($value->properties->sales_name) ? $value->properties->sales_name : null,
                    'hubspot_owner_id' => isset($value->properties->hubspot_owner_id) ? $value->properties->hubspot_owner_id : null,
                    'hs_object_id' => isset($value->properties->hs_object_id) ? $value->properties->hs_object_id : null,
                    'hs_created_by_user_id' => isset($value->properties->hs_created_by_user_id) ? $value->properties->hs_created_by_user_id : null,
                ]);
            }
        }

        return response()->json([
            'ApiName' => 'hubspot properties sale',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' =>$hubspotCreateSale

        ], 200);
    }

    public function createObjectProject(Request $request): JsonResponse
    {

        $url = 'https://api.hubapi.com/crm/v3/objects/p_installs?properties=first_name,project_name,hubspot_owner_id,hs_created_by_user_id,account_manager,project,email,full_name,hubspot_owner_id';

        $headers = [
            'content-type: application/json',
            'Authorization: Bearer pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641',
        ];

        $curl_response = curlRequest($url, '', $headers, 'GET');
        $resp = json_decode($curl_response);

        foreach ($resp as $key => $data) {
            foreach ($data as $key => $value) {

                $hubspotCreateProject = HubspotProject::create([

                    'first_name' => isset($value->properties->first_name) ? $value->properties->first_name : null,
                    'last_name' => isset($value->properties->last_name) ? $value->properties->last_name : null,
                    'email' => isset($value->properties->email) ? $value->properties->email : null,
                    'account_manager' => isset($value->properties->account_manager) ? $value->properties->account_manager : null,
                    'project_name' => isset($value->properties->project) ? $value->properties->project : null,
                    'hubspot_owner_id' => isset($value->properties->hubspot_owner_id) ? $value->properties->hubspot_owner_id : null,
                    'hs_object_id' => isset($value->properties->hs_object_id) ? $value->properties->hs_object_id : null,
                    'hs_created_by_user_id' => isset($value->properties->hs_created_by_user_id) ? $value->properties->hs_created_by_user_id : null,
                ]);
            }
        }

        return response()->json([
            'ApiName' => 'hubspot Custom Porject',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' =>$hubspotCreateProject

        ], 200);
    }

    // Code by Nikhil
    public function get_contact_of_hubspot(Request $request)
    {

        $token = 'pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641';
        $users = User::where('aveyo_hs_id', null)->where('id', '!=', 1)->orderBy('id', 'desc')->first();

        if (count($users) > 0) {
            foreach ($users as $key1 => $val) {

                $data['properties'] = [
                    'email' => $val->email,
                    'firstname' => $val->first_name,
                    'lastname' => $val->last_name,
                    'phone' => $val->mobile_no,
                    'company' => 'HubSpot',
                    'website' => 'hubspot.com',
                    'lifecyclestage' => 'marketingqualifiedlead',
                ];

                // return json_encode($data);

                $create_employees = $this->create_employees($data, $token);

            }

        }

        return response()->json([
            'ApiName' => 'employees_of_Hubspot',
            'status' => true,
            'message' => 'Updated Successfully.',
        ], 200);

    }

    public function create_employees($data, $token)
    {
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts';
        $data = json_encode($data);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestData($url, $data, $headers, 'POST');
        $resp = json_decode($curl_response, true);

        if (count($resp) > 0) {
            $hs_object_id = $resp['properties']['hs_object_id'];
            $email = $resp['properties']['email'];

            $updateuser = User::where('email', $email)->first();

            if ($updateuser) {
                $updateuser->aveyo_hs_id = $hs_object_id;
                $updateuser->save();
            }
        }

    }

    // function curlRequestData($url,$data,$headers,$method='POST'){

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL =>  $url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => $method,
    //         CURLOPT_POSTFIELDS => $data,
    //         CURLOPT_HTTPHEADER => $headers,

    //     ));

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     return $response;

    // }

    // End code by Nikhil

}
