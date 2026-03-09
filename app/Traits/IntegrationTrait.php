<?php

namespace App\Traits;
use Illuminate\Support\Facades\Log;
use App\Models\{User, Integration, OnboardingEmployees, InterigationTransactionLog};


trait IntegrationTrait
{
    public function saveDataToSourceMarketing($userId, $type)
    {
        // $type = ['sales_rep_signup', 'offer_letter_signed', 'hired_employee','offer_resent','offer_expired','creds_sent'];
        try {
            $integration = Integration::where(['name' => 'SourceMarketing', 'status' => 1])->first();
            if ($integration) {
                $pairs = explode(',', $integration['value']);
                $keyValuePairs = [];
                foreach ($pairs as $pair) {
                    [$key, $value] = explode('=', $pair);
                    $keyValuePairs[trim($key)] = trim($value);
                }
                $data = OnboardingEmployees::with('office', 'managerDetail', 'positionDetail', 'departmentDetail')->where($type == 'hired_employee' ? 'user_id' : 'id', $userId)->first();
                if ($data) {
                    $image_s3 = null;
                    if (isset($data->image) && $data->image != null) {
                        $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$data->image);
                    }
                    $fields = json_encode('');
                    if ($type == 'sales_rep_signup') {
                        $fields = json_encode([
                            'type' => $type,
                            'data' => [
                                'id' => $data->id,
                                'profile_pic' => $image_s3,
                                'department_name' => $data->departmentDetail->name,
                                'first_name' => $data->first_name,
                                'last_name' => $data->last_name,
                                'office_name' => $data->office->office_name,
                                'status_name' => 'Active',
                                'manager_name' => $data->managerDetail->first_name.' '.$data->managerDetail->last_name,
                                'email' => $data->email,
                                'phone' => $data->mobile_no,
                                'signedAt' => $data->created_at,
                                'position_name' => $data->positionDetail->position_name,
                                'offer_letter_accepted' => true,
                            ],
                        ]);
                    } elseif ($type == 'offer_letter_signed') {
                        $fields = json_encode([
                            'type' => $type,
                            'data' => [
                                'id' => $data->id,
                                'profile_pic' => $image_s3,
                                'status_name' => 'Offer letter signed',
                                'offer_letter_signed' => true,
                            ],
                        ]);
                    } elseif ($type == 'creds_sent') {
                        $fields = json_encode([
                            'type' => $type,
                            'data' => [
                                'id' => $data->id,
                                'profile_pic' => $image_s3,
                                'status_name' => 'Credentials are sent',
                                'creds_sent' => true,
                            ],
                        ]);
                    } else {
                        $fields = json_encode([
                            'type' => $type,
                            'data' => [
                                'id' => $data->id,
                                'profile_pic' => $image_s3,
                                'status_name' => 'Active',
                            ],
                        ]);
                    }
                    $url = $keyValuePairs['webhook_url'];
                    $method = 'POST';
                    $headers = [
                        'accept: application/json',
                        'content-type: application/json',
                    ];
                    $response = curlRequest($url, $fields, $headers, $method);
                    InterigationTransactionLog::create([
                        'interigation_name' => 'Source Marketing',
                        'api_name' => 'Push Rep Data',
                        'payload' => $fields,
                        'response' => json_encode(['response' => $response]),
                        'url' => $url,
                    ]);
                    // echo json_encode($fields);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error saving data to SourceMarketing', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function saveDataToLGCY($userId)
    {
        try {
            $integration = Integration::where(['name' => 'LGCY', 'status' => 1])->first();
            if ($integration) {
                $pairs = explode(',', $integration['value']);
                $keyValuePairs = [];
                foreach ($pairs as $pair) {
                    [$key, $value] = explode('=', $pair);
                    $keyValuePairs[trim($key)] = trim($value);
                }
                $data = User::with('office', 'managerDetail', 'positionDetail', 'departmentDetail')->where('id', $userId)->first();
                //    dd($data);
                if ($data) {
                    $office = $data->office;
                    $positionDetail = $data->positionDetail;
                    $fields = [
                        'BasicInfo' => [
                            'EmployeeID' => $data->employee_id,
                            'FirstName' => $data->first_name,
                            'LastName' => $data->last_name,
                            'Position' => isset($positionDetail) ? $positionDetail->position_name : null,
                            'Office' => isset($office) ? $office->office_name : null,
                            'OfficeExternalID' => isset($office) ? $office->id : null,
                            'Email' => $data->email,
                            'Phone' => $data->mobile_no,
                        ],
                        'PersonalInfo' => [
                            'EmployeeID' => $data->employee_id,
                            'FirstName' => $data->first_name,
                            'LastName' => $data->last_name,
                            'PersonalEmail' => $data->email,
                            'Phone' => $data->mobile_no,
                            'DateOfBirth' => $data->dob,
                            'Gender' => $data->sex,
                            'Address' => $data->home_address,
                            'City' => $data->home_address_city,
                            'State' => $data->home_address_state,
                            'Postal' => $data->home_address_zip,
                        ],
                        'EmploymentPackage' => [
                            'EmployeeID' => $data->employee_id,
                            'FirstName' => $data->first_name,
                            'LastName' => $data->last_name,
                            'IsManager' => isset($positionDetail) ? $this->isManager($positionDetail->is_manager) : 'No', // 'YES' or 'No'
                            'Role' => isset($positionDetail) ? $positionDetail->position_name : null,
                            'Active' => true,
                        ],
                    ];
                    $url = $keyValuePairs['webhook_url'];
                    // dd($fields);
                    $method = 'POST';
                    $headers = [
                        'accept: application/json',
                        'content-type: application/json',
                        'LGCYWebhookToken: '.$keyValuePairs['LGCYWebhookToken'],
                    ];
                    // Debug request details
                    // echo "URL: " . $url . "<br>";
                    // echo "Headers: " . print_r($headers, true) . "<br>";
                    // echo "Method: " . $method . "<br>";

                    // Convert fields to JSON if it's not already a JSON string
                    if (is_array($fields)) {
                        $fields = json_encode($fields);
                    }
                    // echo "Payload: " . $fields . "<br>";

                    // Use try-catch to catch any cURL errors
                    try {
                        $response = curlRequest($url, $fields, $headers, $method);
                        // echo "Raw Response: " . $response . "<br>";
                        // var_dump($response);
                        // die('Debug complete');
                    } catch (\Exception $e) {
                        // echo "Error: " . $e->getMessage() . "<br>";
                        // die('Error in cURL request');
                    }

                    if ($response) {
                        $response = json_decode($response, true);
                        // dd($response);
                        if (isset($response['success']) && $response['success'] == true) {
                            $data->aveyo_hs_id = $response['employee_id'];
                            $data->save();
                            // dd($data);
                            Log::info('LGCYWebhookToken user saved successfully', ['status' => true, 'user_id' => $data->id]);
                        }
                    } else {
                        Log::info('LGCYWebhookToken user not saved', ['status' => true, 'user_id' => $data->id]);
                        // Handle error
                    }
                    InterigationTransactionLog::create([
                        'interigation_name' => 'LGCYWebhook',
                        'api_name' => 'Push Rep Data',
                        'payload' => json_encode($fields),
                        'response' => json_encode($response),
                        'url' => $url,
                    ]);
                    // echo json_encode($fields);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in saveDataToLGCY', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    public function isManager($value)
    {
        if ($value) {
            if ($value == 1) {
                return 'YES';
            } else {
                return 'NO';
            }
        }

        return 'NO';
    }

    public function updateUserAveyoHsId()
    {
        try {
            $integration = Integration::where(['name' => 'LGCY', 'status' => 1])->first();
            if ($integration) {
                $pairs = explode(',', $integration['value']);
                $keyValuePairs = [];
                foreach ($pairs as $pair) {
                    [$key, $value] = explode('=', $pair);
                    $keyValuePairs[trim($key)] = trim($value);
                }
                $users = User::with('office', 'managerDetail', 'positionDetail', 'departmentDetail')
                    ->whereNull('aveyo_hs_id')
                    ->where('onboardProcess', 1)
                    ->where('is_super_admin', 0)
                    ->get();

                if ($users) {
                    foreach ($users as $data) {
                        $office = $data->office;
                        $positionDetail = $data->positionDetail;
                        $fields = [
                            'BasicInfo' => [
                                'EmployeeID' => $data->employee_id,
                                'FirstName' => $data->first_name,
                                'LastName' => $data->last_name,
                                'Position' => isset($positionDetail) ? $positionDetail->position_name : null,
                                'Office' => isset($office) ? $office->office_name : null,
                                'OfficeExternalID' => isset($office) ? $office->id : null,
                                'Email' => $data->email,
                                'Phone' => $data->mobile_no,
                            ],
                            'PersonalInfo' => [
                                'EmployeeID' => $data->employee_id,
                                'FirstName' => $data->first_name,
                                'LastName' => $data->last_name,
                                'PersonalEmail' => $data->email,
                                'Phone' => $data->mobile_no,
                                'DateOfBirth' => $data->dob,
                                'Gender' => $data->sex,
                                'Address' => $data->home_address,
                                'City' => $data->home_address_city,
                                'State' => $data->home_address_state,
                                'Postal' => $data->home_address_zip,
                            ],
                            'EmploymentPackage' => [
                                'EmployeeID' => $data->employee_id,
                                'FirstName' => $data->first_name,
                                'LastName' => $data->last_name,
                                'IsManager' => isset($positionDetail) ? $this->isManager($positionDetail->is_manager) : 'No', // 'YES' or 'No'
                                'Role' => isset($positionDetail) ? $positionDetail->position_name : null,
                                'Active' => true,
                            ],
                        ];
                        $url = $keyValuePairs['webhook_url'];
                        $method = 'POST';
                        $headers = [
                            'accept: application/json',
                            'content-type: application/json',
                            'LGCYWebhookToken: '.$keyValuePairs['LGCYWebhookToken'],
                        ];

                        // Convert fields to JSON if it's not already a JSON string
                        if (is_array($fields)) {
                            $fields = json_encode($fields);
                        }

                        // Use try-catch to catch any cURL errors
                        try {
                            $response = curlRequest($url, $fields, $headers, $method);
                        } catch (\Exception $e) {
                            Log::error('cURL error in updateUserAveyoHsId', ['status' => false, 'message' => $e->getMessage(), 'user_id' => $data->id]);
                            $response = false;
                        }

                        if ($response) {
                            $response = json_decode($response, true);
                            if (isset($response['success']) && $response['success'] == true) {
                                $data->aveyo_hs_id = $response['employee_id'];
                                $data->save();
                                Log::info('LGCYWebhookToken user saved successfully in updateUserAveyoHsId', ['status' => true, 'user_id' => $data->id]);
                            }
                        } else {
                            Log::info('LGCYWebhookToken user not saved in updateUserAveyoHsId', ['status' => true, 'user_id' => $data->id]);
                            // Handle error
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }

}
