<?php

namespace App\Http\Controllers\API\SClearance;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\DomainSetting;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\OnboardingEmployees;
use App\Models\SClearanceConfiguration;
use App\Models\SClearancePlan;
use App\Models\SClearanceScreeningRequestList;
use App\Models\SClearanceTurnPackageConfiguration;
use App\Models\SClearanceTurnResponse;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SClearanceTurnStatus;
use App\Models\StateMVRCost;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use App\Traits\TurnAiTrait;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Validator;
use Log;

class TurnAiController extends Controller
{
    use EmailNotificationTrait, TurnAiTrait;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * @method get_child_partner_agreement
     * used to get agreement data
     */
    public function get_child_partner_agreement(): JsonResponse
    {
        $childPartnerResponse = $this->getChildPartnerAgreement();
        if (isset($childPartnerResponse['disclosures']) && ! empty($childPartnerResponse['disclosures'])) {
            return response()->json([
                'ApiName' => 'get_child_partner_agreement',
                'status' => true,
                'message' => 'Successfully',
                'disclosures' => $childPartnerResponse['disclosures'],
            ], 200);
        } elseif (isset($childPartnerResponse['description']) && ! empty($childPartnerResponse['description'])) {
            return response()->json([
                'ApiName' => 'get_child_partner_agreement',
                'status' => false,
                'message' => $childPartnerResponse['description'],
                'apiResponse' => @$childPartnerResponse,
            ], 400);
        } elseif (isset($childPartnerResponse['status']) && $childPartnerResponse['status'] == false) {
            return response()->json([
                'ApiName' => 'get_child_partner_agreement',
                'status' => false,
                'message' => $childPartnerResponse['message'],
                'apiResponse' => @$childPartnerResponse,
            ], 400);
        } else {
            return response()->json([
                'ApiName' => 'get_child_partner_agreement',
                'status' => false,
                'message' => 'Something went wrong, please try after sometime',
                'apiResponse' => @$childPartnerResponse,
            ], 400);
        }
    }

    /**
     * @method add_child_partner
     * used for adding child partner for testing
     */
    public function add_child_partner(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'company_name' => 'required',
                'zipcode' => 'required',
                'street_line' => 'required',
                'ip_address' => 'required',
                'user_agent' => 'required',
                'partner_program_agreement' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $requestData = $request->all();
            $childPartnerResponse = $this->addChildPartner($requestData);
            if (isset($childPartnerResponse['account_id']) && ! empty($childPartnerResponse['account_id'])) {
                $account_id = isset($childPartnerResponse['account_id']) ? $childPartnerResponse['account_id'] : '';

                return response()->json([
                    'ApiName' => 'add_child_partner',
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
                'ApiName' => 'add_child_partner',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method getToken testing token
     * Used to get token from Transunion Sharabale API
     */
    public function get_token()
    {
        $tokenData = $this->generateToken();

        return $tokenData;
    }

    /**
     * @method get_package_configurations
     * this method is used to get all package configurations
     */
    public function get_package_configurations(): JsonResponse
    {
        try {
            $packageConfData = SClearanceTurnPackageConfiguration::select('id', 'name', 'code', 'description')->get()->toArray();

            return response()->json([
                'ApiName' => 'get_package_configurations',
                'status' => true,
                'message' => 'successfully',
                'data' => $packageConfData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'get_package_configurations',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method get_configurations
     * This is used to get s-clearance configurations
     */
    public function get_configurations()
    {
        try {
            $configureData = SClearanceConfiguration::select('id', DB::raw("IFNULL(position_id, 'All') as position_id"), DB::raw("IFNULL(hiring_status, 'All') as hiring_status"), DB::raw('(CASE WHEN is_mandatory = 1 THEN true ELSE false END) AS is_mandatory'), DB::raw('(CASE WHEN is_approval_required = 1 THEN true ELSE false END) AS is_approval_required'), 'package_id')->orderBy('id', 'desc')->get();

            $configureData = $configureData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'position_id' => $item->position_id,
                    'hiring_status' => $item->hiring_status,
                    'is_mandatory' => $item->is_mandatory == 1 ? true : false,
                    'is_approval_required' => $item->is_approval_required == 1 ? true : false,
                    'package_id' => $item->package_id ? $item->package_id : '',
                ];
            });

            return response()->json(['ApiName' => 'get_configurations', 'status' => true, 'configureData' => $configureData], 200);
        } catch (Exception $e) {
            return response()->json(['ApiName' => 'get_configurations', 'status' => false, 'message' => $e->getMessage()], 400);
        }

    }

    /**
     * @method get_configurations_position_based
     * this method is used to configurations for position selected
     */
    public function get_configurations_position_based(Request $request): JsonResponse
    {
        try {
            $configurationDetails = SClearanceConfiguration::where(['position_id' => $request->position_id])->first();
            if (empty($configurationDetails)) {
                $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();

                return response()->json([
                    'ApiName' => 'get_configurations_position_based',
                    'status' => true,
                    'data' => $configurationDetails,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'get_configurations_position_based',
                    'status' => true,
                    'data' => $configurationDetails,

                ], 200);
            }
        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['ApiName' => 'get_configurations_position_based', 'error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method configure_setting
     * This is used to s-clearance configurations
     */
    public function configure_setting(Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $Validator = Validator::make(
                $request->all(),
                [
                    'data' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            if (isset($input['data']) && ! empty($input['data'])) {
                $oldDataIds = SClearanceConfiguration::Where('id', '<>', 1)->pluck('id')->toArray();
                foreach ($input['data'] as $data) {
                    $Validator = Validator::make(
                        $data,
                        [
                            'position_id' => 'required',
                            'hiring_status' => 'required',
                            'package_id' => 'required',
                        ]
                    );

                    if ($Validator->fails()) {
                        return response()->json(['error' => $Validator->errors()], 400);
                    }

                    $configureData = [];
                    if (isset($data['id']) && ! empty($data['id'])) {
                        $oldData = SClearanceConfiguration::where(['id' => $data['id']])->select('id')->get();
                        if (! empty($oldData)) {

                            $configUpdate = SClearanceConfiguration::where(['id' => $data['id']])->first(); // added for activity log
                            $configUpdate->position_id = ($data['position_id'] == 'All') ? null : $data['position_id'];
                            $configUpdate->hiring_status = ($data['hiring_status'] == 'All') ? null : $data['hiring_status'];
                            $configUpdate->is_mandatory = ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0);
                            $configUpdate->is_approval_required = ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0);
                            $configUpdate->package_id = isset($data['package_id']) ? $data['package_id'] : null;
                            $configUpdate->save(); // Added for activity log

                            if (($key = array_search($data['id'], $oldDataIds)) !== false) {
                                unset($oldDataIds[$key]);
                            }
                        } else {
                            $configureData = SClearanceConfiguration::create([
                                'position_id' => ($data['position_id'] == 'All') ? null : $data['position_id'],
                                'hiring_status' => ($data['hiring_status'] == 'All') ? null : $data['hiring_status'],
                                'is_mandatory' => ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0),
                                'is_approval_required' => ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0),
                                'package_id' => isset($data['package_id']) ? $data['package_id'] : null,
                                // 'package_configurations' => ((isset($data['package_configurations']) && !empty($data['package_configurations'])) ? json_encode($data['package_configurations']) : null)
                            ]);
                            $configureData->save();
                        }
                    } else {
                        $configureData = SClearanceConfiguration::create([
                            'position_id' => ($data['position_id'] == 'All') ? null : $data['position_id'],
                            'hiring_status' => ($data['hiring_status'] == 'All') ? null : $data['hiring_status'],
                            'is_mandatory' => ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0),
                            'is_approval_required' => ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0),
                            'package_id' => isset($data['package_id']) ? $data['package_id'] : null,
                        ]);
                        $configureData->save();
                    }
                }

                if (! empty($oldDataIds)) {
                    foreach ($oldDataIds as $id) {
                        SClearanceConfiguration::find($id)->delete();
                    }
                }
            }

            return response()->json(['ApiName' => 'configure_setting', 'status' => true, 'message' => 'Added Successfully.'], 200);
        } catch (Exception $e) {
            return response()->json(['ApiName' => 'configure_setting', 'status' => false, 'message' => 'Something went wrong'], 400);
        }

    }

    /**
     * @method SClearance_office_and_position_wise_user_list
     *  This is used to fetch lead, user, onboarding employees data
     *  */
    public function SClearance_office_and_position_wise_user_list(Request $request): JsonResponse
    {
        $ApiName = 'office_and_position_wise_user_list';
        $status_code = 200;
        $status = true;
        $message = 'User list based on Office and Position';
        $user_data = [];
        $package_id = '';
        $search_type = (isset($request->search_type) && $request->search_type == 'textfield') ? $request->search_type : '';
        $search_text = (isset($request->search_text)) ? $request->search_text : '';

        try {
            $office_id = isset($request->office_id) ? $request->office_id : 'All';
            $position_id = isset($request->position_id) ? $request->position_id : 'All';

            /* User Data */
            $user_data_query = User::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    // "zip_code as zipcode",
                    'email',
                    'sub_position_id as position_id',
                    'office_id',
                    DB::raw("'Hired' as user_type"),
                    'image'
                )
                ->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])
                ->orderBy('office_id', 'ASC');

            if ((int) $position_id > 0 && empty($search_type)) {
                $user_data_query = $user_data_query->where('sub_position_id', $position_id);
                // ->orWhere('position_id' , $position_id);
            }

            if ((int) $office_id > 0 && empty($search_type)) {
                $user_data_query = $user_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $user_data_query = $user_data_query->where('first_name', 'like', "%$search_text%")
                    ->orWhere('last_name', 'like', "%$search_text%")
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                    ->orWhere('email', 'like', "%$search_text%");
            }

            $user_data = $user_data_query->get()->toArray();

            /* Lead Data */
            $lead_data_query = Lead::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'last_name',
                    'email',
                    // "zip_code as zipcode", add later
                    DB::raw("'' as position_id"),
                    'office_id',
                    DB::raw("'Lead' as user_type"),
                    DB::raw("'' as image")
                )
                ->orderBy('office_id', 'ASC');

            if ((int) $office_id > 0 && empty($search_type)) {
                $lead_data_query = $lead_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $lead_data_query = $lead_data_query->where(function ($query) use ($search_text) {
                    $query->where('first_name', 'like', "%$search_text%")
                        ->orWhere('last_name', 'like', "%$search_text%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                        ->orWhere('email', 'like', "%$search_text%");
                });
            }

            $lead_data = $lead_data_query->whereNotIn('status', ['Hired', 'Rejected'])->get()->toArray();

            /* Onboarding Employee Data */
            $onb_emp_data_query = OnboardingEmployees::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'email',
                    // "zip_code as zipcode",
                    'sub_position_id as position_id',
                    'office_id',
                    DB::raw("'Onboarding' as user_type"),
                    'image'
                )
                ->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])
                ->orderBy('office_id', 'ASC');

            if ((int) $position_id > 0 && empty($search_type)) {
                $onb_emp_data_query = $onb_emp_data_query->where('sub_position_id', $position_id);
                // ->orWhere('position_id' , $position_id);
            }

            if ((int) $office_id > 0 && empty($search_type)) {
                $onb_emp_data_query = $onb_emp_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $onb_emp_data_query = $onb_emp_data_query->where(function ($query) use ($search_text) {
                    $query->where('first_name', 'like', "%$search_text%")
                        ->orWhere('last_name', 'like', "%$search_text%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                        ->orWhere('email', 'like', "%$search_text%");
                });
            }

            $onb_emp_data = $onb_emp_data_query->whereNull('user_id')->get()->toArray();

            $allData = array_merge($user_data, $lead_data, $onb_emp_data);

            if (empty($search_type)) {
                if ((int) $position_id > 0) {
                    $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id])->first();
                    if (! empty($configurationDetails)) {
                        $package_id = $configurationDetails->package_id;
                    } else {
                        $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                        $package_id = $configurationDetails->package_id;
                    }
                } else {
                    $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                    $package_id = $configurationDetails->package_id;
                }
            }

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }

        return response()->json([
            'ApiName' => $ApiName,
            'status' => $status,
            'message' => $message,
            'user_count' => count($allData),
            'data' => $allData,
            'package_id' => $package_id,
        ], $status_code);
    }

    /**
     * @method new_clearance_external
     * This is used to create screening request for external recipients
     */
    public function new_clearance_external(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required',
                    // 'zipcode' => 'required',
                    'description' => 'required',
                    'package_id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $requestData = $request->all();
            $input = $request->all();
            $input['user_type'] = 'External';

            $newClearanceMailResponse = $this->sendNewClearanceMail($input);
            if ($newClearanceMailResponse['status'] == 'true') {
                return response()->json([
                    'ApiName' => 'new_clearance_external',
                    'status' => true,
                    'message' => 'Mail sent for background check',
                    'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'new_clearance_external',
                    'status' => false,
                    'message' => $newClearanceMailResponse['message'],
                ], 400);
            }

            /* create package in turn.ai */
            /*$selectedPackageConf = $requestData['package_configurations']; // not now will be later
            $packageConfigurations = array(
                "basic/contingent" => (in_array('basic/contingent', $selectedPackageConf) ? 'true' : 'false'),
                "current_county" => (in_array('current_county', $selectedPackageConf) ? 'true' : 'false'),
                "7yr_county" => (in_array('7yr_county', $selectedPackageConf) ? 'true' : 'false'),
                "10yr_county" => (in_array('10yr_county', $selectedPackageConf) ? 'true' : 'false'),
                "mvr" => (in_array('mvr', $selectedPackageConf) ? 'true' : 'false'),
                "mvr_first" => (in_array('mvr_first', $selectedPackageConf) ? 'true' : 'false'),
                "current_federal" => (in_array('current_federal', $selectedPackageConf) ? 'true' : 'false'),
                "7yr_federal" => (in_array('7yr_federal', $selectedPackageConf) ? 'true' : 'false'),
                "10yr_federal" => (in_array('10yr_federal', $selectedPackageConf) ? 'true' : 'false'),
                "drug_test" => (in_array('drug_test', $selectedPackageConf) ? 'true' : 'false')
            );

            $packageData = $this->addPackage($packageConfigurations);*/

        } else {
            return response()->json([
                'ApiName' => 'new_clearance_external',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method new_clearance_internal
     * This is used to create screening request for internal recipients
     */
    public function new_clearance_internal(Request $request): JsonResponse
    {
        $requestData = $request->all();

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'user_data' => 'required',
                    'package_id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            if (isset($requestData['user_data']) && ! empty($requestData['user_data'])) {
                $data = [];
                $processedEmails = [];
                $mailSentCount = 0;
                $mailNotSentCount = 0;
                $mailNotSent = [];
                $mailSent = [];

                foreach ($requestData['user_data'] as $user) {
                    $user['package_id'] = $requestData['package_id'];

                    if (! in_array($user['email'], $processedEmails)) {
                        $Validator1 = Validator::make(
                            $user,
                            [
                                'first_name' => 'required',
                                'last_name' => 'required',
                                'email' => 'required',
                                // 'zipcode' => 'required',
                            ]
                        );
                        if ($Validator1->fails()) {
                            return response()->json(['error' => $Validator1->errors()], 400);
                        }

                        $newClearanceMailResponse = $this->sendNewClearanceMail($user);

                        $data[] = [
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'email' => $user['email'],
                            'status' => $newClearanceMailResponse['status'],
                        ];
                        $processedEmails[] = $user['email'];
                        if ($newClearanceMailResponse['status'] == 'true') {
                            $mailSentCount++;
                            array_push($mailSent, $user['first_name'].' '.$user['last_name']);
                        } else {
                            $mailNotSentCount++;
                            $mailNotSentReason = $user['first_name'].' '.$user['last_name'].' : '.$newClearanceMailResponse['message'];
                            array_push($mailNotSent, $mailNotSentReason);
                        }
                    }
                }

                $response = [
                    'ApiName' => 'new_clearance_internal',
                    'mailSentMessage' => '',
                    'mailNotSentMessage' => '',
                    'mailNotSentReasons' => '',
                    'status' => true,
                ];

                if ($mailSentCount > 0) {
                    $response['mailSentMessage'] = 'Mail sent to ('.implode(', ', $mailSent).')';
                }

                if ($mailNotSentCount > 0) {
                    $response['mailNotSentMessage'] = $mailNotSentCount.' Mails failed to send, due to following reasons';
                    $response['mailNotSentReasons'] = $mailNotSent;
                }

                return response()->json($response, 200);
            } else {
                return response()->json([
                    'ApiName' => 'new_clearance_internal',
                    'status' => false,
                    'message' => 'Please select user',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'new_clearance_internal',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    public function new_clearance_onboarding(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $input = $request->all();
            $input['user_type'] = 'Onboarding';
            $input['user_type_id'] = OnboardingEmployees::where(['user_id' => $input['user_id']])->first()->id;

            $configurationDetails = SClearanceConfiguration::where('position_id', $input['position_id'])->first();
            if (empty($configurationDetails)) {
                $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();
            }
            if (! empty($configurationDetails)) {
                $input['package_id'] = $configurationDetails->package_id;
            } else {
                return response()->json([
                    'ApiName' => 'New Clearance Internal Doc - new_clearance_onboarding',
                    'status' => false,
                    'message' => 'S Clearance Configuration not found, please contact to the Admin',
                ], 400);
            }

            $newClearanceMailResponse = $this->sendNewClearanceMail($input);

            if ($newClearanceMailResponse['status'] == 'true') {
                return response()->json([
                    'ApiName' => 'New Clearance Internal Doc - new_clearance_onboarding',
                    'status' => true,
                    'message' => 'Mail sent for background check',
                    'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'New Clearance Internal Doc - new_clearance_onboarding',
                    'status' => false,
                    'message' => $newClearanceMailResponse['message'],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'New Clearance Internal Doc - new_clearance_onboarding',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method sendNewClearanceMail
     * This is a common code to send mail for background check
     */
    public function sendNewClearanceMail($requestData)
    {
        try {
            $mailResponse = '';
            $sendMail = 0;
            $existingBC = SClearanceTurnScreeningRequestList::where(['email' => $requestData['email']])->orderBy('id', 'desc')->get()->toArray();
            if (count($existingBC) > 0) {
                $statuArr = ['emailed', 'initiated', 'consent', 'processing', 'pending', 'pending__first_notice', 'pending__second_notice', 'review__identity', 'review'];
                if (in_array($existingBC[0]['status'], $statuArr)) {
                    return ['ApiName' => 'sendNewClearanceMail', 'status' => 'false', 'message' => 'This user has already an open Background Verification Check', 'sr_status' => $existingBC[0]['status']];
                } else {
                    $sendMail = 1;
                }
            } else {
                $sendMail = 1;
            }

            if ($sendMail == 1) {
                $check_domain_setting = DomainSetting::check_domain_setting($requestData['email']);
                if ($check_domain_setting['status'] == true) {
                    $srRequestSave = SClearanceTurnScreeningRequestList::create([
                        'email' => $requestData['email'],
                        'user_type' => $requestData['user_type'],
                        'user_type_id' => @$requestData['user_type_id'],
                        'position_id' => @$requestData['position_id'],
                        'office_id' => @$requestData['office_id'],
                        'first_name' => $requestData['first_name'],
                        'middle_name' => @$requestData['middle_name'],
                        'last_name' => $requestData['last_name'],
                        'zipcode' => @$requestData['zipcode'],
                        'package_id' => $requestData['package_id'],
                        'description' => (isset($requestData['description']) ? $requestData['description'] : 'Background Check'),
                        'status' => 'emailed',
                    ]);
                    $srRequestSave->save();
                    $request_id = $srRequestSave->id;
                    $mailData['subject'] = 'Request for Background Check';
                    $mailData['email'] = $requestData['email'];
                    $mailData['request_id'] = $request_id;
                    $encryptedRequestId = encryptData($request_id);
                    $mailData['encrypted_request_id'] = $encryptedRequestId;
                    $mailData['url'] = $requestData['frontend_url'];
                    $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                    $mailResponse = $this->sendEmailNotification($mailData);

                    return ['ApiName' => 'sendNewClearanceMail', 'status' => 'true', 'message' => 'Mail sent', 'encryptedRequestId' => $encryptedRequestId];
                    // if($mailResponse){
                    //     return ['ApiName' => 'sendNewClearanceMail', 'status' => 'true', 'message' => 'Mail sent', 'encryptedRequestId'=>$encryptedRequestId];
                    // }else{
                    //     return ['ApiName' => 'sendNewClearanceMail', 'status' => 'false', 'message' => 'Error in sending mail, Please check domain settiings.'];
                    // }
                } else {
                    return ['ApiName' => 'sendNewClearanceMail', 'status' => 'false', 'message' => "Domain setting isn't allowed to send e-mail on this domain."];
                }
            }
        } catch (Exception $e) {
            return ['ApiName' => 'sendNewClearanceMail', 'status' => 'false', 'message' => 'Something went wrong', 'error' => $e->getMessage()];
        }
    }

    /**
     * @method get_form_data
     * This method is used to get form for user based on zipcode and package id
     */
    public function get_form_data($encrypted_request_id)
    {
        try {
            $request_id = decryptData($encrypted_request_id);
            $userData = SClearanceTurnScreeningRequestList::where('id', '=', $request_id)->first();

            if (isset($userData->package_id) && isset($userData->zipcode) && ! empty($userData->package_id) && ! empty($userData->zipcode)) {
                $formResponse = $this->getFormData($userData);
                if (isset($formResponse['check_id']) && ! empty($formResponse['check_id'])) {
                    SClearanceTurnScreeningRequestList::where('id', $request_id)->update(['form_check_id' => $formResponse['check_id']]);

                    return response()->json([
                        'ApiName' => 'get_form_data',
                        'status' => true,
                        'formData' => $formResponse,
                        'userData' => $userData,
                    ], 200);
                } elseif (isset($formResponse['message']) && ! empty($formResponse['message'])) {
                    return response()->json([
                        'ApiName' => 'get_form_data',
                        'status' => false,
                        'message' => $formResponse['message'],
                        'wrong_zipcode' => (strpos($formResponse['message'], 'zip') !== false) ? 1 : 0,
                        'apiResponse' => @$formResponse,
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'get_form_data',
                    'status' => false,
                    'message' => 'Some required details are missing for this user, please contact to the Admin',
                ], 400);
            }
        } catch (Exception $e) {
            return $e;

            return response()->json([
                'ApiName' => 'get_form_data',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method add_screening_request
     * This is a common code to create screening request for external/internal both recipients
     */
    public function add_screening_request(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'form_check_id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $requestData = $request->all();
            $request_id = decryptData($requestData['request_id']);
            $requestData['request_id'] = $request_id;

            $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
            if ($crmSetting) {
                /* upload file and convert to base64 */
                if ($request->file('drivers_license_image_front')) {
                    $drivers_license_image_front = $this->convertPngToBase64($request->file('drivers_license_image_front'));
                    if ($drivers_license_image_front['status'] == 'success') {
                        $requestData['drivers_license_image_front_base64_img'] = $drivers_license_image_front['base64'];
                    } else {
                        return response()->json([
                            'ApiName' => 'add_screening_request',
                            'status' => false,
                            'message' => 'Error in uploading drivers_license_image_front',
                        ], 400);
                    }
                }

                if ($request->file('drivers_license_image_back')) {
                    $drivers_license_image_back = $this->convertPngToBase64($request->file('drivers_license_image_back'));
                    if ($drivers_license_image_back['status'] == 'success') {
                        $requestData['drivers_license_image_back_base64_img'] = $drivers_license_image_back['base64'];
                    } else {
                        return response()->json([
                            'ApiName' => 'add_screening_request',
                            'status' => false,
                            'message' => 'Error in uploading drivers_license_image_back',
                        ], 400);
                    }
                }

                if ($request->file('consent_document')) {
                    $consent_document = $this->convertPngToBase64($request->file('consent_document'));
                    if ($consent_document['status'] == 'success') {
                        $requestData['consent_document_base64_img'] = $consent_document['base64'];
                    } else {
                        return response()->json([
                            'ApiName' => 'add_screening_request',
                            'status' => false,
                            'message' => 'Error in uploading consent_document',
                        ], 400);
                    }
                }

                // Create screening request
                $SrResponse = $this->createScreeningRequest($requestData);

                if (isset($SrResponse['turn_id']) && ! empty($SrResponse['turn_id'])) {
                    $screening_request = SClearanceTurnScreeningRequestList::where(['id' => $request_id])->first();
                    $screening_request->status = 'initiated';
                    $screening_request->date_sent = date('Y-m-d');
                    $screening_request->turn_id = $SrResponse['turn_id'];
                    // $screening_request->worker_id = @$SrResponse['message']['transaction_uuid'];
                    $screening_request->state = @$requestData['drivers_license_state'];
                    $screening_request->save(); // Added for activity log

                    // Add details to sclearance mediator server
                    $serverData = [
                        'turn_id' => $SrResponse['turn_id'],
                        'worker_id' => null,
                    ];
                    $this->addToSClearanceServer($serverData);

                    return response()->json([
                        'ApiName' => 'add_screening_request',
                        'status' => true,
                        'message' => 'Screening request added successfully',
                    ], 200);
                } elseif (isset($SrResponse['error']) && ! empty(($SrResponse['error']))) {
                    return response()->json([
                        'ApiName' => 'add_screening_request',
                        'status' => false,
                        'message' => $SrResponse['error'],
                        'apiResponse' => @$SrResponse,
                    ], 400);
                } elseif (isset($SrResponse['message']) && ! empty(($SrResponse['message']))) {
                    return response()->json([
                        'ApiName' => 'add_screening_request',
                        'status' => false,
                        'message' => $SrResponse['message'],
                        'apiResponse' => @$SrResponse,
                    ], 400);
                } elseif (isset($SrResponse['_schema']) && ! empty(($SrResponse['_schema']))) {
                    return response()->json([
                        'ApiName' => 'add_screening_request',
                        'status' => false,
                        'message' => @$SrResponse['_schema'][0],
                        'apiResponse' => @$SrResponse,
                    ], 400);
                } else { // some required field missing
                    SClearanceTurnResponse::insert([
                        'webhook_type' => 'add_screening',
                        'status' => 'Error Encountered',
                        'response' => json_encode($SrResponse),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    return response()->json([
                        'ApiName' => 'add_screening_request',
                        'status' => false,
                        'message' => 'Something went wrong, please try later',
                        'apiResponse' => @$SrResponse,
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'add_screening_request',
                    'status' => false,
                    'message' => 'Please Activate S-Clearance First',
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * @method convertPngToBase64
     * This is a common code to convert png to base64 code
     */
    public function convertPngToBase64($file)
    {
        try {
            $fileData = file_get_contents($file->getRealPath());

            // Convert to Base64
            $base64 = base64_encode($fileData);
            $mimeType = $file->getMimeType();

            // Format as a Data URI
            $base64String = "data:$mimeType;base64,$base64";

            return [
                'status' => 'success',
                'base64' => $base64String,
            ];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Something went wrong', 'error' => $e->getMessage()];
        }
    }

    /**
     * @method turn_result_webhook
     * A callback(webhook) URL for turn to send notification when bv is completed(either approved or consider/pending)
     */
    public function turn_result_webhook(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $response = '';
            Log::channel('sclearance_log')->info('Turn Result Callback '.print_r($data, true));

            SClearanceTurnResponse::insert([
                'turn_id' => @$data['turn_id'],
                'worker_id' => @$data['worker_id'],
                'webhook_type' => 'result',
                'status' => 'Record Updated',
                'response' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (isset($data['turn_id']) && ! empty($data['turn_id'])) {
                $screening_request = SClearanceTurnScreeningRequestList::where(['turn_id' => $data['turn_id']])->first();
                if (! empty($screening_request) && isset($data['partner_worker_status']) && ! empty($data['partner_worker_status'])) {
                    $screening_request->status = $data['partner_worker_status'];
                    $screening_request->is_report_generated = 1;
                    $screening_request->report_date = date('Y-m-d');
                    if (empty($screening_request->worker_id)) {
                        $screening_request->worker_id = @$data['worker_id'];
                    }
                    $screening_request->save(); // Added for activity log
                } else {
                    $response = [
                        'message' => 'Screening Request not found',
                    ];
                }
            }

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('error in report webhook '.print_r($e->getMessage(), true));
            SClearanceTurnResponse::insert([
                'turn_id' => @$data['turn_id'],
                'worker_id' => @$data['worker_id'],
                'webhook_type' => 'result',
                'status' => 'Error in catch',
                'response' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * @method turn_status_webhook
     * A callback(webhook) URL for turn to send notification when bv status changes
     */
    public function turn_status_webhook(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $turnData = json_decode($request->message);
            $response = '';
            Log::channel('sclearance_log')->info('Turn Status Callback '.print_r($data, true));

            SClearanceTurnResponse::insert([
                'turn_id' => @$data['turn_id'],
                'worker_id' => @$data['worker_id'],
                'webhook_type' => 'status',
                'status' => 'Record Updated',
                'response' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if (isset($data['turn_id']) && ! empty($data['turn_id'])) {
                $screening_request = SClearanceTurnScreeningRequestList::where(['turn_id' => $data['turn_id']])->first();
                if (! empty($screening_request) && isset($data['status']) && ! empty($data['status'])) {
                    $screening_request->status = $data['status'];
                    if (empty($screening_request->worker_id)) {
                        $screening_request->worker_id = @$data['worker_id'];
                    }
                    $screening_request->save(); // Added for activity log

                    if ($data['status'] == 'approved' || $data['status'] == 'pending') {
                        SClearanceTurnScreeningRequestList::where(['turn_id' => $data['turn_id'], 'is_report_generated' => 0])
                            ->update(['is_report_generated' => 1, 'report_date' => date('Y-m-d')]);
                    }
                } else {
                    $response = [
                        'message' => 'Screening Request not found',
                    ];
                }
            }

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('error in report webhook '.print_r($e->getMessage(), true));
            SClearanceTurnResponse::insert([
                'turn_id' => @$data['turn_id'],
                'worker_id' => @$data['worker_id'],
                'webhook_type' => 'status',
                'status' => 'Error in catch',
                'response' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * @method approve_decline_bv_report
     * this method is used to approve and decline background verification report
     */
    public function approve_decline_bv_report(Request $request): JsonResponse
    {
        try {
            $screeningResquest = SClearanceTurnScreeningRequestList::where('turn_id', $request->turn_id)->first();
            if (! empty($screeningResquest)) {
                $msg = '';
                $updateData = [];

                $this->approveRejectSR($request->approval_status, $request->turn_id);

                if ($request->approval_status == 'approve') {
                    $status = 'approved';
                    $msg = 'Approved Successfully';
                } else {
                    $status = 'rejected';
                    $msg = 'Rejected Successfully';
                }
                $userid = auth()->user()->id;
                $screeningResquest->approved_declined_by = $userid;
                $screeningResquest->status = $status;
                $screeningResquest->save(); // aadded for activity log

                return response()->json([
                    'ApiName' => 'approve_decline_bv_report',
                    'status' => true,
                    'message' => $msg,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'approve_decline_bv_report',
                    'status' => false,
                    'message' => 'Record not found',
                ], 400);
            }

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['ApiName' => 'approve_decline_bv_report', 'error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method resend_sclearance_request
     * this method is used to resend BV Mail to the user
     */
    public function resend_sclearance_request(Request $request)
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $input = $request->all();
            $SrDetails = SClearanceTurnScreeningRequestList::where('id', $input['id'])->first();
            if (! empty($SrDetails)) {
                $SrDetails->frontend_url = $request->frontend_url ?? '';

                // send mail
                try {
                    $newClearanceMailResponse = [];
                    $check_domain_setting = DomainSetting::check_domain_setting($SrDetails->email);
                    if ($check_domain_setting['status'] == true) {
                        $mailResponse = '';
                        $request_id = $SrDetails->id;
                        $mailData['subject'] = 'Request for Background Check';
                        $mailData['email'] = $SrDetails->email;
                        $mailData['request_id'] = $request_id;
                        $encryptedRequestId = encryptData($request_id);
                        $mailData['encrypted_request_id'] = $encryptedRequestId;
                        $mailData['url'] = $SrDetails['frontend_url'];
                        $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                        $mailResponse = $this->sendEmailNotification($mailData);
                        $newClearanceMailResponse = ['status' => true, 'message' => 'Mail sent', 'encryptedRequestId' => $encryptedRequestId];
                    } else {
                        $newClearanceMailResponse = ['status' => false, 'message' => "Domain setting isn't allowed to send e-mail on this domain."];

                    }
                } catch (Exception $e) {
                    return $e;
                    $newClearanceMailResponse = ['status' => false, 'message' => $e->getMessage()];
                }
                if (isset($newClearanceMailResponse['status']) && $newClearanceMailResponse['status'] == true) {
                    // Added for activity log
                    activity()
                        ->causedBy(auth()->user())
                        ->performedOn(SClearanceTurnScreeningRequestList::find($input['id']))
                        ->withProperties(['attributes' => ['user' => $SrDetails['first_name'].' '.$SrDetails['last_name'], 'action' => 'Resent Mail']])
                        ->event('updated')
                        ->log('updated');

                    return response()->json([
                        'ApiName' => 'resend_sclearance_request',
                        'status' => true,
                        'message' => 'Mail sent for background check',
                        'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'resend_sclearance_request',
                        'status' => false,
                        'message' => $newClearanceMailResponse['message'],
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'resend_sclearance_request',
                    'status' => false,
                    'message' => 'Screening Request not found',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'resend_sclearance_request -  Mail',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method get_all_screening_requests_list
     * this method is used to get all screening req in our system
     */
    public function get_all_screening_requests_list(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        $newList = [];
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        $recipient_type = '';
        if (isset($input['recipient_type']) && ! empty($input['recipient_type'])) {
            $recipient_type = $input['recipient_type'];
        }

        $search_text = '';
        if (isset($input['search_text']) && ! empty($input['search_text'])) {
            $search_text = $input['search_text'];
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            if ($recipient_type == 'External') {
                $SrDetails = SClearanceTurnScreeningRequestList::with('positionDetail')
                    ->where([
                        'user_type' => $recipient_type,
                    ]);

                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('turn_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id'])) {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id'])) {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    if ($status == 'approved_by_admin') {
                        $SrDetails->where('status', 'like', '%approved%');
                        $SrDetails->whereNotNull('approved_declined_by');
                    } elseif ($status == 'approved') {
                        $SrDetails->where('status', 'like', '%approved%');
                        $SrDetails->whereNull('approved_declined_by');
                    } else {
                        $SrDetails->where('status', 'like', "%$status%");
                    }
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            } elseif ($recipient_type == 'Internal') {
                $SrDetails = SClearanceTurnScreeningRequestList::with('positionDetail')
                    ->where('user_type', '<>', 'External');
                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('turn_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    if ($status == 'approved_by_admin') {
                        $SrDetails->where('status', 'like', '%approved%');
                        $SrDetails->whereNotNull('approved_declined_by');
                    } elseif ($status == 'approved') {
                        $SrDetails->where('status', 'like', '%approved%');
                        $SrDetails->whereNull('approved_declined_by');
                    } else {
                        $SrDetails->where('status', 'like', "%$status%");
                    }
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            } else {
                $SrDetails = SClearanceTurnScreeningRequestList::with('positionDetail');
                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('turn_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    $SrDetails->where('status', 'like', "%$status%");
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            }

            if (! empty($SrDetails)) {
                $SRList = [];
                foreach ($SrDetails as $list) {
                    $office = '';
                    if (isset($list['office_id']) && ! empty($list['office_id'])) {
                        $office = Locations::select('office_name')->where('id', $list['office_id'])->first();
                    }

                    $image = '';
                    if ($list['user_type'] == 'Onboarding') {
                        $userData = OnboardingEmployees::select('image')->where('id', '=', $list['user_type_id'])->get()->toArray();
                        if (isset($userData[0]['image']) && $userData[0]['image'] != null) {
                            $image = s3_getTempUrl(config('app.domain_name').'/'.$userData[0]['image']);
                        } else {
                            $image = @$userData[0]['image'];
                        }
                    } elseif ($list['user_type'] == 'Hired') {
                        $userData = User::select('image')->where('id', '=', $list['user_type_id'])->get()->toArray();
                        if (isset($userData[0]['image']) && $userData[0]['image'] != null) {
                            $image = s3_getTempUrl(config('app.domain_name').'/'.$userData[0]['image']);
                        } else {
                            $image = @$userData[0]['image'];
                        }
                    }

                    $approverData = [];
                    if (isset($list['approved_declined_by']) && ! empty($list['approved_declined_by'])) {
                        $approverData = User::where('id', $list['approved_declined_by'])->select(DB::raw("CONCAT(first_name, ' ', last_name) as user_name"), 'image')->first();
                    }

                    $newList = [
                        'id' => @$list['id'],
                        'image' => $image,
                        'stage' => @$list['user_type'],
                        'position' => @$list['position_detail']['position_name'],
                        'office' => @$office->office_name,
                        'position_id' => @$list['position_id'],
                        'office_id' => @$list['office_id'],
                        'status' => $list['status'],
                        'is_report_generated' => @$list['is_report_generated'],
                        'date_sent' => @$list['date_sent'],
                        'report_date' => @$list['report_date'],
                        'approved_declined_by' => @$list['approved_declined_by'],
                        'approved_declined_user_name' => @$approverData->user_name,
                        'email' => @$list['email'],
                        'first_name' => @$list['first_name'],
                        'last_name' => @$list['last_name'],
                        'turn_id' => @$list['turn_id'],
                        'worker_id' => @$list['worker_id'],
                    ];

                    array_push($SRList, $newList);
                }

                $data = paginate($SRList, $perpage);

                return response()->json([
                    'ApiName' => 'get all screening requests',
                    'status' => true,
                    'message' => 'Screening Request List',
                    'data' => $data,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'get all screening requests',
                    'status' => false,
                    'message' => 'Screening Requests Not Found',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'new clearance - Screening Request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method get_onboarding_employee_bv_status
     * This is used to get status of background verification of an onboarding employee
     */
    public function get_onboarding_employee_bv_status(Request $request): JsonResponse
    {
        $position_id = $request->position_id;
        $user_id = $request->id;
        $user_type = $request->user_type;
        $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1, 'is_approval_required' => 1])->first();
        if (! empty($configurationDetails)) {
            $data = [];
            $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])
                ->where(function ($query) {
                    $query->where('is_report_generated', '=', 1);
                    $query->orWhereNotNull('report_date');
                })
                ->first();

            if (! empty($reportData)) {
                $data = [
                    'turn_id' => $reportData->turn_id,
                    'is_report_generated' => $reportData->is_report_generated,
                    'background_verification_status' => $reportData->status,
                    'report_date' => $reportData->report_date,
                ];
            }

            return response()->json([
                'ApiName' => 'get_onboarding_employee_bv_status',
                'status' => true,
                'message' => 'Background Verification Report',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get_onboarding_employee_bv_status',
                'status' => false,
                'message' => 'Approval of Backgorund Verification is not required',
            ], 400);
        }
    }

    /**
     * @method addToSClearanceServer
     * This is used to add background verification details to s clearance mediator server
     */
    public function addToSClearanceServer($requestData)
    {
        try {
            $data = [
                'turn_id' => $requestData['turn_id'],
                'worker_id' => @$requestData['worker_id'],
                'domain_name' => config('app.domain_name'),
                'domain_url' => config('app.base_url'),
            ];
            DB::connection('sclearance')->table('screening_domain_details')->insert($data);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('addToSClearanceServer error '.print_r($e->getMessage(), true));
        }
    }

    /**
     * @method withdraw_screening_request
     * This method is used to cancel a screening request by employer
     */
    public function withdraw_screening_request(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'turn_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $turnId = $input['turn_id'];

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $cancelSrResponsee = $this->withdrawScreeningRequest($turnId);
            if (isset($cancelSrResponsee['error']) && ! empty($cancelSrResponsee['error'])) {
                return response()->json([
                    'ApiName' => 'withdraw_screening_request',
                    'status' => false,
                    'message' => $cancelSrResponsee['message'],
                    'apiResponse' => @$cancelSrResponsee,
                ], 400);
            } elseif (isset($cancelSrResponsee['message']) && ! empty($cancelSrResponsee['message'])) {
                return response()->json([
                    'ApiName' => 'withdraw_screening_request',
                    'status' => false,
                    'message' => $cancelSrResponsee['message'],
                    'apiResponse' => @$cancelSrResponsee,
                ], 400);
            } else {
                $srUpdate = SClearanceTurnScreeningRequestList::where('turn_id', $turnId)->first(); // added for activity log
                $srUpdate->status = 'withdrawn';
                $srUpdate->save();

                return response()->json([
                    'ApiName' => 'withdraw_screening_request',
                    'status' => true,
                    'message' => 'Screening Request Withdrawn Successfully',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'withdraw_screening_request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method view_report_details
     * this method is used to get admin details for report
     */
    public function view_report_details(Request $request): JsonResponse
    {
        try {
            $screeningResquest = SClearanceTurnScreeningRequestList::where('turn_id', $request->turn_id)->first();
            if (! empty($screeningResquest)) {
                $userData = User::where('id', $screeningResquest->approved_declined_by)->select(DB::raw("CONCAT(first_name, ' ',  last_name) as user_name"), 'image')->get()->toArray();
                $data = [
                    'user' => @$userData[0],
                    'status' => $screeningResquest->status,
                    'approved_declined_by' => $screeningResquest->approved_declined_by,
                ];

                return response()->json([
                    'ApiName' => 'view_report_details',
                    'status' => true,
                    'data' => $data,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'view_report_details',
                    'status' => false,
                    'message' => 'Record not found',

                ], 400);
            }

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method getStatusLists
     * this method is used to get available turn statuses lists
     */
    public function getTurnStatusLists(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $statusData = SClearanceTurnStatus::select('status_code', 'status_name')->get()->toArray();

            return response()->json([
                'ApiName' => 'getTurnStatusLists',
                'status' => true,
                'message' => 'successfully',
                'data' => $statusData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getTurnStatusLists',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method get_screening_report
     * this method is used to get view report
     */
    public function get_screening_report(Request $request): JsonResponse
    {
        try {
            $screeningResquest = SClearanceTurnScreeningRequestList::where('turn_id', $request->turn_id)->first();
            if (! empty($screeningResquest)) {
                $reportResponse = $this->getScreeningReport($request->turn_id);
                if (isset($reportResponse['url']) && ! empty($reportResponse['url'])) {
                    $pdfS3Url = explode('?', $reportResponse['url']);
                    $pdfUrl = $pdfS3Url[0];

                    return response()->json([
                        'ApiName' => 'get_screening_report',
                        'status' => true,
                        'message' => 'Screening Report',
                        'data' => $pdfUrl,
                    ], 200);
                } elseif (isset($reportResponse['message']) && ! empty($reportResponse['message'])) {
                    return response()->json([
                        'ApiName' => 'get_screening_report',
                        'status' => false,
                        'message' => $reportResponse['message'],
                    ], 400);
                } else {
                    return response()->json([
                        'ApiName' => 'get_screening_report',
                        'status' => false,
                        'message' => 'Something went wrong, please try later',
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'get_screening_report',
                    'status' => false,
                    'message' => 'Record not found',
                ], 400);
            }
        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method getPackageLists
     * this method is used to get available turn packages lists
     */
    public function getPackageLists(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $packageData = SClearancePlan::select('package_id', 'plan_name')->get()->toArray();

            return response()->json([
                'ApiName' => 'getPackageLists',
                'status' => true,
                'message' => 'successfully',
                'data' => $packageData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getPackageLists',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method get_user_details
     * This method is used to get the details added in new clearance to show in mail landing page
     */
    public function get_user_details($encrypted_request_id): JsonResponse
    {
        try {
            $request_id = decryptData($encrypted_request_id);
            $userData = SClearanceTurnScreeningRequestList::where('id', '=', $request_id)->first();
            if (! empty($userData)) {
                if ($userData->status == 'emailed') {
                    return response()->json([
                        'ApiName' => 'background check details',
                        'status' => true,
                        'data' => $userData,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'get_user_details',
                        'status' => false,
                        'message' => 'You have already been verified or your background check is in progress, please contact admin.',
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'get_user_details',
                    'status' => false,
                    'message' => 'Request Not Found',
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'get_user_details',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method update_user_zipcode
     * This method is used to update the zipcode for user
     */
    public function update_user_zipcode($encrypted_request_id, Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $Validator = Validator::make(
                $request->all(),
                [
                    'zipcode' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $request_id = decryptData($encrypted_request_id);
            $userData = SClearanceTurnScreeningRequestList::where('id', '=', $request_id)->first();
            if (! empty($userData)) {
                $userData->zipcode = $input['zipcode'];
                $userData->save();

                return response()->json([
                    'ApiName' => 'background check details',
                    'status' => true,
                    'message' => 'Zipcode updated successfully.',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'get_user_details',
                    'status' => false,
                    'message' => 'Request Not Found',
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'get_user_details',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method users_billing_report
     * This is used to get users report of screening requests with success
     */
    public function users_billing_report(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        $newList = [];
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
            // if($input['column_name'] == 'price'){
            //     $column = 'plan_id';
            // }
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5])->first();
        if ($crmSetting) {
            if ($request->start_date != '') {
                $date = Carbon::createFromFormat('Y-m-d', $request->start_date);
                $billMonth = $date->format('m');
                $billYear = $date->format('Y');
                $start_date = $request->start_date;
            } else {
                $billMonth = Carbon::now()->month;
                $billYear = Carbon::now()->year;
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
            }

            if ($request->end_date != '') {
                $end_date = $request->end_date;
            } else {
                $end_date = Carbon::now()->endOfMonth()->format('Y-m-d');
            }

            $reportCount = 0;
            $totalPrice = 0;

            $planData = SClearancePlan::select('id', 'plan_name', 'price', 'package_id')->get()->toArray();
            $allPlans = [];
            if (! empty($planData)) {
                foreach ($planData as $plan) {
                    $allPlans[$plan['package_id']] = $plan;
                }
            }

            $SrDetails = SClearanceTurnScreeningRequestList::whereBetween('date_sent', [$start_date, $end_date])
                ->whereNotNull('package_id')->get();
            // ->orderBy($column, $sort)
            // ->paginate($perpage);

            if (! empty($SrDetails)) {
                foreach ($SrDetails as $response) {
                    $plan_price = 0;
                    $package_id = 0;
                    $plan_name = '';
                    $planDetails = @$allPlans[$response->package_id];
                    if (! empty($planDetails)) {
                        if ($planDetails['plan_name'] == 'MVR Only') {
                            $stateCost = StateMVRCost::select('cost')->where('state_code', $response->state)->first();
                            $planDetails['price'] = $planDetails['price'] + (isset($stateCost->cost) ? $stateCost->cost : 0);
                        }
                        $plan_price = $planDetails['price'];
                        $plan_id = $planDetails['id'];
                        $plan_name = $planDetails['plan_name'];
                        $package_id = $planDetails['package_id'];
                    }

                    if ($plan_price > 0 && ! empty($plan_name)) {
                        $newList[] = [
                            'id' => $response->id,
                            'email' => $response->email,
                            'first_name' => $response->first_name,
                            'last_name' => $response->last_name,
                            'turn_id' => $response->turn_id,
                            'package_id' => $package_id,
                            'plan_name' => $plan_name,
                            'price' => $plan_price,
                        ];

                        $totalPrice += $plan_price;
                        $reportCount++;
                    }
                }
            }

            $data = paginate($newList, $perpage);

            if (! empty($newList)) {

                $data = paginate($newList, $perpage);

                return response()->json([
                    'ApiName' => 'users_billing_report',
                    'status' => true,
                    'message' => 'Screening Request List',
                    'data' => $data,
                    'report_count' => $reportCount,
                    'total_price' => $totalPrice,
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'users_billing_report',
                    'status' => false,
                    'message' => 'Screening Requests Not Found',
                    'apiResponse' => '',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'users_billing_report',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method sclearance_transunion_data
     * This is used to check old sclearance data with transunion available or not
     */
    public function sclearance_transunion_data(): JsonResponse
    {
        $oldSCDataCount = SClearanceScreeningRequestList::whereIn('status', ['Approval Pending', 'Approved', 'Manual Verification Pending'])->count();
        if ($oldSCDataCount > 0) {
            return response()->json([
                'ApiName' => 'sclearance_transunion_data',
                'status' => true,
                'dataCount' => $oldSCDataCount,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sclearance_transunion_data',
                'status' => false,
                'dataCount' => 0,
            ], 200);
        }
    }

    /**
     * @method screening_results
     * This is used to get screening all details from turn ai (what failed where he is now)
     */
    public function screening_results($turn_id): JsonResponse
    {
        $reportResponse = $this->getScreeningDetails($turn_id);
        if (isset($reportResponse['status']) && $reportResponse['status'] == false) {
            return response()->json([
                'ApiName' => 'screening_results',
                'status' => false,
                'message' => $reportResponse['message'],
                'data' => $reportResponse,
            ], 400);
        } else {// for success
            $postConsent = $this->getPostConsentAction($turn_id);
            $sendReviewMail = 0;
            if (isset($postConsent['actions']) && ! empty($postConsent['actions'])) {
                $sendReviewMail = 1;
            }

            return response()->json([
                'ApiName' => 'screening_results',
                'status' => true,
                'message' => 'Screening Result All Details',
                'sendReviewMail' => $sendReviewMail,
                'postConsentAction' => @$postConsent['actions'],
                'data' => $reportResponse,
            ], 200);
        }
    }

    /**
     * @method send_review_background_email
     * This is used to send email to review background
     */
    public function send_review_background_email(Request $request): JsonResponse
    {
        try {
            $screeningRequest = SClearanceTurnScreeningRequestList::where('turn_id', $request->turn_id)->first();
            if (! empty($screeningRequest)) {
                $check_domain_setting = DomainSetting::check_domain_setting($screeningRequest->email);
                if ($check_domain_setting['status'] == true) {
                    $postConsent = $request->postConsentAction;
                    if (isset($postConsent) && ! empty($postConsent)) {
                        $email = $screeningRequest->email;
                        $turn_id = $screeningRequest->turn_id;
                        $frontend_url = $request->frontend_url;
                        foreach ($postConsent as $action) {
                            $mailData['drug_test_url'] = '';
                            if ($action['type'] == 'identity_verification') {
                                $mailData['subject'] = 'Request to upload required documents';
                                $mailData['body_text'] = 'has requested you upload your documents again to complete your background verification';
                            } elseif ($action['type'] == 'drug_test_scheduling') {
                                $mailData['subject'] = 'Request to schedule drug test';
                                $mailData['body_text'] = 'has requested you to schedule drug test to complete your background verification';
                                $mailData['drug_test_url'] = $action['url'];
                            }
                            $mailData['action_type'] = $action['type'];
                            $mailData['email'] = $email;
                            $mailData['turn_id'] = $turn_id;
                            $mailData['url'] = $frontend_url;
                            $mailData['template'] = view('mail.backgroundCheckReview', compact('mailData'));

                            $mailResponse = $this->sendEmailNotification($mailData);
                        }

                        return response()->json([
                            'ApiName' => 'send_review_background_email',
                            'status' => true,
                            'message' => 'Mail Sent Succeffully',
                        ], 200);
                    } else {
                        return response()->json([
                            'ApiName' => 'send_review_background_email',
                            'status' => true,
                            'message' => 'No pending action found. So, no need to send an email',
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'ApiName' => 'send_review_background_email',
                        'status' => false,
                        'message' => "Domain setting isn't allowed to send e-mail on this domain",
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'send_review_background_email',
                    'status' => false,
                    'message' => 'Screening Request Not Found',
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'send_review_background_email',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method get_post_consent_url
     * This is used to get post consent url
     */
    public function get_post_consent_url(Request $request)
    {
        try {
            $turn_id = $request->turn_id;
            $action_type = $request->action_type;
            $screeningRequest = SClearanceTurnScreeningRequestList::where('turn_id', $turn_id)->first();
            if (! empty($screeningRequest)) {
                $postConsent = $this->getPostConsentAction($turn_id);
                $url = '';
                if (isset($postConsent['actions']) && ! empty($postConsent['actions'])) {
                    $matched = array_filter($postConsent['actions'], fn ($item) => $item['type'] === $action_type);
                    $url = reset($matched)['url'] ?? null;
                }

                return ['ApiName' => 'get_post_consent_url', 'status' => 'true', 'url' => $url];
            } else {
                return ['ApiName' => 'get_post_consent_url', 'status' => 'false', 'message' => 'Screening request not found'];
            }
        } catch (Exception $e) {
            return ['ApiName' => 'get_post_consent_url', 'status' => 'false', 'message' => $e->getMessage()];
        }
    }
}
