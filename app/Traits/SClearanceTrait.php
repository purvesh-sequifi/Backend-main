<?php

namespace App\Traits;

use App\Models\SClearanceScreeningRequestList;
use App\Models\SClearanceToken;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Log;

trait SClearanceTrait
{
    /**
     * @method generateToken
     * This method is used to generate token of Transunion Sharable API for Hires(S-clearance)
     */
    public function generateToken()
    {

        $transunionDetails = [
            'transunion_url' => config('services.transunion.url'),
            'api_key1' => config('services.transunion.api_key1'),
            'api_key2' => config('services.transunion.api_key2'),
            'api_key3' => config('services.transunion.api_key3'),
            'client_id' => config('services.transunion.client_id'),
        ];

        try {
            $tokenResponse = '';
            $mfaTokenResponse = '';
            $response = '';
            // To pick any two random keys
            $availableKeys = [1, 2, 3];
            $randomKeyIndexes = array_rand($availableKeys, 2);
            $tokenKeyId = $availableKeys[$randomKeyIndexes[0]];
            $mfaTokenKeyId = $availableKeys[$randomKeyIndexes[1]];

            $token = SClearanceToken::count();
            if ($token > 0) {
                $tokenDetails = SClearanceToken::select('*')->first()->toArray();
                $currentDateTime = Carbon::now();

                /* Check for expiry of old token */
                $tokenDateTime = Carbon::parse($tokenDetails['expiration_time']);
                $tokenExpirationdateTime = $tokenDateTime->subMinutes(2);
                if ($currentDateTime < $tokenExpirationdateTime) {
                    $tokenResponse = [
                        'token' => $tokenDetails['token'],
                        'expires' => $tokenDetails['expiration_time'],
                        'key_used' => $tokenDetails['token_key_used'],
                    ];
                    $tokenType = 'old';
                    $expirationTime = $tokenDetails['expiration_time'];
                } else {
                    $tokenResponse = $this->getAuthToken($transunionDetails, $tokenKeyId);
                    $tokenResponse['key_used'] = $tokenKeyId;
                    $tokenType = 'new';
                    /* token - convert expiration time in system time zone */
                    $dateString1 = $tokenResponse['expires'];
                    $date1 = Carbon::parse($dateString1, 'UTC');
                    $expirationTime = $date1->setTimezone(date_default_timezone_get());
                }

                /* Check for expiry of old mfa token */
                $mfaTokenDateTime = Carbon::parse($tokenDetails['mfa_expiration_time']);
                $mfaTokenExpirationDateTime = $mfaTokenDateTime->subMinutes(2);
                if ($currentDateTime < $mfaTokenExpirationDateTime) {
                    $mfaTokenResponse = [
                        'token' => $tokenDetails['mfa_token'],
                        'expires' => $tokenDetails['mfa_expiration_time'],
                        'key_used' => $tokenDetails['mfa_token_key_used'],
                    ];
                    $mfaTokenType = 'old';
                    $mfaExpirationTime = $tokenDetails['mfa_expiration_time'];
                } else {
                    $mfaTokenResponse = $this->getMFAAuthToken($transunionDetails, $mfaTokenKeyId);
                    $mfaTokenResponse['key_used'] = $mfaTokenKeyId;
                    $mfaTokenType = 'new';
                    /* mfa token - convert expiration time in system time zone */
                    $dateString2 = $mfaTokenResponse['expires'];
                    $date2 = Carbon::parse($dateString2, 'UTC');
                    $mfaExpirationTime = $date2->setTimezone(date_default_timezone_get());
                }

                /* Update DB */
                if ($tokenType == 'new' || $mfaTokenType == 'new') {
                    $id = $tokenDetails['id'];

                    SClearanceToken::where('id', '=', $id)->update([
                        'token' => $tokenResponse['token'],
                        'token_key_used' => $tokenResponse['key_used'],
                        'expiration_time' => $expirationTime,
                        'mfa_token' => $mfaTokenResponse['token'],
                        'mfa_token_key_used' => $mfaTokenResponse['key_used'],
                        'mfa_expiration_time' => $mfaExpirationTime,
                    ]);
                }

                $response = [
                    'token' => $tokenResponse['token'],
                    'mfa_token' => $mfaTokenResponse['token'],
                ];
            } else {
                $tokenKeyId = 1;
                $mfaTokenKeyId = 2;
                $tokenResponse = $this->getAuthToken($transunionDetails, $tokenKeyId);
                $mfaTokenResponse = $this->getMFAAuthToken($transunionDetails, $mfaTokenKeyId);

                /* update DB */
                if (isset($tokenResponse['token']) && isset($mfaTokenResponse['token'])) {
                    /* token - convert expiration time in system time zone */
                    $expirationTime = '';
                    if (isset($tokenResponse['expires'])) {
                        $dateString1 = $tokenResponse['expires'];
                        $date1 = Carbon::parse($dateString1, 'UTC');
                        $expirationTime = $date1->setTimezone(date_default_timezone_get());
                    }

                    /* mfa token - convert expiration time in system time zone */
                    $mfaExpirationTime = '';
                    if (isset($mfaTokenResponse['expires'])) {
                        $dateString2 = $mfaTokenResponse['expires'];
                        $date2 = Carbon::parse($dateString2, 'UTC');
                        $mfaExpirationTime = $date2->setTimezone(date_default_timezone_get());
                    }

                    $tokenSave = SClearanceToken::create([
                        'token' => $tokenResponse['token'],
                        'mfa_token' => $mfaTokenResponse['token'],
                        'token_key_used' => $tokenKeyId,
                        'mfa_token_key_used' => $mfaTokenKeyId,
                        'expiration_time' => $expirationTime,
                        'mfa_expiration_time' => $mfaExpirationTime,
                    ]);

                    $response = [
                        'token' => $tokenResponse['token'],
                        'mfa_token' => $mfaTokenResponse['token'],
                    ];
                } else {
                    $response = [
                        'status' => false,
                        'token_message' => @$tokenResponse['message'],
                        'mfa_token_message' => @$mfaTokenResponse['message'],
                    ];
                }
            }

            return $response;
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @method get Auth token
     */
    public function getAuthToken($transunionDetails, $keyId)
    {
        $tokenResponse = '';
        $tokenURL = $transunionDetails['transunion_url'].'/Tokens';
        try {
            $credentials1 = [
                'clientId' => $transunionDetails['client_id'],
                'apiKey' => $transunionDetails['api_key'.$keyId],
            ];

            /* new token generate */
            $request1 = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->post($tokenURL, $credentials1);

            $data1 = $request1->body();
            $tokenResponse = json_decode($data1, true);

            if (isset($tokenResponse['errors']) && ! empty($tokenResponse['errors'])) {
                Log::channel('sclearance_log')->info('sClearance Token Error: '.print_r($tokenResponse, true));
            }

            return $tokenResponse;
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @method get MFA Auth token
     */
    public function getMFAAuthToken($transunionDetails, $keyId)
    {
        $mfaTokenResponse = '';
        $tokenURL = $transunionDetails['transunion_url'].'/Tokens';
        try {
            $credentials2 = [
                'clientId' => $transunionDetails['client_id'],
                'apiKey' => $transunionDetails['api_key'.$keyId],
            ];

            /* new token generate */
            $request2 = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
                ->post($tokenURL, $credentials2);

            $data2 = $request2->body();
            $mfaTokenResponse = json_decode($data2, true);
            if (isset($mfaTokenResponse['errors']) && ! empty($mfaTokenResponse['errors'])) {
                Log::channel('sclearance_log')->info('sClearance Token Error: '.print_r($tokenResponse, true));
            }

            return $mfaTokenResponse;
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @method addEmployer
     * This method is used to add  employer in Transunion Sharable API for Hires(S-clearance)
     */
    public function addEmployer($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $employerData = [
                'emailAddress' => $requestData['email'],
                'firstName' => $requestData['first_name'],
                'lastName' => $requestData['last_name'],
                'phoneNumber' => $requestData['phone_number'],
                'phoneType' => $requestData['phone_type'],
                'businessName' => $requestData['business_name'],
                'businessAddress' => [
                    'addressLine1' => $requestData['address_line_1'],
                    'addressLine2' => $requestData['address_line_2'],
                    'addressLine3' => @$requestData['address_line_3'],
                    'addressLine4' => @$requestData['address_line_4'],
                    'locality' => $requestData['locality'],
                    'region' => $requestData['region'],
                    'postalCode' => $requestData['postal_code'],
                    'country' => ((isset($requestData['country']) && ! empty($requestData['country'])) ? $requestData['country'] : 'USA'),
                ],
                'acceptedTermsAndConditions' => (($requestData['accepted_terms_conditions'] == 1) ? true : false),
            ];

            $employerURL = $transunion_url.'/Employers';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->post($employerURL, $employerData);
            $data = $request->body();
            $employerResponse = json_decode($data, true);

            return $employerResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method updateEmployer
     * This method is used to update employer in Transunion Sharable API for Hires(S-clearance)
     */
    public function updateEmployer($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');

            $employerData = [
                'employerId' => @$requestData['employer_id'],
                'emailAddress' => @$requestData['email'],
                'firstName' => @$requestData['first_name'],
                'lastName' => @$requestData['last_name'],
                'phoneNumber' => @$requestData['phone_number'],
                'phoneType' => @$requestData['phone_type'],
                'businessName' => @$requestData['business_name'],
                'businessAddress' => [
                    'addressLine1' => @$requestData['address_line_1'],
                    'addressLine2' => @$requestData['address_line_2'],
                    'addressLine3' => @$requestData['address_line_3'],
                    'addressLine4' => @$requestData['address_line_4'],
                    'locality' => @$requestData['locality'],
                    'region' => @$requestData['region'],
                    'postalCode' => @$requestData['postal_code'],
                    'country' => ((isset($requestData['country']) && ! empty($requestData['country'])) ? $requestData['country'] : 'USA'),
                ],
                'acceptedTermsAndConditions' => ((isset($requestData['accepted_terms_conditions']) && $requestData['accepted_terms_conditions'] == 1) ? true : false),
            ];

            $employerURL = $transunion_url.'/Employers';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->put($employerURL, $employerData);
            $data = $request->body();
            $employerResponse = json_decode($data, true);

            // $employerResponse = ''; //dummy response
            return $employerResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getEmployer
     * This method is used to update employer in Transunion Sharable API for Hires(S-clearance)
     */
    public function getEmployer($employerId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');

            $employerURL = $transunion_url.'/Employers/'.$employerId;

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($employerURL);
            $data = $request->body();
            $employerResponse = json_decode($data, true);

            // $employerResponse = array(
            //     "employerId" => 1,
            //     "emailAddress" => "string",
            //     "firstName" => "string",
            //     "lastName" => "string",
            //     "phoneNumber" => "554690616262",
            //     "phoneType" => "string",
            //     "businessName" => "string",
            //     "businessAddress" => array(
            //         "addressLine1" => "string",
            //         "addressLine2" => "string",
            //         "addressLine3" => "string",
            //         "addressLine4" => "string",
            //         "locality" => "string",
            //         "region" => "string",
            //         "postalCode" => "string",
            //         "country" => "string"
            //     ),
            //     "acceptedTermsAndConditions" => true
            // ); // dummy reponse
            return $employerResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getScreeningReports
     * After notification URL, this method will be called to get screening requests
     */
    public function getScreeningReports($screeningRequestApplicantId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');

            // $reportNameURL = $transunion_url.'/Employers/ScreeningRequestApplicants/'.$screeningRequestApplicantId.'/Reports/Names';

            // $request = Http::withHeaders([
            //             'Content-Type' => 'application/json',
            //             'Authorization' => $token['token'],
            // 'MultiFactorAuthToken' => $token['mfa_token']
            //         ])
            //         ->get($reportNameURL);

            // $data = $request->body();
            // $reportResponse = json_decode($data, true);
            // return $reportResponse;

            $reportURL = $transunion_url.'/Employers/ScreeningRequestApplicants/'.$screeningRequestApplicantId.'/Reports';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($reportURL, ['requestedProduct' => 'criminal']);
            $data = $request->body();
            $reportResponse = json_decode($data, true);

            return $reportResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method addApplicant
     * This method is used to add applicant in Transunion Sharable API for Hires(S-clearance)
     */
    public function addApplicant($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $applicantData = [
                'emailAddress' => $requestData['email'],
                'firstName' => $requestData['first_name'],
                'middleName' => @$requestData['middle_name'],
                'lastName' => $requestData['last_name'],
                'phoneNumber' => $requestData['phone_number'],
                'phoneType' => $requestData['phone_type'],
                'homeAddress' => [
                    'addressLine1' => $requestData['address_line_1'],
                    'addressLine2' => @$requestData['address_line_2'],
                    'addressLine3' => @$requestData['address_line_3'],
                    'addressLine4' => @$requestData['address_line_4'],
                    'locality' => $requestData['locality'],
                    'region' => $requestData['region'],
                    'postalCode' => $requestData['postal_code'],
                    'country' => ((isset($requestData['country']) && ! empty($requestData['country'])) ? $requestData['country'] : 'USA'),
                ],
                'acceptedTermsAndConditions' => ((isset($requestData['accepted_terms_conditions']) && $requestData['accepted_terms_conditions'] == 1) ? true : false),
                'dateOfBirth' => $requestData['date_of_birth'],
                'socialSecurityNumber' => $requestData['social_security_number'],
            ];

            $applicantURL = $transunion_url.'/Applicants';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->post($applicantURL, $applicantData);
            $data = $request->body();
            $applicantResponse = json_decode($data, true);

            if (isset($requestData['request_id']) && ! empty($requestData['request_id'])) {
                $srRequestSave = SClearanceScreeningRequestList::where(['id' => $requestData['request_id']])->update([
                    'applicant_id' => @$applicantResponse['applicantId'],
                ]);
            }

            // $applicantResponse = array(
            //     "applicantId" => 3649951
            // ); // Dummy Reponse
            return $applicantResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token not found',
            ];
        }
    }

    /**
     * @method backgroundVerificationExam
     * This method is used to create exam for background verification
     */
    public function backgroundVerificationExam($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }

            $transunion_url = config('services.transunion.url');

            $applicantURL = $transunion_url.'/Applicants/'.$requestData['applicantId'];

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($applicantURL);
            $data = $request->body();
            $applicantResponse = json_decode($data, true);

            if (isset($applicantResponse['applicantId']) && ! empty(($applicantResponse['applicantId']))) {

                $validateURL = $transunion_url.'/ScreeningRequestApplicants/'.$requestData['screeningRequestApplicantId'].'/validate';

                $request = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => $token['token'],
                    'MultiFactorAuthToken' => $token['mfa_token'],
                ])
                    ->post($validateURL, $applicantResponse);
                $data = $request->body();
                $validateResponse = json_decode($data, true);
                if (isset($validateResponse['status']) && ! empty($validateResponse['status'])) {
                    if ($validateResponse['status'] == 'Unverified') {
                        $examURL = $transunion_url.'/ScreeningRequestApplicants/'.$requestData['screeningRequestApplicantId'].'/Exams';

                        $applicantData = [
                            'applicant' => $applicantResponse,
                        ];
                        $request = Http::withHeaders([
                            'Content-Type' => 'application/json',
                            'Authorization' => $token['token'],
                            'MultiFactorAuthToken' => $token['mfa_token'],
                        ])
                            ->post($examURL, $applicantData);
                        $data = $request->body();
                        $examResponse = json_decode($data, true);

                        return $examResponse;
                    } else {
                        return $validateResponse;
                    }
                } else {
                    return $validateResponse;
                }
            } else {
                return $applicantResponse;
            }
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token not found',
            ];
        }
    }

    /**
     * @method backgroundVerificationExamAnswer
     * This method is used to submit answer of exam for background verification
     */
    public function backgroundVerificationExamAnswer($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }

            $transunion_url = config('services.transunion.url');

            $answerURL = $transunion_url.'/ScreeningRequestApplicants/'.$requestData['screeningRequestApplicantId'].'/Exams/'.$requestData['examId'].'/Answers';
            $answerData = [
                'answers' => $requestData['answers'],
            ];

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->post($answerURL, $answerData);
            $data = $request->body();
            $answerResponse = json_decode($data, true);

            return $answerResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token not found',
            ];
        }
    }

    /**
     * @method createScreeningRequest
     * This method is used to create screening request for an applicant in Transunion Sharable API for Hires(S-clearance)
     */
    public function createScreeningRequest($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');

            $SrData = [
                'employerId' => $requestData['employer']['employerId'],
                'description' => $requestData['description'],
                // 'businessName' => $requestData['employer']['businessName'],
                // 'salaryRange' => $requestData['salary_range'],
                // 'employmentType' => $requestData['employment_type'],
                'doCreditStateRestrictionsApply' => false,
                // 'isCreditStateRestrictionOverridden' => (($requestData['credit_state_restriction_overriden']) ? $requestData['credit_state_restriction_overriden'] : false),
                'filterAddress' => [
                    'addressLine1' => $requestData['employer']['businessAddress']['addressLine1'],
                    'addressLine2' => $requestData['employer']['businessAddress']['addressLine2'],
                    'addressLine3' => $requestData['employer']['businessAddress']['addressLine3'],
                    'addressLine4' => $requestData['employer']['businessAddress']['addressLine4'],
                    'locality' => $requestData['employer']['businessAddress']['locality'],
                    'region' => $requestData['employer']['businessAddress']['region'],
                    'postalCode' => $requestData['employer']['businessAddress']['postalCode'],
                    'country' => (! empty($requestData['employer']['businessAddress']['country']) ? $requestData['employer']['businessAddress']['country'] : 'USA'),
                ],
                'initialBundleId' => $requestData['bundle_id'],
                'screeningRequestApplicant' => [
                    'employerId' => $requestData['employer']['employerId'],
                    'applicantId' => $requestData['applicant']['applicantId'],
                    'bundleId' => $requestData['bundle_id'],
                    'applicantStatus' => 'IdentityVerificationPending',
                    // "doCreditStateRestrictionsApplyForConsumerAddress" => (($requestData['credit_state_restriction_apply_consumer']) ? $requestData['credit_state_restriction_apply_consumer'] : false),
                    // 'applicantFirstName' => $requestData['first_name'],
                    // 'applicantLastName' => $requestData['last_name']
                ],
            ];

            $SrURL = $transunion_url.'/ScreeningRequests';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->post($SrURL, $SrData);
            $data = $request->body();
            $SrResponse = json_decode($data, true);
            // $SrResponse = array(
            //     "screeningRequestId" => 1616080
            // ); // Dummy Reponse

            if (isset($SrResponse['screeningRequestId']) && ! empty($SrResponse['screeningRequestId'])) {
                if (isset($requestData['request_id']) && ! empty($requestData['request_id'])) {
                    $srRequestSave = SClearanceScreeningRequestList::where(['id' => $requestData['request_id']])->update([
                        'screening_request_id' => @$SrResponse['screeningRequestId'],
                    ]);
                }

                $sendResponse = [
                    'screeningRequestId' => $SrResponse['screeningRequestId'],
                ];

                $SraURL = $transunion_url.'/ScreeningRequests/'.$SrResponse['screeningRequestId'].'/ScreeningRequestApplicants';
                $request = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => $token['token'],
                    'MultiFactorAuthToken' => $token['mfa_token'],
                ])
                    ->post($SraURL, $SrData['screeningRequestApplicant']);

                $data = $request->body();
                $SraResponse = json_decode($data, true);

                $sendResponse['screeningRequestApplicantId'] = @$SraResponse['screeningRequestApplicantId'];
                if (isset($SraResponse['screeningRequestApplicantId']) && ! empty($SraResponse['screeningRequestApplicantId'])) {
                    if (isset($requestData['request_id']) && ! empty($requestData['request_id'])) {
                        $srRequestSave = SClearanceScreeningRequestList::where(['id' => $requestData['request_id']])->update([
                            'screening_request_applicant_id' => @$SraResponse['screeningRequestApplicantId'],
                        ]);
                    }
                }

                // $SraResponse = array(
                //     "screeningRequestApplicantId" => 1616080
                // ); // Dummy Reponse
                return $sendResponse;
            } else {
                return $SrResponse;
            }
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getEmployerScreeningRequests
     * After notification URL, this method will be called to get screening requests
     */
    public function getEmployerScreeningRequests($employer_id, $pageNumber, $pageSize)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }

            $transunion_url = config('services.transunion.url');

            $SRURL = $transunion_url.'/Employers/'.$employer_id.'/ScreeningRequests?pageNumber='.$pageNumber.'&pageSize='.$pageSize;

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($SRURL);
            $data = $request->body();
            $employerResponse = json_decode($data, true);

            return $employerResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method cancelScreeningRequest
     * This is used to cancel a screening request
     */
    public function cancelScreeningRequest($screeningRequestApplicantId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $SrCancelURL = $transunion_url.'/ScreeningRequestApplicants/'.$screeningRequestApplicantId.'/Cancel';

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->put($SrCancelURL);
            $data = $request->body();
            $SrCancelResponse = json_decode($data, true);

            return $SrCancelResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getScreeningRequestApplicant
     * This is used to get a screening request applicant
     */
    public function getScreeningRequestApplicant($screeningRequestApplicantId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $SrGetURL = $transunion_url.'/ScreeningRequestApplicants/'.$screeningRequestApplicantId;

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($SrGetURL);
            $data = $request->body();
            $SrGetResponse = json_decode($data, true);

            return $SrGetResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getScreeningRequests
     * This is used to get a screening request
     */
    public function getScreeningRequest($screeningRequestId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $SrGetURL = $transunion_url.'/ScreeningRequestApplicants/'.$screeningRequestId;

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($SrGetURL);
            $data = $request->body();
            $SrGetResponse = json_decode($data, true);

            return $SrGetResponse;
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method getScreeningRequest
     * This is used to get a screening request
     */
    public function postApplicantReport($requestData)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');

            $SrResponse = $this->getScreeningRequestApplicant($requestData['screeningRequestApplicantId']);

            $requestData['applicantId'] = @$SrResponse['applicantId'];

            $applicantURL = $transunion_url.'/Applicants/'.$requestData['applicantId'];
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($applicantURL);
            $data = $request->body();
            $applicantResponse = json_decode($data, true);

            if (isset($applicantResponse['applicantId']) && ! empty(($applicantResponse['applicantId']))) {
                if (isset($SrResponse['applicantStatus']) && $SrResponse['applicantStatus'] != 'ReportsDeliverySuccess') {
                    $bundleURL = $transunion_url.'/Bundles/StateRestrictedAlternativeBundle/'.$SrResponse['bundleId'];
                    $request = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => $token['token'],
                        'MultiFactorAuthToken' => $token['mfa_token'],
                    ])
                        ->get($bundleURL);
                    $data = $request->body();
                    $bundleResponse = json_decode($data, true);

                    if ($bundleResponse == 1) {
                        $bundleAdjustURL = $transunion_url.'/ScreeningRequestApplicants/'.$requestData['screeningRequestApplicantId'].'/AdjustBundle';
                        $bundleData = [
                            'employerId' => $SrResponse['employerId'],
                            'applicantId' => $SrResponse['applicantId'],
                            'bundleId' => $SrResponse['bundleId'],
                        ];

                        $request = Http::withHeaders([
                            'Content-Type' => 'application/json',
                            'Authorization' => $token['token'],
                            'MultiFactorAuthToken' => $token['mfa_token'],
                        ])
                            ->put($bundleAdjustURL, $bundleData);
                        $data = $request->body();
                        $bundleAdjustResponse = json_decode($data, true);
                        if (isset($bundleAdjustResponse['message'])) {
                            return $bundleAdjustResponse;
                        }
                    }

                    $applicantData = [
                        'applicant' => $applicantResponse,
                    ];
                    $postApplicantURL = $transunion_url.'/Applicants/ScreeningRequestApplicants/'.$requestData['screeningRequestApplicantId'].'/Reports';
                    $request = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => $token['token'],
                        'MultiFactorAuthToken' => $token['mfa_token'],
                    ])
                        ->post($postApplicantURL, $applicantData);
                    $data = $request->body();
                    $postApplicantResponse = json_decode($data, true);

                    return $postApplicantResponse;
                } else {
                    return $SrResponse;
                }
            } else {
                return $applicantResponse;
            }
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token  not found',
            ];
        }
    }

    /**
     * @method validateScreeningRequest
     *  USed to validate screenning request passed or not
     */
    public function validateScreeningRequest($screeningRequestApplicantId, $applicantId)
    {
        $token = $this->generateToken();
        if (! empty($token) && isset($token['token']) && isset($token['mfa_token'])) {
            // if(config('app.env') === 'production'){
            //     $transunion_url = config('services.transunion.url');
            // }else{
            //     $transunion_url = config('services.transunion.test_url');
            // }
            $transunion_url = config('services.transunion.url');
            $applicantURL = $transunion_url.'/Applicants/'.$applicantId;

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token['token'],
                'MultiFactorAuthToken' => $token['mfa_token'],
            ])
                ->get($applicantURL);
            $data = $request->body();
            $applicantResponse = json_decode($data, true);

            if (isset($applicantResponse['applicantId']) && ! empty(($applicantResponse['applicantId']))) {
                $validateURL = $transunion_url.'/ScreeningRequestApplicants/'.$screeningRequestApplicantId.'/validate';

                $request = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => $token['token'],
                    'MultiFactorAuthToken' => $token['mfa_token'],
                ])
                    ->post($validateURL, $applicantResponse);
                $data = $request->body();
                $validateResponse = json_decode($data, true);
                if (isset($validateResponse['status']) && ! empty($validateResponse['status'])) {
                    return $validateResponse['status'];
                } else {
                    return '';
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Applicant Not Found',
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Transunion token not found',
            ];
        }
    }
}
