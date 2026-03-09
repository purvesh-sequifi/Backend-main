<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Services\DashboardHistoryService;
use App\Models\ActivityLog;
use App\Models\AdditionalPayFrequency;
use App\Models\Announcement;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\DeductionAlert;
use App\Models\Documents;
use App\Models\EventCalendar;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\LegacyApiNullData;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\MonthlyPayFrequency;
use App\Models\OnboardingEmployees;
use App\Models\Payroll;
use App\Models\PayrollAlerts;
use App\Models\PayrollHistory;
use App\Models\Positions;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SetGoals;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use App\Models\WeeklyPayFrequency;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    private $userOverrides;

    private $getPayrollData;

    private $clawbackSettlement;

    private $approvalsAndRequest;

    private $dashboardHistoryService;

    public function __construct(
        UserOverrides $userOverrides,
        GetPayrollData $getPayrollData,
        ClawbackSettlement $clawbackSettlement,
        ApprovalsAndRequest $approvalsAndRequest,
        DashboardHistoryService $dashboardHistoryService
    ) {
        $this->userOverrides = $userOverrides;
        $this->getPayrollData = $getPayrollData;
        $this->clawbackSettlement = $clawbackSettlement;
        $this->approvalsAndRequest = $approvalsAndRequest;
        $this->dashboardHistoryService = $dashboardHistoryService;
    }

    public function dashboardAlertCenter(Request $request)
    {
        $result = [];
        $data = LegacyApiNullData::orderBy('updated_at', 'desc')
            ->whereNotNull('data_source_type')
            ->where(function ($query) {
                $query->whereNotNull('sales_alert')
                    ->orWhereNotNull('missingrep_alert')
                    ->orWhereNotNull('closedpayroll_alert')
                    ->orWhereNotNull('locationredline_alert')
                    ->orWhereNotNull('repredline_alert');
            })
            ->limit(6)
            ->get();

        $deduction = DeductionAlert::with('users')->orderBy('updated_at', 'desc')->limit(5)->get();
        $clawback = ClawbackSettlement::with('users')->orderBy('updated_at', 'desc')->limit(5)->get();
        $payrollAlert = PayrollAlerts::with('users')->where('status', 1)->orderBy('updated_at', 'desc')->limit(5)->get();

        if (isset($data)) {
            $companyProfile = CompanyProfile::first();
            $data->transform(function ($val) use ($companyProfile) {
                $value = '';
                $value1 = '';
                $valType = '';

                $state = State::where('state_code', $val->customer_state)->first();
                $location = Locations::with('State', 'Cities', 'additionalRedline')->where('general_code', $val->customer_state)->first();

                if (! empty($state)) {
                    $state_data = ['state_id' => $state->id, 'state_name' => $state->name, 'general_code' => $state->state_code];
                }

                if (empty($location)) {
                    if (! empty($state)) {
                        $location = Locations::where('state_id', $state->id)->first();
                        if (empty($location)) {
                            $state_data = ['state_id' => $state->id, 'state_name' => $state->name, 'general_code' => $val->customer_state];
                        }
                    } else {
                        $state_data = ['state_id' => '', 'general_code' => $val->customer_state];
                    }
                }

                // Sales alerts
                if (! empty($val->sales_alert)) {
                    $salesAlerts = explode(',', $val->sales_alert);
                    $alertMessages = [];
                    foreach ($salesAlerts as $alert) {
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $alert = str_replace('customer_signoff', 'sale_date', $alert);
                        }
                        $alertMessages[] = str_replace('_', ' ', $alert);
                    }
                    $value = implode(', ', $alertMessages);
                    $valType = 'Sales';
                }
                // Location redline alerts
                elseif (! empty($val->locationredline_alert)) {
                    $locationAlerts = explode(',', $val->locationredline_alert);
                    $alertMessages = [];
                    foreach ($locationAlerts as $alert) {
                        if ($alert == 'Location_redline') {
                            $alertMessages[] = 'Location redline missing for sale approval - '.date('m/d/Y', strtotime($val->customer_signoff));
                        } else {
                            $alertMessages[] = str_replace('_', ' ', $alert);
                        }
                    }
                    $value = implode(', ', $alertMessages);
                    $valType = 'location Redline';
                }
                // Missing rep alerts
                elseif (! empty($val->missingrep_alert)) {
                    $missingRepAlerts = explode(',', $val->missingrep_alert);
                    $alertMessages = [];

                    foreach ($missingRepAlerts as $alert) {
                        if (strpos($alert, 'sales_rep_email_saleapproval') !== false) {
                            $alertMessages[] = 'sales rep is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } elseif (strpos($alert, 'sales_setter_email_saleapproval') !== false) {
                            $alertMessages[] = 'sales setter is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } elseif (strpos($alert, 'sales_rep_terminated') !== false) {
                            $alertMessages[] = 'Sales rep terminated';
                        } elseif (strpos($alert, 'sales_rep_contract_ended') !== false) {
                            $alertMessages[] = 'Sale rep contract ended';
                        } elseif (strpos($alert, 'sales_rep_dismissed') !== false) {
                            $alertMessages[] = 'Sales rep dismissed';
                        } elseif (strpos($alert, 'sales_setter_terminated') !== false) {
                            $alertMessages[] = 'Sales setter terminated';
                        } elseif (strpos($alert, 'sales_setter_contract_ended') !== false) {
                            $alertMessages[] = 'Sales setter contract ended';
                        } elseif (strpos($alert, 'sales_setter_dismissed') !== false) {
                            $alertMessages[] = 'Sales setter dismissed';
                        } else {
                            $alertMessages[] = str_replace('_', ' ', $alert);
                        }
                    }
                    $value = implode(', ', $alertMessages);
                    $valType = 'Missing Rep';
                }
                // Rep redline alerts
                elseif (! empty($val->repredline_alert)) {
                    $repRedlineAlerts = explode(',', $val->repredline_alert);
                    $alertMessages = [];
                    foreach ($repRedlineAlerts as $alert) {
                        if (strpos($alert, 'repredline_closer_redline_saleapproval') !== false) {
                            $alertMessages[] = 'closer Redline is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } elseif (strpos($alert, 'repredline_closer_selfgenredline_saleapproval') !== false) {
                            $alertMessages[] = 'Closer Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } elseif (strpos($alert, 'repredline_setter_redline_saleapproval') !== false) {
                            $alertMessages[] = 'Setter Redline is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } elseif (strpos($alert, 'repredline_setter_selfgenredline_saleapproval') !== false) {
                            $alertMessages[] = 'Setter Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($val->customer_signoff));
                        } else {
                            $alertMessages[] = str_replace('_', ' ', $alert);
                        }
                    }
                    $value = implode(', ', $alertMessages);
                    $valType = 'Rep Redline';
                }
                // Closed payroll alerts
                elseif (! empty($val->closedpayroll_alert)) {
                    $value = str_replace('_', ' ', $val->closedpayroll_alert);
                    $valType = 'Closed Payroll';
                }

                return [
                    'id' => $val->id,
                    'pid' => $val->pid,
                    'heading' => $val->pid.'-'.$val->customer_name.'-'.$valType,
                    'alert_summary' => 'Update '.$value,
                    'sales_rep_name' => $val->sales_rep_name,
                    'type' => $valType,
                    'severity' => 'High',
                    'status' => ($val->action_status == 1) ? 'Resolve' : 'Pending',
                    'updated' => $val->updated_at,
                ];
            });
            $result['missing_data'] = $data;
        }

        if (isset($deduction)) {
            $deduction->transform(function ($vall) {
                return [
                    'id' => $vall->id,
                    'pid' => $vall->pid,
                    'sales_rep_name' => $vall?->users?->first_name.' '.$vall?->users?->last_name,
                    'alert_summary' => 'Adjust addres amount',
                    'severity' => 'Low',
                    'type' => 'adders',
                    'status' => ($vall->action_status == 1) ? 'Resolve' : 'Pending',
                    'updated' => $vall->updated_at,
                ];
            });
            $result['addres_data'] = [];
        }

        if (isset($payrollAlert)) {
            $payrollAlert->transform(function ($vall) {
                $frequencyTypeId = isset($vall->users->positionDetail->payFrequency->frequency_type_id) ? $vall->users->positionDetail->payFrequency->frequency_type_id : null;

                return [
                    'id' => $vall->id,
                    'user_id' => $vall->user_id,
                    'frequency_type_id' => $frequencyTypeId,
                    'commission' => $vall->commission,
                    'pay_period_from' => $vall->pay_period_from,
                    'pay_period_to' => $vall->pay_period_to,
                    'pid' => null,
                    'sales_rep_name' => $vall?->users?->first_name.' '.$vall?->users?->last_name,
                    'alert_summary' => 'Payroll Alert',
                    'severity' => 'High',
                    'type' => 'payroll',
                    'status' => ($vall->status == 1) ? 'Resolve' : 'Pending',
                    'updated' => $vall->updated_at,
                ];
            });
            $result['payroll_alert'] = [];
        }

        if (isset($clawback)) {
            $clawback->transform(function ($value) {
                return [
                    'id' => $value->id,
                    'pid' => $value->pid,
                    'sales_rep_name' => $value?->users?->first_name.' '.$value?->users?->last_name,
                    'alert_summary' => 'Adjust clawback',
                    'severity' => 'Low',
                    'type' => 'Clawback',
                    'status' => ($value->action_status == 1) ? 'Resolve' : 'Pending',
                    'updated' => $value->updated_at,
                ];
            });
            $result['clawback_data'] = [];
        }

        return response()->json(['ApiName' => 'Alert Dashborad Data Api', 'status' => true, 'message' => 'Successfully', 'data' => $result], 200);
    }

    public function dashboardItemSection_old(Request $request): JsonResponse
    {
        $data = [];
        $user = auth::user();
        $acknowledgeCount = 0;
        $countData = 0;
        $overrideCount = 0;
        $redlineCount = 0;
        $commissionCount = 0;
        $upfrontCount = 0;
        $withheldCount = 0;
        $reqCount = 0;
        $docCount = 0;
        $missingCount = 0;
        $socialSecurityNumber = '';
        // $taxInfo = ActivityLog::where('subject_id',$user->id)->where('subject_id','!=',1)->orderBy('id','DESC')->pluck('properties')->first();
        // Fix redundant WHERE clause
        $taxInfo = ActivityLog::where('subject_id', $user->id)
            ->orderBy('id', 'DESC')
            ->value('properties');

        if ($taxInfo) {
            $data = json_decode($taxInfo, true);
            if (isset($data['attributes']['social_sequrity_no'])) {
                $socialSecurityNumber = $data['attributes']['social_sequrity_no'];
            }
        }

        if ($user->is_super_admin == 1) {

            $newRequests = ApprovalsAndRequest::where('status', 'Pending')->orderBy('id', 'DESC')->limit(3)->get();
            $documentSignReview = Documents::with('categoryType')->where('user_id', $user->id)->where('document_response_status', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $missingData = LegacyApiNullData::select('pid', 'customer_name')->where('setter_id', null)->whereNotNull('data_source_type')->orderBy('id', 'DESC')->limit(3)->get();
            $taxdata = User::select('id', 'first_name', 'email')->where('id', $user->id)->where('social_sequrity_no', $socialSecurityNumber)->first();
            $override = UserOverrideHistory::where('old_direct_overrides_amount', '!=', 0)
                ->orWhere('old_direct_overrides_type', '!=', '')
                ->orWhere('old_indirect_overrides_amount', 0)
                ->orWhere('old_indirect_overrides_type', '!=', '')
                ->orWhere('old_office_overrides_amount', '!=', 0)
                ->orWhere('old_office_overrides_type', '!=', '')
                ->orWhere('old_office_stack_overrides_amount', '!=', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $redline = UserRedlines::where('old_redline_amount_type', '!=', '')
                ->orWhere('old_redline_type', '!=', '')
                ->orWhere('old_redline', '!=', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $commission = UserCommissionHistory::where('old_commission', '!=', 0)->limit(3)->get();
            $upfront = UserUpfrontHistory::where('old_upfront_pay_amount', '!=', 0)->orWhere('old_upfront_sale_type', '!=', '')->limit(3)->get();
            $withheld = UserWithheldHistory::where('old_withheld_amount', '!=', 0)->orWhere('old_withheld_type', '!=', '')->limit(3)->get();

        } elseif ($user->is_manager == 1) {

            $userId = User::where('manager_id', $user->id)->pluck('id')->toArray();
            $newRequests = ApprovalsAndRequest::where('manager_id', $user->id)->where('status', 'Pending')->orderBy('id', 'DESC')->limit(3)->get();
            $documentSignReview = Documents::with('categoryType')->where('user_id', $user->id)->where('document_response_status', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $taxdata = User::select('id', 'first_name', 'email')->where('id', $user->id)->where('social_sequrity_no', $socialSecurityNumber)->first();
            $missingData = LegacyApiNullData::Select('pid', 'customer_name')->where('setter_id', null)->whereNotNull('data_source_type')->orderBy('id', 'DESC')->limit(3)->get();
            $override = UserOverrideHistory::whereIn('user_id', $userId)->where('old_direct_overrides_amount', '!=', 0)
                ->orWhere('old_direct_overrides_type', '!=', '')
                ->orWhere('old_indirect_overrides_amount', 0)
                ->orWhere('old_indirect_overrides_type', '!=', '')
                ->orWhere('old_office_overrides_amount', '!=', 0)
                ->orWhere('old_office_overrides_type', '!=', '')
                ->orWhere('old_office_stack_overrides_amount', '!=', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $redline = UserRedlines::whereIn('user_id', $userId)->where('old_redline_amount_type', '!=', '')
                ->orWhere('old_redline_type', '!=', '')
                ->orWhere('old_redline', '!=', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $commission = UserCommissionHistory::whereIn('user_id', $userId)->where('old_commission', '!=', 0)->limit(3)->get();
            $upfront = UserUpfrontHistory::whereIn('user_id', $userId)->where('old_upfront_pay_amount', '!=', 0)->orWhere('old_upfront_sale_type', '!=', '')->limit(3)->get();
            $withheld = UserWithheldHistory::whereIn('user_id', $userId)->where('old_withheld_amount', '!=', 0)->orWhere('old_withheld_type', '!=', '')->limit(3)->get();

        } else {
            $newRequests = ApprovalsAndRequest::where('user_id', $user->id)->where('req_no', '!=', null)->orderBy('id', 'DESC')->limit(3)->get();
            $documentSignReview = Documents::with('categoryType')->where('user_id', $user->id)->where('document_response_status', 0)->orderBy('id', 'DESC')->limit(3)->get();
            $taxdata = User::select('id', 'first_name', 'email')->where('id', $user->id)->where('social_sequrity_no', $socialSecurityNumber)->first();
            $missingData = LegacyApiNullData::select('pid', 'customer_name')->where('setter_id', null)->whereNotNull('data_source_type')->orderBy('id', 'DESC')->limit(3)->get();
            $override = UserOverrideHistory::where('user_id', $user->id)->orderBy('id', 'DESC')->limit(3)->get();
            $redline = UserRedlines::where('user_id', $user->id)->where('old_redline_amount_type', '!=', '')->orderBy('id', 'DESC')->limit(3)->get();
            $commission = UserCommissionHistory::where('user_id', $user->id)->where('old_commission', '!=', 0)->limit(3)->get();
            $upfront = UserUpfrontHistory::where('user_id', $user->id)->where('old_upfront_pay_amount', '!=', 0)->limit(3)->get();
            $withheld = UserWithheldHistory::where('user_id', $user->id)->where('old_withheld_amount', '!=', 0)->limit(3)->get();

        }

        if (count($newRequests) > 0) {
            $Count = $newRequests->count();
            $reqCount = isset($Count) ? $Count : 0;
            $newReqData = [];
            foreach ($newRequests as $newReq) {
                $newReqData[] = [
                    'title' => 'You Got A New Request',
                    'status' => 'Reimbursmant For Annette Black',
                    'req_no' => $newReq->req_no,
                    'user_id' => $newReq->user_id,
                ];
            }
        }

        if (count($documentSignReview) > 0) {
            $Count = $documentSignReview->count();
            $docCount = isset($Count) ? $Count : 0;
            $docData = [];
            foreach ($documentSignReview as $docSign) {
                $docData[] = [
                    'title' => 'New Document',
                    'type' => isset($docSign->categoryType->categories) ? $docSign->categoryType->categories : null,
                    'status' => 'Document Sign And Review',
                    'user_id' => $docSign->user_id,
                ];
            }
        }

        if ($taxdata) {
            $Count = $taxdata->count();
            $taxCount = isset($Count) ? $Count : 0;
            $taxInformation = [
                'title' => 'Tax Information',
                'status' => 'Social Sequrity Number Update',
                'user_id' => $taxdata->id,
            ];
        }

        if ($user->position_id == 2) {
            if (count($missingData) > 0) {
                $Count = $missingData->count();
                $missingCount = isset($Count) ? $Count : 0;
                $missingDataStatus = [];
                foreach ($missingData as $missingDatas) {
                    $missingDataStatus[] = [
                        'title' => isset($missingDatas->customer_name) ? $missingDatas->customer_name : null,
                        'status' => 'Missing Setter',
                        'pid' => $missingDatas->pid,
                    ];
                }

            }
        }

        $countData = $reqCount + $docCount + $missingCount;

        if ($user->is_super_admin == 0) {

            if (count($override) > 0) {
                $Count = $override->count();
                $overrideCount = isset($Count) ? $Count : 0;
                $overrideData = [];
                foreach ($override as $overrides) {
                    $overrideData[] = [
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Overide Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $overrides->user_id,
                    ];
                }
            }

            if (count($redline) > 0) {
                $Count = $redline->count();
                $redlineCount = isset($Count) ? $Count : 0;
                $redlineStatus = [];
                foreach ($redline as $redlines) {
                    $redlineStatus[] = [
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Redline Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $redlines->user_id,
                    ];

                }
            }

            if (count($commission) > 0) {
                $Count = $commission->count();
                $commissionCount = isset($Count) ? $Count : 0;
                $commssionStatus = [];
                foreach ($commission as $commissions) {
                    $commssionStatus[] = [
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Commission Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $commissions->user_id,
                    ];
                }

            }

            if (count($upfront) > 0) {
                $Count = $upfront->count();
                $upfrontCount = isset($Count) ? $Count : 0;
                $upfrontStatus = [];
                foreach ($upfront as $upfronts) {
                    $upfrontStatus[] = [
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Upfront Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $upfronts->user_id,
                    ];
                }

            }

            if (count($withheld) > 0) {
                $Count = $withheld->count();
                $withheldCount = isset($Count) ? $Count : 0;
                $withheldStatus = [];
                foreach ($withheld as $withhelds) {
                    $withheldStatus[] = [
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Withheld Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $withhelds->user_id,
                    ];
                }

            }

            $acknowledge = [
                'override_data' => isset($overrideData) ? $overrideData : null,
                'redline_data' => isset($redlineStatus) ? $redlineStatus : null,
                'commssion_data' => isset($commssionStatus) ? $commssionStatus : null,
                'upfront_data' => isset($upfrontStatus) ? $upfrontStatus : null,
                'withheld_data' => isset($withheldStatus) ? $withheldStatus : null,
            ];

            $acknowledgeCount = $overrideCount + $redlineCount + $commissionCount + $upfrontCount + $withheldCount;
        }

        $data = [
            'new_request' => isset($newReqData) ? $newReqData : null,
            'document_sign_review' => isset($docData) ? $docData : null,
            'missing_data' => isset($missingDataStatus) ? $missingDataStatus : null,
            'tax_information' => isset($taxInformation) ? $taxInformation : null,
            'acknowledge_data' => isset($acknowledge) ? $acknowledge : null,
            'total' => $acknowledgeCount + $countData,

        ];

        return response()->json(['ApiName' => 'Alert Dashborad Data Api', 'status' => true, 'message' => 'Successfully', 'data' => $data], 200);
    }

    public function dashboardItemSection(Request $request)
    {
        $data = [];
        $user = auth::user();
        $acknowledgeCount = 0;
        $countData = 0;
        $overrideCount = 0;
        $redlineCount = 0;
        $commissionCount = 0;
        $upfrontCount = 0;
        $withheldCount = 0;
        $userOrganizationCount = 0;
        $hiringCount = 0;
        $reqCount = 0;
        $docCount = 0;
        $missingCount = 0;
        $socialSecurityNumber = '';
        // Fix redundant WHERE clause and use more efficient value() method
        $taxInfo = ActivityLog::where('subject_id', $user->id)
            ->orderBy('id', 'DESC')
            ->value('properties');
        if ($taxInfo) {
            $data = json_decode($taxInfo, true);
            if (isset($data['attributes']['social_sequrity_no'])) {
                $socialSecurityNumber = $data['attributes']['social_sequrity_no'];
            }
        }

        if ($user->is_super_admin == 1) {

            $newRequests = ApprovalsAndRequest::select('id', 'req_no', 'user_id')
                ->where('status', 'Pending')
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $documentSignReview = Documents::with('categoryType:id,categories')
                ->select('id', 'user_id', 'document_response_status', 'action_item_status')
                ->where('user_id', $user->id)
                ->where('document_response_status', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $taxdata = User::select('id', 'first_name', 'email')
                ->where('id', $user->id)
                ->where('social_sequrity_no', $socialSecurityNumber)
                ->where('action_item_status', 0)
                ->first();

            // Optimized join with covering indexes
            $missingData = SaleMasterProcess::join('sale_masters as sm', 'sm.pid', '=', 'sale_master_process.pid')
                ->select('sm.pid', 'sm.customer_name')
                ->where('sale_master_process.closer1_id', $user->id)
                ->whereNull('sale_master_process.setter1_id')
                ->where('sm.action_item_status', 0)
                ->whereNotNull('sm.data_source_type')
                ->orderBy('sm.id', 'DESC')
                ->limit(3)
                ->get();

            // Use DashboardHistoryService to consolidate all history queries
            $historyData = $this->dashboardHistoryService->getUserHistoryData($user);

            $override = $historyData['override'];
            $redline = $historyData['redline'];
            $commission = $historyData['commission'];
            $upfront = $historyData['upfront'];
            $withheld = $historyData['withheld'];
            $userOrganization = $historyData['user_organization'];
            $hiringAccepted = $historyData['hiring'];
            // return $withheld;

        } elseif ($user->is_manager == 1) {

            $userId = User::select('id')->where('manager_id', $user->id)->pluck('id')->toArray();

            $newRequests = ApprovalsAndRequest::select('id', 'req_no', 'user_id', 'manager_id')
                ->where('manager_id', $user->id)
                ->where('status', 'Pending')
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $documentSignReview = Documents::with('categoryType:id,categories')
                ->select('id', 'user_id', 'document_response_status', 'action_item_status')
                ->where('user_id', $user->id)
                ->where('document_response_status', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $taxdata = User::select('id', 'first_name', 'email')
                ->where('id', $user->id)
                ->where('social_sequrity_no', $socialSecurityNumber)
                ->where('action_item_status', 0)
                ->first();

            // Optimized join with covering indexes
            $missingData = SaleMasterProcess::join('sale_masters as sm', 'sm.pid', '=', 'sale_master_process.pid')
                ->select('sm.pid', 'sm.customer_name')
                ->where('sale_master_process.closer1_id', $user->id)
                ->whereNull('sale_master_process.setter1_id')
                ->where('sm.action_item_status', 0)
                ->whereNotNull('sm.data_source_type')
                ->orderBy('sm.id', 'DESC')
                ->limit(3)
                ->get();

            // $override = UserOverrideHistory::where('user_id', $user->id)->where('old_direct_overrides_amount','!=',0)
            // ->orWhere('old_direct_overrides_type','!=',"")
            // ->orWhere('old_indirect_overrides_amount',0)
            // ->orWhere('old_indirect_overrides_type','!=',"")
            // ->orWhere( 'old_office_overrides_amount','!=',0)
            // ->orWhere('old_office_overrides_type','!=',"")
            // ->orWhere('old_office_stack_overrides_amount','!=',0)->orderBy('id','DESC')->limit(3)->get();

            // Use DashboardHistoryService to consolidate all history queries for manager
            $historyData = $this->dashboardHistoryService->getUserHistoryData($user);

            $override = $historyData['override'];
            $redline = $historyData['redline'];
            $commission = $historyData['commission'];
            $upfront = $historyData['upfront'];
            $withheld = $historyData['withheld'];
            $userOrganization = $historyData['user_organization'];
            $hiringAccepted = $historyData['hiring'];

        } else {

            $newRequests = ApprovalsAndRequest::select('id', 'req_no', 'user_id')
                ->where('user_id', $user->id)
                ->whereNotNull('req_no')
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $documentSignReview = Documents::with('categoryType:id,categories')
                ->select('id', 'user_id', 'document_response_status', 'action_item_status')
                ->where('user_id', $user->id)
                ->where('document_response_status', 0)
                ->where('action_item_status', 0)
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $taxdata = User::select('id', 'first_name', 'email')
                ->where('id', $user->id)
                ->where('social_sequrity_no', $socialSecurityNumber)
                ->where('action_item_status', 0)
                ->first();

            // Optimized join with covering indexes
            $missingData = SaleMasterProcess::join('sale_masters as sm', 'sm.pid', '=', 'sale_master_process.pid')
                ->select('sm.pid', 'sm.customer_name')
                ->where('sale_master_process.closer1_id', $user->id)
                ->whereNull('sale_master_process.setter1_id')
                ->where('sm.action_item_status', 0)
                ->whereNotNull('sm.data_source_type')
                ->orderBy('sm.id', 'DESC')
                ->limit(3)
                ->get();

            // Use DashboardHistoryService to consolidate all history queries for regular user
            $historyData = $this->dashboardHistoryService->getUserHistoryData($user);

            $override = $historyData['override'];
            $redline = $historyData['redline'];
            $commission = $historyData['commission'];
            $upfront = $historyData['upfront'];
            $withheld = $historyData['withheld'];
            $userOrganization = $historyData['user_organization'];
            $hiringAccepted = $historyData['hiring'];

        }

        if ($user->is_super_admin == 0 || $user->is_super_admin == 1) {

            if ($user->is_manager == 1 && count($newRequests) > 0) {
                $Count = $newRequests->count();
                $reqCount = isset($Count) ? $Count : 0;
                $newReqData = [];
                foreach ($newRequests as $newReq) {
                    $newReqData[] = [
                        'id' => $newReq->id,
                        'title' => 'You Got A New Request',
                        'status' => 'Reimbursmant For Annette Black',
                        'type' => 'New Request',
                        'item_type' => 'new_request',
                        'req_no' => $newReq->req_no,
                        'user_id' => $newReq->user_id,
                    ];
                }
            }

            if (count($documentSignReview) > 0) {
                $Count = $documentSignReview->count();
                $docCount = isset($Count) ? $Count : 0;
                $docData = [];
                foreach ($documentSignReview as $docSign) {
                    $docData[] = [
                        'id' => $docSign->id,
                        'title' => 'New Document',
                        // 'type' => isset($docSign->categoryType->categories)?$docSign->categoryType->categories:null,
                        'type' => 'New Document',
                        'status' => 'Document Sign And Review',
                        'item_type' => 'new_document',
                        'user_id' => $docSign->user_id,
                    ];
                }
            }

            if (count($hiringAccepted) > 0) {
                $Count = $hiringAccepted->count();
                $hiringCount = isset($Count) ? $Count : 0;
                $hiringData = [];
                foreach ($hiringAccepted as $hiring) {
                    $hiringData[] = [
                        'id' => $hiring->id,
                        'title' => 'Offer Letter has been Accepted',
                        'type' => 'Offer Letter Accepted',
                        'status' => 'Offer Letter Accepted',
                        'item_type' => 'hiring_document',
                        'user_id' => $hiring->id,
                    ];
                }
            }

            if ($taxdata) {
                $Count = $taxdata->count();
                $taxCount = isset($Count) ? $Count : 0;
                $taxInformation[] = [
                    'id' => $taxdata->id,
                    'title' => 'Tax Information',
                    'type' => 'Tax Information',
                    'status' => 'Social Sequrity Number Update',
                    'user_id' => $taxdata->id,
                ];
            }

            if ($user->position_id == 2) {
                if (count($missingData) > 0) {
                    $Count = $missingData->count();
                    $missingCount = isset($Count) ? $Count : 0;
                    $missingDataStatus = [];
                    foreach ($missingData as $missingDatas) {
                        $missingDataStatus[] = [
                            'id' => $missingDatas->pid,
                            'user_id' => $missingDatas->pid,
                            'title' => isset($missingDatas->customer_name) ? $missingDatas->customer_name : null,
                            'status' => 'Missing Setter',
                            'type' => 'Missing Setter',
                            'item_type' => 'missing_setter',
                            'pid' => $missingDatas->pid,
                        ];
                    }

                }
            }

            $countData = $reqCount + $docCount + $missingCount + $hiringCount;

            if (count($override) > 0) {
                $Count = $override->count();
                $overrideCount = isset($Count) ? $Count : 0;
                $overrideData = [];
                foreach ($override as $overrides) {
                    $overrideData[] = [
                        'id' => $overrides->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Overide Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $overrides->user_id,
                    ];
                }
            }

            if (count($redline) > 0) {
                $Count = $redline->count();
                $redlineCount = isset($Count) ? $Count : 0;
                $redlineStatus = [];
                foreach ($redline as $redlines) {
                    if ($redlines->redline_amount_type == 'Fixed') {
                        $rtype = 'Fixed';
                    } else {
                        $rtype = 'Location';
                    }

                    $redlineStatus[] = [
                        'id' => $redlines->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => $rtype.' Redline Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $redlines->user_id,
                    ];

                }
            }

            if (count($commission) > 0) {
                $Count = $commission->count();
                $commissionCount = isset($Count) ? $Count : 0;
                $commssionStatus = [];
                foreach ($commission as $commissions) {
                    if ($commissions->self_gen_user == '1') {
                        $ctype = 'Self Gen ';
                    } else {
                        $ctype = '';
                    }
                    $commssionStatus[] = [
                        'id' => $commissions->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => $ctype.'Commission Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $commissions->user_id,
                    ];
                }

            }

            if (count($upfront) > 0) {
                $Count = $upfront->count();
                $upfrontCount = isset($Count) ? $Count : 0;
                $upfrontStatus = [];
                foreach ($upfront as $upfronts) {
                    $upfrontStatus[] = [
                        'id' => $upfronts->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Upfront Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $upfronts->user_id,
                    ];
                }

            }

            if (count($withheld) > 0) {
                $Count = $withheld->count();
                $withheldCount = isset($Count) ? $Count : 0;
                $withheldStatus = [];
                foreach ($withheld as $withhelds) {
                    $withheldStatus[] = [
                        'id' => $withhelds->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Withheld Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $withhelds->user_id,
                    ];
                }
            }

            if (count($userOrganization) > 0) {
                $Count = $userOrganization->count();
                $userOrganizationCount = isset($Count) ? $Count : 0;
                $userOrganizationStatus = [];
                foreach ($userOrganization as $userOrganiz) {
                    $userOrganizationStatus[] = [
                        'id' => $userOrganiz->id,
                        'title' => 'Acknowledge Changes To Contract',
                        'type' => 'Position Changes',
                        'status' => 'Acknowledge',
                        'user_id' => $userOrganiz->user_id,
                    ];
                }
            }

            $acknowledge = [
                'override_data' => isset($overrideData) ? $overrideData : null,
                'redline_data' => isset($redlineStatus) ? $redlineStatus : null,
                'commssion_data' => isset($commssionStatus) ? $commssionStatus : null,
                'upfront_data' => isset($upfrontStatus) ? $upfrontStatus : null,
                'withheld_data' => isset($withheldStatus) ? $withheldStatus : null,
                'position_data' => isset($userOrganizationStatus) ? $userOrganizationStatus : null,
            ];

            $acknowledgeCount = $overrideCount + $redlineCount + $commissionCount + $upfrontCount + $withheldCount + $userOrganizationCount;
        }

        $data = [
            'new_request' => isset($newReqData) ? $newReqData : null,
            'document_sign_review' => isset($docData) ? $docData : null,
            'missing_data' => isset($missingDataStatus) ? $missingDataStatus : null,
            'tax_information' => isset($taxInformation) ? $taxInformation : null,
            'hiring_accepted' => isset($hiringData) ? $hiringData : null,
            'acknowledge_data' => isset($acknowledge) ? $acknowledge : null,
            'total' => $acknowledgeCount + $countData,

        ];

        return response()->json(['ApiName' => 'Alert Dashborad Data Api', 'status' => true, 'message' => 'Successfully', 'data' => $data], 200);
    }

    public function actionItemStatusChange(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required',
                'user_id' => 'required',
                'type' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if ($request->type == 'Overide Changes') {
            $update = UserOverrideHistory::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);

        } elseif ($request->type == 'Fixed Redline Changes' || $request->type == 'Location Redline Changes') {
            $update = UserRedlines::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Commission Changes' || $request->type == 'Self Gen Commission Changes') {
            $update = UserCommissionHistory::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Upfront Changes') {
            $update = UserUpfrontHistory::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Withheld Changes') {
            $update = UserWithheldHistory::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Position Change') {
            $update = UserOrganizationHistory::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Offer Letter Accepted') {
            $update = OnboardingEmployees::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Tax Information') {
            $update = User::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'Missing Setter') {
            $update = SalesMaster::where('pid', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'New Request') {
            $update = ApprovalsAndRequest::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        } elseif ($request->type == 'New Document') {
            $update = Documents::where('id', $request->id)->where('action_item_status', 0)->update(['action_item_status' => 1]);
        }

        return response()->json(['ApiName' => 'action_item_status_change', 'status' => true, 'message' => 'Successfully Updated'], 200);
        // if ($update) {
        //     return response()->json(['ApiName'=>'action_item_status_change', 'status' => true,'message'=>'Successfully Updated'], 200);
        // }else {
        //     return response()->json(['ApiName'=>'action_item_status_change', 'status' => false,'message'=>'failed'], 400);
        // }

    }

    // public function addListAnnouncementsOld(Request $request)
    // {
    //    $id = $request->id;
    //    $userId = auth::user()->id;
    //    $file = Announcement::where('id',$id)->first();
    //    $image = $request->file('file');
    //     if (isset($image) && count($image)>=1 && $image != null && $image!='') {
    //         foreach ($image as $files){
    //             $file = $files;
    //             //s3 bucket
    //                 $img_path =  time().$file->getClientOriginalName();
    //                 $awsPath = config('app.domain_name').'/'.'announcement/'.$img_path;
    //                 s3_upload($awsPath,file_get_contents($file),false);
    //                 $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.'announcement/'.$img_path);
    //         //s3 bucket end
    //             $image_path = time() . $file->getClientOriginalName();
    //             $ex = $file->getClientOriginalExtension();
    //             $destinationPath = 'announcement';
    //             $image_path1 = $file->move($destinationPath, $image_path );
    //             $imagePath = "announcement/".$image_path;
    //         }
    //     }

    //     if($id){
    //        $data = [
    //             'user_id'     => $userId,
    //             'title'       =>isset($request['title']) ? $request['title']:null,
    //             'description' =>isset($request['description']) ? $request['description']:null,
    //             'positions'   =>isset($request['positions']) ? $request['positions']:null,
    //             'office'      =>isset($request['office']) ? $request['office']:null,
    //             'link'        =>isset($request['link']) ? $request['link']:null,
    //             'start_date'  =>isset($request['start_date']) ? $request['start_date']:null,
    //             'durations'   =>isset($request['durations']) ? $request['durations']:0,
    //             'pin_to_top'  =>isset($request['pin_to_top']) ? $request['pin_to_top']:null,
    //             'file'        =>isset($imagePath)?$imagePath:$file->file,

    //         ];
    //     }else{
    //         $data = [
    //                 'user_id'     => $userId,
    //                 'title'       =>isset($request['title']) ? $request['title']:null,
    //                 'description' =>isset($request['description']) ? $request['description']:null,
    //                 'positions'   =>isset($request['positions']) ? $request['positions']:null,
    //                 'office'      =>isset($request['office']) ? $request['office']:null,
    //                 'link'        =>isset($request['link']) ? $request['link']:null,
    //                 'start_date'  =>isset($request['start_date']) ? $request['start_date']:null,
    //                 'durations'   =>isset($request['durations']) ? $request['durations']:0,
    //                 'pin_to_top'  =>isset($request['pin_to_top']) ? $request['pin_to_top']:null,
    //                 'file'        =>isset($imagePath)?$imagePath:null,

    //         ];
    //     }

    //    $announcement = Announcement::where('id',$id)->first();

    //    if($request['pin_to_top']==1){
    //     $pinToTop = Announcement::where('pin_to_top',1)->count();

    //     if(empty($announcement->id) && $pinToTop != 3)
    //     {

    //         $announcement = Announcement::Create($data);
    //         $duration = Announcement::where('id',$announcement->id)->first();
    //         $startDate = $duration->start_date;
    //         $durations = '';
    //         if($duration->durations =='1 day'){
    //             $durations = 1;
    //         }
    //         if($duration->durations =='1 week'){
    //             $durations = 7;
    //         }
    //         if($duration->durations =='2 week'){
    //             $durations = 14;
    //         }
    //         if($duration->durations =='3 week'){
    //             $durations = 21;
    //         }
    //         if($duration->durations =='1 month'){
    //             $durations = 30;
    //         }
    //         $endDate = date('Y-m-d', strtotime($startDate. ' + '.$durations.' days'));
    //         $announcement = Announcement::where('id',$announcement->id)->update(['end_date'=>$endDate]);
    //     }else
    //     if(!empty($announcement->id) && $pinToTop <= 3){
    //             if($request['pin_to_top'] != 0 && $pinToTop != 3){
    //               $pinToUpdate = Announcement::where('id',$announcement->id)->update(['pin_to_top'=>$request['pin_to_top']]);
    //             }else{
    //                 return response()->json([
    //                     'ApiName' => 'Add List Announcements',
    //                     'status' => false,
    //                     'message' => 'you can not do more than pin 3',
    //                 ], 400);
    //             }

    //             $announcement = Announcement::where('id',$announcement->id)->update($data);
    //             $duration = Announcement::where('id',$id)->first();
    //             $startDate = $duration->start_date;
    //             $durations = '';
    //             if($duration->durations =='1 day'){
    //                 $durations = 1;
    //             }
    //             if($duration->durations =='1 week'){
    //                 $durations = 7;
    //             }
    //             if($duration->durations =='2 week'){
    //                 $durations = 14;
    //             }
    //             if($duration->durations =='3 week'){
    //                 $durations = 21;
    //             }
    //             if($duration->durations =='1 month'){
    //                 $durations = 30;
    //             }

    //             $endDate = date('Y-m-d', strtotime($startDate. ' + '.$durations.' days'));
    //             $announcement = Announcement::where('id',$id)->update(['end_date'=>$endDate]);
    //     }else{
    //         return response()->json([
    //             'ApiName' => 'Add List Announcements',
    //             'status' => false,
    //             'message' => 'you can not do more than pin 3',
    //         ], 400);
    //     }
    //    }else{
    //     if(empty($announcement->id))
    //     {
    //         $announcement = Announcement::Create($data);
    //         $duration = Announcement::where('id',$announcement->id)->first();
    //         $startDate = $duration->start_date;
    //         $durations = '';
    //         if($duration->durations =='1 day'){
    //             $durations = 1;
    //         }
    //         if($duration->durations =='1 week'){
    //             $durations = 7;
    //         }
    //         if($duration->durations =='2 week'){
    //             $durations = 14;
    //         }
    //         if($duration->durations =='3 week'){
    //             $durations = 21;
    //         }
    //         if($duration->durations =='1 month'){
    //             $durations = 30;
    //         }
    //         $endDate = date('Y-m-d', strtotime($startDate. ' + '.$durations.' days'));
    //         $announcement = Announcement::where('id',$announcement->id)->update(['end_date'=>$endDate]);
    //     }else{
    //         $announcement = Announcement::where('id',$announcement->id)->update($data);
    //         $duration = Announcement::where('id',$id)->first();
    //         $startDate = $duration->start_date;
    //         $durations = '';
    //         if($duration->durations =='1 day'){
    //             $durations = 1;
    //         }
    //         if($duration->durations =='1 week'){
    //             $durations = 7;
    //         }
    //         if($duration->durations =='2 week'){
    //             $durations = 14;
    //         }
    //         if($duration->durations =='3 week'){
    //             $durations = 21;
    //         }
    //         if($duration->durations =='1 month'){
    //             $durations = 30;
    //         }

    //         $endDate = date('Y-m-d', strtotime($startDate. ' + '.$durations.' days'));
    //         $announcement = Announcement::where('id',$id)->update(['end_date'=>$endDate]);
    //     }
    //    }
    //    foreach($data as $key => $d){
    //     if(isset($data['file']) && $data['file']!=null){
    //         $data['file_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$data['file']);
    //     }else{
    //         $data['file_s3'] = null;
    //     }
    //  }
    //    return response()->json([
    //        'ApiName' => 'Add List Announcements',
    //        'status' => true,
    //        'message' => 'Successfully.',
    //        'data' => $data,
    //    ], 200);

    // }

    public function addListAnnouncements(Request $request): JsonResponse
    {
        $id = $request->id;
        $userId = auth::user()->id;
        $file = Announcement::where('id', $id)->first();
        $image = $request->file('file');
        if (isset($image) && count($image) >= 1 && $image != null && $image != '') {
            foreach ($image as $files) {
                $file = $files;
                // s3 bucket
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'announcement/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false);
                $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.'announcement/'.$img_path);
                // s3 bucket end
                $image_path = time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'announcement';
                $image_path1 = $file->move($destinationPath, $img_path);
                $imagePath = 'announcement/'.$img_path;
            }
        }

        if ($id) {
            $file = isset($imagePath) ? $imagePath : $file->file;
        } else {
            $file = isset($imagePath) ? $imagePath : null;
        }

        $data = [
            'user_id' => $userId,
            'title' => isset($request['title']) ? $request['title'] : null,
            'description' => isset($request['description']) ? $request['description'] : null,
            'positions' => isset($request['positions']) ? $request['positions'] : null,
            'office' => isset($request['office']) ? $request['office'] : null,
            'link' => isset($request['link']) ? $request['link'] : null,
            'start_date' => isset($request['start_date']) ? $request['start_date'] : null,
            'durations' => isset($request['durations']) ? $request['durations'] : 0,
            // 'pin_to_top'  =>isset($request['pin_to_top']) ? $request['pin_to_top']:null,
            'file' => $file,
        ];

        $announcement = Announcement::where('id', $id)->first();

        if (empty($announcement->id)) {
            $pinToTop = Announcement::where('pin_to_top', 1)->count();

            if ($pinToTop == 3 && $request['pin_to_top'] == 1) {
                return response()->json([
                    'ApiName' => 'Add List Announcements',
                    'status' => false,
                    'message' => 'you can not do more than 3 pin ',
                ], 400);
            }

            $announcement = Announcement::Create($data);

            $duration = Announcement::where('id', $announcement->id)->first();
            $startDate = $duration->start_date;
            $durations = '';
            if ($duration->durations == '1 day') {
                $durations = 0;
            }
            if ($duration->durations == '1 week') {
                $durations = 6;
            }
            if ($duration->durations == '2 week') {
                $durations = 13;
            }
            if ($duration->durations == '3 week') {
                $durations = 20;
            }
            if ($duration->durations == '1 month') {
                $durations = 29;
            }
            $endDate = date('Y-m-d', strtotime($startDate.' + '.$durations.' days'));
            $announcement = Announcement::where('id', $announcement->id)->update(['end_date' => $endDate]);
            foreach ($data as $key => $d) {
                if (isset($data['file']) && $data['file'] != null) {
                    $data['file_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$data['file']);
                } else {
                    $data['file_s3'] = null;
                }
            }

            return response()->json([
                'ApiName' => 'Add List Announcements',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {

            $pinToTop = Announcement::where('pin_to_top', 1)->count();
            if ($pinToTop == 3 && $request['pin_to_top'] == 1) {
                return response()->json([
                    'ApiName' => 'Add List Announcements',
                    'status' => false,
                    'message' => 'you can not do more than 3 pin ',
                ], 400);
            }

            if ($request['pin_to_top'] != 0 && $pinToTop != 3) {
                $pinToUpdate = Announcement::where('id', $announcement->id)->update(['pin_to_top' => $request['pin_to_top']]);
            }

            if ($request['pin_to_top'] != 1 && $pinToTop <= 3) {
                $pinToUpdate = Announcement::where('id', $announcement->id)->update(['pin_to_top' => $request['pin_to_top']]);
            }

            $data = Announcement::where('id', $id)->first();
            $data->user_id = $userId;
            $data->title = isset($request['title']) ? $request['title'] : null;
            $data->description = isset($request['description']) ? $request['description'] : null;
            $data->positions = isset($request['positions']) ? $request['positions'] : null;
            $data->office = isset($request['office']) ? $request['office'] : null;
            $data->link = isset($request['link']) ? $request['link'] : null;
            $data->start_date = isset($request['start_date']) ? $request['start_date'] : null;
            $data->durations = isset($request['durations']) ? $request['durations'] : null;
            $data->file = $file;
            $data->save();

            $duration = Announcement::where('id', $id)->first();
            $startDate = $duration->start_date;
            $durations = '';
            if ($duration->durations == '1 day') {
                $durations = 0;
            }
            if ($duration->durations == '1 week') {
                $durations = 6;
            }
            if ($duration->durations == '2 week') {
                $durations = 13;
            }
            if ($duration->durations == '3 week') {
                $durations = 20;
            }
            if ($duration->durations == '1 month') {
                $durations = 29;
            }

            $endDate = date('Y-m-d', strtotime($startDate.' + '.$durations.' days'));
            $announcement = Announcement::where('id', $id)->update(['end_date' => $endDate]);
            foreach ($data as $key => $d) {
                if (isset($data['file']) && $data['file'] != null) {
                    $data['file_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$data['file']);
                } else {
                    $data['file_s3'] = null;
                }
            }

            return response()->json([
                'ApiName' => 'Update List Announcements',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

    }

    public function getStandardAnnouncementCard(Request $request)
    {

        // $announcement = Announcement::select('title','description')->where('user_id',$user->id)->where('pin_to_top',1)->orderBy('id','desc')->limit(8)->get();
        $userId = auth::user();

        if ($userId->is_super_admin == 1) {
            $data = Announcement::orderBy('id', 'desc')->get();
        } elseif ($userId->is_manager == 1) {
            $data = Announcement::where('positions', 'LIKE', '%'.$userId->sub_position_id.'%')->where('office', 'LIKE', '%'.$userId->office_id.'%')->orderBy('id', 'desc')->get();
        } else {
            $data = Announcement::where('positions', 'LIKE', '%'.$userId->sub_position_id.'%')->where('office', 'LIKE', '%'.$userId->office_id.'%')->where('disable', 0)->where('end_date', '>=', date('Y-m-d'))->orderBy('id', 'desc')->get();
        }
        $data->transform(function ($announcement) {
            $officeId = $announcement->office;
            $officeExpload = explode(',', $officeId);
            $office = Locations::select('id', 'office_name')->whereIn('id', $officeExpload)->get();
            $officeName = [];
            foreach ($office as $value) {
                $officeName[] = $value['office_name'];
            }

            $positionId = $announcement->positions;
            $positionExpload = explode(',', $positionId);
            $position = Positions::select('id', 'position_name')->whereIn('id', $positionExpload)->get();
            $positionName = [];
            foreach ($position as $value) {
                $positionName['id'] = $value['id'];
                $positionName['position_name'] = $value['position_name'];
            }
            $startDate = $announcement->start_date;
            $endDate = $announcement->end_date;

            $status = null;
            if (date('Y-m-d') < $startDate) {
                $status = 'Upcoming';
            }
            // if(date('Y-m-d', strtotime($announcement->durations, strtotime($startDate))) < date('Y-m-d')){
            //     $status = 'Expired';
            // }
            // if( (date('Y-m-d') >= $startDate) && ( date('Y-m-d') < date('Y-m-d', strtotime($announcement->durations, strtotime($startDate))) ) ){
            //     $status = 'Live';
            // }

            if (date('Y-m-d') > $endDate) {
                $status = 'Expired';
            }

            if (date('Y-m-d') <= $endDate && date('Y-m-d') >= $startDate) {
                $status = 'Live';
            }

            if ($announcement->disable == 1) {
                $status = 'Disabled';
            }

            return [
                'id' => isset($announcement->id) ? $announcement->id : null,
                'user_id' => isset($announcement->user_id) ? $announcement->user_id : null,
                'title' => isset($announcement->title) ? $announcement->title : null,
                'description' => isset($announcement->description) ? $announcement->description : null,
                'pin_to_top' => isset($announcement->pin_to_top) ? $announcement->pin_to_top : null,
                'status' => $status,
                'positions' => $position,
                'office' => $office,
                'link' => isset($announcement->link) ? $announcement->link : null,
                'start_date' => isset($announcement->start_date) ? $announcement->start_date : null,
                'end_date' => isset($announcement->end_date) ? $announcement->end_date : null,
                'durations' => isset($announcement->durations) ? $announcement->durations : null,
                'file' => isset($announcement->file) ? $announcement->file : null,
                'disable' => isset($announcement->disable) ? $announcement->disable : null,
            ];
        });

        return response()->json([
            'ApiName' => 'get Card List Announcements',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getListAnnouncement(Request $request)
    {
        $announcement = '';
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $user = auth::user();
        if ($user->is_super_admin == 1) {
            $announcement = Announcement::orderBy('pin_to_top', 'desc');
        } elseif ($user->is_manager == 1) {
            $announcement = Announcement::where('positions', 'LIKE', '%'.$user->sub_position_id.'%')->where('office', 'LIKE', '%'.$user->office_id.'%')->orderBy('pin_to_top', 'desc');
        } else {
            $announcement = Announcement::where('positions', 'LIKE', '%'.$user->sub_position_id.'%')->where('office', 'LIKE', '%'.$user->office_id.'%')->where('disable', 0)->where('end_date', '>=', date('Y-m-d'))->orderBy('pin_to_top', 'desc');
        }

        $search = $request->input('search');
        // $office = $request->input('office_filter');
        $office = $request->input('office');
        $positionFilter = $request->input('position_filter');
        // $statusFilter = $request->input('status_filter');
        $statusFilter = $request->input('status');

        if ($statusFilter == 'Upcoming') {
            $statusFilter = date('Y-m-d');
            // if ($request->has('status_filter') && !empty($statusFilter)) {
            if ($request->has('status') && ! empty($statusFilter)) {
                $announcement->where(function ($query) use ($statusFilter) {
                    return $query->where('start_date', '>', $statusFilter)->whereNot('disable', 1);
                });
            }
        } elseif ($statusFilter == 'Live') {

            $statusFilter = date('Y-m-d');
            // if ($request->has('status_filter') && !empty($statusFilter)) {
            if ($request->has('status') && ! empty($statusFilter)) {
                $announcement->where(function ($query) use ($statusFilter) {
                    return $query->where('end_date', '>=', $statusFilter)->whereNot('disable', 1)->whereNot('start_date', '>', $statusFilter);
                });
            }
        } elseif ($statusFilter == 'Expired') {
            $statusFilter = date('Y-m-d');
            // if ($request->has('status_filter') && !empty($statusFilter)) {
            if ($request->has('status') && ! empty($statusFilter)) {
                $announcement->where(function ($query) use ($statusFilter) {
                    return $query->where('end_date', '<', $statusFilter)->where('disable', '!=', 1);
                });

            }
        } elseif ($statusFilter == 'disabled') {
            // if ($request->has('status_filter') && !empty($statusFilter)) {
            if ($request->has('status') && ! empty($statusFilter)) {
                $announcement->where(function ($query) {
                    return $query->where('disable', 1);
                });
            }
        }
        if ($request->has('search') && ! empty($search)) {
            $announcement->where(function ($query) use ($search) {
                return $query->where('title', 'LIKE', '%'.$search.'%');
            });
        }
        // if ($request->has('office_filter') && !empty($office)) {
        if ($request->has('office') && ! empty($office) && $office != 'all') {
            $announcement->where(function ($query) use ($office) {
                return $query->where('office', 'LIKE', '%'.$office.'%');
            });
        }
        if ($request->has('position_filter') && ! empty($positionFilter) && $positionFilter != 'all') {
            $announcement->where(function ($query) use ($positionFilter) {
                return $query->where('positions', 'LIKE', '%'.$positionFilter.'%');
            });
        }

        $announcement->orderBy('id', 'desc');
        $data = $announcement->paginate($perpage);

        $data->transform(function ($announcement) {
            $officeId = $announcement->office;
            $officeExpload = explode(',', $officeId);
            $office = Locations::select('id', 'office_name')->whereIn('id', $officeExpload)->get();
            $officeName = [];
            foreach ($office as $value) {
                $officeName[] = $value['office_name'];
            }
            $positionId = $announcement->positions;
            $positionExpload = explode(',', $positionId);
            $position = Positions::select('id', 'position_name')->whereIn('id', $positionExpload)->get();
            $positionName = [];
            foreach ($position as $value) {
                $positionName['id'] = $value['id'];
                $positionName['position_name'] = $value['position_name'];
            }
            $startDate = $announcement->start_date;
            $endDate = $announcement->end_date;

            if (date('Y-m-d') < $startDate) {
                $status = 'Upcoming';
            }

            // if(date('Y-m-d', strtotime($announcement->durations, strtotime($startDate))) < date('Y-m-d')){
            //     $status = 'Expired';
            // }
            // if( (date('Y-m-d') >= $startDate) && ( date('Y-m-d') <= date('Y-m-d', strtotime($announcement->durations, strtotime($startDate))) ) ){
            //     $status = 'Live';
            // }

            if (date('Y-m-d') > $endDate) {
                $status = 'Expired';
            }

            if (date('Y-m-d') <= $endDate && date('Y-m-d') >= $startDate) {
                $status = 'Live';
            }

            if ($announcement->disable == 1) {
                $status = 'Disabled';
            }

            return [
                'id' => isset($announcement->id) ? $announcement->id : null,
                'user_id' => isset($announcement->user_id) ? $announcement->user_id : null,
                'title' => isset($announcement->title) ? $announcement->title : null,
                'description' => isset($announcement->description) ? $announcement->description : null,
                // 'position_id'=>$positionId,
                // 'office_id'=> $officeId,
                'positions' => $position,
                'office' => $office,
                'link' => isset($announcement->link) ? $announcement->link : null,
                'start_date' => isset($announcement->start_date) ? $announcement->start_date : null,
                'end_date' => isset($announcement->end_date) ? $announcement->end_date : null,
                'durations' => isset($announcement->durations) ? $announcement->durations : null,
                'file' => isset($announcement->file) ? $announcement->file : null,
                'status' => isset($status) ? $status : null,
                'pin_to_top' => $announcement->pin_to_top,
                'disable' => isset($announcement->disable) ? $announcement->disable : null,

            ];
        });

        return response()->json([
            'ApiName' => 'get List Announcements',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function deleteListAnnouncement(Request $request): JsonResponse
    {

        $id = $request->id;

        if ($id) {
            $data = Announcement::find($id)->delete();

            return response()->json([
                'ApiName' => 'delete List Announcements',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'delete List Announcements',
                'status' => false,
                'message' => 'list not found.',
            ], 200);

        }

    }

    /**
     * Method getPayrollSummary
     *
     * @param  Request  $request  [explicite description]
     * @param  Payroll  $payroll  [explicite description]
     * @param  UserCommission  $userCommission  [explicite description]
     */
    public function getPayrollSummary(Request $request): JsonResponse
    {
        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            $filterDate = $this->getFilterDate($filterDataDateWise);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } else {
                return response()->json([
                    'ApiName' => 'Admin Payroll Summary',
                    'status' => false,
                    'message' => 'Invalid filter provided. Please use a valid filter option.',
                    'data' => [],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'Admin Payroll Summary',
                'status' => false,
                'message' => 'Admin payroll summary filter not selected please select filter',
                'data' => [],
            ], 400);
        }
        $payrollHistoryData = PayrollHistory::whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate])
            ->get();
        $payrollData = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate])
            ->get();

        $commission = $payrollHistoryData->sum('commission') + $payrollData->sum('commission');
        $override = $payrollHistoryData->sum('override') + $payrollData->sum('override');
        $adjustment = $payrollHistoryData->sum('adjustment') + $payrollData->sum('adjustment');
        $reimbursement = $payrollHistoryData->sum('reimbursement') + $payrollData->sum('reimbursement');
        $deduction = $payrollHistoryData->sum('deduction') + $payrollData->sum('deduction');
        $clawback = $payrollHistoryData->sum('clawback') + $payrollData->sum('clawback');
        $totalNetPay = $commission + $override + $adjustment + $reimbursement + $deduction + $clawback;

        $getPayrollData = $this->getPayrollData->whereBetween('pay_period_to', [$startDate, $endDate]);
        $payroll_status = round($getPayrollData->where('status', 1)->orWhere('status', 2)->count('id'), 2);

        if ($payroll_status > 0) {
            $payroll_execute_status = false;
        } else {
            $payroll_execute_status = true;
        }

        $data = [
            'commission' => round($commission, 2),
            'override' => round($override, 2),
            'adjustment' => round($adjustment, 2),
            'reimbursement' => round($reimbursement, 2),
            'deduction' => round($deduction, 2),
            'clawback' => round($clawback, 2),
            'totalPayroll' => round($totalNetPay, 2),
        ];

        $latesFailedPayroll = PayrollHistory::where('everee_payment_status', 2)->where('pay_type', 'Bank')->orderBy('id', 'DESC');
        $payrollFailedEvereeCount = $latesFailedPayroll->count();
        $payrollFailedEvereeData = $latesFailedPayroll->first();

        $is_payroll_failed = false;
        $pay_period_start = $pay_period_end = null;
        $payment_failed_count = 0;
        $frequencyTypeId = 0;
        $frequencyTypeName = 0;
        $payrollCount = 0;
        if ($payrollFailedEvereeCount > 0) {
            $is_payroll_failed = true;
            $pay_period_start = $payrollFailedEvereeData->pay_period_from ?? null;
            $pay_period_end = $payrollFailedEvereeData->pay_period_to ?? null;
            $payment_failed_count = $payrollFailedEvereeCount ?? 0;

            $checkFrequencey = WeeklyPayFrequency::where('pay_period_from', $pay_period_start)->where('pay_period_to', $pay_period_end)->where('open_status_from_bank', 1)->first();

            if ($checkFrequencey) {

                $PayrollDATA = PayrollHistory::where('everee_payment_status', 2)->where(['pay_type' => 'Bank', 'pay_period_from' => $payrollFailedEvereeData->pay_period_from, 'pay_period_to' => $payrollFailedEvereeData->pay_period_to])->orderBy('id', 'DESC');
                $payrollCount = $PayrollDATA->count();
                $frequencyTypeId = $checkFrequencey != null ? 2 : 0;

            } elseif ($checkFrequencey == null) {

                $checkFrequenceyMonthlyPayFrequency = MonthlyPayFrequency::where('pay_period_from', $pay_period_start)->where('pay_period_to', $pay_period_end)->first();
                $frequencyTypeId = $checkFrequenceyMonthlyPayFrequency != null ? 5 : 0;
                $PayrollDATA = PayrollHistory::where('everee_payment_status', 2)->where(['pay_type' => 'Bank', 'pay_period_from' => $pay_period_start, 'pay_period_to' => $pay_period_end])->orderBy('id', 'DESC');
                $payrollCount = $PayrollDATA->count();

            } elseif ($checkFrequenceyMonthlyPayFrequency == null) {

                $checkFrequencey = AdditionalPayFrequency::where('pay_period_from', $pay_period_start)->where('pay_period_to', $pay_period_end)->where('type', '1')->first();
                $frequencyTypeId = $checkFrequencey != null ? 3 : 0;
                $PayrollDATA = PayrollHistory::where('everee_payment_status', 2)->where(['pay_type' => 'Bank', 'pay_period_from' => $pay_period_start, 'pay_period_to' => $pay_period_end])->orderBy('id', 'DESC');
                $payrollCount = $PayrollDATA->count();

                if ($checkFrequencey == null) {

                    $checkFrequencey = AdditionalPayFrequency::where('pay_period_from', $pay_period_start)->where('pay_period_to', $pay_period_end)->where('type', '2')->first();
                    $frequencyTypeId = $checkFrequencey != null ? 4 : 0;
                    $PayrollDATA = PayrollHistory::where('everee_payment_status', 2)->where(['pay_type' => 'Bank', 'pay_period_from' => $pay_period_start, 'pay_period_to' => $pay_period_end])->orderBy('id', 'DESC');
                    $payrollCount = $PayrollDATA->count();
                }
            } else {
                $checkFrequencey != null ? 4 : 0;
                $payrollCount = 0;
            }

            $frequencyType = FrequencyType::find($frequencyTypeId);

            $payroll_failed_data = [
                'is_payroll_failed' => $is_payroll_failed,
                'pay_period_start' => $pay_period_start,
                'pay_period_end' => $pay_period_end,
                'payment_failed_count' => $payrollCount,
                'frequency_type_id' => isset($frequencyType->id) ? $frequencyType->id : 0,
                'frequency_type_name' => isset($frequencyType->name) ? $frequencyType->name : '',
            ];
        } else {

            $payroll_failed_data = [
                'is_payroll_failed' => $is_payroll_failed,
                'pay_period_start' => $pay_period_start,
                'pay_period_end' => $pay_period_end,
                'payment_failed_count' => $payrollCount,
                'frequency_type_id' => 0,
                'frequency_type_name' => '',
            ];
        }

        return response()->json([
            'ApiName' => 'get Admin Payroll Summary',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'payroll_failed_data' => $payroll_failed_data,
            'payroll_execute_status' => $payroll_execute_status,
        ], 200);

    }

    /**
     * Method getFilterDate : This funnction return date as per filter name
     *
     * @param  $filterName  $filterName [explicite description]
     * @return array
     */
    public function getFilterDate($filterName)
    {
        $startDate = '';
        $endDate = '';
        if ($filterName == 'this_week') {
            $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterName == 'last_week') {
            $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
        } elseif ($filterName == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterName == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));
        } elseif ($filterName == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterName == 'last_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
        } elseif ($filterName == 'this_year') {
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
        } elseif ($filterName == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterName == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    /**
     * Method getFilterLastDate: get date previous dates as per given filter name
     *
     * @param  $filterName  $filterName [explicite description]
     * @return void
     */
    public function getFilterLastDate($filterName)
    {
        $startDate = '';
        $endDate = '';
        if ($filterName == 'this_week') {
            $startDate = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');
        } elseif ($filterName == 'this_year') {
            $startDate = Carbon::now()->subYear()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->subYear()->endOfYear()->format('Y-m-d');
        } elseif ($filterName == 'this_month') {
            $startDate = new Carbon('first day of last month');
            $endDate = new Carbon('last day of last month');

            $startDate = $startDate->format('Y-m-d');
            $endDate = $endDate->format('Y-m-d');
        } elseif ($filterName == 'this_quarter') {
            $currentDate = Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d');

            $startDate = Carbon::parse($currentDate)->subMonths(3)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()->format('Y-m-d');

        } elseif ($filterName == 'last_12_months') {
            $currentDate = Carbon::now()->subYear();
            $startDate = Carbon::parse($currentDate)->subYear()->format('Y-m-d');
            $endDate = Carbon::parse($currentDate)->format('Y-m-d');
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    public function getPayrollSummaryOld(Request $request, Payroll $Payroll, UserCommission $userCommission): JsonResponse
    {

        $filter = $request->filter;
        $Payroll->newQuery();
        $userCommition = $userCommission->newQuery();
        $this->userOverrides->newQuery();
        $this->getPayrollData->newQuery();
        $this->clawbackSettlement->newQuery();
        $this->approvalsAndRequest->newQuery();

        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_month') {

                // $startOfMonth = \Carbon\Carbon::now()->subDays(0)->startOfMonth();
                // $endOfMonth = \Carbon\Carbon::now()->endOfMonth();
                // $startDate =  date('Y-m-d', strtotime($startOfMonth));
                // $endDate =  date('Y-m-d', strtotime($endOfMonth));

                $new = Carbon::now(); // returns current day
                $firstDay = $new->firstOfMonth();
                $startDate = date('Y-m-d', strtotime($firstDay));
                $end = Carbon::now();
                $endDate = date('Y-m-d', strtotime($end));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));

                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_quarter') {
                // $currentMonthDay = \Carbon\Carbon::now()->daysInMonth+Carbon::now()->month(01)->daysInMonth+Carbon::now(03)->month()->daysInMonth;
                // $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                // $endDate =  date('Y-m-d');

                $currentDate = Carbon::now();
                $startDate = date('Y-m-d', strtotime($currentDate->startOfQuarter()));
                // $endDate = date('Y-m-d', strtotime($currentDate->endOfQuarter()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = \Carbon\Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_year') {
                // $startDate = date('Y-m-d',strtotime(Carbon::now()->subYears(0)->startOfYear()));
                // $endDate =   date('Y-m-d',strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $now = Carbon::now();
                $monthStart = $now->startOfYear();
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $Payroll->whereBetween('pay_period_to', [$startDate, $endDate]);
            }
        }

        $userCommition->whereBetween('pay_period_to', [$startDate, $endDate])->where('status', 3);
        $overrides = $this->userOverrides->whereBetween('pay_period_to', [$startDate, $endDate])->where('status', 3);
        $getPayrollData = $this->getPayrollData->whereBetween('pay_period_to', [$startDate, $endDate]);
        $clawback = $this->clawbackSettlement->whereBetween('pay_period_to', [$startDate, $endDate])->where('status', 3);
        $adjustment = $this->approvalsAndRequest->whereBetween('pay_period_to', [$startDate, $endDate])->where('adjustment_type_id', '!=', 2)->where('status', 'Accept');
        $reimbursement = $this->approvalsAndRequest->whereBetween('pay_period_to', [$startDate, $endDate])->where('adjustment_type_id', 2)->where('status', 'Accept');
        $data = [];
        // $Payroll->sum('commission');
        $data['commission'] = round($userCommition->sum('amount'), 2);
        $data['override'] = round($overrides->sum('amount'), 2);
        $data['adjustment'] = round($adjustment->sum('amount'), 2);
        $data['reimbursement'] = round($reimbursement->sum('amount'), 2);
        $data['deduction'] = round($getPayrollData->sum('deduction'), 2);
        $data['clawback'] = round($clawback->sum('clawback_amount'), 2);
        $payroll_status = round($getPayrollData->where('status', 1)->orWhere('status', 2)->count('id'), 2);

        if ($payroll_status > 0) {
            $payroll_execute_status = false;
        } else {
            $payroll_execute_status = true;
        }

        $totalPayroll = $data['commission'] + $data['override'] + $data['adjustment'] + $data['reimbursement'] + $data['deduction'] + $data['clawback'];
        $data['totalPayroll'] = round($totalPayroll, 2);

        return response()->json([
            'ApiName' => 'get Admin Payroll Summary',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'payroll_execute_status' => $payroll_execute_status,
        ], 200);

    }

    public function dashboardSalesReport(DashboardFilterRequest $request): JsonResponse
    {
        $user = auth()->user();
        $userId = $user->id;
        $isSuperAdmin = $user->is_super_admin == 1;

        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Get Sales Report API',
                    'status' => false,
                    'message' => 'Invalid filter parameters.',
                    'totalSales' => 0.00,
                    'totalKw' => 0.00,
                    'totalEarning' => 0.00,
                ], 400);
            }

            $companyProfile = CompanyProfile::first();

            if (! $companyProfile) {
                return response()->json([
                    'ApiName' => 'Get Sales Report API',
                    'status' => false,
                    'message' => 'Company profile not found.',
                ], 500);
            }

            if ($isSuperAdmin) {
                // ADMIN LOGIC: Show aggregated data for all users
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->count();

                // Calculate total KW/value for all sales based on company type
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                        ->sum('gross_account_value');
                } else {
                    $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                        ->sum('kw');
                }

                // Calculate total earnings for all users from SaleMasterProcess
                $salesPidsInRange = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->pluck('pid')
                    ->toArray();

                if (! empty($salesPidsInRange)) {
                    // Calculate total earnings from UserCommission for sales in date range
                    $totalEarning = UserCommission::whereIn('pid', $salesPidsInRange)
                        ->where('is_displayed', '1')
                        ->whereIn('status', [3]) // Active and paid commissions
                        ->sum('amount') ?? 0;

                    // If commission amounts are 0, calculate estimated earnings as percentage of gross account value within date range
                    if ($totalEarning == 0) {
                        $totalGrossValue = SalesMaster::whereIn('pid', $salesPidsInRange)
                            ->sum('gross_account_value');
                        $totalEarning = $totalGrossValue * 0.10;
                    }
                } else {
                    // If no sales in date range, get all earnings from UserCommission
                    $totalEarning = UserCommission::where('is_displayed', '1')
                        ->whereIn('status', [3]) // Active and paid commissions
                        ->sum('amount') ?? 0;

                    // If commission amounts are 0, calculate estimated earnings as percentage of total gross account value
                    // Note: This is all-time fallback - should be avoided, keeping period-only metrics
                    if ($totalEarning == 0) {
                        // Skip all-time fallback to maintain period-only metrics
                        $totalEarning = 0;
                    }
                }

            } else {
                // REGULAR USER LOGIC: Show only user's data
                // Get sales PIDs from UserCommission for this user within date range
                $userCommissionPids = UserCommission::where('user_id', $userId)
                    ->where('is_displayed', '1')
                    ->pluck('pid')->toArray();

                $salesPids = SalesMaster::whereIn('pid', $userCommissionPids)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->pluck('pid')->toArray();

                $totalSales = SalesMaster::whereIn('pid', $salesPids)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();

                // Calculate KW/value for user's sales based on company type
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $totalKw = SalesMaster::whereIn('pid', $salesPids)
                        ->whereBetween('customer_signoff', [$startDate, $endDate])
                        ->sum('gross_account_value');
                } else {
                    $totalKw = SalesMaster::whereIn('pid', $salesPids)
                        ->whereBetween('customer_signoff', [$startDate, $endDate])
                        ->sum('kw');
                }

                // Calculate user-specific earnings from UserCommission
                $totalEarning = UserCommission::where('user_id', $userId)
                    ->whereIn('pid', $salesPids)
                    ->where('is_displayed', '1')
                    ->whereIn('status', [3]) // Active and paid commissions
                    ->sum('amount') ?? 0;

                // If commission amounts are 0, calculate estimated earnings as percentage of user's gross account value within date range
                if ($totalEarning == 0) {
                    $userGrossValue = SalesMaster::whereIn('pid', $salesPids)
                        ->sum('gross_account_value');
                    $totalEarning = $userGrossValue * 0.10;
                }
            }

            return response()->json([
                'ApiName' => 'Get Sales Report API',
                'status' => true,
                'message' => 'Successfully.',
                'totalSales' => (int) $totalSales,
                'totalKw' => (float) $totalKw,
                'totalEarning' => (float) $totalEarning,
                'is_admin_view' => $isSuperAdmin,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Get Sales Report API',
                'status' => true,
                'message' => 'Successfully.',
                'totalSales' => 0.00,
                'totalKw' => 0.00,
                'totalEarning' => 0.00,
            ], 200);
        }
    }

    public function dashboardSalesReportOld(Request $request): JsonResponse
    {
        $userId = auth()->user()->id;
        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'last_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
        } elseif ($filterDataDateWise == 'this_year') {
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
        }
        $salesPid = SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid');

        $totalSales = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();
        $totalKw = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
        // ->where('install_complete_date','!=', null)
            ->sum('kw');
        $pids = SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->whereIn('pid', $salesPid)
            ->get();
        foreach ($pids as $key => $value) {
            /* all coloser-1-2 and setter-1-2 user id is same */
            if ($value->closer1_id == $userId && $value->closer2_id == $userId && $value->setter1_id == $userId && $value->setter2_id == $userId) {

                $m1[$value->pid][] = $value->closer1_m1;
                $m1[$value->pid][] = $value->closer2_m1;
                $m1[$value->pid][] = $value->setter1_m1;
                $m1[$value->pid][] = $value->setter2_m1;

                $m2[$value->pid][] = $value->closer1_m2;
                $m2[$value->pid][] = $value->closer2_m2;
                $m2[$value->pid][] = $value->setter1_m2;
                $m2[$value->pid][] = $value->setter2_m2;
            }
            /* closer-1 and setter-1-2 user id is same */
            elseif ($value->closer1_id == $userId && $value->setter1_id == $userId && $value->setter2_id == $userId) {
                $m1[$value->pid][] = $value->closer1_m1;
                $m1[$value->pid][] = $value->setter1_m1;
                $m1[$value->pid][] = $value->setter2_m1;

                $m2[$value->pid][] = $value->closer1_m2;
                $m2[$value->pid][] = $value->setter1_m2;
                $m2[$value->pid][] = $value->setter2_m2;
            }
            /* closer-2 and setter-1-2 user id is same */
            elseif ($value->closer2_id == $userId && $value->setter1_id == $userId && $value->setter2_id == $userId) {
                $m1[$value->pid][] = $value->closer2_m1;
                $m1[$value->pid][] = $value->setter1_m1;
                $m1[$value->pid][] = $value->setter2_m1;

                $m2[$value->pid][] = $value->closer2_m2;
                $m2[$value->pid][] = $value->setter1_m2;
                $m2[$value->pid][] = $value->setter2_m2;
            }
            /* closer-1-2 and setter-1 user id is same */
            elseif ($value->closer1_id == $userId && $value->closer2_id == $userId && $value->setter1_id == $userId) {
                $m1[$value->pid][] = $value->closer2_m1;
                $m1[$value->pid][] = $value->setter1_m1;
                $m1[$value->pid][] = $value->closer1_m1;

                $m2[$value->pid][] = $value->closer2_m2;
                $m2[$value->pid][] = $value->setter1_m2;
                $m2[$value->pid][] = $value->closer1_m2;
            }
            /* closer-1-2 and setter-2 user id is same */
            elseif ($value->closer1_id == $userId && $value->closer2_id == $userId && $value->setter2_id == $userId) {
                $m1[$value->pid][] = $value->closer2_m1;
                $m1[$value->pid][] = $value->setter2_m1;
                $m1[$value->pid][] = $value->closer1_m1;

                $m2[$value->pid][] = $value->closer2_m2;
                $m2[$value->pid][] = $value->setter2_m2;
                $m2[$value->pid][] = $value->closer1_m2;
            }
            /* closer-1-2 user id is same */
            elseif ($value->closer1_id == $userId && $value->closer2_id == $userId) {
                $m1[$value->pid][] = $value->closer2_m1;
                $m1[$value->pid][] = $value->closer1_m1;

                $m2[$value->pid][] = $value->closer2_m2;
                $m2[$value->pid][] = $value->closer1_m2;
            }
            /* setter-1-2 user id is same */
            elseif ($value->setter1_id == $userId && $value->setter2_id == $userId) {
                $m1[$value->pid][] = $value->setter2_m1;
                $m1[$value->pid][] = $value->setter1_m1;

                $m2[$value->pid][] = $value->setter2_m2;
                $m2[$value->pid][] = $value->setter1_m2;
            }
            /* setter-1 user id is same */
            elseif ($value->setter1_id == $userId) {
                $m1[$value->pid][] = $value->setter1_m1;
                $m2[$value->pid][] = $value->setter1_m2;
            }
            /* setter-2 user id is same */
            elseif ($value->setter2_id == $userId) {
                $m1[$value->pid][] = $value->setter2_m1;
                $m2[$value->pid][] = $value->setter2_m2;
            }
            /* closer-2 user id is same */
            elseif ($value->closer2_id == $userId) {
                $m1[$value->pid][] = $value->closer2_m1;
                $m2[$value->pid][] = $value->closer2_m2;
            }
            /* closer- user id is same */
            elseif ($value->closer1_id == $userId) {
                $m1[$value->pid][] = $value->closer1_m1;
                $m2[$value->pid][] = $value->closer1_m2;
            }
        }
        $totalM1 = array_sum(array_map('floatval', Arr::flatten($m1)));
        $totalM2 = array_sum(array_map('floatval', Arr::flatten($m2)));

        /* // dd(\DB::getQueryLog());
        $amountM1 = 0;
        $amountM2 = 0;
        if (count($pids)>0) {
        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
        ->whereIn('pid',$pids)
        // ->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
        ->first();
        $amountM1 = $salesm1m2Amount->m1;
        $amountM2 = $salesm1m2Amount->m2;
        } */
        return response()->json([
            'ApiName' => 'Get Sales Report API',
            'status' => true,
            'message' => 'Successfully.',
            'totalSales' => $totalSales,
            'totalKw' => $totalKw,
            'totalEarning' => $totalM1 + $totalM2,
        ], 200);

    }

    public function dashboardSetGoals(Request $request): JsonResponse
    {
        $user = auth()->user();
        $userId = $user->id;
        $isSuperAdmin = $user->is_super_admin == 1;

        // Security check: Regular users can only set their own goals
        $targetUserId = $request->input('user_id');
        if (! $isSuperAdmin && $targetUserId && $targetUserId != $userId) {
            return response()->json([
                'ApiName' => 'Set Goals API',
                'status' => false,
                'message' => 'Unauthorized. You can only set your own goals.',
            ], 403);
        }

        // If user_id not provided or regular user, use authenticated user's ID
        if (! $targetUserId || ! $isSuperAdmin) {
            $targetUserId = $userId;
        }

        // Validate required fields
        $request->validate([
            'earning' => 'required|numeric|min:0',
            'account' => 'required|integer|min:0',
            'kw_sold' => 'required|numeric|min:0',
            'filter' => 'required|string|in:this_week,this_month,this_quarter,this_year,last_12_months',
        ]);

        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        } else {
            // Set a default date range (optional)
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
        }

        $goals = SetGoals::where('user_id', $targetUserId)->where(['start_date' => $startDate, 'end_date' => $endDate])->first();

        if (! $goals) {
            $data = SetGoals::create([
                'user_id' => $targetUserId,
                'earning' => $request->earning,
                'account' => $request->account,
                'kw_sold' => $request->kw_sold,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        } else {
            $goals->user_id = $targetUserId;
            $goals->earning = $request->earning;
            $goals->account = $request->account;
            $goals->kw_sold = $request->kw_sold;
            $goals->start_date = $startDate;
            $goals->end_date = $endDate;
            $goals->save();
        }

        return response()->json([
            'ApiName' => 'Set Goals API',
            'status' => true,
            'message' => 'Goals set successfully.',
            'target_user_id' => $targetUserId,
        ], 200);
    }

    public function dashboardGoalsTracker(DashboardFilterRequest $request): JsonResponse
    {
        $user = auth()->user();
        $userId = $user->id;
        $isSuperAdmin = $user->is_super_admin == 1;

        // Security check: Regular users can only view their own data
        $targetUserId = $request->input('user_id');
        if (! $isSuperAdmin && $targetUserId && $targetUserId != $userId) {
            return response()->json([
                'ApiName' => 'Goals Tracker API',
                'status' => false,
                'message' => 'Unauthorized. You can only view your own data.',
            ], 403);
        }

        // If admin doesn't specify user_id, or regular user, use authenticated user's ID
        if (! $targetUserId || ! $isSuperAdmin) {
            $targetUserId = $userId;
        }

        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        } else {
            // Set a default date range (optional)
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
        }

        $companyProfile = CompanyProfile::first();

        if (! $companyProfile) {
            return response()->json([
                'ApiName' => 'Goals Tracker API',
                'status' => false,
                'message' => 'Company profile not found.',
            ], 500);
        }

        // Initialize response arrays
        $goalsVal = ['earning' => 0, 'account' => 0, 'kw_sold' => 0];
        $getVal = ['get_earning' => 0, 'get_account' => 0, 'get_kw_sold' => 0];

        if ($isSuperAdmin && (! $request->input('user_id') || $request->input('user_id') == $userId)) {
            // ADMIN LOGIC: Show aggregated data for all users (default for admins, or when viewing their own ID)

            // Aggregate goals from all users (sum of all user goals in date range)
            $allGoals = SetGoals::where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    // Goal period overlaps with our date range
                    $q->whereDate('start_date', '<=', $endDate)
                        ->whereDate('end_date', '>=', $startDate);
                });
            })->get();

            if ($allGoals->count() > 0) {
                $goalsVal['earning'] = $allGoals->sum('earning');
                $goalsVal['account'] = $allGoals->sum('account');
                $goalsVal['kw_sold'] = $allGoals->sum('kw_sold');
            } else {
                // No goals in date range - get latest goals from all users and aggregate them
                $latestGoals = SetGoals::whereIn('id', function ($query) {
                    $query->select(\DB::raw('MAX(id)'))
                        ->from('set_goals')
                        ->groupBy('user_id');
                })->get();

                if ($latestGoals->count() > 0) {
                    $goalsVal['earning'] = $latestGoals->sum('earning');
                    $goalsVal['account'] = $latestGoals->sum('account');
                    $goalsVal['kw_sold'] = $latestGoals->sum('kw_sold');
                } else {
                    // No goals at all - use reasonable organizational defaults
                    $goalsVal['earning'] = 50000; // Organizational earning goal
                    $goalsVal['account'] = 100;   // Organizational account goal
                    $goalsVal['kw_sold'] = 500;   // Organizational KW goal
                }
            }

            // Get actual performance for all users
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->count();

            // Calculate total KW/value for all sales based on company type
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('gross_account_value');
            } else {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
            }

            // Calculate total earnings for all users from SaleMasterProcess
            $salesPidsInRange = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                ->pluck('pid')
                ->toArray();

            if (! empty($salesPidsInRange)) {
                // Calculate total earnings from UserCommission for sales in date range
                $earning = UserCommission::whereIn('pid', $salesPidsInRange)
                    ->where('is_displayed', '1')
                    ->whereIn('status', [3]) // Active and paid commissions
                    ->sum('amount') ?? 0;

                // If commission amounts are 0, calculate estimated earnings as percentage of gross account value
                if ($earning == 0) {
                    $totalGrossValue = SalesMaster::whereIn('pid', $salesPidsInRange)
                        ->sum('gross_account_value');
                    $earning = $totalGrossValue * 0.10;
                }
            } else {
                // If no sales in date range, get all earnings from UserCommission
                $earning = UserCommission::where('is_displayed', '1')
                    ->whereIn('status', [3]) // Active and paid commissions
                    ->sum('amount') ?? 0;

                // If commission amounts are 0, calculate estimated earnings as percentage of total gross account value
                // Note: This is all-time fallback - should be avoided, keeping period-only metrics
                if ($earning == 0) {
                    // Skip all-time fallback to maintain period-only metrics
                    $earning = 0;
                }
            }

        } else {
            // REGULAR USER LOGIC OR ADMIN VIEWING SPECIFIC USER: Show user-specific data

            // Look for goals that overlap with the current date range
            $goals = SetGoals::where('user_id', $targetUserId)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        // Goal period overlaps with our date range
                        $q->whereDate('start_date', '<=', $endDate)
                            ->whereDate('end_date', '>=', $startDate);
                    });
                })
                ->orderBy('id', 'desc') // latest inserted first
                ->first();

            // Fallback: If no overlapping goals found, get the latest goal for this user
            if (! $goals) {
                $goals = SetGoals::where('user_id', $targetUserId)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            // Enhanced fallback: If user has no goals, use default values that make sense
            if ($goals) {
                $goalsVal['earning'] = isset($goals->earning) ? $goals->earning : 0;
                $goalsVal['account'] = isset($goals->account) ? $goals->account : 0;
                $goalsVal['kw_sold'] = isset($goals->kw_sold) ? $goals->kw_sold : 0;
            } else {
                // Default goals if user has no goals set
                $goalsVal['earning'] = 5000; // Default monthly earning goal
                $goalsVal['account'] = 10;    // Default monthly account goal
                $goalsVal['kw_sold'] = 50;    // Default monthly KW goal
            }

            // Get user's sales from UserCommission and filter by date range
            $userCommissionPids = UserCommission::where('user_id', $targetUserId)
                ->where('is_displayed', '1')
                ->pluck('pid')->toArray();

            $salesPids = SalesMaster::whereIn('pid', $userCommissionPids)
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                ->pluck('pid')->toArray();

            $totalSales = SalesMaster::whereIn('pid', $salesPids)
                ->count();

            // Calculate KW/value for user's sales based on company type
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $totalKw = SalesMaster::whereIn('pid', $salesPids)
                    ->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $totalKw = SalesMaster::whereIn('pid', $salesPids)
                    ->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $totalKw = SalesMaster::whereIn('pid', $salesPids)
                    ->sum('gross_account_value');
            } else {
                $totalKw = SalesMaster::whereIn('pid', $salesPids)
                    ->sum('kw');
            }

            // Calculate earnings from UserCommission for this user's sales in date range
            if (! empty($salesPids)) {
                $earning = UserCommission::where('user_id', $targetUserId)
                    ->whereIn('pid', $salesPids)
                    ->where('is_displayed', '1')
                    ->whereIn('status', [3]) // Active and paid commissions
                    ->sum('amount') ?? 0;

                // If commission amounts are 0, calculate estimated earnings as percentage of user's gross account value within date range
                if ($earning == 0) {
                    $userGrossValue = SalesMaster::whereIn('pid', $salesPids)
                        ->sum('gross_account_value');
                    $earning = $userGrossValue * 0.10;
                }
            } else {
                // User has no sales in the date range, so no earnings
                $earning = 0;
            }
        }

        $getVal['get_earning'] = (float) $earning;
        $getVal['get_account'] = (int) (isset($totalSales) ? $totalSales : 0);
        $getVal['get_kw_sold'] = (float) (isset($totalKw) ? $totalKw : 0);

        return response()->json([
            'ApiName' => 'Goals Tracker API',
            'status' => true,
            'message' => 'Successfully.',
            'weekly_set_value' => $goalsVal,
            'get_value' => $getVal,
            'is_admin_view' => $isSuperAdmin && (! $request->input('user_id') || $request->input('user_id') == $userId),
            'target_user_id' => $targetUserId,
        ], 200);
    }

    public function dashboardOfficePerformance(Request $request): JsonResponse
    {
        $userId = auth()->user()->id;
        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $lastStartDate = date('Y-m-d', strtotime($startOfLastWeek));
            $lastEndDate = date('Y-m-d', strtotime($endOfLastWeek));

        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            $lastStartDate = Carbon::now()->subMonth()->startOfMonth();
            $lastEndDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));

            $lastStartDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(24)));
            $lastEndDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)->subDays()));
        } else {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            $lastStartDate = Carbon::now()->subMonth()->startOfMonth();
            $lastEndDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        }

        $office_id = auth()->user()->office_id;

        $users = User::select('id')->where('office_id', $office_id)->get();

        // Initialize variables to hold the totals
        $totalSalesSum = 0;
        $totalKwSum = 0;
        $totalSoldSum = 0;
        $m1CompleteSum = 0;
        $m2CompleteSum = 0;
        $cancelledSum = 0;
        $clawbackSum = 0;

        // Initialize last period variables
        $lastTotalSalesSum = 0;
        $lastM1CompleteSum = 0;
        $lastM2CompleteSum = 0;
        $lastCancelledSum = 0;
        $lastClawbackSum = 0;

        foreach ($users as $user) {

            $userId = $user->id;

            // $clawbackPid = ClawbackSettlement::where('user_id',$userId)->pluck('pid')->groupBy('pid')->toArray();
            $clawbackPid = ClawbackSettlement::where('user_id', $userId)->pluck('pid');
            $salesPid = SaleMasterProcess::where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId)->pluck('pid');

            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->count();
            $totalSalesSold = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('m2_date', null)->count();
            $companyProfile = CompanyProfile::first();

            if (! $companyProfile) {
                return response()->json([
                    'ApiName' => 'Goals Tracker API',
                    'status' => false,
                    'message' => 'Company profile not found.',
                ], 500);
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->sum('gross_account_value');
            } else {
                $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->sum('kw');
            }
            // $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->groupBy('sales_rep_email')->count();
            $m1Complete = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('m1_date', '!=', null)->count();
            // $m1Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $m2Complete = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('m2_date', '!=', null)->count();
            // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $cancelled = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->count();

            // $lastTotalSales = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->count();
            $lastTotalSalesSold = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('m2_date', null)->count();
            // $lastTotalKw = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->sum('kw');
            // $lastTotalReps  = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->groupBy('sales_rep_email')->count();
            $lastM1Complete = SalesMaster::whereBetween('m1_date', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('m1_date', '!=', null)->count();
            // $lastM1Pending  = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $lastM2Complete = SalesMaster::whereBetween('m2_date', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('m2_date', '!=', null)->count();
            // $lastM2Pending  = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $lastCancelled = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('date_cancelled', '!=', null)->count();
            $lastClawback = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->count();

            // Add to the totals
            $totalSalesSum += $totalSales;
            $totalKwSum += $totalKw;
            $totalSoldSum += $totalSalesSold;
            $m1CompleteSum += $m1Complete;
            $m2CompleteSum += $m2Complete;
            $cancelledSum += $cancelled;
            $clawbackSum += $clawback;

            $lastTotalSalesSum += $lastTotalSalesSold;
            $lastM1CompleteSum += $lastM1Complete;
            $lastM2CompleteSum += $lastM2Complete;
            $lastCancelledSum += $lastCancelled;
            $lastClawbackSum += $lastClawback;
        }

        $data = [
            'totalSales' => $totalSalesSum,
            // 'lastTotalSalesXZ' => $lastTotalSales,
            'totalKw' => $totalKwSum,
            // 'lastTotalKw' => $lastTotalKw,
            'totalSold' => $totalSoldSum,
            'lastTotalSold' => $lastTotalSalesSum,
            'm1Complete' => $m1CompleteSum,
            'lastM1Complete' => $lastM1CompleteSum,
            'm2Complete' => $m2CompleteSum,
            'lastM2Complete' => $lastM2CompleteSum,
            'cancelled' => $cancelledSum,
            'lastCancelled' => $lastCancelledSum,
            'clawback' => $clawbackSum,
            'lastClawback' => $lastClawbackSum,
        ];

        return response()->json([
            'ApiName' => 'Office Performance API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    // public function dashboardOfficePerformanceSelesTeam(Request $request)
    // {
    //     $filterDataDateWise = $request->input('filter');

    //     if ($filterDataDateWise == 'this_week') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
    //         $endDate =  date('Y-m-d', strtotime(now()));
    //     } else if ($filterDataDateWise == 'this_month') {
    //         $startOfMonth = Carbon::now()->startOfMonth();
    //         $endOfMonth = Carbon::now()->endOfMonth();
    //         $startDate =  date('Y-m-d', strtotime($startOfMonth));
    //         $endDate =  date('Y-m-d', strtotime($endOfMonth));
    //     } else if ($filterDataDateWise == 'this_quarter') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //     } else if ($filterDataDateWise == 'this_year') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //     } else if ($filterDataDateWise == 'last_12_months') {
    //         $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
    //         $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //     }

    //     $team = ManagementTeam::where('office_id', auth()->user()->office_id)->get();
    //     $totalSales = [];

    //     $topTeam = [];
    //     $topCloser = [];
    //     $topSetter = [];
    //     $companyProfile = CompanyProfile::first();
    //     foreach ($team as $teams) {
    //         $teamUserId = User::where('team_id', $teams->id)->pluck('id');
    //         $teamUserIdAccount = SaleMasterProcess::whereIn('closer1_id', $teamUserId)->orWhereIn('closer2_id', $teamUserId)->orWhereIn('setter1_id', $teamUserId)->orWhereIn('setter2_id', $teamUserId)->pluck('pid');
    //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //             $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('gross_account_value');
    //         } else {
    //             $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('kw');
    //         }

    //         $accountTeam = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->count();
    //         $topTeam[] = [
    //             'team' => isset($teams->team_name) ? $teams->team_name : null,
    //             'account' =>  isset($accountTeam) ? $accountTeam : 0,
    //             'kw' =>  isset($kw) ? round($kw, 2) : 0
    //         ];
    //     }

    //     // $topUser = User::where('manager_id', auth()->user()->id)->get();
    //     $topUser = User::where('office_id', auth()->user()->office_id)->get();

    //     foreach ($topUser as $topUsers) {
    //         // dd($topUsers->id);

    //         $teamUserIdAccount = SaleMasterProcess::whereIn('closer1_id', [$topUsers->id])->orWhereIn('closer2_id', [$topUsers->id])->orWhereIn('setter1_id', [$topUsers->id])->orWhereIn('setter2_id', [$topUsers->id])->pluck('pid');
    //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //             $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('gross_account_value');
    //         } else {
    //             $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('kw');
    //         }
    //         $accountTeam = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->count();

    //         if ($topUsers->position_id == 2) {
    //             $topCloser[] = [
    //                 'image' => $topUsers->image,
    //                 'closer_first_name' => $topUsers->first_name,
    //                 'closer_last_name' => $topUsers->last_name,
    //                 'kw' => $kw,
    //                 'account' =>  isset($accountTeam) ? round($accountTeam, 2) : 0
    //             ];
    //         }
    //         if ($topUsers->position_id == 3) {
    //             $topSetter[] = [
    //                 'image' => $topUsers->image,
    //                 'setter_first_name' => $topUsers->first_name,
    //                 'setter_last_name' => $topUsers->last_name,
    //                 'kw' => $kw,
    //                 'account' =>  isset($accountTeam) ? round($accountTeam, 2) : 0
    //             ];
    //         }
    //     }

    //     $topUser = User::where('office_id', auth()->user()->office_id)->where('is_manager', '1')->get();
    //     $topManager = [];
    //     foreach ($topUser as $topUsers) {
    //         $managerUsers = User::where('manager_id', $topUsers->id)->get();
    //         $totalKw = 0;
    //         $account = 0;
    //         if (sizeof($managerUsers) != 0) {
    //             foreach ($managerUsers as $managerUser) {
    //                 $teamUserIdAccount = SaleMasterProcess::whereIn('closer1_id', $managerUser)->orWhereIn('closer2_id', $managerUser)->orWhereIn('setter1_id', $managerUser)->orWhereIn('setter2_id', $managerUser)->pluck('pid');
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('gross_account_value');
    //                 } else {
    //                     $kw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->sum('kw');
    //                 }
    //                 $accountTeam = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $teamUserIdAccount)->count();

    //                 $totalKw += $kw;
    //                 $account += $accountTeam;
    //             }
    //         }
    //         $topManager[] = [
    //             'manager_image' => $topUsers->image,
    //             'manager_first_name' => $topUsers->first_name,
    //             'manager_last_name' => $topUsers->last_name,
    //             'total_kw' => $totalKw,
    //             'total_account' =>  isset($account) ? round($account, 2) : 0
    //         ];
    //     }

    //     // top Setter...........

    //     if (!empty($topSetter)) {
    //         $maxSetterKw =  max(array_column($topSetter, 'kw'));
    //         foreach ($topSetter as $val) {
    //             if ($val['kw'] ==  $maxSetterKw) {
    //                 $topSetter = [
    //                     'setter_first_name' => $val['setter_first_name'],
    //                     'setter_last_name' => $val['setter_last_name'],
    //                     'setter_image' => $val['image'],
    //                     'total_kw' => round($val['kw'], 2),
    //                     'total_account' => $val['account'],
    //                 ];
    //             }
    //         }
    //     } else {
    //         $topSetter = [
    //             'setter_first_name' => null,
    //             'setter_last_name' => null,
    //             'setter_image' => null,
    //             'total_kw' => null,
    //             'total_account' => null
    //         ];
    //     }
    //     // top closer
    //     if (!empty($topCloser)) {
    //         $maxCloserKw =  max(array_column($topCloser, 'kw'));
    //         foreach ($topCloser as $val) {
    //             if ($val['kw'] ==  $maxCloserKw) {
    //                 $topCloser = [
    //                     'closer_first_name' => $val['closer_first_name'],
    //                     'closer_last_name' => $val['closer_last_name'],
    //                     'closer_image' => $val['image'],
    //                     'total_kw' => round($val['kw']),
    //                     'total_account' => $val['account']
    //                 ];
    //             }
    //         }
    //     } else {
    //         $topCloser = [
    //             'closer_first_name' => null,
    //             'closer_last_name' => null,
    //             'closer_image' => null,
    //             'total_kw' => null,
    //             'total_account' => null
    //         ];
    //     }
    //     //top team ..........

    //     if (!empty($topTeam)) {
    //         $maxTeamKw =  max(array_column($topTeam, 'kw'));
    //         foreach ($topTeam as $val) {
    //             if ($val['kw'] ==  $maxTeamKw) {
    //                 $topTeam = [
    //                     'team' => $val['team'],
    //                     'total_kw' => $val['kw'],
    //                     'total_account' => $val['account']
    //                 ];
    //             }
    //         }
    //     } else {
    //         $topTeam = [
    //             'team' => null,
    //             'total_kw' => null,
    //             'total_account' => null
    //         ];
    //     }

    //     if (sizeof($topManager) != 0) {
    //         $maxManagerKw =  max(array_column($topManager, 'total_kw'));
    //         foreach ($topManager as $val) {
    //             if ($val['total_kw'] ==  $maxManagerKw) {
    //                 $topManager = [
    //                     'manager_first_name' => $val['manager_first_name'],
    //                     'manager_last_name' => $val['manager_last_name'],
    //                     'manager_image' => $val['manager_image'],
    //                     'total_kw' => $val['total_kw'],
    //                     'total_account' => $val['total_account']
    //                 ];
    //             }
    //         }
    //     } else {
    //         $topManager = [
    //             'manager_first_name' => null,
    //             'manager_last_name' => null,
    //             'manager_image' => null,
    //             'total_kw' => null,
    //             'total_account' => null
    //         ];
    //     }

    //     $data = [
    //         'top_team' => $topTeam,
    //         'closer' => $topCloser,
    //         'setter' => $topSetter,
    //         'manager' => $topManager
    //     ];
    //     return response()->json([
    //         'ApiName' => 'Office Performance Sales Team API',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $data
    //     ]);
    // }

    public function dashboardOfficePerformanceSelesTeam(Request $request)
    {
        $filterDataDateWise = $request->input('filter');
        // Date range setup
        if ($filterDataDateWise == 'this_week') {
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        } elseif ($filterDataDateWise == 'this_month') {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = Carbon::now()->subMonths(12)->format('Y-m-d');
            $endDate = Carbon::now()->addDay()->format('Y-m-d');
        } else {
            // Set a default date range (optional)
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
        }
        $companyProfile = CompanyProfile::first();

        if (! $companyProfile) {
            return response()->json([
                'ApiName' => 'Office Performance API',
                'status' => false,
                'message' => 'Company profile not found.',
            ], 500);
        }

        $users = User::where('office_id', auth()->user()->office_id)->get();
        $userIds = $users->pluck('id')->toArray();
        // Fetch all SaleMasterProcess and SalesMaster in batch
        $saleMasterProcesses = SaleMasterProcess::where(function ($query) use ($userIds) {
            $query->whereIn('closer1_id', $userIds)
                ->orWhereIn('closer2_id', $userIds)
                ->orWhereIn('setter1_id', $userIds)
                ->orWhereIn('setter2_id', $userIds);
        })->get();
        // Group PIDs by user ID
        $pidsByUser = [];
        foreach ($saleMasterProcesses as $process) {
            foreach (['closer1_id', 'closer2_id', 'setter1_id', 'setter2_id'] as $role) {
                if (! empty($process->$role)) {
                    $pidsByUser[$process->$role][] = $process->pid;
                }
            }
        }
        $saleMasters = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->get();
        $saleMastersByPid = $saleMasters->groupBy('pid');
        // Top Teams
        $teams = ManagementTeam::where('office_id', auth()->user()->office_id)->get();
        $topTeamCollection = [];
        foreach ($teams as $team) {
            $teamUserIds = $users->where('team_id', $team->id)->pluck('id')->toArray();
            $teamPids = collect($teamUserIds)->flatMap(fn ($id) => $pidsByUser[$id] ?? []);
            $sales = collect($teamPids)->flatMap(fn ($pid) => $saleMastersByPid[$pid] ?? []);
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $kw = $sales->sum('gross_account_value');
            } else {
                $kw = $sales->sum('kw');
            }

            $accountCount = $sales->count();
            $topTeamCollection[] = [
                'team' => $team->team_name,
                'account' => $accountCount,
                'kw' => round($kw, 2),
            ];
        }
        // Top Closers & Setters
        $topCloserCollection = [];
        $topSetterCollection = [];
        foreach ($users as $user) {
            $pids = $pidsByUser[$user->id] ?? [];
            $sales = collect($pids)->flatMap(fn ($pid) => $saleMastersByPid[$pid] ?? []);
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $kw = $sales->sum('gross_account_value');
            } else {
                $kw = $sales->sum('kw');
            }
            $account = $sales->count();
            if ($user->position_id == 2) {
                $topCloserCollection[] = [
                    'image' => $user->image,
                    'closer_first_name' => $user?->first_name,
                    'closer_last_name' => $user?->last_name,
                    'kw' => $kw,
                    'account' => round($account, 2),
                ];
            } elseif ($user->position_id == 3) {
                $topSetterCollection[] = [
                    'image' => $user->image,
                    'setter_first_name' => $user?->first_name,
                    'setter_last_name' => $user?->last_name,
                    'kw' => $kw,
                    'account' => round($account, 2),
                ];
            }
        }
        // Top Manager
        $topManagerCollection = [];
        $managers = $users->where('is_manager', 1);
        foreach ($managers as $manager) {
            $managerUsers = $users->where('manager_id', $manager->id);
            $managerPids = $managerUsers->pluck('id')->flatMap(fn ($id) => $pidsByUser[$id] ?? []);
            $sales = collect($managerPids)->flatMap(fn ($pid) => $saleMastersByPid[$pid] ?? []);
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $kw = $sales->sum('gross_account_value');
            } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
                $kw = $sales->sum('gross_account_value');
            } else {
                $kw = $sales->sum('kw');
            }
            $account = $sales->count();
            $topManagerCollection[] = [
                'manager_image' => $manager->image,
                'manager_first_name' => $manager?->first_name,
                'manager_last_name' => $manager?->last_name,
                'total_kw' => $kw,
                'total_account' => round($account, 2),
            ];
        }
        // Extract top performers
        $topTeam = collect($topTeamCollection)->sortByDesc('kw')->first() ?? ['team' => null, 'total_kw' => null, 'total_account' => null];
        $topCloser = collect($topCloserCollection)->sortByDesc('kw')->map(function ($c) {
            return [
                'closer_first_name' => $c['closer_first_name'],
                'closer_last_name' => $c['closer_last_name'],
                'closer_image' => $c['image'],
                'total_kw' => round($c['kw']),
                'total_account' => $c['account'],
            ];
        })->first() ?? ['closer_first_name' => null, 'closer_last_name' => null, 'closer_image' => null, 'total_kw' => null, 'total_account' => null];
        $topSetter = collect($topSetterCollection)->sortByDesc('kw')->map(function ($s) {
            return [
                'setter_first_name' => $s['setter_first_name'],
                'setter_last_name' => $s['setter_last_name'],
                'setter_image' => $s['image'],
                'total_kw' => round($s['kw'], 2),
                'total_account' => $s['account'],
            ];
        })->first() ?? ['setter_first_name' => null, 'setter_last_name' => null, 'setter_image' => null, 'total_kw' => null, 'total_account' => null];
        $topManager = collect($topManagerCollection)->sortByDesc('total_kw')->first() ?? [
            'manager_first_name' => null,
            'manager_last_name' => null,
            'manager_image' => null,
            'total_kw' => null,
            'total_account' => null,
        ];

        // Final response
        return response()->json([
            'ApiName' => 'Office Performance Sales Team API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => [
                'top_team' => $topTeam,
                'closer' => $topCloser,
                'setter' => $topSetter,
                'manager' => $topManager,
            ],
        ]);
    }

    /**
     * Method adminDashboardOfficePerformance: get data for office data performannce graph
     * Case-1 m1 date is not null then m1 completed, m2 date is not null then m2 is completed
     * Case-2 m1 and m2 both date i  not null then sale is complete
     * Case-3 m1 and m2 and cancel date is not null then sale is completed then after cancel then create clawback.
     * Case-4 m1, m2 date is null ad cancel date is nnot empty then sales is cancel
     *
     * @param  Request  $request  [explicite description]
     */
    public function adminDashboardOfficePerformance(Request $request): JsonResponse
    {
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);
            $previousFilterDate = $this->getFilterLastDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];

                $lastStartDate = $previousFilterDate['startDate'];
                $lastEndDate = $previousFilterDate['endDate'];

            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Office Performance Sales Team API',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'Office Performance Sales Team API',
                'status' => false,
                'message' => 'Sales performance filter not selected please select filter',
                'data' => [],
            ], 400);
        }

        $salesPids = null;
        // Filter by office if provided
        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            $office_id = $request->input('office_id');
            if ($office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
                $salesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                    ->orWhereIn('closer2_id', $userIds)
                    ->orWhereIn('setter1_id', $userIds)
                    ->orWhereIn('setter2_id', $userIds)
                    ->pluck('pid');
            }
        }

        $clawbackPid = ClawbackSettlement::where('pid', '!=', null)->groupBy('pid')->pluck('pid')->toArray();
        /* Response for testing api */
        $totalCurrentSalesQuery = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$startDate, $endDate]);
        $totalLastSalesQuery = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate]);
        /* Response for api */
        $totalSoldQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            /* ->where(function($query) {
                $query->whereNotNull('m1_date')
                      ->orWhereNotNull('m2_date')
                      ->orWhereNotNull('date_cancelled');
            }) */;

        // /* $lastTotalSoldQuery = SalesMaster::with('salesMasterProcess')->whereBetween('install_complete_date',[$lastStartDate,  $lastEndDate]); */
        $lastTotalSoldQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            /* ->where(function($query) {
                $query->whereNotNull('m1_date')
                      ->orWhereNotNull('m2_date')
                      ->orWhereNotNull('date_cancelled');
            }) */;

        $totalKwQuery = SalesMaster::with('salesMasterProcess');

        $m1CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull('m1_date')
            ->whereNull('date_cancelled');
        $lastM1CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull('m1_date')
            ->whereNull('date_cancelled');

        $m2CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull('m2_date')
            ->whereNull('date_cancelled');
        $lastM2CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull('m2_date')
            ->whereNull('date_cancelled');

        $cancelledQuery = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull('date_cancelled')
            ->whereNotIn('pid', $clawbackPid);
        $lastCancelledQuery = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull('date_cancelled')
            ->whereNotIn('pid', $clawbackPid);

        $clawbackQuery = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull('date_cancelled')
            ->whereIn('pid', $clawbackPid);
        $lastClawbackQuery = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull('date_cancelled')
            ->whereIn('pid', $clawbackPid);

        if ($salesPids) {
            $totalCurrentSalesQuery->whereIn('pid', $salesPids);
            $totalLastSalesQuery->whereIn('pid', $salesPids);
            $totalSoldQuery->whereIn('pid', $salesPids);
            $lastTotalSoldQuery->whereIn('pid', $salesPids);
            $totalKwQuery->whereIn('pid', $salesPids);
            $m1CompleteQuery->whereIn('pid', $salesPids);
            $lastM1CompleteQuery->whereIn('pid', $salesPids);
            $m2CompleteQuery->whereIn('pid', $salesPids);
            $lastM2CompleteQuery->whereIn('pid', $salesPids);
            $cancelledQuery->whereIn('pid', $salesPids);
            $lastCancelledQuery->whereIn('pid', $salesPids);
            $clawbackQuery->whereIn('pid', $salesPids);
            $lastClawbackQuery->whereIn('pid', $salesPids);
        }
        $data = [
            'totalCurrentSales' => $totalCurrentSalesQuery->count(),
            'totalLastSales' => $totalLastSalesQuery->count(),
            'totalSales' => $totalCurrentSalesQuery->count(),
            'totalSold' => $totalSoldQuery->count(),
            'lastTotalSold' => $lastTotalSoldQuery->count(),
            'totalKw' => $totalKwQuery->sum('kw'),
            'm1Complete' => $m1CompleteQuery->count(),
            'lastM1Complete' => $lastM1CompleteQuery->count(),
            'm2Complete' => $m2CompleteQuery->count(),
            'lastM2Complete' => $lastM2CompleteQuery->count(),
            'cancelled' => $cancelledQuery->count(),
            'lastCancelled' => $lastCancelledQuery->count(),
            'clawback' => $clawbackQuery->count(),
            'lastClawback' => $lastClawbackQuery->count(),
        ];

        return response()->json([
            'ApiName' => 'Admin Office Performance API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // public function adminDashboardOfficePerformance(Request $request)
    public function adminDashboardOfficePerformanceOld(Request $request): JsonResponse
    {
        $filterDataDateWise = $request->input('filter');
        $officeId = $request->input('office_id');

        if ($filterDataDateWise == 'this_week') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $lastStartDate = date('Y-m-d', strtotime($startOfLastWeek));
            $lastEndDate = date('Y-m-d', strtotime($endOfLastWeek));

        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            $lastStartDate = Carbon::now()->subMonth()->startOfMonth();
            $lastEndDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

        } elseif ($filterDataDateWise == 'last_quarter') {
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(9)->addDays(30)->startOfMonth()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(0)->endOfMonth()));

        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(1)));

            $lastStartDate = date('Y-m-d', strtotime(Carbon::now()->subYears(2)->startOfYear()));
            $lastEndDate = date('Y-m-d', strtotime(Carbon::now()->subYears(2)->endOfYear()));

        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));

            $lastStartDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(24)));
            $lastEndDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)->subDays()));
        }

        if ($officeId == 'all') {
            // $salesPid = SaleMasterProcess::where('closer1_id',$userId)->orWhere('closer2_id',$userId)->orWhere('setter1_id',$userId)->orWhere('setter2_id',$userId)->pluck('pid');

            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->count();
            $totalSalesSold = SalesMaster::whereBetween('install_complete_date', [$startDate, $endDate])->where('install_complete_date', '!=', null)->count();
            $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
            $totalReps = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->groupBy('sales_rep_email')->count();
            $m1Complete = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])->where('m1_date', '!=', null)->count();
            $m1Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $m2Complete = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
            $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $cancelled = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('m1_date', null)->where('m2_date', '=', null)->count();
            $clawbackPid = ClawbackSettlement::orderBy('user_id', 'desc')->groupBy('pid')->pluck('pid')->toArray();
            // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $clawbackPid)->count();

            $lastTotalSales = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->count();
            $lastTotalSalesSold = SalesMaster::whereBetween('install_complete_date', [$lastStartDate, $lastEndDate])->where('install_complete_date', '!=', null)->count();
            $lastTotalKw = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->sum('kw');
            $lastTotalReps = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->groupBy('sales_rep_email')->count();
            $lastM1Complete = SalesMaster::whereBetween('m1_date', [$lastStartDate, $lastEndDate])->where('m1_date', '!=', null)->count();
            $lastM1Pending = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $lastM2Complete = SalesMaster::whereBetween('m2_date', [$lastStartDate, $lastEndDate])->where('m2_date', '!=', null)->count();
            $lastM2Pending = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->where('date_cancelled', null)->where('m2_date', '=', null)->count();
            $lastCancelled = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->where('date_cancelled', '!=', null)->where('m1_date', null)->where('m2_date', '=', null)->count();
            // $lastClawback   = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();
            $lastClawback = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->where('date_cancelled', '!=', null)->whereIn('pid', $clawbackPid)->count();

        } else {

            $userId = User::where('office_id', $officeId)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
            $clawbackPid = ClawbackSettlement::whereIn('user_id', $userId)->pluck('pid')->unique('pid'); // ->groupBy('pid')->toArray();
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->count();
            $totalSalesSold = SalesMaster::whereBetween('install_complete_date', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('install_complete_date', '!=', null)->count();
            $totalKw = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->sum('kw');
            $totalReps = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->groupBy('sales_rep_email')->count();
            $m1Complete = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('m1_date', '!=', null)->count();
            $m1Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $m2Complete = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('m2_date', '!=', null)->count();
            $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $cancelled = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->whereIn('pid', $salesPid)->where('date_cancelled', '!=', null)->where('m1_date', null)->count();
            // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereIn('pid',$salesPid)->count();
            $clawback = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->count();

            $lastTotalSales = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->count();
            $lastTotalSalesSold = SalesMaster::whereBetween('install_complete_date', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('install_complete_date', '!=', null)->count();
            $lastTotalKw = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->sum('kw');
            $lastTotalReps = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->groupBy('sales_rep_email')->count();
            $lastM1Complete = SalesMaster::whereBetween('m1_date', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('m1_date', '!=', null)->count();
            $lastM1Pending = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('date_cancelled', '=', null)->where('m1_date', '=', null)->count();
            $lastM2Complete = SalesMaster::whereBetween('m2_date', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('m2_date', '!=', null)->count();
            $lastM2Pending = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $lastCancelled = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->whereIn('pid', $salesPid)->where('date_cancelled', '!=', null)->where('m1_date', null)->count();
            // $lastClawback   = SalesMaster::whereBetween('customer_signoff',[$lastStartDate,$lastEndDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();
            $lastClawback = SalesMaster::whereBetween('date_cancelled', [$lastStartDate, $lastEndDate])->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereIn('pid', $clawbackPid)->count();

        }

        $data = [
            'totalSales' => $totalSales,
            // 'lastTotalSales' => $lastTotalSales,
            'totalKw' => $totalKw,
            // 'lastTotalKw' => $lastTotalKw,
            'totalSold' => $totalSalesSold,
            'lastTotalSold' => $lastTotalSalesSold,
            'm1Complete' => $m1Complete,
            'lastM1Complete' => $lastM1Complete,
            'm2Complete' => $m2Complete,
            'lastM2Complete' => $lastM2Complete,
            'cancelled' => $cancelled,
            'lastCancelled' => $lastCancelled,
            'clawback' => $clawback,
            'lastClawback' => $lastClawback,
        ];

        return response()->json([
            'ApiName' => 'Admin Office Performance API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }
    /**
     * Method adminDashboardTopPayRollByLocation : get Top Payroll Locations data for admin dashboard
     *
     * @param  Request  $request  [explicite description]
     */
    // public function adminDashboardTopPayRollByLocation(Request $request): JsonResponse{
    //     if ($request->has('filter')) {
    //         $filterDataDateWise = $request->input('filter');
    //         $filterDate = $this->getFilterDate($filterDataDateWise);

    //         if(!empty($filterDate['startDate']) && !empty($filterDate['endDate'])){
    //            $startDate = $filterDate['startDate'];
    //            $endDate = $filterDate['endDate'];
    //         }
    //         if(!empty($filterDate['startDate']) && !empty($filterDate['endDate'])){
    //             $startDate = $filterDate['startDate'];
    //             $endDate = $filterDate['endDate'];
    //         }

    //         // Get data from payroll history
    //         $payrollHistoryData = State::whereHas('user', function($query) use ($startDate, $endDate) {
    //             $query->whereHas('payrollHistory', function ($query) use ($startDate, $endDate) {
    //                 $query->withinDateRange($startDate, $endDate);
    //             });
    //         })->with(['user' => function($query) use ($startDate, $endDate) {
    //             $query->whereHas('payrollHistory', function ($query) use ($startDate, $endDate) {
    //                 $query->withinDateRange($startDate, $endDate);
    //             })->with(['payrollHistory' => function ($query) use ($startDate, $endDate) {
    //                 $query->withinDateRange($startDate, $endDate);
    //             }]);
    //         }])->get();

    //         $payrollHistoryResult = [];

    //         foreach ($payrollHistoryData as $state) {
    //             $stateName = $state->name;
    //             $totalCommission = 0;
    //             $totalOverride = 0;
    //             $totalAdjustment = 0;
    //             $totalReimbursement = 0;
    //             $totalDeduction = 0;

    //             foreach ($state->user as $user) {
    //                 $totalCommission += $user->payrollHistory->sum('commission');
    //                 $totalOverride += $user->payrollHistory->sum('override');
    //                 $totalAdjustment += $user->payrollHistory->sum('adjustment');
    //                 $totalReimbursement += $user->payrollHistory->sum('reimbursement');
    //                 $totalDeduction += $user->payrollHistory->sum('deduction');
    //             }

    //             if (!isset($payrollHistoryResult[$stateName])) {
    //                 $payrollHistoryResult[$stateName] = [
    //                     'total_commission' => 0,
    //                     'total_override' => 0,
    //                     'total_adjustment' => 0,
    //                     'total_reimbursement' => 0,
    //                     'total_deduction' => 0
    //                 ];
    //             }

    //             $payrollHistoryResult[$stateName]['total_commission'] += $totalCommission;
    //             $payrollHistoryResult[$stateName]['total_override'] += $totalOverride;
    //             $payrollHistoryResult[$stateName]['total_adjustment'] += $totalAdjustment;
    //             $payrollHistoryResult[$stateName]['total_reimbursement'] += $totalReimbursement;
    //             $payrollHistoryResult[$stateName]['total_deduction'] += $totalDeduction;
    //         }

    //         // Get data from payroll table
    //         $payrollData = State::whereHas('user', function($query) use ($startDate, $endDate) {
    //             $query->whereHas('payroll', function ($query) use ($startDate, $endDate) {
    //                 // $query->whereBetween('pay_period_to', [$startDate, $endDate])
    //                 $query->WhereBetween('pay_period_from', [$startDate, $endDate])
    //                 ->whereNotNull("pay_period_from");
    //             });
    //         })->with(['user' => function($query) use ($startDate, $endDate) {
    //             $query->whereHas('payroll', function ($query) use ($startDate, $endDate) {
    //                 // $query->whereBetween('pay_period_to1', [$startDate, $endDate])
    //                  $query->orWhereBetween('pay_period_from', [$startDate, $endDate])
    //                  ->whereNotNull("pay_period_from");
    //             });
    //         }])->get();
    //         // dd($payrollData[0]);

    //         $payrollResult = [];

    //         foreach ($payrollData as $state) {
    //             $stateName = $state->name;
    //             $totalCommission = 0;
    //             $totalOverride = 0;
    //             $totalAdjustment = 0;
    //             $totalReimbursement = 0;
    //             $totalDeduction = 0;

    //             foreach ($state->user as $user) {
    //                 if($user->payroll ){
    //                     $totalCommission += $user?->payroll->commission?? 0;
    //                     $totalOverride += $user?->payroll->override?? 0;
    //                     $totalAdjustment += $user?->payroll->adjustment?? 0;
    //                     $totalReimbursement += $user?->payroll->reimbursement?? 0;
    //                     $totalDeduction += $user?->payroll->deduction?? 0;

    //                 }
    //             }

    //             if (!isset($payrollResult[$stateName])) {
    //                 $payrollResult[$stateName] = [
    //                     'total_commission' => 0,
    //                     'total_override' => 0,
    //                     'total_adjustment' => 0,
    //                     'total_reimbursement' => 0,
    //                     'total_deduction' => 0
    //                 ];
    //             }
    //             // dump($totalCommission);

    //             $payrollResult[$stateName]['total_commission'] += $totalCommission;
    //             $payrollResult[$stateName]['total_override'] += $totalOverride;
    //             $payrollResult[$stateName]['total_adjustment'] += $totalAdjustment;
    //             $payrollResult[$stateName]['total_reimbursement'] += $totalReimbursement;
    //             $payrollResult[$stateName]['total_deduction'] += $totalDeduction;
    //         }
    //         // dd($payrollHistoryResult,$payrollResult);
    //         // Combine the results
    //         $combinedResult = [];

    //         foreach ($payrollHistoryResult as $stateName => $data) {
    //             if (!isset($combinedResult[$stateName])) {
    //                 $combinedResult[$stateName] = [
    //                     'total_commission' => 0,
    //                     'total_override' => 0,
    //                     'total_adjustment' => 0,
    //                     'total_reimbursement' => 0,
    //                     'total_deduction' => 0
    //                 ];
    //             }

    //             $combinedResult[$stateName]['total_commission'] += $data['total_commission'];
    //             $combinedResult[$stateName]['total_override'] += $data['total_override'];
    //             $combinedResult[$stateName]['total_adjustment'] += $data['total_adjustment'];
    //             $combinedResult[$stateName]['total_reimbursement'] += $data['total_reimbursement'];
    //             $combinedResult[$stateName]['total_deduction'] += $data['total_deduction'];
    //         }
    //         foreach ($payrollResult as $stateName => $data) {
    //             if (!isset($combinedResult[$stateName])) {
    //                 $combinedResult[$stateName] = [
    //                     'total_commission' => 0,
    //                     'total_override' => 0,
    //                     'total_adjustment' => 0,
    //                     'total_reimbursement' => 0,
    //                     'total_deduction' => 0
    //                 ];
    //             }

    //             $combinedResult[$stateName]['total_commission'] += $data['total_commission'];
    //             $combinedResult[$stateName]['total_override'] += $data['total_override'];
    //             $combinedResult[$stateName]['total_adjustment'] += $data['total_adjustment'];
    //             $combinedResult[$stateName]['total_reimbursement'] += $data['total_reimbursement'];
    //             $combinedResult[$stateName]['total_deduction'] += $data['total_deduction'];
    //         }

    //         $payrollHistoryFinalData = [];
    //         foreach ($combinedResult as $stateName => $data) {
    //             $totalAmount = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'];
    //             $payrollHistoryFinalData[] = [
    //                 "name" => $stateName,
    //                 "value" => round($totalAmount,2),
    //             ];
    //         }
    //         // Return the response
    //         array_multisort(array_column($payrollHistoryFinalData, 'value'),SORT_DESC, $payrollHistoryFinalData);
    //         return response()->json([
    //             'ApiName' => 'Admin Top PayRoll By Location API',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'data' => $payrollHistoryFinalData,
    //         ], 200);
    //     }else{
    //         $managerState = State::get();
    //         $stateName = [];
    //         foreach($managerState as $key => $state)
    //         {
    //         $userId = User::where('state_id',$state->id)->pluck('id');
    //         $amountM1 = 0;
    //         $amountM2 = 0;

    //             $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
    //             ->whereIn('closer1_id',$userId)
    //             ->orWhereIn('closer2_id',$userId)
    //             ->orWhereIn('setter1_id',$userId)
    //             ->orWhereIn('setter2_id',$userId)
    //             ->first();
    //             $amountM1 = isset($salesm1m2Amount->m1)?$salesm1m2Amount->m1:0;
    //             $amountM2 = isset($salesm1m2Amount->m2)?$salesm1m2Amount->m2:0;
    //             $total =  $amountM1+$amountM2;
    //             if($total>0)
    //             {
    //                 $data[]=[
    //                 'name' => $state->name,
    //                 'value' => $total,
    //                 ];
    //             }

    //         }
    //         array_multisort(array_column($data, 'value'),SORT_DESC, $data);
    //         return response()->json([
    //             'ApiName' => 'Admin Top PayRoll By Location API',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'data' => $data,
    //         ], 200);
    //     }
    // }

    public function adminDashboardTopPayRollByLocation(Request $request): JsonResponse
    {
        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            $filterDate = $this->getFilterDate($filterDataDateWise);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            }

            // Get data from payroll history
            $payrollHistoryData = State::whereHas('user', function ($query) use ($startDate, $endDate) {
                $query->whereHas('payrollHistory', function ($query) use ($startDate, $endDate) {
                    $query->withinDateRange($startDate, $endDate);
                });
            })->with(['user.payrollHistory' => function ($query) use ($startDate, $endDate) {
                $query->withinDateRange($startDate, $endDate);
            }])->get();

            $payrollHistoryResult = [];

            foreach ($payrollHistoryData as $state) {
                $stateName = $state->name;
                $totalCommission = 0;
                $totalOverride = 0;
                $totalAdjustment = 0;
                $totalReimbursement = 0;
                $totalDeduction = 0;

                foreach ($state->user as $user) {
                    foreach ($user->payrollHistory as $payrollHistory) {
                        $totalCommission += $payrollHistory->commission;
                        $totalOverride += $payrollHistory->override;
                        $totalAdjustment += $payrollHistory->adjustment;
                        $totalReimbursement += $payrollHistory->reimbursement;
                        $totalDeduction += $payrollHistory->deduction;
                    }
                }

                if (! isset($payrollHistoryResult[$stateName])) {
                    $payrollHistoryResult[$stateName] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                    ];
                }

                $payrollHistoryResult[$stateName]['total_commission'] += $totalCommission;
                $payrollHistoryResult[$stateName]['total_override'] += $totalOverride;
                $payrollHistoryResult[$stateName]['total_adjustment'] += $totalAdjustment;
                $payrollHistoryResult[$stateName]['total_reimbursement'] += $totalReimbursement;
                $payrollHistoryResult[$stateName]['total_deduction'] += $totalDeduction;
            }

            // Get data from payroll table
            $payrollData = State::whereHas('user', function ($query) use ($startDate, $endDate) {
                $query->whereHas('payroll', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])
                        ->whereNotNull('pay_period_from');
                });
            })->with(['user.payroll' => function ($query) use ($startDate, $endDate) {
                $query->orWhereBetween('pay_period_from', [$startDate, $endDate])
                    ->whereNotNull('pay_period_from');
            }])->get();

            $payrollResult = [];

            foreach ($payrollData as $state) {
                $stateName = $state->name;
                $totalCommission = 0;
                $totalOverride = 0;
                $totalAdjustment = 0;
                $totalReimbursement = 0;
                $totalDeduction = 0;

                foreach ($state->user as $user) {
                    if ($user->payroll) {
                        $totalCommission += $user->payroll->commission ?? 0;
                        $totalOverride += $user->payroll->override ?? 0;
                        $totalAdjustment += $user->payroll->adjustment ?? 0;
                        $totalReimbursement += $user->payroll->reimbursement ?? 0;
                        $totalDeduction += $user->payroll->deduction ?? 0;
                    }
                }

                if (! isset($payrollResult[$stateName])) {
                    $payrollResult[$stateName] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                    ];
                }

                $payrollResult[$stateName]['total_commission'] += $totalCommission;
                $payrollResult[$stateName]['total_override'] += $totalOverride;
                $payrollResult[$stateName]['total_adjustment'] += $totalAdjustment;
                $payrollResult[$stateName]['total_reimbursement'] += $totalReimbursement;
                $payrollResult[$stateName]['total_deduction'] += $totalDeduction;
            }

            // Combine the results
            $combinedResult = [];
            foreach ($payrollHistoryResult as $stateName => $data) {
                if (! isset($combinedResult[$stateName])) {
                    $combinedResult[$stateName] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                    ];
                }

                $combinedResult[$stateName]['total_commission'] += $data['total_commission'];
                $combinedResult[$stateName]['total_override'] += $data['total_override'];
                $combinedResult[$stateName]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$stateName]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$stateName]['total_deduction'] += $data['total_deduction'];
            }

            foreach ($payrollResult as $stateName => $data) {
                if (! isset($combinedResult[$stateName])) {
                    $combinedResult[$stateName] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                    ];
                }

                $combinedResult[$stateName]['total_commission'] += $data['total_commission'];
                $combinedResult[$stateName]['total_override'] += $data['total_override'];
                $combinedResult[$stateName]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$stateName]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$stateName]['total_deduction'] += $data['total_deduction'];
            }

            $payrollHistoryFinalData = [];
            foreach ($combinedResult as $stateName => $data) {
                $totalAmount = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'];
                $payrollHistoryFinalData[] = [
                    'name' => $stateName,
                    'value' => round($totalAmount, 2),
                ];
            }

            // Return the response
            array_multisort(array_column($payrollHistoryFinalData, 'value'), SORT_DESC, $payrollHistoryFinalData);

            return response()->json([
                'ApiName' => 'Admin Top PayRoll By Location API',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $payrollHistoryFinalData,
            ], 200);
        } else {
            $managerState = State::get();
            $data = [];
            foreach ($managerState as $key => $state) {
                $userId = User::where('state_id', $state->id)->pluck('id');
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                    ->whereIn('closer1_id', $userId)
                    ->orWhereIn('closer2_id', $userId)
                    ->orWhereIn('setter1_id', $userId)
                    ->orWhereIn('setter2_id', $userId)
                    ->first();
                $amountM1 = isset($salesm1m2Amount->m1) ? $salesm1m2Amount->m1 : 0;
                $amountM2 = isset($salesm1m2Amount->m2) ? $salesm1m2Amount->m2 : 0;
                $total = $amountM1 + $amountM2;
                if ($total > 0) {
                    $data[] = [
                        'name' => $state->name,
                        'value' => $total,
                    ];
                }

            }
            array_multisort(array_column($data, 'value'), SORT_DESC, $data);

            return response()->json([
                'ApiName' => 'Admin Top PayRoll By Location API',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }
        // Your code for the "else" block
    }

    public function adminDashboardTopPayRollByLocationOld(Request $request): JsonResponse
    {
        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
            } elseif ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()->endOfWeek()));
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

            } elseif ($filterDataDateWise == 'this_month') {

                $startOfMonth = \Carbon\Carbon::now()->subDays(0)->startOfMonth();
                $endOfMonth = \Carbon\Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));

            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonthDay = \Carbon\Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = \Carbon\Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }
            $managerState = State::get();
            $stateName = [];
            $data = [];
            foreach ($managerState as $key => $state) {
                $userId = User::where('state_id', $state->id)->pluck('id');
                $amountM1 = 0;
                $amountM2 = 0;
                $commission = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('commission');
                $override = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('override');
                $adjustment = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('adjustment');
                $reimbursement = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('reimbursement');
                $deduction = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('deduction');
                $clawback = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])->whereIn('user_id', $userId)->sum('clawback');
                // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                // ->whereIn('closer1_id',$userId)
                // ->whereIn('pid',$pid)
                // ->orWhereIn('closer2_id',$userId)
                // ->whereIn('pid',$pid)
                // ->orWhereIn('setter1_id',$userId)
                // ->whereIn('pid',$pid)
                // ->orWhereIn('setter2_id',$userId)
                // ->whereIn('pid',$pid)
                // ->first();
                $amountM1 = $commission + $override + $reimbursement + $deduction;
                // $amountM2 = isset($salesm1m2Amount->m2)?$salesm1m2Amount->m2:0;
                // $total =  $amountM1+$amountM2;
                $total = $amountM1 + $adjustment;

                if ($total > 0) {
                    $data[] = [
                        'name' => $state->name,
                        'value' => $total,
                    ];
                }
            }
        } else {
            $managerState = State::get();
            $stateName = [];
            foreach ($managerState as $key => $state) {
                $userId = User::where('state_id', $state->id)->pluck('id');
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                    ->whereIn('closer1_id', $userId)
                    ->orWhereIn('closer2_id', $userId)
                    ->orWhereIn('setter1_id', $userId)
                    ->orWhereIn('setter2_id', $userId)
                    ->first();
                $amountM1 = isset($salesm1m2Amount->m1) ? $salesm1m2Amount->m1 : 0;
                $amountM2 = isset($salesm1m2Amount->m2) ? $salesm1m2Amount->m2 : 0;
                $total = $amountM1 + $amountM2;
                if ($total > 0) {
                    $data[] = [
                        'name' => $state->name,
                        'value' => $total,
                    ];
                }

            }
        }

        $filterDataDateWise = $request->input('filter');
        $officeId = $request->input('office_id');
        array_multisort(array_column($data, 'value'), SORT_DESC, $data);

        return response()->json([
            'ApiName' => 'Admin Top PayRoll By Location API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    /**
     * Method adminDashboardOfficePerformanceSelesKw: This function get admin sales account dashboard data
     *
     * @param  Request  $request  [explicite description]
     */
    public function adminDashboardOfficePerformanceSelesKw(Request $request): JsonResponse
    {
        // Validate filter input
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Office Performance Sales Team API',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'Office Performance Sales Team API',
                'status' => false,
                'message' => 'Sales performance filter not selected please select filter',
                'data' => [],
            ], 400);
        }

        // Company profile detail
        $companyProfile = CompanyProfile::first();

        if (! $companyProfile) {
            return response()->json([
                'ApiName' => 'Sales Performance API',
                'status' => false,
                'message' => 'Company profile not found.',
            ], 500);
        }

        // Prepare base query with conditional eager loading
        $salesQuery = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate]);
        $hasOfficeFilter = false;

        // Filter by office if provided
        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            $office_id = $request->input('office_id');
            if ($office_id != 'all') {
                $hasOfficeFilter = true;
                // OPTIMIZED: Single JOIN query instead of 3 separate queries
                $salesQuery->join('sale_master_process as smp', 'sale_masters.pid', '=', 'smp.pid')
                    ->leftJoin('users as u1', 'smp.closer1_id', '=', 'u1.id')
                    ->leftJoin('users as u2', 'smp.closer2_id', '=', 'u2.id')
                    ->leftJoin('users as u3', 'smp.setter1_id', '=', 'u3.id')
                    ->leftJoin('users as u4', 'smp.setter2_id', '=', 'u4.id')
                    ->where(function ($q) use ($office_id) {
                        $q->where('u1.office_id', $office_id)
                            ->orWhere('u2.office_id', $office_id)
                            ->orWhere('u3.office_id', $office_id)
                            ->orWhere('u4.office_id', $office_id);
                    })
                    ->select('sale_masters.*'); // Ensure we only select from main table
            }
        }

        // Add eager loading only if we didn't use JOIN (to avoid conflicts)
        if (! $hasOfficeFilter) {
            $salesQuery->with('salesMasterProcess');
        }

        // Clone base query to apply separate filters for installed and pending sales
        $salesInstalledQuery = clone $salesQuery;
        $salesPendingQuery = clone $salesQuery;

        // Apply filtering logic based on company type
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // Installed = Products that have a milestone date set
            $salesInstalledQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->whereNotNull('milestone_date');
            });

            // Pending = Products without a milestone date
            $salesPendingQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->whereNull('milestone_date');
            });
        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            // Installed = Products marked as last date and milestone date is set
            $salesInstalledQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });

            // Pending = Products marked as last date but milestone date is missing
            $salesPendingQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
            // Installed = Same logic as Mortgage (based on last date and milestone)
            $salesInstalledQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });

            // Pending = Last date is marked but milestone is not set
            $salesPendingQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        } else {
            // Default fallback for other company types:
            // Installed = Last date + milestone set
            $salesInstalledQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNotNull('milestone_date');
            });

            // Pending = Last date + milestone not set
            $salesPendingQuery->whereHas('salesProductMasterDetails', function ($q) {
                $q->where('is_last_date', '1')->whereNull('milestone_date');
            });
        }

        // OPTIMIZED: Database-level filtering instead of in-memory collection filtering

        // Get total sales count directly from database
        $totalSalesDataCount = $salesQuery->count();

        // Apply database-level filters for installed sales count
        $salesInstalledCountQuery = clone $salesInstalledQuery;
        $salesInstalledCountQuery->whereNull('date_cancelled')->whereNotNull('m2_date');
        $salesInstallCount = $salesInstalledCountQuery->count();

        // Apply database-level filters for pending sales count
        $salesPendingCountQuery = clone $salesPendingQuery;
        $salesPendingCountQuery->whereNull('date_cancelled')->whereNull('m2_date');
        $salesPendingCount = $salesPendingCountQuery->count();

        // OPTIMIZED: Direct database aggregation (no cloning needed)
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // Direct database-level sum with filtering
            $total_kw_installed = (clone $salesInstalledQuery)->whereNull('date_cancelled')->sum('gross_account_value');
            $total_kw_pending = (clone $salesPendingQuery)->whereNull('m2_date')->whereNull('date_cancelled')->sum('gross_account_value');
        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            // Direct database-level sum with filtering
            $total_kw_installed = (clone $salesInstalledQuery)->whereNull('date_cancelled')->sum('gross_account_value');
            $total_kw_pending = (clone $salesPendingQuery)->whereNull('m2_date')->whereNull('date_cancelled')->sum('gross_account_value');
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') {
            // Direct database-level sum with filtering
            $total_kw_installed = (clone $salesInstalledQuery)->whereNull('date_cancelled')->sum('gross_account_value');
            $total_kw_pending = (clone $salesPendingQuery)->whereNull('m2_date')->whereNull('date_cancelled')->sum('gross_account_value');
        } else {
            // Direct database-level sum with filtering
            $total_kw_installed = (clone $salesInstalledQuery)->whereNull('date_cancelled')->sum('kw');
            $total_kw_pending = (clone $salesPendingQuery)->whereNull('m2_date')->whereNull('date_cancelled')->sum('kw');
        }

        // Prepare response data
        $data = [
            'totalSales' => $totalSalesDataCount,
            'install_account' => round($salesInstallCount, 2),
            'pending_account' => round($salesPendingCount, 2),
            'totalKw' => round(($total_kw_installed + $total_kw_pending), 2),
            'install_kw' => round($total_kw_installed, 2),
            'pending_kw' => round($total_kw_pending, 2),
        ];

        return response()->json([
            'ApiName' => 'Office Performance Sales Team API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function adminDashboardOfficePerformanceSelesKwOld(Request $request): JsonResponse
    {
        $isManager = Auth()->user()->is_manager;
        $bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->groupBy('team_id')->orderBy('countId', 'desc')->first();
        if ($request->office_id != 'all') {
            if ($request->user_id == 1) {
                $userId = User::where('is_super_admin', '!=', 1)->where('office_id', $request->office_id)->orderBy('id', 'asc')->pluck('id');
            } else {
                // $userId = User::where('manager_id',$request->user_id)->where('office_id',$request->office_id)->orderBy('id','asc')->pluck('id');
                $userId = User::where('office_id', $request->office_id)->orderBy('id', 'asc')->pluck('id');
            }
        } else {

            //  $userId = User::where('manager_id',$request->user_id)->orderBy('id','asc')->pluck('id');
            $userId = User::where('is_super_admin', '!=', 1)->orderBy('id', 'asc')->pluck('id');

        }

        $pid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');

        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
            } elseif ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()->endOfWeek()));

            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

            } elseif ($filterDataDateWise == 'this_month') {

                $startOfMonth = \Carbon\Carbon::now()->subDays(0)->startOfMonth();
                $endOfMonth = \Carbon\Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonthDay = \Carbon\Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');

            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = \Carbon\Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }
        }

        $totalSales = [];
        $installAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
        $pendingAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
        $totalKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
        $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

        $data = [
            'totalKw' => round($totalKw, 2),
            'totalSales' => round($totalSales, 2),
            'install_account' => round($installAccount, 2),
            'install_kw' => round($installKw, 2),
            'pending_account' => round($pendingAccount, 2),
            'pending_kw' => round($pendingKw, 2),

        ];

        return response()->json([
            'ApiName' => 'Office Performance Sales Team API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function eventList(Request $request): JsonResponse
    {

        $id = $request->id;

        if ($id) {
            $data = EventCalendar::where('id', $id)
                ->orWhere('type', 'Career Fair')
                ->orWhere('type', 'Meeting')
                ->orWhere('type', 'Training')
                ->orWhere('type', 'Company Event')
                ->orWhere('type', 'Other')
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $data = EventCalendar::where('type', 'Career Fair')
                ->orWhere('type', 'Meeting')
                ->orWhere('type', 'Training')
                ->orWhere('type', 'Company Event')
                ->orWhere('type', 'Other')
                ->orderBy('id', 'desc')
                ->limit(5)->get();

        }

        return response()->json([
            'ApiName' => 'Event List ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function announcementDisable(Request $request): JsonResponse
    {

        $id = $request->id;
        $data = [
            'disable' => $request['disable'],
        ];

        $announcement = Announcement::where('id', $id)->first();
        if (! empty($announcement)) {
            $announcement->disable = $request->disable;
            $announcement->save();

            return response()->json([
                'ApiName' => 'Disable List Announcements',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data['disable'],
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Disable List Announcements',
                'status' => true,
                'message' => 'Successfully.',
                'data' => [],
            ], 200);
        }

    }

    public function officeByPositionList(Request $request): JsonResponse
    {
        $officeId = $request->office_id;

        $data = user::select('id', 'office_id', 'sub_position_id')->where('office_id', $officeId)->groupBy('sub_position_id')->get();

        return response()->json([
            'ApiName' => 'Disable List Announcements',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }
}
