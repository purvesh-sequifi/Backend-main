<?php

namespace App\Traits;

use App\Models\CrmSetting;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

trait TurnAiTrait
{
    /**
     * @method generateToken
     * This method is used to generate token of Transunion Sharable API for Hires(S-clearance)
     */
    public function turnPartnerDetails()
    {
        $turn_url = config('services.turnai.url');
        $turn_jwt_token = config('services.turnai.jwt_token');

        return [
            'turn_url' => $turn_url,
            'turn_jwt_token' => $turn_jwt_token,
        ];
    }

    /**
     * @method generateToken
     * This method is used to generate token of Transunion Sharable API for Hires(S-clearance)
     */
    public function generateToken()
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        $turn_url = $turnPartnerDetails['turn_url'];
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $value = json_decode($crmSetting->value, true);
            if (! empty($value)) {
                $username = @$value['public_key'];
                $password = @$value['secret_key'];

                try {
                    $tokenURL = $turn_url.'oauth/refresh_token';

                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Basic '.base64_encode($username.':'.$password),
                    ])
                        ->post($tokenURL);
                    $data = $request->body();
                    $tokenResponse = json_decode($data, true);

                    if (isset($tokenResponse['partner_key']) && ! empty(($tokenResponse['partner_key']))) {
                        return [
                            'status' => true,
                            'partner_key' => $tokenResponse['partner_key'],
                        ];
                    } elseif (isset($tokenResponse['message']) && ! empty(($tokenResponse['message']))) {
                        return [
                            'status' => false,
                            'message' => $tokenResponse['message'],
                            'apiResponse' => $tokenResponse,
                        ];
                    } else {
                        return [
                            'status' => false,
                            'message' => 'Something went wrong, please try later',
                            'apiResponse' => $tokenResponse,
                        ];
                    }
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }

            } else {
                return [
                    'status' => false,
                    'message' => 'Child partner not found',
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Please Activate S-Clearance First ',
            ];
        }
    }

    /**
     * @method addChildPartner
     * This method is used to add child partner within main account in turn.ai
     */
    public function addChildPartner($requestData)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {

            $turn_url = $turnPartnerDetails['turn_url'];
            $turn_jwt_token = $turnPartnerDetails['turn_jwt_token'];

            $childPartnerData = [
                'company_name' => $requestData['company_name'], // unique
                'zipcode' => $requestData['zipcode'],
                'street_line' => $requestData['street_line'],
                'metadata' => [
                    'ip_address' => $requestData['ip_address'],
                    'user_agent' => $requestData['user_agent'],
                    'user_login' => $requestData['email'],
                    'timestamps' => [
                        'partner_program_agreement' => [
                            $requestData['partner_program_agreement'],
                        ],
                    ],
                ],
                'adverse_action_contact' => [
                    'name' => $requestData['full_name'],
                    'email' => $requestData['email'],
                ],
            ];

            try {
                $childPartnerURL = $turn_url.'partner/child';

                $request = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$turn_jwt_token,
                ])
                    ->post($childPartnerURL, $childPartnerData);
                $data = $request->body();
                $childPartnerResponse = json_decode($data, true);

                return $childPartnerResponse;
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getChildPartnerAgreement
     * This method is used to get child partner aggreement doc
     */
    public function getChildPartnerAgreement()
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $turn_jwt_token = $turnPartnerDetails['turn_jwt_token'];

            try {
                $childPartnerURL = $turn_url.'partner/child/agreement';
                $request = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$turn_jwt_token,
                ])
                    ->get($childPartnerURL);
                $data = $request->body();
                $childPartnerResponse = json_decode($data, true);

                return $childPartnerResponse;
            } catch (Exception $e) {
                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getPackages
     * This method is used to get child partner's packages
     */
    public function getPackages()
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $packageURL = $turn_url.'public_api/me/packages';
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->get($packageURL);
                    $data = $request->body();
                    $packageResponse = json_decode($data, true);

                    return $packageResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method addPackage // no need for now
     * This method is used to add child partner within main account in turn.ai
     */
    public function addPackage($packageConfigurations)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                $packageData = [
                    'duplicate_policy' => '1825', // (5 years) to prevent duplicate package creation
                    'package_configuration' => $packageConfigurations,
                ];

                try {
                    $packageURL = $turn_url.'public_api/me/package';
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->post($packageURL, $packageData);
                    $data = $request->body();
                    $packageResponse = json_decode($data, true);

                    // return $packageResponse;
                    return ['package_id' => 'P1728376542'];
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getFormData
     * This method is used to get form for requested package aand zipcode
     */
    public function getFormData($userData)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];

            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                $inputData = [
                    'zipcode' => $userData->zipcode,
                    'package_id' => $userData->package_id,
                ];

                try {
                    $formURL = $turn_url.'fcra_check/form';
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->post($formURL, $inputData);
                    $data = $request->body();
                    $formResponse = json_decode($data, true);

                    return $formResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method createScreeningRequest
     * This method is used to create screening request for a user in turn.ai
     */
    public function createScreeningRequest($requestData)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];
                $phone = '1'.substr($requestData['phone'], -10);
                $formData = [
                    'first_name' => @$requestData['first_name'],
                    'last_name' => @$requestData['last_name'],
                    'no_middle_name' => @$requestData['no_middle_name'],
                    'middle_name' => ($requestData['no_middle_name'] == false) ? $requestData['middle_name'] : '',
                    'date_of_birth' => @$requestData['date_of_birth'],
                    'zipcode' => @$requestData['zipcode'],
                    'phone' => $phone,
                    'ssn' => @$requestData['ssn'],
                    'email' => @$requestData['email'],
                    'drivers_license_number' => @$requestData['drivers_license_number'],
                    'drivers_license_state' => @$requestData['drivers_license_state'],
                    'consent_document' => @$requestData['consent_document_base64_img'],
                    'callback_url' => url('/api/v1/s_clearance_turn/turn_result_webhook'),
                    'reference_id' => config('app.domain_name').'-'.$requestData['request_id'].'-'.config('app.base_url'),
                    'timestamps' => [
                        'welcome' => [
                            @$requestData['welcome'],
                        ],
                        'summary_of_rights' => [
                            @$requestData['summary_of_rights'],
                        ],
                        'disclosure' => [
                            @$requestData['disclosure'],
                        ],
                        'summary_of_state_rights' => [
                            @$requestData['summary_of_state_rights'],
                        ],
                        'authorization_of_background_investigation' => [
                            @$requestData['authorization_of_background_investigation'],
                        ],
                        'signature' => [
                            @$requestData['signature'],
                        ],
                    ],
                    'metadata' => [
                        'ip_address' => @$requestData['ip_address'],
                        'user_agent' => @$requestData['user_agent'],
                    ],
                ];

                if (isset($requestData['drivers_license_image_front_base64_img']) && ! empty($requestData['drivers_license_image_front_base64_img'])) {
                    $formData['drivers_license_image_front'] = $requestData['drivers_license_image_front_base64_img'];
                }

                if (isset($requestData['drivers_license_image_back_base64_img']) && ! empty($requestData['drivers_license_image_back_base64_img'])) {
                    $formData['drivers_license_image_back'] = $requestData['drivers_license_image_back_base64_img'];
                }

                if (isset($requestData['city']) && ! empty($requestData['city'])) {
                    $formData['city'] = $requestData['city'];
                }

                if (isset($requestData['address']) && ! empty($requestData['address'])) {
                    $formData['address'] = $requestData['address'];
                }

                try {
                    $formURL = $turn_url.'fcra_check/form/'.$requestData['form_check_id'];
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->post($formURL, $formData);
                    $data = $request->body();
                    $formResponse = json_decode($data, true);

                    return $formResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method approveRejectSR
     * This method is used to approve/reject the screening request
     */
    public function approveRejectSR($status, $turn_id)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $note = '';
                    if ($status == 'approve') {
                        $URL = $turn_url.'fcra/'.$turn_id.'/approve';
                    } else {
                        $URL = $turn_url.'fcra/'.$turn_id.'/reject';
                        $note = 'Does not meet company standards';
                    }
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->put($URL, ['note' => $note]);
                    $data = $request->body();
                    $Response = json_decode($data, true);

                    return $Response;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method withdrawScreeningRequest
     * This is used to withdraw a screening request
     */
    public function withdrawScreeningRequest($turn_id)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $withdrawURL = $turn_url.'fcra/'.$turn_id.'/withdraw';

                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->put($withdrawURL, ['note' => '']);
                    $data = $request->body();
                    $withdrawResponse = json_decode($data, true);

                    return $withdrawResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getScreeningReport
     * This is used to get screening reeport
     */
    public function getScreeningReport($turn_id)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $reportURL = $turn_url.'fcra_check/'.$turn_id.'/documents/report';

                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->get($reportURL);
                    $data = $request->body();
                    $reportResponse = json_decode($data, true);

                    return $reportResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getScreeningDetails
     * This is used to get screening details
     */
    public function getScreeningDetails($turn_id)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $detailsURL = $turn_url.'person/'.$turn_id.'/details';

                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->get($detailsURL);
                    $data = $request->body();
                    $detailResponse = json_decode($data, true);

                    return $detailResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }

    /**
     * @method getPostConsentAction
     * This is used to get post consent action
     */
    public function getPostConsentAction($turn_id)
    {
        $turnPartnerDetails = $this->turnPartnerDetails();
        if (! empty($turnPartnerDetails) && ! empty($turnPartnerDetails)) {
            $turn_url = $turnPartnerDetails['turn_url'];
            $childPartnerToken = $this->generateToken();
            if (isset($childPartnerToken['status']) && $childPartnerToken['status'] == true) {
                $turn_jwt_token = $childPartnerToken['partner_key'];

                try {
                    $detailsURL = $turn_url.'fcra_check/'.$turn_id.'/post_consent_actions';
                    $request = Http::withHeaders([
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer '.$turn_jwt_token,
                    ])
                        ->get($detailsURL);
                    $data = $request->body();
                    $detailResponse = json_decode($data, true);

                    return $detailResponse;
                } catch (Exception $e) {
                    return [
                        'status' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            } else {
                return [
                    'status' => false,
                    'message' => $childPartnerToken['message'],
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Turn Partner token  not found',
            ];
        }
    }
}
