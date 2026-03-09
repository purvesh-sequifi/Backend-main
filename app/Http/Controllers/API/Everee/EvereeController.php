<?php

namespace App\Http\Controllers\API\Everee;

use Validator;
use App\Models\Crms;
use App\Models\User;
use App\Models\State;
use App\Models\Payroll;
use App\Models\Locations;
use Illuminate\Http\Request;
use App\Jobs\EvereewebhookJob;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use App\Core\Traits\EvereeTrait;
use App\Models\ApprovalsAndRequest;
use App\Http\Controllers\Controller;
use App\Models\evereeTransectionLog;
use App\Models\AdvancePaymentSetting;
use App\Jobs\PayrollFailedRecordsProcess;

class EvereeController extends Controller
{
    use EvereeTrait;

    // gettoken() method removed - now using the fixed one from EvereeTrait


    public function handle(Request $request)
    {
        $rawData = $request->getContent();
        evereeTransectionLog::create([
            'api_name' => 'webhook_response',
            'response' => $rawData
        ]);

        try {
            $payload = json_decode($rawData, true);
            if (isset($payload['type'])) {
                if ($payload['type'] == 'payment-payables.status-changed') {
                    foreach ($payload['data'] as $statusVal) {
                        $userExist = User::where('employee_id', $statusVal['externalWorkerId'])->exists();
                        if ($userExist) {
                            if ($statusVal['paymentStatus'] == 'PAID') {
                                $exists = OneTimePayments::where(['everee_paymentId' => $statusVal['paymentId'], 'everee_payment_status' => 1])
                                    ->union(PayrollHistory::where(['everee_paymentId' => $statusVal['paymentId'], 'everee_payment_status' => 3]))->exists();
                                if (!$exists) {
                                    EvereewebhookJob::dispatch($statusVal, true);
                                }
                            } else if ($statusVal['paymentStatus'] == 'ERROR') {
                                EvereewebhookJob::dispatch($statusVal, false);
                            }
                        } else {
                            evereeTransectionLog::create([
                                'api_name' => 'webhook_user_not_found - payment.deposit-returned',
                                'response' => $rawData
                            ]);
                        }
                    }
                } else if ($payload['type'] == 'payment.deposit-returned') {
                    foreach ($payload['data'] as $statusVal) {
                        $statusVal['event_type'] = "payment.deposit-returned";
                        $userExist = User::where('employee_id', $statusVal['externalWorkerId'])->exists();
                        if ($userExist) {
                            $exists = OneTimePayments::where(['everee_paymentId' => $statusVal['paymentId']])
                                ->union(PayrollHistory::where(['everee_paymentId' => $statusVal['paymentId']]))->exists();
                            if ($exists) {
                                EvereewebhookJob::dispatch($statusVal, false);
                            }
                        } else {
                            evereeTransectionLog::create([
                                'api_name' => 'webhook_user_not_found - payment.deposit-returned',
                                'response' => $rawData
                            ]);
                        }
                    }
                } else if ($payload['type'] == 'worker.updated-payment-method') {
                    if (isset($payload['data']['object']['externalWorkerId'])) {
                        $externalWorkerId = $payload['data']['object']['externalWorkerId'];
                        $user = User::where('employee_id', $externalWorkerId)->first();
                        if ($user) {
                            $payrollHistory = PayrollHistory::where(['user_id' => $user->id, 'everee_payment_status' => 2])->whereNotNull('everee_external_id')->get();
                            foreach ($payrollHistory as $history) {
                                $history->everee_payment_status = 3;
                                $history->everee_webhook_json = json_encode($payload, JSON_PRETTY_PRINT);
                                $history->save();

                                if ($history->is_onetime_payment) {
                                    OneTimePayments::where('id', $history->one_time_payment_id)->update(['everee_payment_status' => 1, 'everee_webhook_response' => json_encode($payload, JSON_PRETTY_PRINT)]);
                                }
                            }
                            $oneTimePayments = OneTimePayments::where(['user_id' => $user->id, 'everee_payment_status' => 2, 'is_deposit_returned' => 1])->get();
                            foreach ($oneTimePayments as $oneTimePayment) {
                                $oneTimePayment->everee_payment_status = 1;
                                $oneTimePayment->is_deposit_returned = 0;
                                $oneTimePayment->everee_webhook_response = json_encode($payload, JSON_PRETTY_PRINT);
                                $oneTimePayment->save();

                                $date = date('Y-m-d');
                                $approvalAndRequest = ApprovalsAndRequest::where(['id' => $oneTimePayment->req_id, 'status' => 'Accept'])->first();
                                if ($approvalAndRequest && !empty($oneTimePayment->req_id)) {
                                    $approvalAndRequest->status = 'Paid';
                                    $approvalAndRequest->txn_id = $oneTimePayment->req_no;
                                    $approvalAndRequest->payroll_id = 0;
                                    $approvalAndRequest->pay_period_from = $date;
                                    $approvalAndRequest->pay_period_to = $date;
                                    $approvalAndRequest->save();
                                }

                                if ($oneTimePayment->adjustment_type_id == 4) {
                                    $nextFromDate = NULL;
                                    $nextToDate = NULL;
                                    $advanceRequestStatus = "Approved";
                                    $advanceSetting = AdvancePaymentSetting::first();
                                    $user = User::where('id', $oneTimePayment->user_id)->first();
                                    $nextPeriod = $this->openPayFrequency($user->sub_position_id, $user->id);
                                    if (isset($nextPeriod['pay_period_from']) && $advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                                        $nextFromDate = $nextPeriod['pay_period_from'];
                                        $nextToDate = $nextPeriod['pay_period_to'];
                                        $advanceRequestStatus = "Accept";
                                    }

                                    $duplicateCheck = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4])->where('txn_id', $oneTimePayment->req_no)->first();
                                    if (!$duplicateCheck) {
                                        $description = null;
                                        if ($approvalAndRequest && !empty($approvalAndRequest->req_no)) {
                                            $description = 'Advance payment request Id: ' . $approvalAndRequest->req_no . ' Date of request: ' . date("m/d/Y");
                                        }

                                        $approvalAndRequest = ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'id' => $oneTimePayment->req_id, 'status' => 'Paid', 'adjustment_type_id' => 4])->first();
                                        ApprovalsAndRequest::create([
                                            'user_id' => isset($approvalAndRequest) ? $approvalAndRequest->user_id : $oneTimePayment->user_id,
                                            'parent_id' => isset($approvalAndRequest) ? $approvalAndRequest->id : null,
                                            'manager_id' => isset($approvalAndRequest) ? $approvalAndRequest->manager_id : $user->manager_id,
                                            'approved_by' => isset($approvalAndRequest) ? $approvalAndRequest->approved_by : $user->id,
                                            'adjustment_type_id' => isset($approvalAndRequest) ? $approvalAndRequest->adjustment_type_id : $oneTimePayment->adjustment_type_id,
                                            'state_id' => isset($approvalAndRequest) ? $approvalAndRequest->state_id : null,
                                            'dispute_type' => isset($approvalAndRequest) ? $approvalAndRequest->dispute_type : null,
                                            'customer_pid' => isset($approvalAndRequest) ? $approvalAndRequest->customer_pid : null,
                                            'cost_tracking_id' => isset($approvalAndRequest) ? $approvalAndRequest->cost_tracking_id : null,
                                            'cost_date' => isset($approvalAndRequest) ? $approvalAndRequest->cost_date : $date,
                                            'request_date' => isset($approvalAndRequest) ? $approvalAndRequest->request_date : $date,
                                            'amount' => isset($approvalAndRequest) ? (0 - $approvalAndRequest->amount) : (0 - $oneTimePayment->amount),
                                            'status' => $advanceRequestStatus,
                                            'description' => $description,
                                            'pay_period_from' => isset($nextFromDate) ? $nextFromDate : NULL,
                                            'pay_period_to' => isset($nextToDate) ? $nextToDate : NULL,
                                            'user_worker_type' => isset($approvalAndRequest) ? $approvalAndRequest->user_worker_type : $oneTimePayment->user_worker_type,
                                            'pay_frequency' => isset($approvalAndRequest) ? $approvalAndRequest->pay_frequency : $oneTimePayment->pay_frequency,
                                            'txn_id' => $oneTimePayment->req_no
                                        ]);
                                    }
                                }
                            }
                            evereeTransectionLog::create([
                                'api_name' => 'worker_payment_method_updated_success',
                                'user_id' => $user->id,
                                'response' => json_encode([
                                    'externalWorkerId' => $externalWorkerId,
                                    'user_id' => $user->id,
                                    'payroll_records_updated' => count($payrollHistory->toArray()),
                                    'onetime_payments_updated' => count($oneTimePayments->toArray()),
                                    'message' => 'Only actual deposit-returned payments marked as Paid'
                                ])
                            ]);
                        } else {
                            evereeTransectionLog::create([
                                'api_name' => 'webhook_user_not_found - worker.updated-payment-method',
                                'response' => $rawData,
                                'payload' => json_encode([
                                    'externalWorkerId' => $externalWorkerId,
                                    'error' => 'User not found in system'
                                ])
                            ]);
                        }
                    } else {
                        evereeTransectionLog::create([
                            'api_name' => 'worker_updated_payment_method_incomplete_data',
                            'response' => $rawData,
                            'payload' => 'Missing externalWorkerId in webhook data',
                        ]);
                    }
                } elseif ($payload['type'] == 'payment.paid') {
                    foreach ($payload['data'] as $statusVal) {
                        $userExist = User::where('employee_id', $statusVal['externalWorkerId'])->exists();
                        if ($userExist) {
                            $exists = OneTimePayments::where(['everee_paymentId' => $statusVal['paymentId'], 'everee_payment_status' => 1])
                                ->union(PayrollHistory::where(['everee_paymentId' => $statusVal['paymentId'], 'everee_payment_status' => 3]))->exists();
                            if (!$exists) {
                                EvereewebhookJob::dispatch($statusVal, true);
                            }
                        } else {
                            evereeTransectionLog::create([
                                'api_name' => 'webhook_user_not_found - payment.paid',
                                'response' => $rawData
                            ]);
                        }
                    }
                } elseif ($payload['type'] == 'worker.onboarding-completed') {
                    // onboarding completed at everee side
                    // onboardProcess == 1 in users
                    
                    evereeTransectionLog::create([
                        'api_name' => 'worker_onboarding_webhook_response',
                        'response' => $rawData,
                        'payload' => isset($payload['data']['object']['workerId'])?$payload['data']['object']['workerId']:0,
                    ]);
                    
                    if(isset($payload['data']['object']['workerId'])){
                        
                        $user = User::where('everee_workerId',$payload['data']['object']['workerId'])->first();
                        if($user){
                            $everee_workerId_is_empty= false;
                            if($user->everee_embed_onboard_profile == 0){
                                $everee_workerId_is_empty= true;
                            }

                            User::where('everee_workerId',$payload['data']['object']['workerId'])->update([
                                'everee_embed_onboard_profile' => 1
                            ]);

                            if($everee_workerId_is_empty===true) {
                                PayrollFailedRecordsProcess::Dispatch($user->id);
                            }
                            
                            
                            

                            // retrieveWorkerByEvereeWorkerID

                            $workerDataFromEveree = $this->retrieveWorkerByEvereeWorkerID($payload['data']['object']['workerId']);


                            $state = State::where('state_code', $workerDataFromEveree['homeAddress']['current']['state'])->first();
                            $home_address_state = null;
                            if($state)
                            {
                                $home_address_state = $state->state_code;
                            }

                            User::where('everee_workerId',$payload['data']['object']['workerId'])->update([
                                'everee_json_response' => [
                                    'webhookResponse' => $payload,
                                    'workerDataFromEveree' => $workerDataFromEveree,
                                ]
                            ]);

                            if(isset($workerDataFromEveree['taxpayerIdentifier']) && isset($workerDataFromEveree['unverifiedTinType']) && $workerDataFromEveree['unverifiedTinType'] == 'SSN'){
                                User::where('everee_workerId',$payload['data']['object']['workerId'])->update([
                                    'social_sequrity_no' => $workerDataFromEveree['taxpayerIdentifier'],
                                    'entity_type' => 'individual'
                                ]);
                            }

                            if(isset($workerDataFromEveree['taxpayerIdentifier']) && isset($workerDataFromEveree['unverifiedTinType']) && $workerDataFromEveree['unverifiedTinType'] == 'ITIN'){
                                User::where('everee_workerId',$payload['data']['object']['workerId'])->update([
                                    'business_ein' => $workerDataFromEveree['taxpayerIdentifier'],
                                    'entity_type' => 'business'
                                ]);
                            }
                
                            $dataToUpdate = [
                                'first_name' => $workerDataFromEveree['firstName'],
                                'middle_name' => isset($workerDataFromEveree['middleName']) ? $workerDataFromEveree['middleName'] : null,
                                'last_name' => $workerDataFromEveree['lastName'],
                                'dob' => $workerDataFromEveree['dateOfBirth'],
                                'email' => $workerDataFromEveree['email'],
                                'mobile_no' => $workerDataFromEveree['phoneNumber'],
                                'home_address_line_1' => $workerDataFromEveree['homeAddress']['current']['line1'],
                                'home_address_city' => $workerDataFromEveree['homeAddress']['current']['city'],
                                'home_address_state' => $home_address_state,
                                'home_address_zip' => $workerDataFromEveree['homeAddress']['current']['postalCode'],
                                'type_of_account' => $workerDataFromEveree['bankAccounts'][0]['accountType'],
                                'name_of_bank' => $workerDataFromEveree['bankAccounts'][0]['bankName'],
                                'account_name' => $workerDataFromEveree['bankAccounts'][0]['accountName'],
                                'routing_no' => $workerDataFromEveree['bankAccounts'][0]['routingNumber'],
                                'account_no' => $workerDataFromEveree['bankAccounts'][0]['accountNumberLast4'],
                            ];
                
                            if(isset($workerDataFromEveree['homeAddress']['current']['latitude']) && isset($workerDataFromEveree['homeAddress']['current']['longitude'])){
                                $dataToUpdate['home_address_lat'] = $workerDataFromEveree['homeAddress']['current']['latitude'];
                                $dataToUpdate['home_address_long'] = $workerDataFromEveree['homeAddress']['current']['longitude'];
                            }
                
                            if(isset($workerDataFromEveree['homeAddress']['current']['timeZone'])){
                                $dataToUpdate['home_address_timezone'] = $workerDataFromEveree['homeAddress']['current']['timeZone'];
                            }
                    
                            User::where('everee_workerId',$payload['data']['object']['workerId'])->update($dataToUpdate);
                        }else{
                            evereeTransectionLog::create([
                                'api_name' => 'webhook_user_not_found - worker.onboarding-completed',
                                'response' => $rawData
                            ]);
                        } 
                    } else {
                        evereeTransectionLog::create([
                            'api_name' => 'worker_onboarding_webhook_response',
                            'response' => $rawData,
                            'payload' => 'everee worker id not found',
                        ]);
                    }
                }              
            }
            return response()->json([
                'ApiName' => 'everee_webhook',
                'status' => true,
                'message' => 'success'
            ], 200);           
        }catch(\Exception $e){ 
            evereeTransectionLog::create([
                'api_name' => 'webhook_error',
                'response' => $e->getMessage()
            ]);
            return response()->json([
                'ApiName' => 'everee_webhook',
                'status' => false,
                'message' => $e->getMessage(),
                'line'=>$e->getLine()
            ], 400);
        }
    }

    public function add_locations()
    {
        $return = [];
        $locations = Locations::with('State')->where('type', 'Office')->get();
        if (! empty($locations) && (count($locations) > 0)) {
            // $locations= json_decode($locations,true);
            // $locs = array_chunk($locations,20);
            foreach ($locations as $location) {
                $this->add_location($location);
                // foreach($res as $loc)
                // {
                //     if(!empty($loc['type']) && $loc['type'] == 'Office')
                //     {
                //         if(!empty($loc['office_name']) && !empty($loc['business_address']) && !empty($loc['business_city']) && !empty($loc['business_state']) && !empty($loc['business_zip']) && !empty($loc['lat']) && !empty($loc['long']) && !empty($loc['time_zone']))
                //         {
                //         $fields = json_encode([
                //             'name' => $loc['office_name'],
                //             'line1' =>$loc['business_address'],
                //             'line2' =>'',
                //             'city' => $loc['business_city'],
                //             'state' =>$loc['state']['state_code'],
                //             'postalCode' =>$loc['business_zip'],
                //             'latitude' =>$loc['lat'],
                //             'longitude' =>$loc['long'],
                //             'timeZone' => $loc['time_zone'],
                //             'effectiveDate' => date('Y-m-d'),
                //         ]);
                //         $url = "https://api-prod.everee.com/api/v2/work-locations";
                //         $method = "POST";
                //         $headers = [
                //             "Authorization: Basic ".base64_encode($this->api_token),
                //             "accept: application/json",
                //             "content-type: application/json",
                //             "x-everee-tenant-id: ".$this->company_id
                //         ];
                //         $response = curlRequest($url,$fields,$headers,$method);
                //         $resp = json_decode($response, true);
                //         array_push($return,$response);
                //         $rid = isset($resp['id'])?$resp['id']:null;
                //         Locations::with('state')->where('id', $loc['id'])->update(['everee_location_id' =>$rid,'everee_json_response'=>$response]);
                //         }
                //     }
                // }
            }
        }

        return $return;
    }

    public function add_contractors()
    {
        $users = User::with('state', 'city')->where('id', '!=', 1)->where('dismiss', 0)->get();
        foreach ($users as $key => $data) {
            $everee_location_id = Locations::where('id', $data['office_id'])->select('everee_location_id')->first();
            $this->update_emp_personal_info($data, $data->state);
        }
    }

    public function update_user_state_code()
    {
        return $this->update_home_address_state();
    }

    public function update_everee_id($externalid)
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $url = 'https://api-prod.everee.com/api/v2/workers/external/'.$externalid;
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        $resp = json_decode($response, true);

        return $resp;

    }

    public function delete_everee_onboarding($api = 'All', $filter = ['size' => 500])
    {

        $response_arry = [];
        $msg = '';
        $return = [];
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $fields = json_encode([]);
        $filter = [
            'size' => 500,
        ];
        $getPayables = $url = 'https://api-prod.everee.com/api/v2/payables?'.http_build_query($filter);
        $response = curlRequest($url, $fields, $headers, 'GET');
        $response = json_decode($response, true);

        foreach ($response['items'] as $value) {
            $url = 'https://api-prod.everee.com/api/v2/payables/'.$value['id'];
            $response = curlRequest($url, $fields, $headers, 'DELETE');
        }
    }

    public function delete_payables()
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        $users = User::with('payroll')->where('everee_workerId', '!=', null)->get();
        $arr = [];
        $darr = [];
        foreach ($users as $user) {
            if (! empty($user->employee_id)) {
                $url = 'https://api-prod.everee.com/api/v2/payables/unpaid-for-worker/'.$user->employee_id;
                $method = 'GET';
                $headers = [
                    'Authorization: Basic '.base64_encode($this->api_token),
                    'content-type: application/json',
                    'x-everee-tenant-id: '.$this->company_id,
                ];
                $response = curlRequest($url, $fields = '', $headers, $method);
                $resp = json_decode($response, true);
                $arr[] = $resp;
            }
            foreach ($arr as $ar) {
                if (! empty($ar['items'])) {
                    if (! empty($user->payroll->everee_external_id)) {
                        $url = 'https://api-prod.everee.com/api/v2/payables/'.$user->payroll->everee_external_id;
                        $method = 'DELETE';
                        $headers = [
                            'Authorization: Basic '.base64_encode($this->api_token),
                            'accept: application/json',
                            'content-type: application/json',
                            'x-everee-tenant-id: '.$this->company_id,
                        ];
                        $response = curlRequest($url, $fields = '', $headers, $method);
                        $resp = json_decode($response, true);
                        $darr[] = $resp;
                    }
                }
            }
        }
    }

    public function getEvereepayables(Request $request): JsonResponse
    {
        $payable = [];
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $finalized = Payroll::with('usersdata')->where(['status' => 2, 'finalize_status' => 2])->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
        foreach ($finalized as $finalized) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                if ($finalized->everee_external_id != null && $finalized['usersdata']['employee_id'] != null && $finalized['usersdata']['everee_workerId'] != null) {
                    $payable[] = [
                        'id' => $finalized->id,
                        'payroll_id' => $finalized->id,
                        'user_id' => $finalized->user_id,
                        'externalId' => $finalized->everee_external_id,
                        'amount' => $finalized->net_pay,
                        'pay_period_from' => $finalized->pay_period_from,
                        'pay_period_to' => $finalized->pay_period_to,
                        'user_info' => $finalized['usersdata'],
                    ];
                }
            }
        }

        return response()->json([
            'ApiName' => 'get_everee_payables',
            'status' => true,
            'message' => 'success',
            'data' => $payable,
        ], 200);
    }

    public function getEvereeMissingData(): JsonResponse
    {
        $missing_data = [];
        $missing = $this->get_missing_data();
        foreach ($missing as $mis) {
            if (isset($mis['paymentStatus']) && $mis['paymentStatus'] == 'UNPAYABLE_WORKER') {
                $users = User::where('employee_id', $mis['externalWorkerId'])->first();
                $missing_data[] = [
                    'externalWorkerId' => $mis['externalWorkerId'],
                    'paymentId' => $mis['paymentId'],
                    'paymentStatus' => $mis['paymentStatus'],
                    'payablePaymentRequestId' => $mis['payablePaymentRequestId'],
                    'paymentErrorType' => $mis['paymentErrorType'],
                    'userInfo' => $users,
                ];
            }
        }

        return response()->json([
            'ApiName' => 'get_everee_missing_payables',
            'status' => true,
            'message' => 'success',
            'data' => $missing_data,
        ], 200);
    }

    public function encrypt_decrypt_key(Request $request)
    {

        $Validator = Validator::make($request->all(),
            [
                'is_encrypt' => 'required',
                'key' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        if ($request->is_encrypt == 'true') {
            return $encryptedValue = openssl_encrypt(
                $request->key,
                config('app.encryption_cipher_algo'),
                config('app.encryption_key'),
                0,
                config('app.encryption_iv')
            );
            // return encryptData($request->key);
        } elseif ($request->is_encrypt == 'false') {
            return openssl_decrypt(
                $request->key,
                config('app.encryption_cipher_algo'),
                config('app.encryption_key'),
                0,
                config('app.encryption_iv')
            );
            // return decryptData($request->key);
        }
    }

    public function updateEvereeWorkerIdNull(Request $request)
    {
        $workerType = $request->workerType;
        $initialResponse = $this->retriveAllWorkers($workerType);
        $totalPages = $initialResponse['totalPages'] ?? 0;

        $allWorkerIds = [];

        // Loop through all pages if more than 1 page exists
        $pages = $totalPages > 0 ? $totalPages : 1;

        for ($page = 0; $page < $pages; $page++) {
            $response = $totalPages > 0
                ? $this->retriveAllWorkers($workerType, ['page' => $page, 'size' => 50])
                : $initialResponse;

            foreach ($response['items'] as $worker) {
                $allWorkerIds[] = $worker['workerId'];
                $this->updateEvreeExternalWorkerIdNull($worker['workerId'], $workerType);
            }

            // Break after the first loop if only one page exists
            if ($totalPages == 0) {
                break;
            }
        }

        return $allWorkerIds;
    }
}
