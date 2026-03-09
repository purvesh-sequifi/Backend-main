<?php

namespace App\Core\Traits;

use App\Jobs\PayrollFailedRecordsProcess;
use App\Models\Cities;
use App\Models\CrmSetting;
use App\Models\EmployeeIdSetting;
use App\Models\evereeTransectionLog;
use App\Models\Locations;
use App\Models\OneTimePayments;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

trait EvereeTrait
{
    protected $api_token;

    protected $company_id;

    public function gettoken($worker_type = '1099')
    {
        $workerType = strtolower($worker_type);

        if ($workerType === 'w2') {
            $tenantId = config('everee.w2.tenant_id', '');
            $apiKey = config('everee.w2.api_key', '');

            // Decrypt API key if it's encrypted
            $password = '';
            if (!empty($apiKey)) {
                $password = openssl_decrypt(
                    $apiKey,
                    config('app.encryption_cipher_algo'),
                    config('app.encryption_key'),
                    0,
                    config('app.encryption_iv')
                );
            }

            $everee_setting = (object) [
                'username' => $tenantId,
                'password' => $password,
            ];
        } elseif ($workerType === '1099') {
            $tenantId = config('everee.1099.tenant_id', '');
            $apiKey = config('everee.1099.api_key', '');

            // Decrypt API key if it's encrypted
            $password = '';
            if (!empty($apiKey)) {
                $password = openssl_decrypt(
                    $apiKey,
                    config('app.encryption_cipher_algo'),
                    config('app.encryption_key'),
                    0,
                    config('app.encryption_iv')
                );
            }

            $everee_setting = (object) [
                'username' => $tenantId,
                'password' => $password,
            ];
        } else {
            $everee_setting = (object) [
                'username' => '',
                'password' => '',
            ];
        }

        return $everee_setting;
        // $CrmSetting = CrmSetting::where('crm_id',3)->first();
        // if(!empty($CrmSetting))
        // {
        //     $CrmSetting = CrmSetting::where('crm_id',3)->first();
        //     if(!empty($CrmSetting))
        //     {
        //         $everee_setting  = json_decode($CrmSetting['value']);

        // $everee_setting = (object)[
        //     "username" => env('EVEREE_TENANT_ID',''),
        //     "password" => env('EVEREE_API_KEY','')
        // ];
        // return $everee_setting;
        //     }
        // }
    }

    public function add_location($data)
    {
        if (! empty($data->type) && ($data->type == 'Office')) {
            if (! empty($data->office_name) && ! empty($data->business_address) && ! empty($data->business_city) && ! empty($data->business_state) && ! empty($data->business_zip) && ! empty($data->lat) && ! empty($data->long) && ! empty($data->time_zone)) {
                $fields = json_encode([
                    'name' => $data->office_name,
                    'line1' => $data->business_address,
                    'line2' => '',
                    'city' => $data->business_city,
                    'state' => $data->state->state_code,
                    'postalCode' => $data->business_zip,
                    'latitude' => $data->lat,
                    'longitude' => $data->long,
                    'timeZone' => $data->time_zone,
                    'effectiveDate' => date('Y-m-d'),
                ]);
                $url = 'https://api-prod.everee.com/api/v2/work-locations';
                $method = 'POST';

                // location for 1099 worker
                $token = $this->gettoken('1099');
                if ($token->password != '' && $token->username != '') {
                    $this->api_token = $token->password;
                    $this->company_id = $token->username;
                    $headers = [
                        'Authorization: Basic '.base64_encode($this->api_token),
                        'accept: application/json',
                        'content-type: application/json',
                        'x-everee-tenant-id: '.$this->company_id,
                    ];
                    $response = curlRequest($url, $fields, $headers, $method);
                    evereeTransectionLog::create([
                        'api_name' => 'add_location',
                        'api_url' => $url,
                        'payload' => $fields,
                        'response' => $response,
                    ]);
                    $resp = json_decode($response, true);
                    $rid = isset($resp['id']) ? $resp['id'] : $data->everee_location_id;
                    Locations::with('state')->where('id', $data->id)->update(['everee_location_id' => $rid, 'everee_json_response' => $response]);
                }

                // location for w2 worker
                $w2token = $this->gettoken('W2');
                if ($w2token->password != '' && $w2token->username != '') {

                    $this->api_token = $w2token->password;
                    $this->company_id = $w2token->username;
                    $headers = [
                        'Authorization: Basic '.base64_encode($this->api_token),
                        'accept: application/json',
                        'content-type: application/json',
                        'x-everee-tenant-id: '.$this->company_id,
                    ];
                    $response = curlRequest($url, $fields, $headers, $method);
                    evereeTransectionLog::create([
                        'api_name' => 'w2_add_location',
                        'api_url' => $url,
                        'payload' => $fields,
                        'response' => $response,
                    ]);
                    $resp = json_decode($response, true);
                    $rid = isset($resp['id']) ? $resp['id'] : $data->w2_everee_location_id;
                    Locations::with('state')->where('id', $data->id)->update(['w2_everee_location_id' => $rid]);
                }
            }
        }
    }

    public function add_contractor($data, $loc)
    {
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        if (! empty($data->first_name) && ! empty($data->last_name) && ! empty($data->mobile_no) && ! empty($data->email) && ! empty($data->employee_id) && ! empty($data->created_at)) {

            $url = 'https://api-prod.everee.com/api/v2/onboarding/employee';

            if ($data->worker_type == '1099') {
                $url = 'https://api-prod.everee.com/api/v2/onboarding/contractor';

            }

            if ($data->worker_type == 'w2') {
                $fields['payType'] = strtoupper($data->pay_type);
                $fields['payRate'] = [
                    'amount' => round($data->pay_rate, 2),
                    'currency' => 'USD',
                ];
                $fields['paySchedule'] = $data->pay_rate_type;
                $fields['typicalWeeklyHours'] = $data->expected_weekly_hours;
                $fields['withholdingSettings'] = [
                    'haveExactlyTwoJobs' => false,
                    'countOfChildren' => 0,
                    'countOfOtherDependents' => 0,
                ];

            }

            $phone = preg_replace('/[^0-9]/', '', $data->mobile_no);
            $fields = json_encode([
                'legalWorkAddress' => [
                    'useHomeAddress' => false,
                    'workLocationId' => $loc,
                ],
                'firstName' => $data->first_name,
                'middleName' => '',
                'lastName' => $data->last_name,
                'phoneNumber' => $phone,
                'email' => $data->email,
                'onboardingComplete' => true,
                'hireDate' => $data->created_at,
                'externalWorkerId' => $data->employee_id,
                'teamId' => '',
                // "payeeType" => strtoupper($data->entity_type),  // defaults to "INDIVIDUAL"
                // "businessName"=> '',  // optional but recommended if payeeType = "BUSINESS"
                // "doingBusinessAs"=> ''  // optional
            ]);

            $method = 'POST';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'add_contractor',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);

            $resp = json_decode($response, true);
            // if(!empty($resp['workerId']))
            // {
            $wid = isset($resp['workerId']) ? $resp['workerId'] : null;

            $everee_workerId_is_empty = false;
            // if(($data->everee_workerId == "" || empty($data->everee_workerId)) && $wid != null){
            //     $everee_workerId_is_empty = true;
            // }
            User::where('id', $data->id)->update(['everee_workerId' => $wid, 'everee_json_response' => $response]);
            // if($everee_workerId_is_empty===true) {
            //     PayrollFailedRecordsProcess::Dispatch($data->id);
            // }
            if ($wid !== null) {
                $user = User::where('id', $data->id)->first();
                $state = State::where('id', $data->state_id)->first();
                $this->update_emp_personal_info($user, $state);
            }

            return $resp;
        }
    }

    /**
     * Method updateEvreeExternalWorkerId: this function update external id in everee
     *
     * @object $data $data [explicite description]
     */
    public function updateEvreeExternalWorkerId($data, $workerId, $type = '')
    {
        // $getEverreUserResponse = $this->getEvreeUserDetails($data, $workerId);
        $getEverreUserResponse = true;

        if ($getEverreUserResponse) {
            $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
            $token = $this->gettoken($worker_type);
            $this->api_token = $token->password;
            $this->company_id = $token->username;

            $url = "https://api-prod.everee.com/api/v2/workers/$workerId/worker-info";
            $body = json_encode([
                'externalWorkerId' => $data->employee_id,
            ]);
            $method = 'PUT';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $body, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'update_evree_external_worker_id',
                'api_url' => $url,
                'payload' => $body,
                'response' => $response,
            ]);

            return $response;
        }
    }

    /**
     * Method getEvreeUserDetails: get data from everee third party tool
     *
     * @object $data $data [explicite description]
     */
    public function getEvreeUserDetails($data, $workerId)
    {
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = "https://api-prod.everee.com/api/v2/workers/$workerId";
        $body = json_encode([
            'externalWorkerId' => $data->employee_id,
        ]);
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $body, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $data->id,
            'api_name' => 'get_contractor_check',
            'api_url' => $url,
            'payload' => $body,
            'response' => $response,
        ]);
        if (! isset(json_decode($response, true)['errorCode'])) {
            // $userData = User::where("email", json_decode($response)->email)->orWhere("employee_id",json_decode($response)->externalWorkerId)->first();
            // if($userData){
            //     $userData->find($userData->id)->update([
            //         'everee_workerId' => json_decode($response)->workerId,
            //         'everee_json_response' => $response
            //     ]);
            // }

            $resp = json_decode($response, true);
            if (isset($resp['externalWorkerId'])) {
                return false;
            } else {
                return true;
            }
        }
    }

    // public function update_location($post)
    // {
    //     if(empty($post->everee_location_id) || empty($post->w2_everee_location_id)) {
    //         $this->add_location($post);
    //     }
    //     else {
    //         if(!empty($post->everee_location_id))
    //         {
    //             $token = $this->gettoken('1099');
    //             $this->api_token = $token->password;
    //             $this->company_id = $token->username;

    //             $url = "https://api-prod.everee.com/api/v2/work-locations/".$post->everee_location_id;
    //             $method = "GET";
    //             $headers = [
    //                 "Authorization: Basic ".base64_encode($this->api_token),
    //                 "accept: application/json",
    //                 "x-everee-tenant-id: ".$this->company_id
    //             ];
    //             $response = curlRequest($url,$fields='',$headers,$method);
    //             evereeTransectionLog::create([
    //                 // 'user_id' => $post->id,
    //                 'api_name' => 'update_location',
    //                 'api_url' => $url,
    //                 'payload' => $fields,
    //                 'response' => $response
    //             ]);
    //             $resp = json_decode($response, true);
    //         }
    //             if($resp)
    //             {
    //                 if(((!empty($post->office_name)) && ($resp['name'] != $post->office_name)) || ((!empty($post->business_address))&&($resp['line1'] != $post->business_address)) ||((!empty($post->business_city)) && ($resp['city'] != $post->business_city)))
    //                 {
    //                     if((!empty($post->state->state_code))&&(!empty($post->business_zip))&&(!empty($post->lat))&&(!empty($post->long))&&(!empty($post->time_zone)))
    //                     {
    //                       $this->add_location($post);
    //                     }
    //                 }
    //             }
    //         // }

    //         if(!empty($post->w2_everee_location_id))
    //         {
    //             $token = $this->gettoken('W2');
    //             $this->api_token = $token->password;
    //             $this->company_id = $token->username;

    //             $url = "https://api-prod.everee.com/api/v2/work-locations/".$post->everee_location_id;
    //             $method = "GET";
    //             $headers = [
    //                 "Authorization: Basic ".base64_encode($this->api_token),
    //                 "accept: application/json",
    //                 "x-everee-tenant-id: ".$this->company_id
    //             ];
    //             $response = curlRequest($url,$fields='',$headers,$method);
    //             evereeTransectionLog::create([
    //                 // 'user_id' => $post->id,
    //                 'api_name' => 'update_location',
    //                 'api_url' => $url,
    //                 'payload' => $fields,
    //                 'response' => $response
    //             ]);
    //             $resp = json_decode($response, true);

    //             if($resp)
    //             {
    //                 if(((!empty($post->office_name)) && ($resp['name'] != $post->office_name)) || ((!empty($post->business_address))&&($resp['line1'] != $post->business_address)) ||((!empty($post->business_city)) && ($resp['city'] != $post->business_city)))
    //                 {
    //                     if((!empty($post->state->state_code))&&(!empty($post->business_zip))&&(!empty($post->lat))&&(!empty($post->long))&&(!empty($post->time_zone)))
    //                     {
    //                       $this->add_location($post);
    //                     }
    //                 }
    //             }
    //         }
    //     }
    //     // else{
    //     //     $this->add_location($post);
    //     // }
    // }
    public function update_emp_personal_info($data, $state)
    {
        $data = User::find($data->id);
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $data = (object) $data;
        if ($data->onboardProcess == 1) {
            if ($data->everee_workerId == null || $data->everee_workerId == '') {
                $mobile_no = $data->mobile_no;
                $email = $data->email;
                $evereeExistingUser = $this->find_existing_user_in_everee($data->id, $mobile_no, $email, $worker_type);

                if (! empty($evereeExistingUser)) {
                    $workerId = $evereeExistingUser[0]['workerId'];
                    User::where(['id' => $data->id])->update(['everee_workerId' => $workerId]); // update in sequifi
                    $data->everee_workerId = $workerId;

                    if ($data->onboardProcess == 1) {

                    }

                    $this->update_emp_personal_info($data, $state); // update all details
                } else {
                    $location = Locations::where('id', $data->office_id)->first();
                    if ($location) {
                        $this->add_complete_contractor($data, $state, $location);
                    }
                }
            } elseif ((isset($data->everee_workerId) && ! empty($data->everee_workerId))) {
                $workerId = $data->everee_workerId;

                // update location in everee
                $location = Locations::where('id', $data->office_id)->first();
                if ($location) {
                    $this->update_emp_work_location($location, $state, $workerId);
                }

                // update personal tab

                if (! empty($data->first_name) && ! empty($data->last_name) && ! empty($data->dob)) {
                    $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/personal-info';
                    $fields = json_encode([
                        'firstName' => $data->first_name,
                        'middleName' => $data->middle_name ? $data->middle_name : '',
                        'lastName' => $data->last_name,
                        'dateOfBirth' => $data->dob,
                    ]);
                    $headers = [
                        'Authorization: Basic '.base64_encode($this->api_token),
                        'content-type: application/json',
                        'x-everee-tenant-id:'.$this->company_id,
                    ];
                    $method = 'PUT';
                    $response = curlRequest($url, $fields, $headers, $method);
                    evereeTransectionLog::create([
                        'user_id' => $data->id,
                        'api_name' => 'update_emp_personal_info',
                        'api_url' => $url,
                        'payload' => $fields,
                        'response' => $response,
                    ]);
                } else {

                }
                // update home address
                if (! empty($data->home_address) && ! empty($state) && ! empty($data->updated_at)) {
                    $ud = date('Y-m-d', strtotime($data->updated_at));
                    $city = Cities::where('id', $data->city_id)->first();
                    $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/address';
                    $fields = json_encode([
                        'line1' => $data->home_address_line_1,
                        'line2' => $data->home_address_line_2,
                        'city' => $data->home_address_city,
                        'state' => $data->home_address_state,
                        'postalCode' => $data->home_address_zip,
                        'effectiveDate' => $ud,
                    ]);
                    $headers = [
                        'Authorization: Basic '.base64_encode($this->api_token),
                        'content-type: application/json',
                        'x-everee-tenant-id:'.$this->company_id,
                    ];
                    $method = 'PUT';
                    $response = curlRequest($url, $fields, $headers, $method);
                    evereeTransectionLog::create([
                        'user_id' => $data->id,
                        'api_name' => 'update_emp_personal_info',
                        'api_url' => $url,
                        'payload' => $fields,
                        'response' => $response,
                    ]);
                }
                // update emergency contact
                if ((isset($data->emergency_contact_name) && (! empty($data->emergency_contact_name)))) {
                    $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/emergency-contacts/default';
                    $fields = json_encode([
                        'fullName' => $data->emergency_contact_name,
                        'phoneNumber' => $data->emergency_phone ? $data->emergency_phone : '',
                        'email' => $data->email ? $data->email : '',
                        'relationship' => $data->emergency_contact_relationship ? $data->emergency_contact_relationship : '',
                    ]);
                    $headers = [
                        'Authorization: Basic '.base64_encode($this->api_token),
                        'content-type: application/json',
                        'x-everee-tenant-id:'.$this->company_id,
                    ];
                    $method = 'PUT';
                    $response = curlRequest($url, $fields, $headers, $method);
                    evereeTransectionLog::create([
                        'user_id' => $data->id,
                        'api_name' => 'update_emp_personal_info',
                        'api_url' => $url,
                        'payload' => $fields,
                        'response' => $response,
                    ]);
                }
                // updat banking info
                if ((isset($data->name_of_bank) && (! empty($data->name_of_bank)))
                    && (isset($data->type_of_account) && (! empty($data->type_of_account)))
                    && (isset($data->routing_no) && (! empty($data->routing_no)))
                    && (isset($data->account_no) && (! empty($data->account_no)))
                    && (isset($data->account_name) && (! empty($data->account_name)))
                ) {
                    $this->update_emp_banking_info($data, $workerId);
                }
                // update taxpayer identifier
                if ((isset($data->social_sequrity_no) && ! empty($data->social_sequrity_no)) || (isset($data->business_ein) && ! empty($data->business_ein))) {
                    $this->update_emp_taxpayer_info($data, $workerId);
                }
                if ($workerId != null && $workerId != '' && $data->employee_id != null && $data->employee_id != '') {
                    $this->updateEvreeExternalWorkerId($data, $workerId);
                }

                if ($data->created_at != null) {
                    $this->update_hireDate($data);
                }

            }
            // Only process payroll records from last 30 days to avoid reprocessing old failures
            $cutoffDate = Carbon::now()->subDays(30); // Exact 30-day rolling window with time

            $failedPayrollRecords = PayrollHistory::where('user_id', $data->id)
                ->where('everee_status', 2)
                ->where('created_at', '>=', $cutoffDate)
                ->count();

            $failedFinalizedPayrollRecords = Payroll::where('user_id', $data->id)
                ->where('finalize_status', 2)
                ->where('status', 2)
                ->where('created_at', '>=', $cutoffDate)
                ->count();

            if ($failedPayrollRecords > 0 || $failedFinalizedPayrollRecords > 0) {
                PayrollFailedRecordsProcess::Dispatch($data->id);
            }

        } else {
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'update_emp_personal_info',
                'api_url' => '',
                'payload' => '',
                'response' => 'User has not completed self onboarding process yet',
            ]);
        }
    }

    public function add_complete_contractor($data, $state, $location)
    {
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        if ($worker_type == 'w2' || $worker_type == 'W2') {
            $error_messege = 'Only 1099 (contractor) users are allowed. A W-2 (employee) profile has been detected, so we cannot proceed with this user until they complete the self-login process.';
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'add_complete_contractor',
                'api_url' => 'validation-errors',
                'payload' => null,
                'response' => $error_messege,
            ]);

            return $error_messege;
        }
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        if ($worker_type == '1099') {
            $loc = $location->everee_location_id;
        } else {
            $loc = $location->w2_everee_location_id;
        }

        $requiredFields = [
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'mobile_no' => $data->mobile_no,
            'email' => $data->email,
            'employee_id' => $data->employee_id,
            'created_at' => $data->created_at,
            'type_of_account' => $data->type_of_account,
            'home_address_line_1' => $data->home_address_line_1,
            'home_address_city' => $data->home_address_city,
            'home_address_state' => $data->home_address_state,
            'home_address_zip' => $data->home_address_zip,
            'dob' => $data->dob,
            'name_of_bank' => $data->name_of_bank,
            'account_name' => $data->account_name,
            'routing_no' => $data->routing_no,
            'account_no' => $data->account_no,
            'location' => $loc,
            'social_sequrity_no' => $data->social_sequrity_no,
            'business_ein' => $data->business_ein,
        ];

        $errors = [];

        // Check all required fields
        foreach ($requiredFields as $fieldName => $fieldValue) {
            if (empty($fieldValue) && ! in_array($fieldName, ['social_sequrity_no', 'business_ein'])) {
                $errors[] = "The field '$fieldName' is required.";
            }
        }

        // Check social security number or business EIN
        if (empty($data->social_sequrity_no) && empty($data->business_ein)) {
            $errors[] = "Either 'social_sequrity_no' or 'business_ein' is required.";
        }

        if (! empty($errors)) {
            // Return validation errors

            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'add_complete_contractor',
                'api_url' => 'validation-errors',
                'payload' => json_encode($requiredFields),
                'response' => json_encode($errors),
            ]);
        } else {
            $phone = preg_replace('/[^0-9]/', '', $data->mobile_no);
            if ($data->type_of_account == 'checking') {
                $type = 'checking';
            } elseif ($data->type_of_account == 'saving') {
                $type = 'savings';
            } else {
                $type = $data->type_of_account;
            }

            $res = str_replace(['\'', '"', ',', ';', '<', '>', '-'], '', $data->social_sequrity_no);
            $res_ein = str_replace(['\'', '"', ',', ';', '<', '>', '-'], '', $data->business_ein);
            $taxpayerIdentifier = ! empty($res) ? $res : $res_ein;
            $homeAddress = [
                'line1' => $data->home_address_line_1,
                'line2' => $data->home_address_line_2,
                'city' => $data->home_address_city,
                'state' => $data->home_address_state,
                'postalCode' => $data->home_address_zip,
            ];
            $bankAccount = [
                'accountType' => strtoupper($type),
                'bankName' => $data->name_of_bank,
                'accountName' => $data->account_name,
                'routingNumber' => $data->routing_no,
                'accountNumber' => $data->account_no,
            ];
            $legalWorkAddress = [
                'useHomeAddress' => false,
                'workLocationId' => $loc,
            ];
            $filedData = [
                'firstName' => $data->first_name,
                'lastName' => $data->last_name,
                'phoneNumber' => $phone,
                'email' => $data->email,
                'hireDate' => $data->created_at,
                'homeAddress' => $homeAddress,
                'dateOfBirth' => $data->dob,
                'taxpayerIdentifier' => $taxpayerIdentifier,
                'bankAccount' => $bankAccount,
                'legalWorkAddress' => $legalWorkAddress,
                'externalWorkerId' => $data->employee_id,
                'payeeType' => strtoupper($data->entity_type),
                'businessName' => $data->business_name,
                // 'doingBusinessAs' => $data->business_type,
            ];
            $fields = json_encode($filedData);
            $url = 'https://api-prod.everee.com/api/v2/workers/contractor';
            $method = 'POST';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'add_complete_contractor',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);

            $resp = json_decode($response, true);
            $wid = isset($resp['workerId']) ? $resp['workerId'] : null;
            $everee_workerId_is_empty = false;
            if (($data->everee_workerId == '' || empty($data->everee_workerId)) && $wid != null) {
                $everee_workerId_is_empty = true;
                User::where('id', $data->id)->update(['everee_workerId' => $wid, 'everee_json_response' => $response]);
            }
            if ($everee_workerId_is_empty === true) {
                PayrollFailedRecordsProcess::Dispatch($data->id);
            }

            if ($wid == null) {
                // $this->add_contractor($data,$loc);
            }

            return $resp;
        }
    }

    // public function profile_curl($url,$fields)
    // {
    //     $curl = curl_init();

    //         curl_setopt_array($curl, [
    //         CURLOPT_URL =>$url ,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => "",
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 30,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => "PUT",
    //         CURLOPT_POSTFIELDS => json_encode($fields),
    //         CURLOPT_HTTPHEADER => [
    //             "Authorization: Basic ".base64_encode($this->api_token),
    //             "content-type: application/json",
    //             "x-everee-tenant-id:".$this->company_id
    //         ],
    //         ]);
    //         $response = curl_exec($curl);
    //         $err = curl_error($curl);

    //         curl_close($curl);
    // }
    public function update_emp_banking_info($data, $workerId)
    {
        $type = '';
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/bank-accounts/default';
        if ($data->type_of_account == 'checking') {
            $type = 'checking';
        } elseif ($data->type_of_account == 'saving') {
            $type = 'savings';
        } else {
            $type = $data->type_of_account;
        }
        $fields = json_encode([
            'accountType' => strtoupper($type),
            'bankName' => $data->name_of_bank,
            'accountName' => $data->account_name,
            'routingNumber' => $data->routing_no,
            'accountNumber' => $data->account_no,
        ]);
        $method = 'PUT';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $data->id,
            'api_name' => 'update_emp_banking_info',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
    }

    public function update_emp_taxpayer_info($data, $workerId)
    {
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        // echo $workerId; die;

        $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/taxpayer-identifier';
        $res = str_replace(['\'', '"',
            ',', ';', '<', '>', '-'], '', $data->social_sequrity_no);
        $res_ein = str_replace(['\'', '"',
            ',', ';', '<', '>', '-'], '', $data->business_ein);
        // if($data->entity_type == 'individual')
        // {
        //     $ssn = $res;
        // }
        // if($data->entity_type == 'business')
        // {
        //     $ssn = $res_ein;
        // }
        $fields = json_encode([
            'taxpayerIdentifier' => ! empty($res) ? $res : $res_ein,
        ]);
        $method = 'PUT';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $data->id,
            'api_name' => 'update_emp_taxpayer_info',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
        // echo $response; die;
    }

    /**
     * Method update_emp_work_location: this function update work locaton id in everee
     */
    public function update_emp_work_location($office, $state, $everee_worker_id)
    {
        $user = User::where('everee_workerId', $everee_worker_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        if ($worker_type == '1099') {
            $worker_location_id = $office->everee_location_id;
        } else {
            $worker_location_id = $office->w2_everee_location_id;
        }
        $url = 'https://api-prod.everee.com/api/v2/workers/'.$everee_worker_id.'/legal-location';
        $fields = json_encode([
            'useHomeAddress' => false,
            'workLocationId' => $worker_location_id,
            'stateUnemploymentTaxLocationId' => '',
            'effectiveDate' => date('Y-m-d'),
        ]);
        $method = 'PUT';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $user->id,
            'api_name' => 'update_emp_work_location',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);

    }

    public function update_hireDate($request)
    {
        if (isset($request->user_id)) {
            $id = $request->user_id;
        } else {
            $id = $request->id;
        }

        $everee_id = User::where('id', $id)->select('everee_workerId')->first();
        $worker_type = isset($everee_id->worker_type) ? $everee_id->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        // update hire date
        if (! empty($everee_id->everee_workerId)) {
            if ($request->created_at <= date('Y-m-d')) {
                $fields = json_encode([
                    'startDate' => $request['created_at'],
                ]);
                $url = 'https://api-prod.everee.com/api/v2/workers/'.$everee_id->everee_workerId.'/hire-date';
                $method = 'PUT';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields, $headers, $method);
                evereeTransectionLog::create([
                    'user_id' => $id,
                    'api_name' => 'update_hireDate',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);
            }
        }
    }

    public function add_payable($data, $external_id, $earningType)
    {
        $untrackIds = [];
        try {
            $res = $data;
            $res['payable_type'] = isset($data['payable_type']) ? $data['payable_type'] : 'payroll';
            $res['payable_label'] = isset($data['payable_label']) ? $data['payable_label'] : 'payroll';

            $worker_type = isset($res['usersdata']['worker_type']) ? $res['usersdata']['worker_type'] : '1099';
            $token = $this->gettoken($worker_type);
            $this->api_token = $token->password;
            $this->company_id = $token->username;

            if (! empty($res['usersdata']['employee_id']) && ! empty($res['usersdata']['everee_workerId']) && $res['usersdata']['onboardProcess'] == 1) {
                $fields = json_encode([
                    'earningAmount' => [
                        'amount' => round($res['net_pay'], 2),
                        'currency' => 'USD',
                    ],
                    'externalId' => $external_id,
                    'externalWorkerId' => $res['usersdata']['employee_id'],
                    'type' => $res['payable_type'],
                    'label' => $res['payable_label'],
                    'verified' => true,
                    'payableModel' => 'PRE_CALCULATED',
                    'earningType' => $earningType,
                    'earningTimestamp' => time(),
                ]);
                $url = 'https://api-prod.everee.com/api/v2/payables';
                $method = 'POST';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields, $headers, $method);
                $resp = json_decode($response, true);

                evereeTransectionLog::create([
                    'user_id' => $data['usersdata']['id'],
                    'api_name' => 'add_payable',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);
                if (! isset($resp['id'])) {
                    $untrackIds['fail'] = [
                        'user_id' => $res['usersdata']['id'],
                        'employee_id' => $res['usersdata']['employee_id'],
                        'everee_workerId' => $res['usersdata']['everee_workerId'],
                        'everee_response' => $resp,
                        'status' => false,
                    ];
                } else {
                    $untrackIds['success'] = [
                        'everee_response' => $resp,
                        'status' => true,
                        'externalId' => $resp['id'],
                    ];

                }
            } else {
                $untrackIds['fail'] = [
                    'user_id' => $res['usersdata']['id'],
                    'employee_id' => $res['usersdata']['employee_id'],
                    'everee_workerId' => $res['usersdata']['everee_workerId'],
                    'everee_response' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'errorCode' => '400',
                        'errorMessage' => 'Required employee information is missing or the employee has not completed the onboarding process yet.',
                    ],
                    'status' => false,
                ];
            }

            return $untrackIds;
        } catch (\Exception $e) {
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'errorCode' => '400',
                'errorMessage' => $e->getMessage(),
                'line' => $e->getLine(),
            ];

            return $untrackIds[] = ['fail' => [
                'everee_response' => $response,
                'status' => false,
            ],
            ];
        }
    }

    public function add_bulk_payable($payables = [])
    {
        try {
            $token = $this->gettoken();
            $this->api_token = $token->password;
            $this->company_id = $token->username;
            // $res = $data;
            $payablesFields = ['payables' => $payables];
            $untrackIds = [];
            $fields = json_encode($payablesFields);
            $url = 'https://api-prod.everee.com/api/v2/payables/bulk';
            $method = 'POST';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            $resp = json_decode($response, true);

            evereeTransectionLog::create([
                'user_id' => null,
                'api_name' => 'add_bulk_payable',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);

            if (! isset($resp['externalIds'])) {
                $untrackIds['fail'] = [
                    'everee_response' => $resp,
                    'status' => false,
                ];
            } else {
                $untrackIds['success'] = [
                    'everee_response' => $resp,
                    'status' => true,
                    'externalIds' => $resp['externalIds'],
                ];
            }

            return $untrackIds;
        } catch (\Exception $e) {
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'errorCode' => '400',
                'errorMessage' => $e->getMessage(),
                'line' => $e->getLine(),
            ];

            return $untrackIds['fail'] = [
                'everee_response' => $response,
                'status' => false,
            ];
        }
    }

    public function payable_request($data, $onetimePayment = null)
    {
        try {
            $res = $data;

            $untrackIds = [];
            // foreach($data as $res)
            // {
            $worker_type = isset($res['usersdata']['worker_type']) ? $res['usersdata']['worker_type'] : '1099';
            $token = $this->gettoken($worker_type);
            $this->api_token = $token->password;
            $this->company_id = $token->username;

            if ($worker_type == '1099') {
                $includeWorkersOnRegularPayCycle = false;
            } elseif ($worker_type == 'W2' || $worker_type == 'w2') {
                if ($onetimePayment) {
                    $includeWorkersOnRegularPayCycle = true;
                } else {
                    $includeWorkersOnRegularPayCycle = false;
                }
            }
            if (! empty($res['usersdata']['everee_workerId'])) {
                $fields = json_encode([
                    'includeWorkersOnRegularPayCycle' => $includeWorkersOnRegularPayCycle,
                    'externalWorkerIds' => [$res['usersdata']['employee_id']],

                ]);
                $url = 'https://api-prod.everee.com/api/v2/payables/payment-request';
                $method = 'POST';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields, $headers, $method);
                $resp = json_decode($response, true);
                evereeTransectionLog::create([
                    'user_id' => $data['usersdata']['id'],
                    'api_name' => 'payable_request',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);

                if ($includeWorkersOnRegularPayCycle == false && strtolower($worker_type) == 'w2') {
                    $untrackIds['success'] = [
                        'status' => true,
                        'paymentId' => isset($resp['id']) ? $resp['id'] : null,
                        'everee_response' => $resp,
                        'everee_payment_id' => null,
                    ];
                } else {
                    if (! isset($resp['id'])) {
                        $untrackIds['fail'] = [
                            'user_id' => $res['usersdata']['id'],
                            'employee_id' => $res['usersdata']['employee_id'],
                            'everee_workerId' => $res['usersdata']['everee_workerId'],
                            'status' => false,
                            'paymentId' => null,
                            'everee_payment_id' => null,
                            'everee_response' => $resp,
                        ];
                    } else {
                        $untrackIds['success'] = [
                            'status' => true,
                            'paymentId' => $resp['id'],
                            'everee_response' => $resp,
                            'everee_payment_id' => null,
                        ];

                        $get_payable_data = $this->get_payable_by_id($res['usersdata']['employee_id'], $resp['id']);
                        if (isset($get_payable_data['items']) && ! empty($get_payable_data['items']) && isset($get_payable_data['items'][0]['paymentId'])) {
                            $untrackIds['success']['everee_payment_id'] = $get_payable_data['items'][0]['paymentId'];
                        }
                    }
                }

            } else {
                $untrackIds['fail'] = [
                    'user_id' => $res['usersdata']['id'],
                    'employee_id' => $res['usersdata']['employee_id'],
                    'everee_workerId' => $res['usersdata']['everee_workerId'],
                    'status' => false,
                    'paymentId' => null,
                    'everee_payment_id' => null,
                    'everee_response' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'errorCode' => '400',
                        'errorMessage' => 'everee_workerId not found of user',
                    ],
                ];
            }

            return $untrackIds;
        } catch (\Exception $e) {
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'errorCode' => '400',
                'errorMessage' => $e->getMessage(),
                'line' => $e->getLine(),
            ];

            return $untrackIds[] = [
                'fail' => [
                    'status' => false,
                    'paymentId' => null,
                    'everee_payment_id' => null,
                    'everee_response' => $response,
                ],
            ];
        }
    }

    public function delete_payable($external_id, $user_id)
    {
        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        // $res = $data;
        $untrackIds = [];
        // foreach($data as $res)
        //  {
        $resp = [];
        if (! empty($external_id)) {

            $external_ids = explode(',', $external_id);
            foreach ($external_ids as $external_id) {

                $url = 'https://api-prod.everee.com/api/v2/payables/'.$external_id;
                $method = 'DELETE';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields = '', $headers, $method);
                $resp[] = json_decode($response, true);
                evereeTransectionLog::create([
                    'user_id' => $user_id,
                    'api_name' => 'delete_payable',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);
            }

            return $resp;
        }
        // }
    }

    public function update_emp_personal_info_temp($data)
    {
        $worker_type = isset($data->worker_type) ? $data->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $workerId = $data->everee_workerId;
        // update personal tab
        $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/personal-info';
        $fields = json_encode([
            'firstName' => $data->first_name,
            'middleName' => $data->middle_name ? $data->middle_name : '',
            'lastName' => $data->last_name,
            'dateOfBirth' => $data->dob,
        ]);
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $method = 'PUT';
        $response = curlRequest($url, $fields, $headers, $method);
        evereeTransectionLog::create([
            // 'user_id' => $data['user_id'],
            'api_name' => 'update_emp_personal_info_temp',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
        // update home address
        if (empty($data->home_address)) {
            $ud = date('Y-m-d', strtotime($data->updated_at));
            $city = Cities::where('id', $data->city_id)->first();
            $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/address';
            $fields = json_encode([
                'line1' => 'test',
                'line2' => 'test',
                'city' => 'test',
                'state' => 'WA',
                'postalCode' => '434544',
                'effectiveDate' => $ud,
            ]);
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'content-type: application/json',
                'x-everee-tenant-id:'.$this->company_id,
            ];
            $method = 'PUT';
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                // 'user_id' => $data['user_id'],
                'api_name' => 'update_emp_personal_info_temp',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);
        }

    }

    public function update_hireDate_temp($request)
    {
        $everee_id = User::where('id', $request->id)->select('everee_workerId')->first();
        $worker_type = isset($everee_id->worker_type) ? $everee_id->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        // update hire date
        if (! empty($everee_id->everee_workerId)) {

            $fields = json_encode([
                'startDate' => $request->period_of_agreement_start_date,
            ]);
            $url = 'https://api-prod.everee.com/api/v2/workers/'.$everee_id->everee_workerId.'/hire-date';
            $method = 'PUT';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);

            evereeTransectionLog::create([
                'user_id' => $request->id,
                'api_name' => 'update_hireDate_temp',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);

        }
    }

    public function get_missing_data()
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/payables';
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
            'accept: application/json',
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        evereeTransectionLog::create([
            // 'user_id' => $data->id,
            'api_name' => 'get_missing_data',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp['items'];
    }

    public function get_payable_by_id($external_id, $payment_request_id = '', $ext_param = [])
    {
        $user = User::where('employee_id', $external_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $params = [
            'external-worker-id' => $external_id,
            'payable-payment-request-id' => $payment_request_id,
        ];

        $params = array_merge($params, $ext_param);
        $outputArray = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });

        $outputArray = array_combine(array_keys($outputArray), array_values($outputArray));
        $url = 'https://api-prod.everee.com/api/v2/payables?'.http_build_query($outputArray);
        // $url = "https://api-prod.everee.com/api/v2/payables?external-worker-id=".$external_id;
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'x-everee-tenant-id:'.$this->company_id,
            'accept: application/json',
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        evereeTransectionLog::create([
            // 'user_id' => $data->id,
            'api_name' => 'get_payable_by_id',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function update_payable($data, $external_id)
    {
        $res = $data;

        $worker_type = isset($res['usersdata']['worker_type']) ? $res['usersdata']['worker_type'] : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $untrackIds = [];
        if (! empty($res['usersdata']['employee_id']) && ! empty($res['usersdata']['everee_workerId'])) {
            $fields = json_encode([
                'earningAmount' => [
                    'amount' => round($res['net_pay'], 2),
                    'currency' => 'USD',
                ],
                'type' => 'payable',
                'label' => 'payable_update',
                'verified' => true,
                'payableModel' => 'PRE_CALCULATED',
                'earningType' => 'COMMISSION',
                'earningTimestamp' => time(),
            ]);
            $url = 'https://api-prod.everee.com/api/v2/payables/'.$external_id;
            $method = 'PUT';
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'accept: application/json',
                'content-type: application/json',
                'x-everee-tenant-id: '.$this->company_id,
            ];
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $res['usersdata']['id'],
                'api_name' => 'update_payable',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);
            $resp = json_decode($response, true);
            if (! isset($resp['id'])) {
                $untrackIds['fail'] = [
                    'user_id' => $res['usersdata']['id'],
                    'employee_id' => $res['usersdata']['employee_id'],
                    'everee_workerId' => $res['usersdata']['everee_workerId'],
                    'everee_response' => $resp,
                    'status' => false,
                ];
            } else {
                $untrackIds['success'] = [
                    'status' => true,
                    'everee_response' => $resp,
                    'externalId' => $resp['id'],
                ];

            }
        }

        return $untrackIds;
    }

    public function update_payment_id($get_payable_data, $user_id, $payrollId)
    {
        PayrollHistory::where('user_id', $user_id)->where('payroll_id', $payrollId)->update(['everee_paymentId' => $get_payable_data['paymentId']]);
    }

    public function update_payment_id_onetime($get_payable_data, $user_id)
    {
        OneTimePayments::where(['user_id' => $user_id, 'everee_payment_req_id' => $get_payable_data['items'][0]['payablePaymentRequestId']])->update(['everee_paymentId' => $get_payable_data['items'][0]['paymentId']]);
    }

    public function get_payable($external_id, $user_id)
    {
        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $resp = [];
        if (! empty($external_id)) {

            $external_ids = explode(',', $external_id);
            foreach ($external_ids as $external_id) {

                $url = 'https://api-prod.everee.com/api/v2/payables/'.$external_id;
                $method = 'GET';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields = '', $headers, $method);
                $resp[] = json_decode($response, true);
            }
        }

        return $resp;
    }

    public function list_unpaid_payables_of_worker($workerId)
    {
        $user = User::where('employee_id', $workerId)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $query_param = [
            'size' => 50,
        ];

        $url = 'https://api-prod.everee.com/api/v2/payables/unpaid-for-worker/'.$workerId.'?'.http_build_query($query_param);
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        evereeTransectionLog::create([
            // 'user_id' => $data->id,
            'api_name' => 'list_unpaid_payables_of_worker',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function shiftadd($data, $user_id)
    {
        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $url = 'https://api-prod.everee.com/api/v2/labor/timesheet/worked-shifts/epoch';
        $fields = json_encode($data);
        $method = 'POST';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $user_id,
            'api_name' => 'add_shift_in_employee',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);

        return $response;
    }

    public function getEvreeUserinformation($data, $workerId)
    {
        $user = User::where('everee_workerId', $workerId)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = "https://api-prod.everee.com/api/v2/workers/$workerId";
        $body = json_encode([
            'externalWorkerId' => $data->employee_id,
        ]);
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $body, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $data->id,
            'api_name' => 'get_contractor_check',
            'api_url' => $url,
            'payload' => $body,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function addEmployeeForEmbeddedOnboarding($data)
    {
        $user = User::where('id', $data->id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $externalWorkerId = $data->employee_id;

        if ($data->everee_workerId != '' || ! empty($data->everee_workerId)) {
            return [
                'workerId' => $data->everee_workerId,
            ];
        }

        $retrieveWorkerByExternalIDResponse = $this->retrieveWorkerByExternalID($data->employee_id);

        if (isset($retrieveWorkerByExternalIDResponse['externalWorkerId'])) {
            if ($retrieveWorkerByExternalIDResponse['email'] == $data->email) {
                return ['workerId' => $retrieveWorkerByExternalIDResponse['workerId']];
            } else {
                $unixTimestamp = now()->timestamp;
                $EmployeeIdSetting = EmployeeIdSetting::orderBy('id', 'asc')->first();
                if (! empty($EmployeeIdSetting)) {
                    $EmpId = $EmployeeIdSetting->id_code.$unixTimestamp;
                } else {
                    $EmpId = 'EMP'.$unixTimestamp;
                }
                User::where('id', $data->id)->update([
                    'employee_id' => $EmpId,
                ]);
                $externalWorkerId = $EmpId;
            }
        }

        $url = 'https://api-prod.everee.com/api/v2/embedded/workers/employee';
        // payType string required Defaults to HOURLY
        // typicalWeeklyHours int32 required Defaults to 40
        $payType = isset($data->pay_type) ? strtoupper($data->pay_type) : 'HOURLY';
        // $amount = round($data->pay_rate,2);
        $amount = 0;
        $body = json_encode([
            'payType' => $payType,
            'payRate' => [
                'currency' => 'USD',
                'amount' => $amount,
            ],
            'typicalWeeklyHours' => isset($data->expected_weekly_hours) ? $data->expected_weekly_hours : 40,

            'legalWorkAddress' => [
                'useHomeAddress' => true,
                // 'workLocationId' => $loc
            ],

            'homeAddress' => [
                'line1' => $data->home_address_line_1,
                'line2' => $data->home_address_line_2,
                'city' => $data->home_address_city,
                'state' => $data->home_address_state,
                'postalCode' => $data->home_address_zip,
            ],

            'firstName' => $data->first_name,
            // 'middleName' => '',
            'lastName' => $data->last_name,
            'phoneNumber' => $data->mobile_no,
            'email' => $data->email,
            'hireDate' => $data->created_at,
            // optional
            'externalWorkerId' => $externalWorkerId,
        ]);
        $method = 'POST';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $body, $headers, $method);
        evereeTransectionLog::create([
            'user_id' => $data->id,
            'api_name' => 'Create employee for embedded onboarding',
            'api_url' => $url,
            'payload' => $body,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function retrieveWorkerByExternalID($externalWorkerId)
    {
        $user = User::where('employee_id', $externalWorkerId)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/workers/external/'.$externalWorkerId;

        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, [], $headers, $method);

        $resp = json_decode($response, true);

        return $resp;
    }

    public function createComponentSessionOfWorkerId($workerId)
    {

        $user = User::where('everee_workerId', $workerId)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/embedded/session';

        $body = json_encode([
            'experience' => 'ONBOARDING',
            'experienceVersion' => 'V2_0',
            'eventHandlerName' => 'ONBOARDING',
            'workerId' => $workerId,
        ]);

        $method = 'POST';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $body, $headers, $method);
        evereeTransectionLog::create([
            'api_name' => 'Create a Component session',
            'api_url' => $url,
            'payload' => $body,
            'response' => $response,
        ]);
        $resp = json_decode($response, true);

        return $resp;

    }

    public function listWorkerFiles($workerId, $page, $size)
    {
        $user = User::where('everee_workerId', $workerId)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        // https://api-prod.everee.com/api/v2/workers/files?worker-id=5cef0838-7936-4c75-b3d0-f2b0bdf7daa2&page=0&size=20

        $url = 'https://api-prod.everee.com/api/v2/workers/files?worker-id='.$workerId.'&page='.$page.'&size='.$size;

        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, [], $headers, $method);

        $resp = json_decode($response, true);

        return $resp;
    }

    public function send_timesheet_data($data)
    {
        // dd('ok',$data);
        $user = User::where('id', $data['user_id'])->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        // Log::info(['token====>' => $token]);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $workerId = $data['workerId'];
        // update personal tab
        $url = 'https://api-prod.everee.com/api/v2/labor/timesheet/worked-shifts/epoch';
        $fields = json_encode([
            'workerId' => $data['workerId'],
            'externalWorkerId' => $data['externalWorkerId'],
            'shiftStartEpochSeconds' => strtotime($data['clockIn']),
            'shiftEndEpochSeconds' => strtotime($data['clockOut']),
            'createBreaks' => [
                [
                    'breakStartEpochSeconds' => strtotime($data['lunch']),
                    'breakEndEpochSeconds' => strtotime($data['lunchEnd']),
                    'segmentConfigCode' => 'DEFAULT_UNPAID',
                ],
                [
                    'breakStartEpochSeconds' => strtotime($data['break']),
                    'breakEndEpochSeconds' => strtotime($data['breakEnd']),
                    'segmentConfigCode' => 'DEFAULT_UNPAID',
                ],
            ],
        ]);
        // dd($fields);
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        // Log::info(['headers====>' => $headers]);
        $method = 'POST';
        $response = curlRequest($url, $fields, $headers, $method);
        $resp = json_decode($response, true);
        // update home address
        evereeTransectionLog::create([
            'user_id' => $data['user_id'],
            'api_name' => 'send_timesheet_data',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);

        if (! isset($resp['worker'])) {
            $untrackIds['fail'] = [
                // 'user_id' => $res['usersdata']['id'],
                // 'employee_id' => $res['usersdata']['employee_id'],
                // 'everee_workerId' => $res['usersdata']['everee_workerId'],
                'everee_response' => $resp,
                'status' => false,
            ];
        } else {
            $untrackIds['success'] = [
                'everee_response' => $resp,
                'status' => true,
                'externalId' => isset($resp['externalId']) ? $resp['externalId'] : null,
            ];

        }

        return $untrackIds;
        // $response = json_decode($response, true);
        // dd($response);
        // if( isset($response['errorCode']) && $response['errorCode']== '400' ){
        //     return $response['errorMessage'];
        // }else{
        //     if(isset($response['workedShiftId']) && !empty($response['workedShiftId'])){
        //         return $response['workedShiftId'];
        //     }
        //     return 'something went wrong';

        // }

    }

    // W-2 payroll create gross earning
    public function create_gross_earning_data($data, $user_id)
    {
        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        // update personal tab
        $url = 'https://api-prod.everee.com/api/v2/gross-earnings';
        $fields = json_encode($data);
        /*
        $fields = json_encode([
            'workerId' => $data['workerId'],
            //'externalWorkerId' => $data['externalWorkerId'],
            'type' => $earningType,
            'grossAmount' => [
                'amount' => '500',
                'currency'=> 'USD'
            ],
            'unitRate' => [
                'amount' => '200',
                'currency'=> 'USD'
            ],
            'unitCount' => '8.0',
            'referenceDate' => '2024-07-26',
            'workLocationId' => 3005,
            'externalId' => $external_id
        ]); */
        // dd($fields);
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id:'.$this->company_id,
        ];
        $method = 'POST';
        $response = curlRequest($url, $fields, $headers, $method);
        $resp = json_decode($response, true);
        evereeTransectionLog::create([
            'user_id' => $user_id,
            'api_name' => 'W2_payroll_create_gross_earning',
            'api_url' => $url,
            'payload' => $fields,
            'response' => $response,
        ]);

        if (! isset($resp['employeeId'])) {
            $untrackIds['fail'] = [
                'everee_response' => $resp,
                'status' => false,
            ];
        } else {
            $untrackIds['success'] = [
                'everee_response' => $resp,
                'status' => true,
                'externalId' => isset($resp['externalId']) ? $resp['externalId'] : null,
            ];
        }

        return $untrackIds;
        // return $response;

    }

    public function add_payable_emp($data, $user_id)
    {
        try {
            $user = User::where('id', $user_id)->first();
            $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
            $token = $this->gettoken($worker_type);
            $this->api_token = $token->password;
            $this->company_id = $token->username;

            $untrackIds = [];
            $userData = User::select('everee_workerId', 'employee_id', 'onboardProcess')->where('id', $user_id)->first();

            if (! empty($userData['employee_id']) && ! empty($userData['everee_workerId']) && $userData['onboardProcess'] == 1) {
                $fields = json_encode($data);
                $url = 'https://api-prod.everee.com/api/v2/payables';
                $method = 'POST';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'accept: application/json',
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields, $headers, $method);
                $resp = json_decode($response, true);

                evereeTransectionLog::create([
                    'user_id' => $user_id,
                    'api_name' => 'add_payable_w2_employee',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);
                if (! isset($resp['id'])) {
                    $untrackIds['fail'] = [
                        'user_id' => $user_id,
                        'employee_id' => $userData['employee_id'],
                        'everee_workerId' => $userData['everee_workerId'],
                        'everee_response' => $resp,
                        'status' => false,
                    ];
                } else {
                    $untrackIds['success'] = [
                        'everee_response' => $resp,
                        'status' => true,
                        'externalId' => $resp['id'],
                    ];

                }
            }

            return $untrackIds;
        } catch (\Exception $e) {
            $response = [
                'timestamp' => date('Y-m-d H:i:s'),
                'errorCode' => '400',
                'errorMessage' => $e->getMessage(),
                'line' => $e->getLine(),
            ];

            return $untrackIds[] = ['fail' => [
                'everee_response' => $response,
                'status' => false,
            ],
            ];
        }
    }
    // End W-2 payroll create gross earning

    // W-2 get pay statements
    public function get_pay_statements($user_id, $start_date, $end_date)
    {

        // $start_date = '2024-07-09';
        // $end_date = '2024-07-27';

        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $user = User::where('id', $user_id)->first();
        $workerId = isset($user->everee_workerId) ? $user->everee_workerId : null;
        // $workerId = '8d113dd7-a036-437d-9476-904f2e1648ba';
        $page = 0;
        $size = 0;

        $url = 'https://api-prod.everee.com/api/v2/pay-stubs?worker-id='.$workerId.'&start-date='.$start_date.'&end-date='.$end_date.'&page='.$page.'&size='.$size;

        $method = 'GET';
        $fields = [];
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        $resp = json_decode($response, true);

        evereeTransectionLog::create([
            'user_id' => $user_id,
            'api_name' => 'W2_get_pay_statements',
            'api_url' => $url,
            'response' => $response,
        ]);

        return $resp;

    }

    public function get_pay_statement_paymentid($user_id, $paymentId)
    {

        $user = User::where('id', $user_id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/workers/payment-history/'.$paymentId;

        $method = 'GET';
        $fields = [];
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        $resp = json_decode($response, true);

        evereeTransectionLog::create([
            'user_id' => $user_id,
            'api_name' => 'W2_get_pay_statement_paymentid',
            'api_url' => $url,
            'response' => $response,
        ]);

        return $resp;

    }
    // End W-2 get pay statements`

    public function retrieveWorkerByEvereeWorkerID($evereeWorkerID)
    {

        $user = User::where('everee_workerId', $evereeWorkerID)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $url = 'https://api-prod.everee.com/api/v2/workers/'.$evereeWorkerID;

        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, [], $headers, $method);

        $resp = json_decode($response, true);

        return $resp;
    }

    public function update_w2_emp_data($data, $state)
    {
        $user = User::where('id', $data->id)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $workerId = $data->everee_workerId;

        // update personal tab

        if (! empty($data->first_name) && ! empty($data->last_name) && ! empty($data->dob)) {
            $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/personal-info';
            $fields = json_encode([
                'firstName' => $data->first_name,
                'middleName' => $data->middle_name ? $data->middle_name : '',
                'lastName' => $data->last_name,
                'dateOfBirth' => $data->dob,
            ]);
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'content-type: application/json',
                'x-everee-tenant-id:'.$this->company_id,
            ];
            $method = 'PUT';
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'update_emp_personal_info',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);
        }
        // update home address
        if (! empty($data->home_address) && ! empty($state) && ! empty($data->updated_at)) {
            $ud = date('Y-m-d', strtotime($data->updated_at));
            $city = Cities::where('id', $data->city_id)->first();
            $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/address';
            $fields = json_encode([
                'line1' => $data->home_address_line_1,
                'line2' => $data->home_address_line_2,
                'city' => $data->home_address_city,
                'state' => $data->home_address_state,
                'postalCode' => $data->home_address_zip,
                'effectiveDate' => $ud,
            ]);
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'content-type: application/json',
                'x-everee-tenant-id:'.$this->company_id,
            ];
            $method = 'PUT';
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'update_emp_personal_info',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);
        }
        // update emergency contact
        if ((isset($data->emergency_contact_name) && (! empty($data->emergency_contact_name)))) {
            $url = 'https://api-prod.everee.com/api/v2/workers/'.$workerId.'/emergency-contacts/default';
            $fields = json_encode([
                'fullName' => $data->emergency_contact_name,
                'phoneNumber' => $data->emergency_phone ? $data->emergency_phone : '',
                'email' => $data->email ? $data->email : '',
                'relationship' => $data->emergency_contact_relationship ? $data->emergency_contact_relationship : '',
            ]);
            $headers = [
                'Authorization: Basic '.base64_encode($this->api_token),
                'content-type: application/json',
                'x-everee-tenant-id:'.$this->company_id,
            ];
            $method = 'PUT';
            $response = curlRequest($url, $fields, $headers, $method);
            evereeTransectionLog::create([
                'user_id' => $data->id,
                'api_name' => 'update_emp_personal_info',
                'api_url' => $url,
                'payload' => $fields,
                'response' => $response,
            ]);
        }

        if ($data->created_at != null && $data->everee_embed_onboard_profile == 0) {
            $this->update_hireDate($data);
        }

        // $location = Locations::where('id',$data['office_id'])->select('everee_location_id')->first();
        // $everee_location_id = $location->everee_location_id;

    }

    public function listWorkerTaxFiles($userId)
    {
        $user = User::withoutGlobalScopes()->where('id', $userId)->first();
        $workerId = $user->everee_workerId;
        $workerType = isset($user->worker_type) ? $user->worker_type : '1099';

        $token = $this->gettoken($workerType);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        // https://api-prod.everee.com/api/v2/workers/files?worker-id=5cef0838-7936-4c75-b3d0-f2b0bdf7daa2&page=0&size=20
        $url = 'https://api-prod.everee.com/api/v2/workers/files?worker-id='.$workerId.'&document-type=TAXES';
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, [], $headers, $method);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function update_home_address_state()
    {

        $users = User::withoutGlobalScopes()->whereNotNull('everee_workerId')->whereNotNull('home_address_state')->get();
        foreach ($users as $key => $data) {
            $token = $this->gettoken($data->worker_type);
            $this->api_token = $token->password;
            $this->company_id = $token->username;
            if (! empty($data->home_address) && ! empty($data->updated_at)) {
                $ud = date('Y-m-d', strtotime($data->updated_at));
                $url = 'https://api-prod.everee.com/api/v2/workers/'.$data->everee_workerId.'/address';
                $fields = json_encode([
                    'line1' => $data->home_address_line_1,
                    'line2' => $data->home_address_line_2,
                    'city' => $data->home_address_city,
                    'state' => $data->home_address_state,
                    'postalCode' => $data->home_address_zip,
                    'effectiveDate' => $ud,
                ]);
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'content-type: application/json',
                    'x-everee-tenant-id:'.$this->company_id,
                ];
                $method = 'PUT';
                $response = curlRequest($url, $fields, $headers, $method);
                evereeTransectionLog::create([
                    'user_id' => $data->id,
                    'api_name' => 'update_home_address_state',
                    'api_url' => $url,
                    'payload' => $fields,
                    'response' => $response,
                ]);
            }

            if ((isset($data->social_sequrity_no) && ! empty($data->social_sequrity_no)) || (isset($data->business_ein) && ! empty($data->business_ein))) {
                $this->update_emp_taxpayer_info($data, $data->everee_workerId);
            }
        }
    }

    public function find_existing_user_in_everee($user_id, $mobile_no, $email, $worker_type)
    {
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $finalResult = [];

        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $method = 'GET';

        /* search for email */
        $url = "https://api-prod.everee.com/api/v2/workers/search?term=$email";
        $response = curlRequest($url, [], $headers, $method);
        $resp = json_decode($response, true);
        $finalResult = (isset($resp['items']) && ! empty($resp['items'])) ? $resp['items'] : [];

        evereeTransectionLog::create([
            'user_id' => $user_id,
            'api_name' => 'find_existing_user_in_everee',
            'api_url' => $url,
            'payload' => '',
            'response' => $response,
        ]);

        if (empty($finalResult)) {

            /* search for mobile_no */
            $url = "https://api-prod.everee.com/api/v2/workers/search?term=$mobile_no";
            $response = curlRequest($url, [], $headers, $method);
            $resp = json_decode($response, true);
            $finalResult = (isset($resp['items']) && ! empty($resp['items'])) ? $resp['items'] : [];

            evereeTransectionLog::create([
                'user_id' => $user_id,
                'api_name' => 'find_existing_user_in_everee',
                'api_url' => $url,
                'payload' => '',
                'response' => $response,
            ]);
        }

        return $finalResult;
    }

    public function validateTenantApiKey($workerType)
    {
        $token = $this->gettoken($workerType);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = 'https://api-prod.everee.com/api/v2/work-locations/';
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        $response = json_decode($response, true);

        return $response;
    }

    public function retriveAllWorkers($workerType, $params = ['page' => 0, 'size' => 50])
    {
        $token = $this->gettoken($workerType);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $queryParams = http_build_query($params);
        $url = 'https://api.everee.com/api/v2/workers?'.$queryParams;
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        $response = json_decode($response, true);

        return $response;
    }

    public function updateEvreeExternalWorkerIdNull($workerId, $workerType)
    {

        $token = $this->gettoken($workerType);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $url = "https://api-prod.everee.com/api/v2/workers/$workerId/worker-info";
        $body = json_encode([
            'externalWorkerId' => null,
        ]);
        $method = 'PUT';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $body, $headers, $method);

        return $response;
    }
}
