<?php

namespace App\Core\Traits;

use App\Models\OnboardingEmployees;
use App\Models\User;
use Carbon\Carbon;
use Log;

trait FieldRoutesTrait
{
    public function fieldRoutesCreateEmployee($data, $checkStatus, $uid, $authenticationKey, $authenticationToken, $baseURL, $fieldrouteuserdata = false)
    {
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');

        if ($fieldrouteuserdata) {
            // Use the new approach with proper JSON encoding
            $innerJson = json_encode(['timeMark' => $currentDateTime]);
            $dataLink = json_encode([
                'dataLinkAlias' => $data->employee_id ?? '',
                'dataLink' => $innerJson,
            ]);
        } else {
            // Use the original approach with string concatenation
            $dataLink = '{"dataLinkAlias":"'.$data->employee_id.'","dataLink":"{\"timeMark\":\"'.$currentDateTime.'\"}"}';
        }

        // dd($dataLink)
        $fieldRoutesData = [
            'fname' => $data->first_name ?? null,
            'lname' => $data->last_name ?? null,
            'phone' => $data->mobile_no,
            'email' => $data->email,
            'dataLink' => $dataLink,

        ];
        $uid = $data->id;
        $create_employees = $this->createEmployeeForFieldRoutes($fieldRoutesData, $authenticationKey, $authenticationToken, $baseURL, $data->id, $data->id);
        Log::info(['create_evopest_employees===>' => $create_employees]);

    }

    public function getEmployeeDetailsFieldRoutes($authenticationKey, $authenticationToken, $baseURL, $aveyo_hs_id, $employee_id)
    {
        if (! empty($aveyo_hs_id)) {

            // $url = "https://insightpestsolutions.pestroutes.com/api/employee/".$aveyo_hs_id."?dataLink=1";
            $url = $baseURL.'/employee/'.$aveyo_hs_id.'?dataLink=1';

            $headers = [
                'accept: application/json',
                'content-type: application/json',
                'authenticationKey:'.$authenticationKey,
                'authenticationToken:'.$authenticationToken,
            ];

            $curl_response = $this->curlRequestDataForFieldRoutes($url, $data = '', $headers, 'GET');

            $response = json_decode($curl_response, true);

            Log::info(['getEmployeeDetailsFieldRoutes response===>' => $response]);
            // dd($response);
            $err = curl_error($ch);
            if (isset($res_contact['success']) && $res_contact['success'] == true) {
                $hs_object_id = $res_contact['employee']['employeeID'];
            } else {
                $hs_object_id = null;
            }

            return $hs_object_id;
        } else {
            return null;
        }

    }

    public function updateEmployeeForFieldRoutes($employeeData, $authenticationKey, $authenticationToken, $baseURL, $user_id, $aveyo_hs_id, $table = 'User')
    {

        // $url = "https://insightpestsolutions.pestroutes.com/api/employee/update";
        $url = $baseURL.'/employee/update';
        $employeeData = json_encode($employeeData);
        Log::info(['updateEmployeeForFieldRoutes===>' => $employeeData]);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authenticationKey:'.$authenticationKey,
            'authenticationToken:'.$authenticationToken,
        ];

        $curl_response = $this->curlRequestDataForFieldRoutes($url, $employeeData, $headers, 'POST');

        $resp = json_decode($curl_response, true);

        if (isset($resp) && isset($resp['status']) && $resp['status'] == 'error') {
            Log::info(['updateEmployeeForFieldRoutes updateContact error===>' => $resp]);
        } else {
            Log::info(['updateEmployeeForFieldRoutes found===>' => true]);
        }
    }

    public function createEmployeeForFieldRoutes($employeeData, $authenticationKey, $authenticationToken, $baseURL, $user_id, $check_employee_id, $table = 'User')
    {
        // $url = "https://insightpestsolutions.pestroutes.com/api/employee/create";
        $url = $baseURL.'/employee/create';
        $employeeData = json_encode($employeeData);
        Log::info(['createEmployeeForFieldRoutes===>' => $employeeData]);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authenticationKey:'.$authenticationKey,
            'authenticationToken:'.$authenticationToken,
        ];

        $curl_response = $this->curlRequestDataForFieldRoutes($url, $employeeData, $headers, 'POST');

        $resp = json_decode($curl_response, true);

        Log::info(['createEmployeeForFieldRoutes===>' => $resp]);
        if (isset($resp) && isset($resp['success']) && $resp['success'] == false) {
            Log::info(['createEmployeeForFieldRoutes error===>' => $resp]);
        } else {
            Log::info(['createEmployeeForFieldRoutes success===>' => true]);
            if (isset($resp['result'])) {
                $hs_object_id = $resp['result'];
            } else {
                $hs_object_id = 0;
            }

            if ($table == 'User') {
                $updateuser = User::where('id', $user_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['createEmployeeForFieldRoutes UserUpdate===>' => true]);

                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $check_employee_id)->first();
                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['createEmployeeForFieldRoutes OnboardingEmployeeUpdate===>' => true]);

                }

            }
        }
    }

    public function curlRequestDataForFieldRoutes($url, $postdata, $headers, $method)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
}
