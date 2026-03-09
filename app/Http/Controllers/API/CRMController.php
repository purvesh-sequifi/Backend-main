<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\HubspotTrait;
use App\Http\Controllers\Controller;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\LegacyApiRowData;
use App\Models\Locations;
use App\Models\Plans;
use App\Models\SClearanceConfiguration;
use App\Models\SClearancePlan;
use App\Models\SubscriptionBillingHistory;
use App\Models\Subscriptions;
use App\Models\User;
use App\Traits\TurnAiTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CRMController extends Controller
{
    use HubspotTrait, TurnAiTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crmSettingList()
    {
        $crms = Crms::where('id', '!=', 3)->orderBy('id', 'DESC')->get(); // ->paginate(env('PAGINATE'));
        $crms->transform(function ($crm) {
            $Hubspot = 0;
            $everee = [];
            // $crmSetting = CrmSetting::where('crm_id', 1)->first();
            $LegacyApiRowData = LegacyApiRowData::orderBy('updated_at', 'DESC')->first();

            $updated_at = isset($LegacyApiRowData->updated_at) ? $LegacyApiRowData->updated_at : '';
            if ($crm->id == 2) {
                $SyncPending = User::where('aveyo_hs_id', null)->orWhere('aveyo_hs_id', 0)->where('is_super_admin', '!=', 1)->count();
                $Hubspot = $SyncPending;
                $updated_at = '';
                if ($crm->id === 2 && $crm->updated_at != null) {
                    $updated_at = $crm->id === 2 ? ($crm->updated_at)->format('m-d-Y H:i') : '';
                }
            }

            // if($crm->id == 3)
            // {
            //     $locations_pending = Locations::where('everee_location_id',null)->count();
            //     $users_pending = User::where('everee_workerId',null)->count();
            //     $everee['loc_count'] = $locations_pending > 0 ? $locations_pending:0;
            //     $everee['user_count'] = $users_pending  > 0 ? $users_pending :0;
            // }
            if (isset($crm->logo) && $crm->logo != null) {
                $crm->crm_logo_s3 = s3_getTempUrl(config('app.domain_name').'/'.$crm->logo);
            } else {
                $crm->crm_logo_s3 = null;
            }

            $employer_id = '';
            if ($crm->id == 5) {
                $crmSetting = CrmSetting::where('crm_id', 5)->first();
                if ($crmSetting) {
                    $crmValue = json_decode($crmSetting->value, true);
                    // $employer_id = $crmValue['employer_id'];
                }
                $updated_at = isset($crm->updated_at) ? $crm->updated_at : '';
            }

            $activate_date = '';
            if ($crm->id == 6) {
                $crmSetting = CrmSetting::where('crm_id', 6)->first();
                if ($crmSetting) {
                    $crmValue = json_decode($crmSetting->value, true);
                    $date = isset($crmValue['activate_date']) ? Carbon::parse($crmValue['activate_date']) : $crmSetting->updated_at;

                    $activate_date = ($date)->format('m-d-Y H:i');
                    $activate_date = Carbon::createFromFormat('m-d-Y H:i', $activate_date)->toIso8601String();
                }
            }
            // added for fetching activate date and last sync for quickbooks.
            if ($crm->name == 'QuickBooks') {
                $id = $crm->id;
                $crmSetting = CrmSetting::where('crm_id', $id)->first();
                if ($crmSetting) {

                    $date = isset($crmSetting->created_at) ? Carbon::parse($crmSetting->created_at) : $crm->updated_at;

                    $activate_date = ($date)->format('m-d-Y H:i');
                    $activate_date = Carbon::createFromFormat('m-d-Y H:i', $activate_date)->toIso8601String();

                    $updated_at = $crmSetting->updated_at ? ($crmSetting->updated_at)->format('m-d-Y H:i') : '';

                }

            }

            return [
                'id' => $crm->id,
                'name' => $crm->name,
                // 'logo' => $crm->logo,
                'logo' => $crm->crm_logo_s3,
                'status' => $crm->status,
                'hubspot_pending' => $Hubspot,
                // 'employer_id' => $employer_id,
                // 'everee_pending'=>$everee,
                'last_import' => $updated_at,
                // 'last_import' => isset($LegacyApiRowData->updated_at) ? $LegacyApiRowData->updated_at : '',
                'activate_date' => $activate_date,
            ];
        });

        return response()->json(['status' => 'success', 'data' => $crms], 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function getCrmSettingById($id): JsonResponse
    {

        $crmSetting = CrmSetting::where('crm_id', $id)->first();

        if ($crmSetting) {
            $val = json_decode($crmSetting['value']);
        }

        $LegacyApiRowData = LegacyApiRowData::orderBy('id', 'DESC')->first();

        $data['userName'] = isset($val->username) ? $val->username : '';
        $data['password'] = isset($val->password) ? $val->password : '';
        $data['api_key'] = isset($val->api_key) ? $val->api_key : '';
        $data['data_fetch_frequency'] = isset($val->data_fetch_frequency) ? $val->data_fetch_frequency : '';
        $data['day'] = isset($val->day) ? $val->day : '';
        $data['time'] = isset($val->time) ? $val->time : '';
        $data['timezone'] = isset($val->timezone) ? $val->timezone : '';
        $data['last_import'] = isset($LegacyApiRowData->created_at) ? $LegacyApiRowData->created_at : null;

        if ($id == 5) {
            if ($crmSetting) {
                $data = json_decode($crmSetting['value'], true);
            }
        }

        return response()->json([
            'ApiName' => 'Get CRM Setting By Id ',
            'status' => true,
            'message' => 'successfully',
            'data' => $data,
        ], 200);

        // return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    public function crmSetting(Request $request)
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'crm_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if ($request->crm_id == 1) {

            $Validator = Validator::make(
                $request->all(),
                [
                    'username' => 'required',
                    'password' => 'required',
                    'company_id' => 'required',
                    'data_fetch_frequency' => 'required',
                    'time' => 'required',
                    'timezone' => 'required',

                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 200);
            }
            $data = [
                'username' => $request['username'],
                'password' => $request['password'],
            ];
            // dd($data);
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://lgcy-analytics.com/api/api-token-auth', // your preferred url
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    // Set here requred headers
                    'accept: */*',
                    'accept-language: en-US,en;q=0.8',
                    'content-type: application/json',
                ],
            ]
            );
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $res = json_decode($response);
            $token = isset($res->token) ? $res->token : '';
            if ($token) {
                $value = [];
                $value['username'] = isset($request['username']) ? $request['username'] : '';
                $value['password'] = isset($request['password']) ? $request['password'] : '';
                $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
                $value['time'] = isset($request['time']) ? $request['time'] : '';
                $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
                $value['day'] = isset($request['day']) ? $request['day'] : '';
                $data['value'] = json_encode($value);
                $data['crm_id'] = 1;

                $company = CrmSetting::where('crm_id', 1)->first();
                if (empty($company)) {
                    $inserted = CrmSetting::create($data);

                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => true,
                        'message' => 'Successfully',
                        'data' => $inserted,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => false,
                        'message' => 'crm id already exit',
                        // 'data'    => $inserted,
                    ], 200);
                }
            } else {
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'Setting failed please try again letter',

                ], 200);
            }

        } elseif ($request->crm_id == 2) {

            $Validator = Validator::make(
                $request->all(),
                [
                    // 'api_key' => 'required',
                    // 'company_id' => 'required',
                    'data_fetch_frequency' => 'required',
                    'time' => 'required',
                    'timezone' => 'required',

                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 200);
            }
            $token = 'pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641';
            if ($token) {
                $value = [];
                $value['api_key'] = $token;
                $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
                $value['time'] = isset($request['time']) ? $request['time'] : '';
                $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
                $value['day'] = isset($request['day']) ? $request['day'] : '';
                $data['value'] = json_encode($value);
                $data['crm_id'] = 2;

                $company = CrmSetting::where('crm_id', 2)->first();
                if (empty($company)) {
                    $inserted = CrmSetting::create($data);

                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => true,
                        'message' => 'Successfully',
                        'data' => $inserted,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => false,
                        'message' => 'crm id already exit',
                        // 'data'    => $inserted,
                    ], 200);
                }
            } else {
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'Setting failed please try again letter',

                ], 200);
            }
        } elseif ($request->crm_id == 5) {
            return $this->activateSClearance($request->all());
        }
    }

    public function crmSettingUpdates(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'crm_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if ($request->crm_id == 1) {
            $data = [
                'username' => $request['username'],
                'password' => $request['password'],
            ];
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://lgcy-analytics.com/api/api-token-auth', // your preferred url
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    // Set here requred headers
                    'accept: */*',
                    'accept-language: en-US,en;q=0.8',
                    'content-type: application/json',
                ],
            ]);
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $res = json_decode($response);
            $token = isset($res->token) ? $res->token : '';

            if ($token) {
                $value = [];
                $value['username'] = isset($request['username']) ? $request['username'] : '';
                $value['password'] = isset($request['password']) ? $request['password'] : '';
                $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
                $value['time'] = isset($request['time']) ? $request['time'] : '';
                $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
                $value['day'] = isset($request['day']) ? $request['day'] : '';
                $data['value'] = json_encode($value);
                // $data['company_id'] = 1;
                $data['crm_id'] = 1;
                $company = CrmSetting::where('crm_id', 1)->first();

                if (isset($token)) {
                    if (empty($company)) {
                        $inserted = CrmSetting::Create($data);
                    } else {
                        $company->value = json_encode($value);
                        $company->save();
                    }

                    return response()->json([
                        'ApiName' => 'update legacy setting',
                        'status' => true,
                        'message' => 'Upload Sheet Successfully',
                        'data' => $value,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'update legacy setting',
                        'status' => false,
                        'message' => 'Company Id not find in table',
                    ], 404);
                }
            } else {
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'These credentials do not match.',
                    // 'data'    => $inserted,
                ], 400);
            }
        } elseif ($request->crm_id == 2) {
            // $token = "pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
            $token = $request->api_key;

            $Validator = Validator::make(
                $request->all(),
                [
                    'crm_id' => 'required',
                    'api_key' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 200);
            }

            if ($token) {

                $url = 'https://api.hubapi.com/crm/v3/objects/sales';
                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'authorization: Bearer '.$token,
                ];

                $curl_response = curlRequest($url, '', $headers, 'GET');
                $resp = json_decode($curl_response);

                if (empty($resp->results)) {
                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => false,
                        'message' => 'These credentials do not match.',
                    ], 400);
                }

                $value = [];
                $value['api_key'] = $token;
                $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
                $value['time'] = isset($request['time']) ? $request['time'] : '';
                $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
                $value['day'] = isset($request['day']) ? $request['day'] : '';
                $data['value'] = json_encode($value);
                // $data['company_id'] = 1;
                $data['crm_id'] = 2;

                $company = CrmSetting::where('crm_id', 2)->first();
                if (isset($token)) {
                    if (empty($company)) {
                        $inserted = CrmSetting::Create($data);
                    } else {
                        $company->value = json_encode($value);
                        $company->save();
                    }

                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                        $data = User::where('aveyo_hs_id', null)->where('is_super_admin', '!=', 1)->get();
                        $val = json_decode($CrmSetting['value']);
                        $token = $val->api_key;
                        $hubspotSaleDataCreate = $this->SyncHsSalesDataCreate($data, $token);

                    }

                    return response()->json([
                        'ApiName' => 'update hubspot setting',
                        'status' => true,
                        'message' => 'Upload Sheet Successfully',
                        'data' => $value,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'update hubspot setting',
                        'status' => false,
                        'message' => 'Company Id not find in table',
                    ], 404);
                }
            } else {
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'These credentials do not match.',
                    // 'data'    => $inserted,
                ], 400);
            }
        } elseif ($request->crm_id == 4) {
            // dd($request);
            try {
                $Validator = Validator::make($request->all(), [
                    'api_key' => 'required',
                ]);
                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }
                $curl = curl_init();
                $options = [
                    CURLOPT_URL => 'https://app.jobnimbus.com/api1/contacts?size=1',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer'.$request->api_key,
                        'Content-Type: application/json',
                    ],
                ];
                curl_setopt_array($curl, $options);
                $response = curl_exec($curl);
                if (curl_errno($curl)) {
                    throw new \Exception('cURL error: '.curl_error($curl));
                }
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($httpCode >= 400) {
                    throw new \Exception($response);
                }
                curl_close($curl);
                $response = json_decode($response);
                if ($response->results) {
                    DB::beginTransaction();
                    $updateValue = [
                        'value' => json_encode($request->all()),
                        'crm_id' => $request->crm_id,
                        // 'status' => 1,
                    ];
                    CrmSetting::updateOrInsert(['crm_id' => $request->crm_id], $updateValue);
                    // Crms::where(['id'=> $request->crm_id , 'name' => 'JobNimbus' ])->update(['status' => 1]);
                    DB::commit();

                    return response()->json(['ApiName' => 'CRM Setting API', 'status' => true, 'message' => 'connected Successfully', 'data' => $request->all()], 200);
                } else {
                    return response()->json(['ApiName' => 'CRM Setting API', 'status' => false, 'message' => 'something went wrong!'], 400);
                }
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json(['ApiName' => 'CRM Setting API', 'status' => false, 'message' => $e->getMessage()], 400);
            }
        }
        // elseif ($request->crm_id == 5) {
        // return $this->SClearanceUpdates($request->all()); // No need for turn.ai it was for transunion
        // }

    }

    public function crmSettingActiveInactive(Request $request): JsonResponse
    {
        $data = Crms::where('id', $request->id)->first();
        $crmSetting = CrmSetting::where('crm_id', $request->id)->first();

        if (empty($crmSetting)) {
            return response()->json([
                'ApiName' => 'CRM Setting API',
                'status' => false,
                'message' => 'First connect your setting.',

            ], 400);
        }

        if ($data->status == 0) {
            $data->status = 1;
            $crmSetting->status = 1;
            $data->save();
            $crmSetting->save();

            // Crms::where('id', '=', $request->id)->update(['status' => 0]);

            return response()->json([
                'ApiName' => 'update legacy setting',
                'status' => true,
                'message' => 'Connected Successfully',
            ], 200);

        } else {
            if ($request->id == 6) {
                return response()->json([
                    'ApiName' => 'sequai_crm_setting_update',
                    'status' => false,
                    'message' => 'Unable to Inactive Integration, please contact with Adam or Roshan!',
                ], 400);
            }

            $data->status = 0;
            $crmSetting->status = 0;
            $data->save();
            $crmSetting->save();

            return response()->json([
                'ApiName' => 'update legacy setting',
                'status' => true,
                'message' => 'Disconnected Successfully',
            ], 200);
        }

    }

    /**
     * @method activateSClearance
     * This method is used to Activate S Clearance
     */
    public function activateSClearance($requestData): JsonResponse
    {

        $Validator = Validator::make(
            $requestData,
            [
                'company_name' => 'required',
                'zipcode' => 'required',
                'street_line' => 'required',
                'ip_address' => 'required',
                'user_agent' => 'required',
                'full_name' => 'required',
                'email' => 'required',
                'partner_program_agreement' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $company = CrmSetting::where('crm_id', 5)->first();
        if (empty($company)) {
            $childPartnerResponse = $this->addChildPartner($requestData);
            if (isset($childPartnerResponse['account_id']) && ! empty($childPartnerResponse['account_id'])) {
                $value = [];
                $account_id = isset($childPartnerResponse['account_id']) ? $childPartnerResponse['account_id'] : '';
                $data['value'] = json_encode($childPartnerResponse);
                $data['crm_id'] = 5;
                $data['status'] = 1;
                $inserted = CrmSetting::create($data);

                /* insert packages */
                $packageResponse = $this->getPackages();
                $basicPackage = null;
                if (isset($packageResponse['packages']) && ! empty($packageResponse['packages'])) {
                    if (SClearancePlan::count() > 0) {
                        DB::table('s_clearance_plans')->truncate(); // Truncate table if count > 0
                    }
                    foreach ($packageResponse['packages'] as $package) {
                        if ($package['name'] == 'Basic') {
                            $package['price'] = 25;
                            $basicPackage = $package['package_id'];
                        }
                        if ($package['name'] == 'Enhanced') {
                            $package['price'] = 45;
                        }
                        if ($package['name'] == 'Drug Test Only') {
                            $package['price'] = 65;
                        }
                        if ($package['name'] == 'Premium MVR') {
                            $package['price'] = 60;
                        }
                        if ($package['name'] == 'MVR Only') {
                            $package['price'] = 10;
                        }
                        if ($package['name'] == 'Premium + Drug Test') {
                            $package['price'] = 120;
                        }
                        if ($package['name'] == 'LDP GSA Risk Assessment') {
                            $package['price'] = 90;
                        }

                        $package['plan_name'] = $package['name'];
                        unset($package['name']);

                        SClearancePlan::insert($package);
                    }
                }

                // Add Default plan and subscription for billing
                $this->addPlanSubscriptionforSClearance();

                Crms::where('id', 5)->update(['status' => 1]);
                $configureOldData = SClearanceConfiguration::select('id')->get()->toArray();
                if (empty($configureOldData)) {
                    $configureData = SClearanceConfiguration::create([
                        'position_id' => null,
                        'hiring_status' => 1,
                        'is_mandatory' => 1,
                        'is_approval_required' => 1,
                        'package_id' => $basicPackage,
                    ]);
                    $configureData->save();
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Successfully',
                    'account_id' => $account_id,
                ], 200);
            } elseif (isset($childPartnerResponse['message']) && ! empty($childPartnerResponse['message'])) {
                return response()->json([
                    'ApiName' => 'add_child_partner',
                    'status' => false,
                    'message' => $childPartnerResponse['message'],
                    'apiResponse' => @$childPartnerResponse,
                ], 400);
            } elseif (isset($childPartnerResponse['status']) && $childPartnerResponse['status'] == false) {
                return response()->json([
                    'ApiName' => 'add_child_partner',
                    'status' => false,
                    'message' => $childPartnerResponse['message'],
                    'apiResponse' => @$childPartnerResponse,
                ], 400);
            } else {
                return response()->json([
                    'ApiName' => 'add_child_partner',
                    'status' => false,
                    'message' => 'Something went wrong, please try after sometime',
                    'apiResponse' => @$childPartnerResponse,
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'CRM Setting API',
                'status' => false,
                'message' => 'CRM Id already exist',
            ], 200);
        }

        // Transunion code
        /*$Validator = Validator::make(
            $requestData,
            [
                'plan_id' => 'required',
                'email' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'phone_number' => 'required',
                'phone_type' => 'required',
                'business_name' => 'required',
                'address_line_1' => 'required',
                // 'address_line_2' => 'required',
                'locality' => 'required',
                'region' => 'required',
                'postal_code' => 'required',
                // 'country' => 'required',
                'accepted_terms_conditions' => 'required'
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $company = CrmSetting::where('crm_id', 5)->first();
        if (empty($company)) {
            $value = [];

            $employerResponse = $this->addEmployer($requestData);
            if(isset($employerResponse['employerId']) && !empty($employerResponse['employerId'])){
                $employer_id = isset($employerResponse['employerId']) ? $employerResponse['employerId'] : '';
                $value['plan_id'] = isset($requestData['plan_id']) ? $requestData['plan_id'] : '';
                $value['bundle_id'] = isset($requestData['bundle_id']) ? $requestData['bundle_id'] : '';
                $value['employer_id'] = $employer_id;
                $data['value'] = json_encode($value);
                $data['crm_id'] = 5;
                $data['status'] = 1;
                $inserted = CrmSetting::create($data);
                //Add Default plan and subscription for billing
                $this->addPlanSubscriptionforSClearance();

                $billingPlan = Plans::where(['id' => 2])->update(['sclearance_plan_id' => $value['plan_id']]);
                Crms::where('id', 5)->update(['status' => 1]);
                $configureOldData = SClearanceConfiguration::select('id')->get()->toArray();
                if(empty($configureOldData)){
                    $configureData = SClearanceConfiguration::create([
                        'position_id' => null,
                        'hiring_status' => null,
                        'is_mandatory' => 1,
                        'is_approval_required' => 1
                    ]);
                    $configureData->save();
                }
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => true,
                    'message' => 'Successfully',
                    'employer_id' => $employer_id,
                    'data' => $inserted
                ], 200);
            }else if(isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess'){
                app('App\Http\Controllers\API\SClearance\SClearanceController')->sendMailforUnAuthorized();
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$employerResponse,
                ], 400);
            }else{
                if(isset($employerResponse['errors']) && !empty($employerResponse['errors'])){
                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => false,
                        'message' => $employerResponse['errors'][0]['message'],
                        'apiResponse' => @$employerResponse,
                    ], 400);
                }else{
                    return response()->json([
                        'ApiName' => 'CRM Setting API',
                        'status' => false,
                        'message' => $employerResponse['message'],
                        'apiResponse' => @$employerResponse,
                    ], 400);
                }
            }
        } else {
            return response()->json([
                'ApiName' => 'CRM Setting API',
                'status' => false,
                'message' => 'CRM Id already exist',
            ], 200);
        }*/
    }

    /**
     * @method SClearanceUpdates
     * This method is used to update details of S Clearance
     */
    public function SClearanceUpdates($requestData): JsonResponse
    {
        $Validator = Validator::make(
            $requestData,
            [
                'crm_id' => 'required',
                'plan_id' => 'required',
                'bundle_id' => 'required',
                'employer_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (isset($requestData['employer_id']) && ! empty($requestData['employer_id'])) {
            $employerResponse = $this->updateEmployer($requestData);
            if (isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess') {
                app(\App\Http\Controllers\API\SClearance\SClearanceController::class)->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'SClearanceUpdates',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['errors']) && ! empty($employerResponse['errors'])) {
                return response()->json([
                    'ApiName' => 'SClearanceUpdates',
                    'status' => false,
                    'message' => $employerResponse['errors'][0]['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['message']) && ! empty($employerResponse['message'])) {
                return response()->json([
                    'ApiName' => 'SClearanceUpdates',
                    'status' => false,
                    'message' => $employerResponse['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            }
        }

        $company = CrmSetting::where('crm_id', 5)->first();
        if (! empty($company)) {
            $value = [];
            $crmValue = json_decode($company->value, true);
            $employer_id = $crmValue['employer_id'];
            $value['plan_id'] = isset($requestData['plan_id']) ? $requestData['plan_id'] : $crmValue['plan_id'];
            $value['bundle_id'] = isset($requestData['bundle_id']) ? $requestData['bundle_id'] : $crmValue['bundle_id'];
            $value['employer_id'] = isset($requestData['employer_id']) ? $requestData['employer_id'] : $crmValue['employer_id'];
            $company->value = json_encode($value);
            $company->save();
            $billingPlan = Plans::where(['id' => 2])->update(['sclearance_plan_id' => $value['plan_id']]);

            return response()->json([
                'ApiName' => 'update s-clearance setting',
                'status' => true,
                'message' => 'Updated s-clearance Successfully',
                'data' => $value,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'update s-clearance setting',
                'status' => false,
                'message' => 'S-Clearance entry not found',
            ], 404);
        }
    }

    public function sequiAiCrmSettingUpdates(Request $request): JsonResponse
    {
        $request->validate([
            'crm_id' => 'required',
        ]);
        $data = Crms::where('id', $request->crm_id)->first();
        if ($data == null) {
            return response()->json([
                'ApiName' => 'sequai_crm_setting_update',
                'status' => false,
                'message' => 'CRM not found!',
            ], 400);
        }
        $status = 0;
        if ($data->status == 0) {
            $status = 1;
        } else {
            $status = 0;

            return response()->json([
                'ApiName' => 'sequai_crm_setting_update',
                'status' => false,
                'message' => 'Unable to Inactive Integration, please contact with Adam or Roshan!',
            ], 400);
        }

        $data->status = $status;

        $crmSetting = CrmSetting::where('crm_id', $request->crm_id)->first();
        if (empty($crmSetting)) {
            $crmSetting = new CrmSetting;
        }
        $plan = Plans::where('id', 4)->first();
        if ($plan == null) {
            return response()->json([
                'ApiName' => 'sequai_crm_setting_update',
                'status' => false,
                'message' => 'Plan not found!',
            ], 400);
        }
        $current_date = date('Y-m-d H:i:s');
        if ($status == 1) {
            $planValue = ['plan_id' => $plan->id, 'sequiai_plan_id' => $request->sequiai_plan_id ?? 0, 'activate_date' => $current_date];
        } else {
            $crmValue = json_decode($crmSetting->value, true);
            $activate_date = $crmValue['activate_date'] ?? $crmSetting->updated_at;

            $inactivate_date = $current_date;
            $planValue = ['plan_id' => $plan->id, 'sequiai_plan_id' => $request->sequiai_plan_id ?? 0, 'activate_date' => $activate_date, 'inactivate_date' => $inactivate_date];
        }
        // dd($planValue);
        $crmSetting->crm_id = $request->crm_id;
        $crmSetting->value = json_encode($planValue);
        $crmSetting->status = $status;
        $crmSetting->created_at = new \DateTime;
        $crmSetting->updated_at = new \DateTime;
        if ($crmSetting->save()) {
            $data->save();

            // if($status==1){
            //     $subscription_last = Subscriptions::with('plans','billingType')->where('plan_id', 4)->where('status',1)->first();
            //     if($subscription_last!=null){
            //         $invoice_no = SubscriptionBillingHistory::genrate_invoice();
            //         app('App\Http\Controllers\API\ChatGPT\ChatGPTController')->generateSquiAiBilling($subscription_last, $invoice_no);
            //     }
            // }

            return response()->json([
                'ApiName' => 'sequai_crm_setting_update',
                'status' => true,
                'message' => 'Setting update successfully!',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sequai_crm_setting_update',
                'status' => false,
                'message' => 'Unable to update setting!',
            ], 400);
        }
    }

    /**
     * @method addPlanSubscriptionforSClearance
     * This method is used to add deufault plan and subscription for billing on s clearance activation
     */
    public function addPlanSubscriptionforSClearance()
    {
        // Add Plan
        $planName = 'S Clearance';
        $planData = Plans::where('product_name', $planName)->first();
        if ($planData == null) {
            $planData = new Plans;
            $planData->name = 'Per background verification check';
            $planData->product_name = $planName;
            $planData->unique_pid_rack_price = 0;
            $planData->unique_pid_discount_price = 0;
            $planData->m2_rack_price = 0;
            $planData->m2_discount_price = 0;
            // $planData->sclearance_plan_id = 1;
            $planData->created_at = new \DateTime;
            $planData->updated_at = new \DateTime;
            $planAdded = $planData->save();
            if ($planData->id != 2) {
                $existingPlanData = Plans::where('id', 2)->first();
                if (empty($existingPlanData)) {
                    $planData->id = 2;
                    $planData->save();
                }
            }
        } else {
            if ($planData->id != 2) {
                $existingPlanData = Plans::where('id', 2)->first();
                if (empty($existingPlanData)) {
                    $planData->id = 2;
                    $planData->save();
                }
            }
        }

        // Add Subscription
        if ($planData != null) {
            $planId = $planData->id;
            $checkSCSubscription = Subscriptions::where('plan_id', $planId)->first();

            $crmSetting = CrmSetting::where('crm_id', 5)->first();
            $status = $crmSetting->status ?? 0;

            $currentDate = Carbon::now();
            $firstDayOfMonth = $currentDate->startOfMonth()->toDateString();
            $lastDayOfMonth = $currentDate->endOfMonth()->toDateString();

            if ($checkSCSubscription == null) {
                $newSubsscription = new Subscriptions;
                $newSubsscription->plan_type_id = 1;
                $newSubsscription->plan_id = $planId;
                $newSubsscription->start_date = $firstDayOfMonth;
                $newSubsscription->end_date = $lastDayOfMonth;
                $newSubsscription->status = $status;
                $newSubsscription->paid_status = 0;
                $newSubsscription->total_pid = 0;
                $newSubsscription->total_m2 = 0;
                $newSubsscription->sales_tax_per = 7.25;
                $newSubsscription->sales_tax_amount = 0;
                $newSubsscription->amount = 0;
                $newSubsscription->credit_amount = 0;
                $newSubsscription->used_credit = 0;
                $newSubsscription->balance_credit = 0;
                $newSubsscription->taxable_amount = 0;
                $newSubsscription->grand_total = 0;
                $newSubsscription->save();
            }
        }
    }
}
