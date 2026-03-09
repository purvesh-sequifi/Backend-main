<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdjustementType;
use App\Models\ApprovalsAndRequest;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Documents;
use App\Models\EmailNotificationSetting;
use App\Models\FrequencyType;
use App\Models\GroupMaster;
use App\Models\GroupPolicies;
use App\Models\HiringStatus;
use App\Models\Locations;
use App\Models\MarkAccountStatus;
use App\Models\OnboardingEmployees;
use App\Models\OverridesType;
use App\Models\Payroll;
use App\Models\Permissions;
use App\Models\PoliciesTabs;
use App\Models\Positions;
use App\Models\Roles;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceScreeningRequestList;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SequiDocsTemplateCategories;
use App\Models\State;
use App\Models\User;
use App\Models\UserStatus;
use App\Services\ClickHouseConnectionService;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Validator;

class ActivityLogController extends Controller
{
    public function activityLog(Request $request): JsonResponse
    {
        // Validate all input parameters
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date|after_or_equal:2020-01-01',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1|max:1000',
            'perpage' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'activity-logs',
                'status' => false,
                'message' => 'Invalid input parameters.',
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        // Sanitize and set validated parameters
        $perpage = $request->perpage ?? 10;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Sanitize search input
        $search = null;
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = preg_replace('/[^\w\s\-@.]/', '', $request->input('search'));
            if (strlen($search) < 2) {
                return response()->json([
                    'ApiName' => 'activity-logs',
                    'status' => false,
                    'message' => 'Search term must be at least 2 characters.',
                    'data' => [],
                ], 422);
            }
        }

        $result = DB::table('activity_log')
            ->select('users.id as userid', 'users.first_name', 'users.last_name', 'users.created_at as date', 'activity_log.*')
            ->leftjoin('users', 'users.id', '=', 'activity_log.causer_id');

        if ($startDate && $endDate) {
            $result = $result->whereBetween(DB::raw('DATE(activity_log.created_at)'), [$startDate, $endDate]);
        }

        if ($search) {
            $result->where(function ($query) use ($search) {
                $query->where('log_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('description', 'LIKE', '%'.$search.'%')
                    ->orWhere('subject_type', 'LIKE', '%'.$search.'%')
                    ->orWhere('subject_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('event', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.first_name', 'like', '%'.$search.'%')
                    ->orWhere('users.last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ",users.last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        // Implement cursor-based pagination for MySQL (similar to ClickHouse implementation)
        $page = (int) ($request->page ?? 1);
        $lastId = $request->input('last_id', null);

        // If no cursor provided but page > 1, calculate cursor from page number
        if (! $lastId && $page > 1) {
            // Calculate how many records to skip
            $recordsToSkip = ($page - 1) * $perpage;

            // Get the ID at the skip position to use as cursor
            $cursorQuery = DB::table('activity_log')
                ->select('activity_log.id')
                ->leftjoin('users', 'users.id', '=', 'activity_log.causer_id');

            // Apply same filters as main query
            if ($startDate && $endDate) {
                $cursorQuery = $cursorQuery->whereBetween(DB::raw('DATE(activity_log.created_at)'), [$startDate, $endDate]);
            }

            if ($search) {
                $cursorQuery->where(function ($query) use ($search) {
                    $query->where('log_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('description', 'LIKE', '%'.$search.'%')
                        ->orWhere('subject_type', 'LIKE', '%'.$search.'%')
                        ->orWhere('subject_id', 'LIKE', '%'.$search.'%')
                        ->orWhere('event', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.first_name', 'like', '%'.$search.'%')
                        ->orWhere('users.last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ",users.last_name) LIKE ?', ['%'.$search.'%']);
                });
            }

            $cursorResult = $cursorQuery->orderBy('activity_log.id', 'DESC')
                ->skip($recordsToSkip)
                ->take(1)
                ->first();

            if ($cursorResult) {
                $lastId = $cursorResult->id;
            }
        }

        // Apply cursor filtering if we have a lastId
        if ($lastId) {
            $result = $result->where('activity_log.id', '<', $lastId);
        }

        // Get the records with cursor-based pagination
        $userActivityLogs = $result->orderBy('activity_log.id', 'DESC')
            ->take($perpage)
            ->get()
            ->toArray();

        // Get total count for pagination info
        $totalQuery = DB::table('activity_log')
            ->leftjoin('users', 'users.id', '=', 'activity_log.causer_id');

        if ($startDate && $endDate) {
            $totalQuery = $totalQuery->whereBetween(DB::raw('DATE(activity_log.created_at)'), [$startDate, $endDate]);
        }

        if ($search) {
            $totalQuery->where(function ($query) use ($search) {
                $query->where('log_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('description', 'LIKE', '%'.$search.'%')
                    ->orWhere('subject_type', 'LIKE', '%'.$search.'%')
                    ->orWhere('subject_id', 'LIKE', '%'.$search.'%')
                    ->orWhere('event', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.first_name', 'like', '%'.$search.'%')
                    ->orWhere('users.last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ",users.last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        $total = $totalQuery->count();

        // DEBUG: Add comprehensive debugging
        $debugInfo = [
            'total_records' => $total,
            'current_page_records' => count($userActivityLogs),
            'perpage' => $perpage,
            'page' => $page,
            'last_id' => $lastId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'search' => $search,
            'calculated_has_next' => count($userActivityLogs) === (int) $perpage,
            'expected_pages' => ceil($total / $perpage),
            'request_params' => $request->all(),
        ];

        Log::info('[MySQL ActivityLog Debug]', $debugInfo);

        // DEBUG: Log the actual SQL query being executed
        $queryDebug = $result->toSql();
        $bindingsDebug = $result->getBindings();
        Log::info('[MySQL Query Debug]', ['sql' => $queryDebug, 'bindings' => $bindingsDebug]);

        // Get the next cursor for pagination
        $nextCursor = null;
        if (! empty($userActivityLogs)) {
            $lastRecord = end($userActivityLogs);
            $nextCursor = $lastRecord->id; // Access as object property, not array
        }

        $user = [];
        $data = [];
        $new_array = [];
        $old_array = [];
        $action = [];
        foreach ($userActivityLogs as $key => $logs) {
            $fname = isset($logs->first_name) ? $logs->first_name : ' ';
            $lname = isset($logs->last_name) ? $logs->last_name : ' ';
            if ($logs->subject_type == \App\Models\User::class) {
                $change = DB::table('users')->where('id', $logs->subject_id)->first();
                $firstname = isset($change->first_name) ? $change->first_name : ' ';
                $lastname = isset($change->last_name) ? $change->last_name : ' ';
                $emp = $firstname.' '.$lastname;
                if (isset($change->image) && $change->image != null) {
                    $s3_image = $this->getS3ImageUrl($change->image);
                } else {
                    $s3_image = null;
                }
            } else {
                $replace = str_replace("App\Models", '', $logs->subject_type);
                $events = str_replace('\\', '', $replace);
                $emp = $events;
                $s3_image = null;
            }
            $user[$key]['user_id'] = $logs->causer_id;
            $user[$key]['action_by'] = $fname.' '.$lname;
            $user[$key]['log_name'] = $logs->log_name;
            $user[$key]['subject'] = $logs->subject_type;
            $user[$key]['changes_id'] = $logs->subject_id;
            $user[$key]['change'] = $emp;
            $user[$key]['user_image_s3'] = $s3_image;
            $user[$key]['created_at'] = $logs->created_at;
            $properties = json_decode($logs->properties);
            $new_values = isset($properties->attributes) ? ($properties->attributes) : '';
            $old_values = isset($properties->old) ? ($properties->old) : '';
            $action = $logs->event;

            $attributeOption = isset($properties->attributes->attribute_option);
            if (isset($properties->attributes->is_deleted) && $properties->attributes->is_deleted == 1) {
                $event = 'deleted';
                $description = 'deleted';
            } else {
                $event = $logs->event;
                $description = $logs->description;
            }
            $user[$key]['event'] = $event;
            $user[$key]['description'] = $description;
            $list = [];
            if ($action == 'created') {
                foreach ($new_values as $array_key => $array_data) {
                    $new_value = (! empty($new_values->$array_key)) ? $new_values->$array_key : null;
                    $old_value = (! empty($old_values->$array_key)) ? $old_values->$array_key : null;
                    if ($array_key == 'id' || $array_key == 'hiring_id' || $array_key == 'everee_json_response' || $array_key == 'reconciliation_id' || $array_key == 'payroll_id' || $array_key == 'created_at' || $array_key == 'updated_at' || $array_key == 'password' || $array_key == 'employee_personal_detail' || $array_key == 'setter2_id' || $array_key == 'request_id' || $array_key == 'setter1_id' || $array_key == 'closer2_id' || $array_key == 'closer1_id' || $array_key == 'sale_master_id' || $array_key == 'category_id' || $array_key == 'template_id' || $array_key == 'document_type_id' || $array_key == 'settlement_id' || $array_key == 'reconciliation' || $array_key == 'attribute_option' || $array_key == 'configuration_id' || $array_key == 'org_parent_id' || $array_key == 'parent_id' || $array_key == 'additional_info_for_employee_to_get_started' || $array_key == 'cost_tracking_id' || $old_value == $new_value) {
                        continue;
                    }
                    if (in_array($array_key, ['amount', 'salary', 'bonus'])) { // Add more keys as necessary
                        $new_value = '$'.number_format($new_value, 2);
                    }
                    if ($array_key == 'payment_status' || $array_key == 'everee_payment_status') {
                        // Safely check if properties exist before accessing them
                        $payment_status = property_exists($new_values, 'payment_status') ? $new_values->payment_status : null;
                        $everee_payment_status = property_exists($new_values, 'everee_payment_status') ? $new_values->everee_payment_status : null;

                        if ($payment_status == 3) {
                            if ($everee_payment_status == 0) {
                                $new_value = 'pending';
                            } elseif ($everee_payment_status == 1) {
                                $new_value = 'success';
                            } elseif ($everee_payment_status == 2) {
                                $new_value = 'failed';
                            } else {
                                $new_value = 'all';
                            }
                        } else {
                            $new_value = 'all';
                        }
                    }
                    // Handle 'adjustment_type_id' field
                    if ($array_key == 'adjustment_type_id') {
                        switch ($new_value) {
                            case 1:
                                $new_value = 'Payroll Dispute';
                                break;
                            case 2:
                                $new_value = 'Reimbursement';
                                break;
                            case 3:
                                $new_value = 'Bonus';
                                break;
                            case 4:
                                $new_value = 'Advance';
                                break;
                            case 5:
                                $new_value = 'Fine/fee';
                                break;
                            case 6:
                                $new_value = 'Incentive';
                                break;
                            default:
                                // Handle default case if necessary
                                break;
                        }
                    }
                    if ($array_key == 'office_id') {
                        $new_value = $this->locationName($new_value);
                    }
                    if ($array_key == 'OnboardProcess') {
                        $new_value = $this->onboardProcess($new_value);
                    }
                    if ($array_key == 'state_id') {
                        $stateName = State::where('id', $new_value)->value('name');
                        $new_value = $stateName;
                    }
                    if ($array_key == 'user_id') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'old_state_id') {
                        $new_value = $this->oldStateId($new_value);
                    }
                    if ($array_key == 'old_office_id') {
                        $new_value = $this->oldOfficeId($new_value);
                    }
                    if ($array_key == 'old_department_id') {
                        $new_value = $this->oldDepartmentName($new_value);
                    }
                    if ($array_key == 'old_position_id') {
                        $new_value = $this->oldPositionName($new_value);
                    }
                    if ($array_key == 'old_sub_position_id') {
                        $new_value = $this->oldSubPositionName($new_value);
                    }
                    if ($array_key == 'old_team_id') {
                        $new_value = $this->oldTeamName($new_value);
                    }
                    if ($array_key == 'old_self_gen_accounts') {
                        $new_value = $this->oldSelfGenAccount($new_value);
                    }
                    if ($array_key == 'old_manager_id') {
                        $new_value = $this->oldManager($new_value);
                    }
                    if ($array_key == 'old_is_manager') {
                        $new_value = $this->oldIsManager($new_value);
                    }
                    if ($array_key == 'reporting_manager_id') {
                        $new_value = $this->reportingManager($new_value);
                    }
                    if ($array_key == 'sub_position_type') {
                        $new_value = $this->subPositionType($new_value);
                    }
                    if ($array_key == 'updated_by') {
                        $new_value = $this->updatedBy($new_value);
                    }
                    if ($array_key == 'setter1_m1_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'closer1_m1_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'setter1_m2_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'closer1_m2_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'mark_account_status_id') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'start_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'end_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'pay_period_from') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'pay_period_to') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'cost_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'event_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'position_id') {
                        $new_value = $this->positionName($new_value);
                    }
                    if ($array_key == 'sub_position_id') {
                        $new_value = $this->positionName($new_value);
                    }
                    if ($array_key == 'additional_recruiter_id1') {
                        $new_value = $this->additionalRecruiter1($new_value);
                    }
                    if ($array_key == 'additional_recruiter_id2') {
                        $new_value = $this->additionalRecruiter2($new_value);
                    }
                    if ($array_key == 'additional_recruiter') {
                        $new_value = $this->additionalRecruiter($new_value);
                    }
                    if ($array_key == 'group_id') {
                        $groupnewlist = GroupMaster::where('id', $new_value)->value('name');
                        $new_value = $groupnewlist;
                    }
                    if ($array_key == 'created_by') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'approved_by') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'sale_user_id') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'commission_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'customer_signoff') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'withheld_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'period_of_agreement_start_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'offer_expiry_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'm1_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'self_gen_commission_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'upfront_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'self_gen_upfront_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'self_gen_withheld_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'override_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'm2_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'm1_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'date_cancelled') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'self_gen_redline_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'redline_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'dob') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'transfer_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'manager_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'is_manager_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'team_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'position_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'document_send_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'setter_id') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'closer_id') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'updater_id') {
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'document_id') {
                        $new_value = $this->documentStatus($new_value);
                    }
                    if ($array_key == 'recruiter_id') {
                        $new_value = $this->recruiterName($new_value);
                    }
                    if ($array_key == 'manager_id') {
                        $new_value = $this->managerName($new_value);
                    }
                    if ($array_key == 'recipient_sign_req') {
                        $new_value = $this->recipientSignReq($new_value);
                    }
                    if ($array_key == 'completed_step') {
                        $new_value = $this->completedStep($new_value);
                    }
                    if ($array_key == 'is_template_ready') {
                        $new_value = $this->isTemplateReady($new_value);
                    }
                    if ($array_key == 'is_pdf') {
                        $new_value = $this->ispdf($new_value);
                    }
                    if ($array_key == 'category_id') {
                        $new_value = $this->templateCategories($new_value);
                    }
                    if ($array_key == 'is_manager') {
                        $new_value = $this->isManager($new_value);
                    }
                    if ($array_key == 'is_next_payroll') {
                        $new_value = $this->isNextPayroll($new_value);
                    }
                    if ($array_key == 'status_id') {
                        $new_value = $this->UserStatus($new_value);
                    }
                    if ($array_key == 'stop_payroll') {
                        if ($new_value == 1) {
                            $stopPayroll = 'Stop Payroll';
                        } else {
                            $stopPayroll = 'Not Stop Payroll';
                        }
                        $new_value = $stopPayroll;
                    }
                    if ($array_key == 'is_stack') {
                        if ($new_value == 1) {
                            $isstack = 'Button On';
                        } else {
                            $isstack = 'Button Off';
                        }
                        $new_value = $isstack;
                    }
                    if ($array_key == 'setup_status') {
                        if ($new_value == 1) {
                            $setupStatus = 'Position Done';
                        } else {
                            $setupStatus = 'Position Pending';
                        }
                        $new_value = $setupStatus;
                    }
                    if ($array_key == 'self_gen_accounts') {
                        if ($new_value == 1) {
                            $selfstatus = 'Active';
                        } else {
                            $selfstatus = 'Inactive';
                        }
                        $new_value = $selfstatus;
                    }
                    if ($array_key == 'everee_payment_status') {
                        if ($new_value == 1) {
                            $evereeStatus = 'default';
                        } elseif ($new_value == 2) {
                            $evereeStatus = 'Fail';
                        } else {
                            $evereeStatus = 'Success';
                        }
                        $new_value = $evereeStatus;
                    }
                    if ($array_key == 'status') {
                        if ($new_value == 1) {
                            $newstatus = 'Active';
                        } else {
                            $newstatus = 'Inactive';
                        }
                        $new_value = $newstatus;
                    }
                    if ($logs->subject_type == \App\Models\GroupPermissions::class) {
                        if ($array_key == 'role_id') {
                            $roleName = Roles::where('id', $new_value)->value('name');
                            $new_value = $roleName;
                        }
                        if ($array_key == 'group_policies_id') {
                            $policieName = GroupPolicies::where('id', $new_value)->value('policies');
                            $new_value = $policieName;
                        }
                        if ($array_key == 'policies_tabs_id') {
                            $policiesTabs = PoliciesTabs::where('id', $new_value)->value('tabs');
                            $new_value = $policiesTabs;
                        }
                        if ($array_key == 'permissions_id') {
                            $permission = Permissions::where('id', $new_value)->value('name');
                            $new_value = $permission;
                        }
                    }
                    if ($logs->subject_type == \App\Models\Payroll::class) {
                        if ($array_key == 'status') {
                            $payRollStatus = Payroll::where('id', $new_value)->value('status');
                            if ($payRollStatus == 1) {
                                $payRollsta = 'pending';
                            } elseif ($payRollStatus == 2) {
                                $payRollsta = 'finalize';
                            } else {
                                $payRollsta = 'cancellation';
                            }

                            $new_value = $payRollsta;
                        }
                    }
                    if ($logs->subject_type == \App\Models\PositionOverride::class) {
                        if ($array_key == 'override_id') {
                            $overrideName = OverridesType::where('id', $new_value)->value('overrides_type');
                            $new_value = $overrideName;
                        }
                    }

                    if ($logs->subject_type == \App\Models\Documents::class) {
                        if ($array_key == 'is_active') {
                            $documents = Documents::where('is_active', $new_value)->first();
                            if ($documents->is_active == 1) {
                                $docStatus = 'Active';
                            } else {
                                $docStatus = 'Inactive';
                            }
                            $new_value = $docStatus;
                        }
                    }
                    if ($logs->subject_type == \App\Models\ApprovalsAndRequest::class) {
                        if ($array_key == 'adjustment_type_id') {
                            $adjustementName = AdjustementType::where('id', $new_value)->first();
                            if (isset($adjustementName)) {
                                $adjustement_value = $adjustementName->name;
                            } else {
                                $adjustement_value = null;
                            }
                            $new_value = $adjustement_value;
                        }
                        if ($array_key == 'status') {
                            $ApprovalsStatus = ApprovalsAndRequest::where('status', $new_value)->value('status');
                            $new_value = $ApprovalsStatus;
                        }
                    }
                    if ($logs->subject_type == \App\Models\UserTransferHistory::class) {
                        if ($array_key == 'department_id') {
                            $new_value = $this->departmentName($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\UserDeduction::class) {
                        if ($array_key == 'cost_center_id') {
                            $CostCenterName = CostCenter::where('id', $new_value)->first();
                            $CostCenter = isset($CostCenterName->name) ? $CostCenterName->name : ' ';
                            $new_value = $CostCenter;
                        }
                    }
                    if ($logs->subject_type == \App\Models\UserRedlines::class) {
                        if ($array_key == 'position_type') {
                            $new_value = $this->positionName($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\Announcement::class) {
                        if ($array_key == 'positions') {
                            $new_value = $this->positionStatus($new_value);
                        }
                        if ($array_key == 'office') {
                            $new_value = $this->officeStatus($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\AdditionalRecruiters::class) {
                        if ($array_key == 'hiring_id') {
                            $new_value = $this->usersData($new_value);
                        }
                        if ($array_key == 'user_id') {
                            $new_value = $this->usersData($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\Positions::class) {
                        if ($array_key == 'department_id') {
                            $new_value = $this->departmentName($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\Locations::class) {
                        if ($array_key == 'everee_location_id') {
                            $evereeLocation = Locations::where('everee_location_id', $new_value)->value('office_name');
                            $new_value = $evereeLocation;
                        }
                    }
                    if ($logs->subject_type == \App\Models\SaleMasterProcess::class) {
                        if ($array_key == 'setter1_m2_paid_status') {
                            $new_value = $this->SaleMasterProcess($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\User::class) {
                        if ($array_key == 'department_id') {
                            $new_value = $this->departmentName($new_value);
                        }
                    }

                    if ($logs->subject_type == \App\Models\payFrequencySetting::class) {
                        if ($array_key == 'frequency_type_id') {
                            $array_key = 'frequency_type';
                            $new_value = FrequencyType::where('id', $new_value)->value('name');
                        }
                        if ($array_key == 'deadline_to_run_payroll') {
                            $new_value = date('m/d/Y', strtotime($new_value));
                        }
                        if ($array_key == 'monthly_pay_type') {
                            if ($new_value == 'fifteenAndLastDayOfMonth') {
                                $new_value = '15th and last day of month';
                            }
                        }
                        if ($array_key == 'first_day_pay_of_manths') {
                            $array_key = 'first_day_pay_of_months';
                        }
                    }
                    if ($logs->subject_type == \App\Models\AdvancePaymentSetting::class) {
                        if ($array_key == 'adwance_setting') {
                            $array_key = 'Advance setting';
                        }
                    }
                    if ($logs->subject_type == \App\Models\overrideSystemSetting::class) {
                        if ($array_key == 'allow_office_stack_override_status' || $array_key == 'allow_manual_override_status') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                        }
                    }

                    if ($logs->subject_type == \App\Models\SClearanceScreeningRequestList::class) {
                        if ($array_key == 'status') {
                            $new_value = $new_values->$array_key;
                        }

                        if ($array_key == 'plan_id') {
                            $new_value = $this->getPlanName();
                        }

                        if ($array_key == 'is_manual_verification' || $array_key == 'is_report_generated') {
                            if ($new_value == 1) {
                                $new_value = 'Yes';
                            } else {
                                $new_value = 'No';
                            }
                        }
                    }

                    if ($logs->subject_type == \App\Models\SClearanceConfiguration::class) {
                        if ($array_key == 'hiring_status') {
                            if ($new_value == 1) {
                                $new_value = 'Pre Hiring';
                            } else {
                                $new_value = 'Post Hiring';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Pre Hiring';
                            } else {
                                $old_value = 'Post Hiring';
                            }
                        }

                        if ($array_key == 'is_mandatory') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Active';
                            } else {
                                $old_value = 'Inactive';
                            }
                        }

                        if ($array_key == 'is_approval_required') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Active';
                            } else {
                                $old_value = 'Inactive';
                            }
                        }
                    }

                    if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $new_value)) {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'user_id') {
                        $array_key = 'User name';
                    }
                    if ($array_key == 'position_id') {
                        $array_key = 'Position';
                    }
                    if ($array_key == 'override_id') {
                        $array_key = 'Override';
                    }
                    if ($logs->subject_type == \App\Models\OnboardingUserRedline::class) {
                        if ($array_key == 'User name') {
                            continue;
                        }
                    }
                    if ($logs->subject_type == \App\Models\EventCalendar::class) {
                        if ($array_key == 'User name') {
                            continue;
                        }
                    }
                    if ($logs->subject_type != \App\Models\payFrequencySetting::class) {
                        $unix = strtotime($new_value);
                        if ($unix !== false && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $new_value)) {
                            $new_value = date('m/d/Y | H:i', $unix);
                        }
                    }
                    $user[$key]['properties'][] = ['new_value' => strip_tags($new_value), 'modified_key' => str_replace('_', ' ', ucfirst($array_key))];
                }
            }
            if ($action == 'updated') {
                foreach ($new_values as $array_key => $array_data) {
                    $old_value = (! empty($old_values->$array_key)) ? $old_values->$array_key : null;
                    $new_value = (! empty($new_values->$array_key)) ? $new_values->$array_key : null;
                    if ($logs->subject_type != \App\Models\payFrequencySetting::class) {
                        if ($array_key == 'created_at' || $array_key == 'updated_at' || $array_key == 'cost_center_id' || $array_key == 'cost_tracking_id' || $array_key == 'id' || $array_key == 'password' || $array_key == 'pdf_file_other_parameter' || $old_value == $new_value) {
                            continue;
                        }
                    }
                    if ($array_key == 'cost_center_id') {
                        $old_value = $this->costCenterName($old_value);
                    }
                    if ($array_key == 'updated_at') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'employee_personal_detail') {
                        $employeePersonal = json_decode($logs->properties);
                        $newemployees = isset($employeePersonal->attributes->employee_personal_detail) ? ($employeePersonal->attributes->employee_personal_detail) : '';
                        $oldemployees = isset($employeePersonal->old->employee_personal_detail) ? ($employeePersonal->old->employee_personal_detail) : '';
                        $newValuess = json_decode($newemployees);
                        $oldValuess = json_decode($oldemployees);
                        if (! empty($newValuess)) {
                            foreach ($newValuess as $valkey => $datalist) {
                                $old_value = (! empty($oldValuess[$valkey]->value)) ? $oldValuess[$valkey]->value : null;
                                $new_value = (! empty($newValuess[$valkey]->value)) ? $newValuess[$valkey]->value : null;
                                if ((isset($new_value) || isset($old_value)) && ($new_value != $old_value)) {
                                    $old_value = isset($oldValuess[$valkey]->value) ? $oldValuess[$valkey]->value : null;
                                    // if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $old_value))
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $old_value) || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $old_value)) {
                                        $old_value = date('m/d/Y', strtotime($old_value));
                                    }
                                    // if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $newValuess[$valkey]->value))
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $newValuess[$valkey]->value) || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $newValuess[$valkey]->value)) {
                                        $new_value = date('m/d/Y', strtotime($newValuess[$valkey]->value));
                                    }
                                    $user[$key]['properties'][] = ['old_value' => ($old_value), 'new_value' => ($new_value), 'modified_key' => str_replace('_', ' ', ucfirst($newValuess[$valkey]->field_name))];
                                }
                            }
                        }
                    }
                    if ($array_key == 'setter1_m1_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'closer1_m1_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'setter1_m2_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'closer1_m2_paid_status') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'mark_account_status_id') {
                        $new_value = $this->SaleMasterProcess($new_value);
                    }
                    if ($array_key == 'start_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'end_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'pay_period_from') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'pay_period_to') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'cost_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'transfer_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'manager_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'is_manager_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'team_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'position_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'document_send_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'manager_id') {
                        $old_value = $this->usersData($old_value);
                        $new_value = $this->usersData($new_value);
                    }
                    if ($array_key == 'sale_user_id') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'order_by') {
                        $groupnewlist = GroupMaster::where('id', $new_value)->value('name');
                        $new_value = $groupnewlist;
                        $groupoldlist = GroupMaster::where('id', $old_value)->value('name');
                        $old_value = $groupoldlist;
                    }
                    if ($array_key == 'group_id') {
                        $old_value = $this->positionName($old_value);
                        $new_value = $this->positionName($new_value);
                    }
                    if ($array_key == 'created_by') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'approved_by') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'm1_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'self_gen_commission_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'upfront_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'self_gen_upfront_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'self_gen_withheld_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'self_gen_redline_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'redline_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'override_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'm2_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'm1_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'team_id_effective_date') {
                        $new_value = date('m/d/Y', strtotime($new_value));
                        $old_value = date('m/d/Y', strtotime($old_value));
                    }
                    if ($array_key == 'setter_id') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'closer_id') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'updater_id') {
                        $new_value = $this->usersData($new_value);
                        $old_value = $this->usersData($old_value);
                    }
                    if ($array_key == 'document_id') {
                        $new_value = $this->documentStatus($new_value);
                        $old_value = $this->documentStatus($old_value);
                    }
                    if ($array_key == 'old_state_id') {
                        $new_value = $this->oldStateId($new_value);
                        $old_value = $this->oldStateId($old_value);
                    }
                    if ($array_key == 'old_office_id') {
                        $new_value = $this->oldOfficeId($new_value);
                        $old_value = $this->oldOfficeId($old_value);
                    }
                    if ($array_key == 'old_department_id') {
                        $new_value = $this->oldDepartmentName($new_value);
                        $old_value = $this->oldDepartmentName($old_value);
                    }
                    if ($array_key == 'old_position_id') {
                        $new_value = $this->oldPositionName($new_value);
                        $old_value = $this->oldPositionName($old_value);
                    }
                    if ($array_key == 'old_sub_position_id') {
                        $new_value = $this->oldSubPositionName($new_value);
                        $old_value = $this->oldSubPositionName($old_value);
                    }
                    if ($array_key == 'old_team_id') {
                        $new_value = $this->oldTeamName($new_value);
                        $old_value = $this->oldTeamName($old_value);
                    }
                    if ($array_key == 'old_self_gen_accounts') {
                        $new_value = $this->oldSelfGenAccount($new_value);
                        $old_value = $this->oldSelfGenAccount($old_value);
                    }
                    if ($array_key == 'old_manager_id') {
                        $new_value = $this->oldManager($new_value);
                        $old_value = $this->oldManager($old_value);
                    }
                    if ($array_key == 'old_is_manager') {
                        $new_value = $this->oldIsManager($new_value);
                        $old_value = $this->oldIsManager($old_value);
                    }
                    if ($array_key == 'reporting_manager_id') {
                        $new_value = $this->reportingManager($new_value);
                        $old_value = $this->reportingManager($old_value);
                    }
                    if ($array_key == 'sub_position_type') {
                        $new_value = $this->subPositionType($new_value);
                        $old_value = $this->subPositionType($old_value);
                    }
                    if ($array_key == 'additional_recruiter') {
                        $new_value = $this->additionalRecruiter($new_value);
                        $old_value = $this->additionalRecruiter($old_value);
                    }
                    if ($array_key == 'disable') {
                        if ($new_value == 1) {
                            $status = 'Active';
                        } else {
                            $status = 'Inactive';
                        }
                        $new_value = $status;
                        if ($old_value == 1) {
                            $statusold = 'Active';
                        } else {
                            $statusold = 'Inactive';
                        }
                        $old_value = $statusold;
                    }
                    if ($array_key == 'everee_payment_status') {
                        if ($new_value == 1) {
                            $evereeStatus = 'default';
                        } elseif ($new_value == 2) {
                            $evereeStatus = 'Fail';
                        } else {
                            $evereeStatus = 'Success';
                        }
                        $new_value = $evereeStatus;
                        if ($old_value == 1) {
                            $evereeoldStatus = 'default';
                        } elseif ($old_value == 2) {
                            $evereeoldStatus = 'Fail';
                        } else {
                            $evereeoldStatus = 'Success';
                        }
                        $old_value = $evereeoldStatus;
                    }
                    if ($array_key == 'department_id') {
                        $new_value = $this->departmentName($new_value);
                        $old_value = $this->departmentName($old_value);
                    }
                    if ($array_key == 'position_id') {
                        $old_value = $this->positionName($old_value);
                        $new_value = $this->positionName($new_value);
                    }
                    if ($array_key == 'sub_position_id') {
                        $old_value = $this->positionName($old_value);
                        $new_value = $this->positionName($new_value);
                    }
                    if ($array_key == 'office_id') {
                        $old_value = $this->locationName($old_value);
                        $new_value = $this->locationName($new_value);
                    }
                    if ($array_key == 'is_next_payroll') {
                        $old_value = $this->isNextPayroll($old_value);
                        $new_value = $this->isNextPayroll($new_value);
                    }
                    if ($array_key == 'status_id') {
                        $new_value = $this->HiringStatus($new_value);
                        $old_value = $this->HiringStatus($old_value);
                    }
                    if ($array_key == 'commission_effective_date') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'withheld_effective_date') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'customer_signoff') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'period_of_agreement_start_date') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'offer_expiry_date') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'date_cancelled') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'dob') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'effective_date') {
                        $old_value = date('m/d/Y', strtotime($old_value));
                        $new_value = date('m/d/Y', strtotime($new_value));
                    }
                    if ($array_key == 'recruiter_id') {
                        $new_value = $this->recruiterName($new_value);
                        $old_value = $this->recruiterName($old_value);
                    }
                    if ($array_key == 'additional_recruiter_id1') {
                        $new_value = $this->additionalRecruiter1($new_value);
                        $old_value = $this->additionalRecruiter1($old_value);
                    }
                    if ($array_key == 'additional_recruiter_id2') {
                        $new_value = $this->additionalRecruiter2($new_value);
                        $old_value = $this->additionalRecruiter2($old_value);
                    }
                    if ($array_key == 'recipient_sign_req') {
                        $new_value = $this->recipientSignReq($new_value);
                        $old_value = $this->recipientSignReq($old_value);
                    }
                    if ($array_key == 'completed_step') {
                        $new_value = $this->completedStep($new_value);
                        $old_value = $this->completedStep($old_value);
                    }
                    if ($array_key == 'is_template_ready') {
                        $new_value = $this->isTemplateReady($new_value);
                        $old_value = $this->isTemplateReady($old_value);
                    }
                    if ($array_key == 'is_pdf') {
                        $new_value = $this->ispdf($new_value);
                        $old_value = $this->ispdf($old_value);
                    }
                    if ($array_key == 'category_id') {
                        $new_value = $this->templateCategories($new_value);
                        $old_value = $this->templateCategories($old_value);
                    }
                    if ($array_key == 'is_manager') {
                        $new_value = $this->isManager($new_value);
                        $old_value = $this->isManager($old_value);
                    }
                    if ($array_key == 'state_id') {
                        $stateName = State::where('id', $old_value)->value('name');
                        $old_value = $stateName;
                        $statenewName = State::where('id', $new_value)->value('name');
                        $new_value = $statenewName;
                    }
                    if ($array_key == 'OnboardProcess') {
                        $new_value = $this->onboardProcess($new_value);
                        $old_value = $this->onboardProcess($old_value);
                    }
                    if ($array_key == 'status') {
                        if ($new_value == 1) {
                            $newstatus = 'Active';
                        } else {
                            $newstatus = 'Inactive';
                        }
                        $new_value = $newstatus;

                        if ($old_value == 1) {
                            $oldstatus = 'Active';
                        } else {
                            $oldstatus = 'Inactive';
                        }
                        $old_value = $oldstatus;
                    }
                    if ($array_key == 'self_gen_accounts') {
                        if ($new_value == 1) {
                            $selfstatus = 'Active';
                        } else {
                            $selfstatus = 'Inactive';
                        }
                        $new_value = $selfstatus;

                        if ($old_value == 1) {
                            $selfoldstatus = 'Active';
                        } else {
                            $selfoldstatus = 'Inactive';
                        }
                        $old_value = $selfoldstatus;
                    }
                    if ($array_key == 'setup_status') {
                        if ($new_value == 1) {
                            $setupStatus = 'Position Done';
                        } else {
                            $setupStatus = 'Position Pending';
                        }
                        $new_value = $setupStatus;
                        if ($old_value == 1) {
                            $setupoldStatus = 'Position Done';
                        } else {
                            $setupoldStatus = 'Position Pending';
                        }
                        $old_value = $setupoldStatus;
                    }
                    if ($array_key == 'stop_payroll') {
                        if ($new_value == 1) {
                            $stopPayroll = 'Stop Payroll';
                        } else {
                            $stopPayroll = 'Not Stop Payroll';
                        }
                        $new_value = $stopPayroll;
                        if ($old_value == 1) {
                            $stopoldPayroll = 'Stop Payroll';
                        } else {
                            $stopoldPayroll = 'Not Stop Payroll';
                        }
                        $old_value = $stopoldPayroll;
                    }
                    if ($logs->subject_type == \App\Models\User::class) {
                        if ($array_key == 'status_id') {
                            $new_value = $this->UserStatus($new_value);
                            $old_value = $this->UserStatus($old_value);
                        }
                        if ($array_key == 'dismiss') {
                            $dismissNew = User::where('dismiss', $new_value)->value('dismiss');
                            if ($dismissNew == 0) {
                                $dismissval = 'unable';
                            } elseif ($dismissNew == 1) {
                                $dismissval = 'disable';
                            } else {
                                $dismissval = 'null';
                            }
                            $new_value = $dismissval;

                            $dismissOld = User::where('dismiss', $old_value)->value('dismiss');
                            if ($dismissOld == 0) {
                                $dismissOldVal = 'unable';
                            } elseif ($dismissOld == 1) {
                                $dismissOldVal = 'disable';
                            } else {
                                $dismissOldVal = 'null';
                            }
                            $old_value = $dismissOldVal;
                        }
                    }
                    if ($array_key == 'is_stack') {
                        if ($new_value == 1) {
                            $isstack = 'Button On';
                        } else {
                            $isstack = 'Button Off';
                        }
                        $new_value = $isstack;
                        if ($old_value == 1) {
                            $isoldstack = 'Button On';
                        } else {
                            $isoldstack = 'Button Off';
                        }
                        $old_value = $isoldstack;
                    }
                    if ($logs->subject_type == \App\Models\OverridesType::class) {
                        if ($array_key == 'override_id') {
                            $overrideName = OverridesType::where('id', $new_value)->value('overrides_type');
                            $new_value = $overrideName;
                        }
                    }
                    if ($logs->subject_type == \App\Models\EmailNotificationSetting::class) {
                        if ($array_key == 'email_setting_type') {
                            // $emailoldNotification = EmailNotificationSetting::where('email_setting_type',$old_value)->first();
                            // if(!empty($emailoldNotification)){
                            //     if($emailoldNotification->email_setting_type == 1){
                            //         $emailNotificationOld = 'Active';
                            //     }else{
                            //         $emailNotificationOld = 'Inactive';
                            //     }
                            // }else{
                            //     $emailNotificationOld = 'Inactive';
                            // }
                            if ($old_value == 1) {
                                $emailNotificationOld = 'Active';
                            } else {
                                $emailNotificationOld = 'Inactive';
                            }
                            $old_value = $emailNotificationOld;

                            // $emailNewNotification = EmailNotificationSetting::where('email_setting_type',$new_value)->first();
                            // if(!empty($emailNewNotification)){
                            //     if($emailNewNotification->email_setting_type == 1){
                            //         $emailNotificationNew = 'Active';
                            //     }else{
                            //         $emailNotificationNew = 'Inactive';
                            //     }
                            // }else{
                            //     $emailNotificationNew = 'Inactive';
                            // }
                            if ($new_value == 1) {
                                $emailNotificationNew = 'Active';
                            } else {
                                $emailNotificationNew = 'Inactive';
                            }
                            $new_value = $emailNotificationNew;
                            $array_key = 'Allow all domains';
                        }
                        if ($array_key == 'status') {
                            $array_key = 'Email';
                        }
                    }

                    if ($logs->subject_type == \App\Models\Announcement::class) {
                        if ($array_key == 'user_id') {
                            $new_value = $this->usersData($new_value);
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'positions') {
                            $new_value = $this->positionStatus($new_value);
                            $old_value = $this->positionStatus($old_value);
                        }
                        if ($array_key == 'office') {
                            $new_value = $this->officeStatus($new_value);
                            $old_value = $this->officeStatus($old_value);
                        }

                    }
                    if ($logs->subject_type == \App\Models\AdditionalRecruiters::class) {
                        if ($array_key == 'hiring_id') {
                            $new_value = $this->usersData($new_value);
                        }
                    }
                    if ($logs->subject_type == \App\Models\OnboardingEmployees::class) {
                        if ($array_key == 'hiring_id') {
                            $new_value = $this->usersData($new_value);
                        }
                    }

                    if ($logs->subject_type == \App\Models\ApprovalsAndRequest::class) {
                        if ($array_key == 'status') {
                            $ApprovalsStatus = ApprovalsAndRequest::where('status', $new_value)->value('status');
                            $new_value = $ApprovalsStatus;

                            $ApprovalsoldStatus = ApprovalsAndRequest::where('status', $old_value)->value('status');
                            $old_value = $ApprovalsoldStatus;
                        }
                    }

                    if ($logs->subject_type == \App\Models\CompanySetting::class || $logs->subject_type == \App\Models\DomainSetting::class) {
                        if ($array_key == 'status') {
                            $array_key = isset($properties->setting_type) ? ($properties->setting_type) : 'Status';
                        }
                    }

                    if ($logs->subject_type == \App\Models\payFrequencySetting::class) {
                        if ($array_key == 'frequency_type_id') {
                            $array_key = 'frequency_type';
                            $new_value = FrequencyType::where('id', $new_value)->value('name');
                            $old_value = FrequencyType::where('id', $old_value)->value('name');
                        }
                        if ($array_key == 'deadline_to_run_payroll') {
                            $new_value = date('m/d/Y', strtotime($new_value));
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }

                        if ($array_key == 'monthly_pay_type') {
                            if ($new_value == 'fifteenAndLastDayOfMonth') {
                                $new_value = '15th and last day of month';
                            }
                            if ($old_value == 'fifteenAndLastDayOfMonth') {
                                $old_value = '15th and last day of month';
                            }
                        }
                        if ($array_key == 'first_day_pay_of_manths') {
                            $array_key = 'first_day_pay_of_months';
                        }
                    }

                    if ($logs->subject_type == \App\Models\AdvancePaymentSetting::class) {
                        if ($array_key == 'adwance_setting') {
                            $array_key = 'Advance setting';
                        }
                    }

                    if ($logs->subject_type == \App\Models\overrideSystemSetting::class) {
                        if ($array_key == 'allow_office_stack_override_status' || $array_key == 'allow_manual_override_status') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Active';
                            } else {
                                $old_value = 'Inactive';
                            }

                        }
                    }

                    if ($logs->subject_type == \App\Models\SClearanceScreeningRequestList::class) {
                        if ($array_key == 'approved_declined_by') {
                            $new_value = $this->usersData($new_value);
                            if ($properties->attributes->status == 'Approved') {
                                $array_key = 'Approved by';
                            } elseif ($properties->attributes->status == 'Declined') {
                                $array_key = 'Declined by';
                            }

                        }
                        if ($array_key == 'status') {
                            $new_value = $new_values->$array_key;
                            $old_value = $old_values->$array_key;
                        }

                        if ($array_key == 'plan_id') {
                            $new_value = $this->getPlanName($new_value);
                            $old_value = $this->getPlanName($old_value);
                        }

                        if ($array_key == 'is_manual_verification' || $array_key == 'is_report_generated') {
                            if ($new_value == 1) {
                                $new_value = 'Yes';
                            } else {
                                $new_value = 'No';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Yes';
                            } else {
                                $old_value = 'No';
                            }
                        }
                    }

                    if ($logs->subject_type == \App\Models\SClearanceConfiguration::class) {
                        if ($array_key == 'hiring_status') {
                            if ($new_value == 1) {
                                $new_value = 'Pre Hiring';
                            } else {
                                $new_value = 'Post Hiring';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Pre Hiring';
                            } else {
                                $old_value = 'Post Hiring';
                            }
                        }
                        if ($array_key == 'is_mandatory') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Active';
                            } else {
                                $old_value = 'Inactive';
                            }
                        }

                        if ($array_key == 'is_approval_required') {
                            if ($new_value == 1) {
                                $new_value = 'Active';
                            } else {
                                $new_value = 'Inactive';
                            }

                            if ($old_value == 1) {
                                $old_value = 'Active';
                            } else {
                                $old_value = 'Inactive';
                            }
                        }
                    }

                    if ($logs->subject_type == \App\Models\CompanyProfile::class) {
                        if ($array_key == 'company_margin') {
                            $new_value = ! empty($new_value) ? $new_value.'%' : '0%';
                            $old_value = ! empty($old_value) ? $old_value.'%' : '0%';
                        }
                    }

                    if ($logs->subject_type != \App\Models\SClearanceScreeningRequestList::class || $logs->subject_type != \App\Models\CompanyProfile::class) {
                        if (($old_value == null) || ($old_value == '')) {
                            $old_value = 'Blank‌';
                        }
                        if (($new_value == null) || ($new_value == '')) {
                            $new_value = 'Blank‌';
                        }
                    }
                    if ($array_key == 'user_id') {
                        $array_key = 'User name';
                    }
                    if ($array_key == 'position_id') {
                        $array_key = 'Position';
                    }
                    if ($array_key == 'override_id') {
                        $array_key = 'Override';
                    }
                    if ($logs->subject_type != \App\Models\payFrequencySetting::class) {
                        // Handle $new_value
                        if (! is_string($new_value)) {
                            // Check if $new_value is an array
                            if (is_array($new_value)) {
                                // Handle the case where $new_value is an array (optional: convert to string)
                                $new_value = json_encode($new_value); // Convert the array to a JSON string
                            } else {
                                // If $new_value is not an array, proceed with original logic
                                $unix = strtotime($new_value);
                                if ($unix !== false && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $new_value)) {
                                    $new_value = date('m/d/Y | H:i', $unix);
                                }
                            }
                        }

                        // Handle $old_value
                        if (! is_string($old_value)) {
                            // Check if $old_value is an array
                            if (is_array($old_value)) {
                                // Handle the case where $old_value is an array (optional: convert to string)
                                $old_value = json_encode($old_value); // Convert the array to a JSON string
                            } else {
                                // If $old_value is not an array, proceed with original logic
                                $unix = strtotime($old_value);
                                if ($unix !== false && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $old_value)) {
                                    $old_value = date('m/d/Y | H:i', $unix);
                                }
                            }
                        }
                    }

                    $user[$key]['properties'][] = ['old_value' => ($old_value), 'new_value' => ($new_value), 'modified_key' => str_replace('_', ' ', ucfirst($array_key))];
                }
                if ($logs->subject_type == \App\Models\OnboardingEmployees::class) {
                    $userName = OnboardingEmployees::where('id', $logs->subject_id)->select('first_name', 'last_name')->first();
                    if (! empty($userName)) {
                        $userDetails = [
                            'old_value' => $userName->first_name.' '.$userName->last_name,
                            'new_value' => $userName->first_name.' '.$userName->last_name,
                            'modified_key' => 'User',
                        ];
                        array_unshift($user[$key]['properties'], $userDetails);
                    }
                }
                if ($logs->subject_type == \App\Models\SClearanceTurnScreeningRequestList::class) {
                    $userName = SClearanceTurnScreeningRequestList::where('id', $logs->subject_id)->select('first_name', 'last_name')->first();
                    if (! empty($userName)) {
                        $userDetails = [
                            'old_value' => $userName->first_name.' '.$userName->last_name,
                            'new_value' => $userName->first_name.' '.$userName->last_name,
                            'modified_key' => 'User',
                        ];
                        array_unshift($user[$key]['properties'], $userDetails);
                    }
                }
                if ($logs->subject_type == \App\Models\SClearanceScreeningRequestList::class) {
                    $userName = SClearanceScreeningRequestList::where('id', $logs->subject_id)->select('first_name', 'last_name')->first();
                    if (! empty($userName)) {
                        $userDetails = [
                            'old_value' => $userName->first_name.' '.$userName->last_name,
                            'new_value' => $userName->first_name.' '.$userName->last_name,
                            'modified_key' => 'User',
                        ];
                        array_unshift($user[$key]['properties'], $userDetails);
                    }
                }
                if ($logs->subject_type == \App\Models\SClearanceConfiguration::class) {
                    if ($logs->subject_id > 1) {
                        $type = 'Custom';
                    } else {
                        $type = 'Default';
                    }

                    if (! array_key_exists('position_id', json_decode(json_encode($new_values), true))) {
                        $configData = SClearanceConfiguration::where('id', $logs->subject_id)->select('position_id')->first();
                        if (! empty($configData)) {
                            $positionName = $this->positionName($configData->position_id);
                            $positionDetails = [
                                'old_value' => empty($positionName) ? 'All' : $positionName,
                                'new_value' => empty($positionName) ? 'All' : $positionName,
                                'modified_key' => 'Position',
                            ];

                            array_unshift($user[$key]['properties'], $positionDetails);
                        }
                    }

                    $typeDetails = [
                        'old_value' => $type,
                        'new_value' => $type,
                        'modified_key' => 'Setting Type',
                    ];

                    array_unshift($user[$key]['properties'], $typeDetails);

                }
            }
            if ($action == 'deleted') {
                if (is_array($old_values) || is_object($old_values)) {
                    foreach ($old_values as $array_key => $array_data) {
                        $old_value = (! empty($old_values->$array_key)) ? $old_values->$array_key : null;
                        $new_value = (! empty($new_values->$array_key)) ? $new_values->$array_key : null;
                        if ($array_key == 'created_at' || $array_key == 'updated_at' || $array_key == 'cost_center_id' || $array_key == 'cost_tracking_id' || $array_key == 'id' || $array_key == 'password' || $array_key == 'pdf_file_other_parameter' || $old_value == $new_value) {
                            continue;
                        }
                        if ($array_key == 'position_id') {
                            $old_value = $this->positionName($old_value);
                        }
                        if ($array_key == 'cost_center_id') {
                            $old_value = $this->costCenterName($old_value);
                        }
                        if ($array_key == 'setter1_m1_paid_status') {
                            $old_value = $this->SaleMasterProcess($old_value);
                        }
                        if ($array_key == 'closer1_m1_paid_status') {
                            $old_value = $this->SaleMasterProcess($old_value);
                        }
                        if ($array_key == 'setter1_m2_paid_status') {
                            $old_value = $this->SaleMasterProcess($old_value);
                        }
                        if ($array_key == 'closer1_m2_paid_status') {
                            $old_value = $this->SaleMasterProcess($old_value);
                        }
                        if ($array_key == 'mark_account_status_id') {
                            $old_value = $this->SaleMasterProcess($old_value);
                        }
                        if ($array_key == 'start_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'end_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'pay_period_from') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'pay_period_to') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'cost_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'office') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'commission_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'customer_signoff') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'withheld_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'period_of_agreement_start_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'offer_expiry_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'm1_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'self_gen_commission_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'upfront_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'self_gen_upfront_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'self_gen_withheld_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'override_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'm2_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'm1_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'date_cancelled') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'self_gen_redline_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'redline_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'dob') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'transfer_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'manager_id_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'is_manager_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'team_id_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'position_id_effective_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'document_send_date') {
                            $old_value = date('m/d/Y', strtotime($old_value));
                        }
                        if ($array_key == 'setter_id') {
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'closer_id') {
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'updater_id') {
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'sale_user_id') {
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'document_id') {
                            $old_value = $this->documentStatus($old_value);
                        }
                        if ($array_key == 'department_id') {
                            $old_value = $this->departmentName($new_value);
                        }
                        if ($array_key == 'department_id') {
                            $old_value = $this->departmentName($new_value);
                        }
                        if ($array_key == 'sub_position_id') {
                            $old_value = $this->positionName($old_value);
                        }
                        if ($array_key == 'recruiter_id') {
                            $old_value = $this->recruiterName($old_value);
                        }
                        if ($array_key == 'manager_id') {
                            $old_value = $this->managerName($old_value);
                        }
                        if ($array_key == 'additional_recruiter_id1') {
                            $old_value = $this->additionalRecruiter1($old_value);
                        }
                        if ($array_key == 'additional_recruiter_id2') {
                            $old_value = $this->additionalRecruiter2($old_value);
                        }
                        if ($array_key == 'recipient_sign_req') {
                            $old_value = $this->recipientSignReq($old_value);
                        }
                        if ($array_key == 'completed_step') {
                            $old_value = $this->completedStep($old_value);
                        }
                        if ($array_key == 'is_template_ready') {
                            $old_value = $this->isTemplateReady($old_value);
                        }
                        if ($array_key == 'is_pdf') {
                            $old_value = $this->ispdf($old_value);
                        }
                        if ($array_key == 'category_id') {
                            $old_value = $this->templateCategories($old_value);
                        }
                        if ($array_key == 'created_by') {
                            $old_value = $this->usersData($old_value);
                        }
                        if ($array_key == 'is_manager') {
                            $old_value = $this->isManager($old_value);
                        }
                        if ($array_key == 'is_next_payroll') {
                            $old_value = $this->isNextPayroll($old_value);
                        }
                        if ($array_key == 'OnboardProcess') {
                            $old_value = $this->onboardProcess($old_value);
                        }
                        if ($array_key == 'old_state_id') {
                            $old_value = $this->oldStateId($old_value);
                        }
                        if ($array_key == 'old_office_id') {
                            $old_value = $this->oldOfficeId($old_value);
                        }
                        if ($array_key == 'old_department_id') {
                            $old_value = $this->oldDepartmentName($old_value);
                        }
                        if ($array_key == 'old_position_id') {
                            $old_value = $this->oldPositionName($old_value);
                        }
                        if ($array_key == 'old_sub_position_id') {
                            $old_value = $this->oldSubPositionName($old_value);
                        }
                        if ($array_key == 'old_team_id') {
                            $old_value = $this->oldTeamName($old_value);
                        }
                        if ($array_key == 'old_self_gen_accounts') {
                            $old_value = $this->oldSelfGenAccount($old_value);
                        }
                        if ($array_key == 'old_manager_id') {
                            $old_value = $this->oldManager($old_value);
                        }
                        if ($array_key == 'old_is_manager') {
                            $old_value = $this->oldIsManager($old_value);
                        }
                        if ($array_key == 'reporting_manager_id') {
                            $old_value = $this->reportingManager($old_value);
                        }
                        if ($array_key == 'sub_position_type') {
                            $old_value = $this->subPositionType($old_value);
                        }
                        if ($array_key == 'additional_recruiter') {
                            $old_value = $this->additionalRecruiter($old_value);
                        }
                        if ($logs->subject_type == \App\Models\OnboardingEmployees::class) {
                            if ($array_key == 'status_id') {
                                $old_value = $this->UserStatus($old_value);
                            }
                        }
                        if ($array_key == 'office_id') {
                            $old_value = $this->locationName($old_value);
                        }
                        if ($array_key == 'everee_payment_status') {
                            if ($old_value == 1) {
                                $evereeStatus = 'default';
                            } elseif ($old_value == 2) {
                                $evereeStatus = 'Fail';
                            } else {
                                $evereeStatus = 'Success';
                            }
                            $old_value = $evereeStatus;
                        }
                        if ($array_key == 'is_stack') {
                            if ($old_value == 1) {
                                $isoldstack = 'Button On';
                            } else {
                                $isoldstack = 'Button Off';
                            }
                            $old_value = $isoldstack;
                        }
                        if ($array_key == 'status') {
                            if ($old_value == 1) {
                                $statusold = 'Active';
                            } else {
                                $statusold = 'Inactive';
                            }
                            $old_value = $statusold;
                        }
                        if ($array_key == 'state_id') {
                            $stateName = State::where('id', $old_value)->value('name');
                            $old_value = $stateName;
                        }
                        if ($array_key == 'stop_payroll') {
                            if ($old_value == 1) {
                                $stopoldPayroll = 'Stop Payroll';
                            } else {
                                $stopoldPayroll = 'Not Stop Payroll';
                            }
                            $old_value = $stopoldPayroll;
                        }
                        if ($array_key == 'OnboardProcess') {
                            if ($old_value == 1) {
                                $onboardOldProcess = 'User onboarding';
                            } else {
                                $onboardOldProcess = 'Blank';
                            }
                            $old_value = $onboardOldProcess;
                        }
                        if ($logs->subject_type == \App\Models\Announcement::class) {
                            if ($array_key == 'user_id') {
                                $old_value = $this->usersData($old_value);
                            }
                            if ($array_key == 'positions') {
                                $old_value = $this->positionStatus($old_value);
                            }
                        }
                        if ($logs->subject_type == \App\Models\Locations::class) {
                            if ($array_key == 'everee_location_id') {
                                $evereeLocation = Locations::where('everee_location_id', $new_value)->value('office_name');
                                $new_value = $evereeLocation;
                            }
                        }
                        if ($logs->subject_type == \App\Models\ApprovalsAndRequest::class) {
                            if ($array_key == 'status') {
                                $ApprovalsoldStatus = ApprovalsAndRequest::where('status', $old_value)->value('status');
                                $old_value = $ApprovalsoldStatus;
                            }
                        }
                        if ($logs->subject_type == \App\Models\payFrequencySetting::class) {
                            if ($array_key == 'frequency_type_id') {
                                $array_key = 'frequency_type';
                                $old_value = FrequencyType::where('id', $old_value)->value('name');
                            }
                            if ($array_key == 'deadline_to_run_payroll') {
                                $old_value = date('m/d/Y', strtotime($old_value));
                            }

                            if ($array_key == 'monthly_pay_type') {
                                if ($old_value == 'fifteenAndLastDayOfMonth') {
                                    $old_value = '15th and last day of month';
                                }
                            }
                            if ($array_key == 'first_day_pay_of_manths') {
                                $array_key = 'first_day_pay_of_months';
                            }
                        }

                        if ($logs->subject_type == \App\Models\overrideSystemSetting::class) {
                            if ($array_key == 'allow_office_stack_override_status' || $array_key == 'allow_manual_override_status') {
                                if ($old_value == 1) {
                                    $old_value = 'Active';
                                } else {
                                    $old_value = 'Inactive';
                                }

                            }
                        }

                        if ($logs->subject_type == \App\Models\SClearanceConfiguration::class) {
                            if ($array_key == 'hiring_status') {
                                if ($old_value == 1) {
                                    $old_value = 'Pre Hiring';
                                } else {
                                    $old_value = 'Post Hiring';
                                }
                            }

                            if ($array_key == 'is_mandatory') {
                                if ($old_value == 1) {
                                    $old_value = 'Active';
                                } else {
                                    $old_value = 'Inactive';
                                }
                            }

                            if ($array_key == 'is_approval_required') {
                                if ($old_value == 1) {
                                    $old_value = 'Active';
                                } else {
                                    $old_value = 'Inactive';
                                }
                            }
                        }

                        if ($array_key == 'user_id') {
                            $array_key = 'User name';
                        }
                        if ($array_key == 'position_id') {
                            $array_key = 'Position';
                        }
                        if ($array_key == 'override_id') {
                            $array_key = 'Override';
                        }
                        if ($logs->subject_type != \App\Models\payFrequencySetting::class) {
                            $unix = strtotime($old_value);
                            if ($unix !== false && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $old_value)) {
                                $old_value = date('m/d/Y | H:i', $unix);
                            }
                        }
                        $user[$key]['properties'][] = ['old_value' => strip_tags($old_value), 'modified_key' => str_replace('_', ' ', ucfirst($array_key))];
                    }
                } else {
                    // Handle the case where $old_values is not an array or object
                    Log::error('Expected $old_values to be an array or object, got: '.gettype($old_values));
                }
            }
        }

        //       $response = paginate($user,$perpage);
        $response = $userActivityLogs;
        $response['data'] = $user;
        $lastId = $response['data'][count($response['data']) - 1]['id'] ?? null;
        $hasNextPage = count($response['data']) === (int) $perpage;
        $currentPage = (int) ($request->page ?? 1);
        $responseData = [
            'current_page' => $currentPage,
            'data' => $response['data'],
            'first_page_url' => url('/api/archive-activity-logs?page=1'),
            'from' => ($request->page ?? 1) * $perpage - $perpage + 1,
            'last_page' => ceil($total / $perpage),
            'last_page_url' => url('/api/archive-activity-logs?page='.ceil($total / $perpage)),
            'next_page_url' => $hasNextPage && $lastId ? url('/api/archive-activity-logs?last_id='.$lastId.'&page='.($currentPage + 1)) : null,
            'path' => url('/api/archive-activity-logs'),
            'per_page' => (int) $perpage,
            'prev_page_url' => $currentPage > 1 ? url('/api/archive-activity-logs?page='.($currentPage - 1)) : null,
            'to' => min(($request->page ?? 1) * $perpage, $total),
            'total' => $total,
        ];

        return response()->json([
            'ApiName' => 'User-activity-log',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $responseData,
        ], 200);
    }

    public function usersData($user_id)
    {
        $userName = User::where('id', $user_id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function positionName($id)
    {
        $positionName = Positions::where('id', $id)->value('position_name');

        return $positionName;
    }

    public function costCenterName($cost_center_id)
    {
        return CostCenter::where('id', $cost_center_id)->value('name');
    }

    public function oldPositionName($posid)
    {
        $positionoldName = Positions::where('id', $posid)->value('position_name');

        return $positionoldName;
    }

    public function oldSubPositionName($sub_posid)
    {
        $oldPositionName = Positions::where('id', $sub_posid)->value('position_name');

        return $oldPositionName;
    }

    public function departmentName($dep_id)
    {
        $departmentName = Department::where('id', $dep_id)->value('name');
        $depName = isset($departmentName) ? $departmentName : '';

        return $depName;
    }

    public function oldDepartmentName($depar_id)
    {
        $departmentOldName = Department::where('id', $depar_id)->value('name');
        $depOldName = isset($departmentOldName) ? $departmentOldName : '';

        return $depOldName;
    }

    public function HiringStatus($id)
    {
        $hiringName = HiringStatus::where('id', $id)->value('status');
        if (! empty($hiringName)) {
            $hiringStatus = $hiringName;
        } else {
            $hiringStatus = '';
        }

        return $hiringStatus;
    }

    public function positionStatus($id)
    {
        $positionStatus = [];
        $arrayPosition = explode(',', $id);
        $positionNames = Positions::whereIn('id', $arrayPosition)->get();
        foreach ($positionNames as $key => $position) {
            $positionStatus[] = $position->position_name;
        }

        return implode(',', $positionStatus);
    }

    public function officeStatus($id)
    {
        $officeStatus = [];
        $arrayLocation = explode(',', $id);
        $locationNames = Locations::whereIn('id', $arrayLocation)->get();
        foreach ($locationNames as $key => $location) {
            $officeStatus[] = $location->office_name;
        }

        return implode(',', $officeStatus);
    }

    public function SaleMasterProcess($id)
    {
        $saleStatus = MarkAccountStatus::where('id', $id)->value('account_status');
        if (! empty($saleStatus) || $saleStatus != '') {
            return $saleStatus;
        } else {
            return 'Status not found.';
        }
    }

    public function UserStatus($id)
    {
        $payStatus = UserStatus::where('id', $id)->value('status');

        return $payStatus;
    }

    public function documentStatus($id)
    {
        $documentStatus = Documents::where('id', $id)->value('description');

        return $documentStatus;
    }

    public function locationName($id)
    {
        $locationName = Locations::where('id', $id)->value('office_name');

        return $locationName;
    }

    public function recruiterName($id)
    {
        $userName = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function managerName($id)
    {
        $userName = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function additionalRecruiter1($id)
    {
        $userName = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function additionalRecruiter2($id)
    {
        $userName = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function additionalRecruiter($id)
    {
        $userName = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $username = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    public function recipientSignReq($recipient_id)
    {
        if ($recipient_id == 1) {
            $recipientSignReq = 'Not required';

            return $recipientSignReq;
        } else {
            return 'Required.';
        }
    }

    public function completedStep($step)
    {
        if ($step == 1) {
            $stepReq = $step.'.step';

            return $stepReq;
        } elseif ($step == 2) {
            $stepReq = $step.'.step';

            return $stepReq;
        } elseif ($step == 3) {
            $stepReq = $step.'.step';

            return $stepReq;
        } elseif ($step == 4) {
            $stepReq = $step.'.step';

            return $stepReq;
        } else {
            return 'Not step.';
        }
    }

    public function isTemplateReady($templateStatus)
    {
        if ($templateStatus == 1) {
            $template = 'Ready';

            return $template;
        } else {
            return 'Not ready.';
        }
    }

    public function ispdf($ispdf)
    {
        if ($ispdf == 1) {
            $ispdfStatus = 'PDF template';

            return $ispdfStatus;
        } else {
            return 'HTML template.';
        }
    }

    public function templateCategories($cat_id)
    {
        $categories = SequiDocsTemplateCategories::where('id', $cat_id)->value('categories');
        if (! empty($categories)) {
            $category = $categories;

            return $category;
        } else {
            return 'Categories not found.';
        }
    }

    public function isManager($is_manager)
    {
        if ($is_manager == 1) {
            $isManager = 'Manager';

            return $isManager;
        } else {
            return 'Blank.';
        }
    }

    public function isNextPayroll($next_payroll)
    {
        if ($next_payroll == 1) {
            $isManager = 'Move to next payroll';

            return $isManager;
        } else {
            return '-';
        }
    }

    public function onboardProcess($onboard)
    {
        if ($onboard == 1) {
            $onboardProcess = 'User onboarding';
        } else {
            $onboardProcess = 'Blank';
        }

        return $onboardProcess;
    }

    public function oldStateId($state_id)
    {
        $oldstateName = State::where('id', $state_id)->value('name');

        return $oldstateName;
    }

    public function oldOfficeId($office_id)
    {
        $oldOfficeName = Locations::where('id', $office_id)->value('office_name');

        return $oldOfficeName;
    }

    public function oldTeamName($old_team_id)
    {
        $oldOfficeName = DB::table('management_teams')->where('id', $old_team_id)->value('team_name');

        return isset($oldOfficeName) ? $oldOfficeName : '';
    }

    public function oldSelfGenAccount($old_self_id)
    {
        if ($old_self_id == 1) {
            $oldselfstatus = 'Active';
        } else {
            $oldselfstatus = 'Inactive';
        }

        return $oldselfstatus;
    }

    public function oldIsManager($old_is_manager)
    {
        if ($old_is_manager == 1) {
            $oldmanage = 'Manager';
        } else {
            $oldmanage = 'Not manager';
        }

        return $oldmanage;
    }

    public function oldManager($old_maneger)
    {
        $userName = User::where('id', $old_maneger)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $oldManager = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $oldManager;
        } else {
            return '-';
        }
    }

    /**
     * Generate pagination links similar to Laravel's paginator
     */
    protected function generatePaginationLinks(int $currentPage, int $lastPage): array
    {
        $links = [];
        $path = url('/api/clickhouse-activity-logs');

        // Previous link
        $links[] = [
            'url' => $currentPage > 1 ? $path.'?page='.($currentPage - 1) : null,
            'label' => '&laquo; Previous',
            'active' => false,
        ];

        // First few pages
        $windowSize = 10;
        $window = min($windowSize, $lastPage);

        for ($i = 1; $i <= $window; $i++) {
            $links[] = [
                'url' => $path.'?page='.$i,
                'label' => (string) $i,
                'active' => $i === $currentPage,
            ];
        }

        // Dots if needed
        if ($lastPage > $windowSize) {
            $links[] = [
                'url' => null,
                'label' => '...',
                'active' => false,
            ];

            // Last two pages
            if ($lastPage - 1 > $windowSize) {
                $links[] = [
                    'url' => $path.'?page='.($lastPage - 1),
                    'label' => (string) ($lastPage - 1),
                    'active' => ($lastPage - 1) === $currentPage,
                ];
            }

            $links[] = [
                'url' => $path.'?page='.$lastPage,
                'label' => (string) $lastPage,
                'active' => $lastPage === $currentPage,
            ];
        }

        // Next link
        $links[] = [
            'url' => $currentPage < $lastPage ? $path.'?page='.($currentPage + 1) : null,
            'label' => 'Next &raquo;',
            'active' => false,
        ];

        return $links;
    }

    public function reportingManager($maneger_id)
    {
        $userName = User::where('id', $maneger_id)->select('first_name', 'last_name')->first();
        if (! empty($userName || $userName != '')) {
            $reportingManager = ((! empty($userName->first_name)) ? $userName->first_name : '').' '.((! empty($userName->last_name)) ? $userName->last_name : '');

            return $reportingManager;
        } else {
            return '';
        }
    }

    public function subPositionType($id)
    {
        $subPositionNames = Positions::where('id', $id)->value('position_name');

        return $subPositionNames;
    }

    public function updatedBy($id)
    {
        $updatedBy = User::where('id', $id)->select('first_name', 'last_name')->first();
        if (! empty($updatedBy || $updatedBy != '')) {
            $username = ((! empty($updatedBy->first_name)) ? $updatedBy->first_name : '').' '.((! empty($updatedBy->last_name)) ? $updatedBy->last_name : '');

            return $username;
        } else {
            return '';
        }
    }

    /**
     * Fetch activity logs directly from ClickHouse
     * No fallback to MySQL, uses same connection approach as SyncClickhouseActivityLog
     */
    public function clickhouseActivityLog(Request $request): JsonResponse
    {
        // Validate all input parameters
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'start_date' => 'nullable|date|after_or_equal:2020-01-01',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1|max:1000',
            'perpage' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'clickhouse-activity-logs',
                'status' => false,
                'message' => 'Invalid input parameters.',
                'errors' => $validator->errors(),
                'data' => [],
            ], 422);
        }

        // Sanitize and set validated parameters
        $perpage = $request->perpage ?? 10;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Sanitize search input
        $search = null;
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = preg_replace('/[^\w\s\-@.]/', '', $request->input('search'));
            if (strlen($search) < 2) {
                return response()->json([
                    'ApiName' => 'clickhouse-activity-logs',
                    'status' => false,
                    'message' => 'Search term must be at least 2 characters.',
                    'data' => [],
                ], 422);
            }
        }

        try {
            // Use ClickHouseConnectionService to eliminate code duplication
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                Log::error('[ClickHouse API] Failed to establish ClickHouse connection.');

                return response()->json([
                    'ApiName' => 'clickhouse-activity-logs',
                    'status' => false,
                    'message' => 'Database connection failed',
                    'data' => [],
                ], 500);
            }

            // Get database name from config
            $database = config('clickhouse.connections.default.database');
            if (! $database) {
                Log::error('[ClickHouse API] ClickHouse database not configured.');

                return response()->json([
                    'ApiName' => 'clickhouse-activity-logs',
                    'status' => false,
                    'message' => 'Database configuration error',
                    'data' => [],
                ], 500);
            }

            $client->database($database);
            Log::info('[ClickHouse API] Successfully connected to ClickHouse database.');

            // Build query with filters
            $query = 'SELECT * FROM activity_log';
            $conditions = [];
            $parameters = [];

            if ($startDate != '' && $endDate != '') {
                $conditions[] = 'toDate(created_at) BETWEEN {start_date:Date} AND {end_date:Date}';
                $parameters['start_date'] = $startDate;
                $parameters['end_date'] = $endDate;
            }

            if ($search) {
                $conditions[] = '(log_name LIKE {search:String} OR description LIKE {search:String} OR subject_type LIKE {search:String} OR subject_id LIKE {search:String} OR event LIKE {search:String})';
                $parameters['search'] = '%'.$search.'%';

                // Note: Cannot do CONCAT search for user names like in MySQL
                // We'll handle this in post-processing if needed
            }

            if (! empty($conditions)) {
                $query .= ' WHERE '.implode(' AND ', $conditions);
            }

            // Get total count for pagination
            $countQuery = 'SELECT count() as total FROM activity_log';
            if (! empty($conditions)) {
                $countQuery .= ' WHERE '.implode(' AND ', $conditions);
            }

            $totalResult = $client->select($countQuery, $parameters)->rows();
            $total = $totalResult[0]['total'] ?? 0;

            // Implement cursor-based pagination for better performance with large datasets
            // Instead of OFFSET/LIMIT, use ID-based filtering which is much faster
            $lastId = $request->input('last_id', 0); // Cursor for pagination
            $page = (int) ($request->page ?? 1);

            // If no cursor provided but page > 1, calculate cursor from page number
            if ($lastId == 0 && $page > 1) {
                // Calculate how many records to skip
                $recordsToSkip = ($page - 1) * $perpage;

                // Get the ID at the skip position to use as cursor
                $cursorQuery = 'SELECT id FROM activity_log';
                $cursorConditions = $conditions; // Use same filters as main query
                $cursorParameters = $parameters;

                if (! empty($cursorConditions)) {
                    $cursorQuery .= ' WHERE '.implode(' AND ', $cursorConditions);
                }
                $cursorQuery .= ' ORDER BY id DESC LIMIT 1 OFFSET {skip:UInt32}';
                $cursorParameters['skip'] = $recordsToSkip;

                try {
                    $cursorResult = $client->select($cursorQuery, $cursorParameters)->rows();
                    if (! empty($cursorResult)) {
                        $lastId = $cursorResult[0]['id'];
                    }
                } catch (\Exception $e) {
                    // If cursor calculation fails, fall back to no cursor (will show first page)
                    Log::warning('[ClickHouse API] Cursor calculation failed: '.$e->getMessage());
                    $lastId = 0;
                }
            }

            if ($lastId > 0) {
                // Cursor-based pagination: get records with ID less than last_id
                $conditions[] = 'id < {last_id:String}';
                $parameters['last_id'] = (string) $lastId;
            }

            // Complete query with cursor-based pagination (much faster than OFFSET)
            if (! empty($conditions)) {
                $query .= ' WHERE '.implode(' AND ', $conditions);
            }
            $query .= ' ORDER BY id DESC LIMIT {limit:UInt32}';
            $parameters['limit'] = (int) $perpage;

            $logs = $client->select($query, $parameters)->rows();

            // Get the last ID for next page cursor
            $nextCursor = null;
            if (! empty($logs)) {
                $lastRecord = end($logs);
                $nextCursor = $lastRecord['id']; // Access as array key, not object property
            }

            // Format logs like the MySQL implementation
            $user = [];
            foreach ($logs as $key => $log) {
                // Get user details from MySQL (since we need joins)
                $causer = DB::table('users')
                    ->select('id', 'first_name', 'last_name', 'image')
                    ->where('id', $log['causer_id'])
                    ->first();

                $fname = isset($causer->first_name) ? $causer->first_name : ' ';
                $lname = isset($causer->last_name) ? $causer->last_name : ' ';

                // Get subject/change details
                if ($log['subject_type'] == \App\Models\User::class) {
                    $change = DB::table('users')->where('id', $log['subject_id'])->first();
                    $firstname = isset($change->first_name) ? $change->first_name : ' ';
                    $lastname = isset($change->last_name) ? $change->last_name : ' ';
                    $emp = $firstname.' '.$lastname;
                    if (isset($change->image) && $change->image != null) {
                        $s3_image = $this->getS3ImageUrl($change->image);
                    } else {
                        $s3_image = null;
                    }
                } else {
                    $replace = str_replace("App\Models", '', $log['subject_type']);
                    $events = str_replace('\\', '', $replace);
                    $emp = $events;
                    $s3_image = null;
                }

                // Build base log entry
                $user[$key]['user_id'] = $log['causer_id'];
                $user[$key]['action_by'] = $fname.' '.$lname;
                $user[$key]['log_name'] = $log['log_name'];
                $user[$key]['subject'] = $log['subject_type'];
                $user[$key]['changes_id'] = $log['subject_id'];
                $user[$key]['change'] = $emp;
                $user[$key]['user_image_s3'] = $s3_image;
                $user[$key]['created_at'] = $log['created_at'];

                // Process properties
                $properties = json_decode($log['properties'] ?? '{}', true);

                // Handle special case for is_deleted flag
                $attributeOption = isset($properties['attributes']['attribute_option']);
                if (isset($properties['attributes']['is_deleted']) && $properties['attributes']['is_deleted'] == 1) {
                    $event = 'deleted';
                    $description = 'deleted';
                } else {
                    $event = $log['event'];
                    $description = $log['description'];
                }
                $user[$key]['event'] = $event;
                $user[$key]['description'] = $description;

                // Enhanced processing for specific model types - add right after line 2671
                if (! empty($log['subject_type'])) {
                    switch ($log['subject_type']) {
                        case \App\Models\PositionTier::class:
                        case \App\Models\PositionTier::class:
                            // Get the position tier record
                            $positionTier = \App\Models\PositionTier::find($log['subject_id']);
                            if ($positionTier && $positionTier->position_id) {
                                $position = \App\Models\Positions::find($positionTier->position_id);
                                if ($position) {
                                    // Initialize properties if needed
                                    if (! isset($user[$key]['properties'])) {
                                        $user[$key]['properties'] = [];
                                    }

                                    // Add position name for reference
                                    array_unshift($user[$key]['properties'], [
                                        'old_value' => '',
                                        'new_value' => $position->position_name,
                                        'modified_key' => 'Position name',
                                    ]);
                                }
                            }
                            break;

                        case 'App\\Models\\Position':
                        case 'App\Models\Position':
                        case \App\Models\Positions::class:
                        case \App\Models\Positions::class:
                            // Add department name if department_id exists in the properties
                            $properties = json_decode($log['properties'] ?? '{}', true);
                            $attributes = $properties['attributes'] ?? [];
                            if (isset($attributes['department_id'])) {
                                $department = \App\Models\Department::find($attributes['department_id']);
                                if ($department) {
                                    // Initialize properties if needed
                                    if (! isset($user[$key]['properties'])) {
                                        $user[$key]['properties'] = [];
                                    }

                                    array_unshift($user[$key]['properties'], [
                                        'old_value' => '',
                                        'new_value' => $department->name,
                                        'modified_key' => 'Department',
                                    ]);
                                }
                            }
                            break;

                        case \App\Models\User::class:
                        case \App\Models\User::class:
                            // Add department and position details if available
                            $userModel = \App\Models\User::find($log['subject_id']);
                            if ($userModel) {
                                // Initialize properties if needed
                                if (! isset($user[$key]['properties'])) {
                                    $user[$key]['properties'] = [];
                                }

                                if ($userModel->department_id) {
                                    $department = \App\Models\Department::find($userModel->department_id);
                                    if ($department) {
                                        array_unshift($user[$key]['properties'], [
                                            'old_value' => '',
                                            'new_value' => $department->name,
                                            'modified_key' => 'Department',
                                        ]);
                                    }
                                }

                                if ($userModel->position_id) {
                                    $position = \App\Models\Positions::find($userModel->position_id);
                                    if ($position) {
                                        array_unshift($user[$key]['properties'], [
                                            'old_value' => '',
                                            'new_value' => $position->position_name,
                                            'modified_key' => 'Position',
                                        ]);
                                    }
                                }
                            }
                            break;
                    }
                }
                // Process properties with special handling for updated events
                if ($log['event'] === 'updated') {
                    $processed = [];

                    // Add OnboardingEmployees name first if applicable
                    if ($log['subject_type'] === \App\Models\OnboardingEmployees::class) {
                        $userName = \App\Models\OnboardingEmployees::where('id', $log['subject_id'])
                            ->select('first_name', 'last_name')
                            ->first();
                        if ($userName) {
                            $userDetails = [
                                'old_value' => $userName->first_name.' '.$userName->last_name,
                                'new_value' => $userName->first_name.' '.$userName->last_name,
                                'modified_key' => 'User',
                            ];
                            array_unshift($user[$key]['properties'], $userDetails);
                        }
                    }

                    // Extract attributes and old values
                    $attributes = $properties['attributes'] ?? [];
                    $oldValues = $properties['old'] ?? [];

                    // Process each field with proper formatting
                    foreach ($attributes as $propKey => $newValue) {
                        // Using propKey instead of key to avoid variable conflict
                        $oldValue = $oldValues[$propKey] ?? null;

                        // Skip system fields
                        if (in_array($propKey, ['id', 'created_at', 'updated_at', 'password'])) {
                            continue;
                        }

                        // Skip unchanged values
                        if ($oldValue === $newValue) {
                            continue;
                        }

                        // Format values based on field type
                        $formattedOldValue = $this->formatValue($oldValue, $propKey);
                        $formattedNewValue = $this->formatValue($newValue, $propKey);

                        $processed[] = [
                            'old_value' => $formattedOldValue,
                            'new_value' => $formattedNewValue,
                            'modified_key' => str_replace('_', ' ', ucfirst($propKey)),
                        ];
                    }

                    $user[$key]['properties'] = $processed;
                } else {
                    // For other events (created, deleted)
                    $user[$key]['properties'] = $this->processProperties($properties, $log);
                }
            }

            // Calculate pagination metadata
            $lastPage = ceil($total / $perpage);

            // Build response with pagination
            $responseData = [
                'current_page' => (int) ($request->page ?? 1),
                'data' => $user,
                'first_page_url' => url('/api/clickhouse-activity-logs?page=1'),
                'from' => ($request->page ?? 1) * $perpage - $perpage + 1,
                'last_page' => $lastPage,
                'last_page_url' => url('/api/clickhouse-activity-logs?page='.$lastPage),
                'next_page_url' => ($request->page ?? 1) < $lastPage ? url('/api/clickhouse-activity-logs?page='.(($request->page ?? 1) + 1)) : null,
                'path' => url('/api/clickhouse-activity-logs'),
                'per_page' => (int) $perpage,
                'prev_page_url' => ($request->page ?? 1) > 1 ? url('/api/clickhouse-activity-logs?page='.(($request->page ?? 1) - 1)) : null,
                'to' => min(($request->page ?? 1) * $perpage, $total),
                'total' => $total,
            ];

            return response()->json([
                'ApiName' => 'clickhouse-activity-logs',
                'status' => true,
                'message' => 'Successfully fetched activity logs from ClickHouse',
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('ClickHouse activity log error: '.$e->getMessage());
            Log::error($e->getTraceAsString());

            // Log more detailed error information
            Log::error('[ClickHouse API] Connection error: '.$e->getMessage());

            // Fall back to MySQL if ClickHouse fails
            Log::info('[ClickHouse API] Falling back to MySQL activity logs');

            return $this->activityLog($request);
        }
    }

    /**
     * Process properties to extract meaningful changes
     */
    private function processProperties(array $properties, array $log): array
    {
        Log::info('processProperties input', [
            'action' => $log['description'] ?? 'unknown',
            'subject_type' => $log['subject_type'] ?? null,
            'properties' => $properties,
        ]);

        $result = [];

        if (empty($properties)) {
            return $result;
        }

        $action = $log['description'] ?? 'unknown';

        // Defensive normalization for ClickHouse logs or inconsistent structures
        if ($action == 'updated') {
            // If properties is a string, try to decode
            if (is_string($properties)) {
                $decoded = json_decode($properties, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $properties = $decoded;
                }
            }
            // Ensure both keys exist and are arrays
            if (! isset($properties['attributes']) || ! is_array($properties['attributes'])) {
                $properties['attributes'] = [];
            }
            if (! isset($properties['old']) || ! is_array($properties['old'])) {
                $properties['old'] = [];
            }
            // Special handling for OnboardingEmployees if structure is still empty
            if (
                isset($log['subject_type']) &&
                $log['subject_type'] === \App\Models\OnboardingEmployees::class &&
                empty($properties['attributes']) && empty($properties['old'])
            ) {
                // Try to find alternate keys or structures (customize as needed)
                foreach ($properties as $k => $v) {
                    if (stripos($k, 'attribute') !== false && is_array($v)) {
                        $properties['attributes'] = $v;
                    }
                    if (stripos($k, 'old') !== false && is_array($v)) {
                        $properties['old'] = $v;
                    }
                }
            }
        }

        // Handle created event
        if ($action == 'created' && isset($properties['attributes'])) {
            $new_values = $properties['attributes'] ?? [];

            foreach ($new_values as $key => $value) {
                if (in_array($key, ['id', 'created_at', 'updated_at', 'password'])) {
                    continue;
                }

                $result[] = [
                    'old_value' => '',
                    'new_value' => $this->formatValue($value, $key),
                    'modified_key' => $this->formatKey($key),
                ];
            }
        }

        // Handle updated event
        if ($action == 'updated' && isset($properties['attributes']) && isset($properties['old'])) {
            $old_values = $properties['old'] ?? [];
            $new_values = $properties['attributes'] ?? [];
            foreach ($new_values as $key => $value) {
                if (in_array($key, ['id', 'created_at', 'updated_at', 'password'])) {
                    continue;
                }
                // Always show the change, even if old is null (show as "Blank")
                $result[] = [
                    'old_value' => $this->formatValue($old_values[$key] ?? null, $key),
                    'new_value' => $this->formatValue($value, $key),
                    'modified_key' => $this->formatKey($key),
                ];
            }
        }

        // Handle deleted event
        if ($action == 'deleted' && isset($properties['attributes'])) {
            $old_values = $properties['attributes'] ?? [];

            foreach ($old_values as $key => $value) {
                if (in_array($key, ['id', 'created_at', 'updated_at', 'password'])) {
                    continue;
                }

                $result[] = [
                    'old_value' => $this->formatValue($value, $key),
                    'new_value' => '',
                    'modified_key' => $this->formatKey($key),
                ];
            }
        }

        // Special handling for OnboardingEmployees: always prepend user details if available
        if (
            (isset($log['subject_type']) && $log['subject_type'] === \App\Models\OnboardingEmployees::class) ||
            (isset($log['subject_type']) && $log['subject_type'] === \App\Models\OnboardingEmployees::class)
        ) {
            $userName = \App\Models\OnboardingEmployees::where('id', $log['subject_id'] ?? null)
                ->select('first_name', 'last_name')
                ->first();
            if (! empty($userName)) {
                $userDetails = [
                    'old_value' => $userName->first_name.' '.$userName->last_name,
                    'new_value' => $userName->first_name.' '.$userName->last_name,
                    'modified_key' => 'User',
                ];
                array_unshift($result, $userDetails);
            }
        }

        return $result;
    }

    /**
     * Format keys to be more readable
     */
    private function formatKey(string $key): string
    {
        return str_replace('_', ' ', ucfirst($key));
    }

    /**
     * Format values based on key type
     *
     * @param  mixed  $value
     */
    private function formatValue($value, string $key): string
    {
        if (is_null($value)) {
            return '';
        }

        switch ($key) {
            case 'manager_id':
            case 'hired_by_uid':
                $user = \App\Models\User::find($value);

                return $user ? $user->first_name.' '.$user->last_name : $value;
            case 'department_id':
                $department = \App\Models\Department::find($value);

                return $department ? $department->name : $value;
            case 'position_id':
            case 'sub_position_id':
                $position = \App\Models\Positions::where('id', $value)->first();

                return $position ? $position->position_name : $value;
            case 'office_id':
                $location = \App\Models\Locations::where('id', $value)->first();

                return $location ? $location->office_name : $value;
            case 'user_id':
            case 'updater_id':
                $user = \App\Models\User::where('id', $value)->first();

                return $user ? $user->first_name.' '.$user->last_name : $value;
            case 'OnboardProcess':
                return $this->onboardProcess($value);
            case 'state_id':
                $stateName = State::where('id', $value)->value('name');

                return $stateName ?: $value;
            case 'old_state_id':
                return $this->oldStateId($value);
            case 'old_office_id':
                return $this->oldOfficeId($value);
            case 'old_department_id':
                return $this->oldDepartmentName($value);
            case 'old_position_id':
                return $this->oldPositionName($value);
            case 'old_sub_position_id':
                return $this->oldSubPositionName($value);
            case 'old_team_id':
                return $this->oldTeamName($value);
            case 'old_self_gen_accounts':
                return $this->oldSelfGenAccount($value);
            case 'old_manager_id':
                return $this->oldManager($value);
            case 'old_is_manager':
                return $this->oldIsManager($value);
            case 'reporting_manager_id':
                return $this->reportingManager($value);
            case 'sub_position_type':
                return $this->subPositionType($value);
            case 'updated_by':
                return $this->updatedBy($value);
                // Add more field-specific formatting as needed
            default:
                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }

                if (preg_match('/_date$/', $key) && $value) {
                    return date('m/d/Y', strtotime($value));
                }

                return (string) $value;
        }
    }

    /**
     * Format subject type to be more readable
     */
    private function formatSubjectType(string $subjectType): string
    {
        return str_replace('App\\Models\\', '', $subjectType);
    }

    public function userActivityLog(Request $request): JsonResponse
    {
        $userActivityLogs = DB::table('activity_log')->get();
        $data = [];
        foreach ($userActivityLogs as $key => $logs) {
            $action = DB::table('users')->where('id', $logs->causer_id)->first();
            $fname = isset($action->first_name) ? $action->first_name : ' ';
            $lname = isset($action->last_name) ? $action->last_name : ' ';
            if ($logs->subject_type == \App\Models\User::class) {
                $change = DB::table('users')->where('id', $logs->subject_id)->first();
                $emp = $change->first_name.' '.$change->last_name;
            } else {
                $replace = str_replace("App\Models", ' ', $logs->subject_type);
                $events = str_replace('\\', '', $replace);
                $emp = $events;
            }
            // Laravel log
            $data['user_id'] = $logs->causer_id;
            $data['action_by'] = $fname.' '.$lname;
            $data['log_name'] = $logs->log_name;
            $data['description'] = $logs->description;
            $data['subject'] = $logs->subject_type;
            $data['changes_id'] = $logs->subject_id;
            $data['changes_event'] = $emp;
            $data['event'] = $logs->event;
            $data['properties'] = json_decode($logs->properties);
            Log::channel('user_activity_log')->info('User Activity Logs', [$data]);
        }

        return response()->json([
            'ApiName' => 'user_activity_log',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    /**
     * Generate S3 URL for user images
     */
    private function getS3ImageUrl(?string $imagePath): ?string
    {
        if (empty($imagePath)) {
            return null;
        }

        return s3_getTempUrl(config('app.domain_name').'/'.$imagePath);
    }
}
