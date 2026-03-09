<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiMissingDataValidatedRequest;
use App\Http\Requests\SwaggerSaleDataRequest;
use App\Jobs\GenerateAlertJob;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\DeductionAlert;
use App\Models\ImportExpord;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRowData;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
// use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Models\SaleMasterExcluded;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Services\SalesCalculationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Activitylog\Facades\Activity;

class ApiMissingDataController extends Controller
{
    use EditSaleTrait, ReconRoutineTraits, SetterSubroutineListTrait {
        EditSaleTrait::updateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::executedSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::salesDataHistory insteadof SetterSubroutineListTrait;
    }

    /**
     * Display a listing of the resource.
     */
    public function alert_center_count(Request $request): JsonResponse
    {
        $finalData = [];
        $filter = '';
        $sales_keys = ['pid', 'customer_signoff', 'gross_account_value', 'epc', 'net_epc', 'dealer_fee_percentage', 'customer_name', 'customer_state', 'kw'];
        $missingRep_keys = ['sales_rep_email', 'setter_id'];
        $people_keys = ['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'onboardProcess', 'redline', 'self_gen_redline'];

        /***** sales alert  ********/

        $sales_query = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')
            ->where(function ($q) {
                $q->orWhereNull(['pid', 'customer_signoff', 'gross_account_value', 'epc', 'net_epc', 'dealer_fee_percentage', 'customer_name', 'customer_state', 'kw']);
            });

        $sales_result = $sales_query->orderBy('id', 'desc')->first();

        $resultTotal['sales'] = ! empty($sales_result) ? 1 : 0;

        /***** missingRep alert  ********/
        $user_email_data = User::where('id', '!=', 1)->get('email')->toArray();
        $user_email_array = array_column($user_email_data, 'email');

        $missingRep_query = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type');

        $missingRep_data = $missingRep_query->get();

        $missingRep_alert_data = [];
        $value = [];
        $keys = [];
        foreach ($missingRep_data as $salesCountVal) {

            $new_rep_email = null;
            if (empty($salesCountVal->sales_rep_email)) {
                $value[] = 'Sales Rep Email';
                $keys[] = 'sales_rep_email';
            }
            if (empty($salesCountVal->setter_id)) {
                $value[] = 'Setter';
                $keys[] = 'setter_id';
            }
            if ($salesCountVal->sales_rep_email != null || $salesCountVal->sales_rep_email != '') {
                // $user = User::where('email',$salesCountVal->sales_rep_email)->first();
                // if(empty($user))
                if (! in_array($salesCountVal->sales_rep_email, $user_email_array)) {
                    $value[] = 'Closer '.$salesCountVal->sales_rep_email.' not in users';
                    $keys[] = 'sales_rep_new';
                    $new_rep_email = $salesCountVal->sales_rep_email;
                }
            }
        }
        $resultTotal['missingRep'] = ! empty($value) ? 1 : 0;

        $closedPayroll_query = LegacyApiNullData::where('action_status', 0)->where('type', 'Payroll')->where('status', '!=', 'Resolved')->whereNotNull('data_source_type')->orderBy('id', 'DESC')->first();

        $resultTotal['closedPayroll'] = ! empty($closedPayroll_query) ? 1 : 0;

        /***** locationRedline alert  ********/
        $state_all_data = State::get()->toArray();
        $location_all_data = Locations::with('State', 'additionalRedline')->get()->toArray();
        $location_state_id_array = array_column($location_all_data, 'state_id');

        $location_Redline = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')->get();

        $location_Redline_data = [];
        $value = [];
        $keys = [];
        foreach ($location_Redline as $salesCountVal) {

            $location_data = '';
            $state_data = '';
            $state = [];
            $location = [];
            // geting state

            foreach ($state_all_data as $index => $state_code_row) {
                if (! empty($salesCountVal->customer_state) && $state_code_row['state_code'] == $salesCountVal->customer_state) {
                    $state = $state_code_row;
                    break;
                }
            }

            // geting locations
            foreach ($location_all_data as $index => $location_row) {
                if (! empty($salesCountVal->customer_state) && $location_row['general_code'] == $salesCountVal->customer_state) {
                    $location = $location_row;
                    break;
                }
            }

            // logic start
            if (! empty($state)) {
                $state_data = ['state_id' => $state['id'], 'state_name' => $state['name'], 'general_code' => $state['state_code']];
            }

            // geting location data
            if (empty($location)) {
                if (! empty($state)) {
                    // $location = Locations::where('state_id',$state['id'])->first();
                    $location_id_index = array_search($state['id'], $location_state_id_array);
                    if ($location_id_index != '' && $location_id_index != null) {
                        $location = $location_all_data[$location_id_index];
                    }

                    if (empty($location)) {
                        $state_data = ['state_id' => $state['id'], 'state_name' => $state['name'], 'general_code' => $salesCountVal->customer_state];
                    }
                } else {
                    $state_data = ['state_id' => '', 'general_code' => $salesCountVal->customer_state];
                }
            }

            // setting location alert
            if (empty($location)) {
                $value[] = 'location';
                $keys[] = 'Location';
            }

            if (! empty($location) && empty($location['redline_standard'])) {
                $value[] = 'location Redline';
                $keys[] = 'Location_redline';
                $location_data = $location;
                $location_data['redline_data'] = $location['additional_redline'];
                $location_data['effective_date'] = $location['date_effective'];
            }

            $date_found = true;
            if (! empty($location['additional_redline'])) {
                foreach ($location['additional_redline'] as $redlinedata) {
                    if (! empty($redlinedata['effective_date']) && strtotime($redlinedata['effective_date']) < strtotime($salesCountVal->customer_signoff)) {
                        $date_found = false;
                    }
                }
            }
            if (! empty($location['date_effective']) && ($date_found)) {
                // $value[] = 'Location Redline missing for sale approval -'.$salesCountVal->customer_signoff;
                $value[] = 'Location Redline missing for sale approval - '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                $keys[] = 'Location_redline';
                $location_data = $location;
                $location_data['redline_data'] = $location['additional_redline'];
                $location_data['effective_date'] = $location['date_effective'];
            }
        }
        $resultTotal['locationRedline'] = (count($value) > 0) ? 1 : 0;

        $people_alert_query = User::where(function ($q) {
            $q->orWhereNull(['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'work_email', 'onboardProcess', 'redline', 'self_gen_redline']);
        })->where('id', '!=', 1)->first();

        $resultTotal['people'] = ! empty($people_alert_query) ? 1 : 0;

        $repRedline_query = LegacyApiNullData::where('action_status', 0)
            ->leftjoin('users as setter', 'setter.id', '=', 'legacy_api_data_null.setter_id')
            ->leftjoin('users', 'users.email', '=', 'legacy_api_data_null.sales_rep_email')
            ->where('legacy_api_data_null.action_status', 0)
            ->WhereNotNull('legacy_api_data_null.setter_id')
            ->WhereNotNull('legacy_api_data_null.sales_rep_email')
            ->whereNotNull('legacy_api_data_null.data_source_type')
            ->select(
                'setter.id as setter_id',
                'setter.first_name as setter_first_name',
                'setter.last_name as setter_last_name',
                'setter.redline as setter_redline',
                'setter.self_gen_redline as setter_self_gen_redline',
                'setter.redline_effective_date as setter_redline_effective_date',
                'setter.self_gen_redline_effective_date as setter_self_gen_redline_effective_date',
                'setter.position_id as setter_position_id',
                'setter.sub_position_id as setter_sub_position_id',
                'setter.self_gen_accounts as setter_self_gen_accounts',
                'setter.self_gen_type as setter_self_gen_type',
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.redline',
                'users.self_gen_redline',
                'users.redline_effective_date',
                'users.self_gen_redline_effective_date',
                'users.position_id',
                'users.self_gen_accounts',
                'users.self_gen_type',
                'users.sub_position_id',
                'legacy_api_data_null.pid',
                'legacy_api_data_null.updated_at',
                'legacy_api_data_null.customer_name',
                'legacy_api_data_null.customer_signoff'
            )
            ->selectRaw('CONCAT(users.first_name, " ", users.last_name) as full_name')
            ->selectRaw('CONCAT(setter.first_name, " ", setter.last_name) as setter_full_name');

        $repRedline_data = $repRedline_query->get();

        $rep_redline_alert_data = [];
        foreach ($repRedline_data as $salesCountVal) {
            $value = [];
            $keys = [];
            $position = '';
            $position_name = ['2' => 'Closer', '3' => 'Setter'];
            $setter_full_name = $salesCountVal->setter_full_name;
            $full_name = $salesCountVal->full_name;

            if ($salesCountVal->self_gen_accounts == null || $salesCountVal->self_gen_accounts == 0) {
                // CLOSER - for main postion not self gen account
                $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                    ->where('start_date', '<=', $salesCountVal->customer_signoff)
                    ->where(function ($query) use ($salesCountVal) {
                        $query->Where('position_type', $salesCountVal->sub_position_id)->orWhere('position_type', $salesCountVal->position_id);
                    })->orderby('start_date', 'desc')->first();

                if (empty($user_redline_history_data)) {
                    $value[] = 'closer '.$full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                    $keys[] = 'closer_rep_redline';
                    $position = ($salesCountVal->sub_position_id == 2) ? $salesCountVal->sub_position_id : $salesCountVal->position_id;
                    // if($salesCountVal->redline == null || $salesCountVal->redline_effective_date == null || $salesCountVal->redline_effective_date > $salesCountVal->customer_signoff)
                    // {
                    //     $value[] = 'Redline is missing for sale approval '.date('m/d/Y',strtotime($salesCountVal->customer_signoff));
                    //     $keys[] = 'rep_redline';
                    //     $position = ($salesCountVal->sub_position_id==2)?$salesCountVal->sub_position_id:$salesCountVal->position_id;
                    // }
                }

            } elseif (($salesCountVal->self_gen_accounts != null || $salesCountVal->self_gen_accounts == 1)) {

                if ($salesCountVal->position_id == 2 || $salesCountVal->sub_position_id == 2) {
                    // CLOSER -  for main postion if has self gen account
                    $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->sub_position_id)->orWhere('position_type', $salesCountVal->position_id);
                        })->orderby('start_date', 'desc')->first();
                    if (empty($user_redline_history_data)) {
                        $value[] = 'Closer '.$full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'closer_rep_redline';
                        $position = ($salesCountVal->sub_position_id == 2) ? $salesCountVal->sub_position_id : $salesCountVal->position_id;
                    }

                } elseif ($salesCountVal->self_gen_type == 2) {
                    // CLOSER -  for self gen position
                    $self_gen_user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->self_gen_type);
                        })->orderby('start_date', 'desc')->first();

                    if (empty($self_gen_user_redline_history_data)) {
                        $value[] = 'Closer '.$full_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'closer_self_gen_redline';
                        $position = $salesCountVal->self_gen_type;
                    }
                }
            }

            // for setter
            if ($salesCountVal->setter_self_gen_accounts == null || $salesCountVal->setter_self_gen_accounts == 0) {
                // SETTER - for main postion not self gen account
                $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                    ->where('start_date', '<=', $salesCountVal->customer_signoff)
                    ->where(function ($query) use ($salesCountVal) {
                        $query->Where('position_type', $salesCountVal->setter_sub_position_id)->orWhere('position_type', $salesCountVal->setter_position_id);
                    })->orderby('start_date', 'desc')->first();

                if (empty($user_redline_history_data)) {
                    $value[] = 'Setter '.$setter_full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                    $keys[] = 'setter_rep_redline';
                    $position = ($salesCountVal->setter_sub_position_id == 2) ? $salesCountVal->setter_sub_position_id : $salesCountVal->setter_position_id;
                }
            } elseif (($salesCountVal->setter_self_gen_accounts != null || $salesCountVal->setter_self_gen_accounts == 1)) {

                if ($salesCountVal->setter_position_id == 2 || $salesCountVal->setter_sub_position_id == 2) {
                    // CLOSER -  for main postion if has self gen account
                    $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->setter_sub_position_id)->orWhere('position_type', $salesCountVal->setter_position_id);
                        })->orderby('start_date', 'desc')->first();
                    if (empty($user_redline_history_data)) {
                        $value[] = 'Setter '.$setter_full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'setter_rep_redline';
                        $position = ($salesCountVal->setter_sub_position_id == 2) ? $salesCountVal->setter_sub_position_id : $salesCountVal->setter_position_id;
                    }

                } elseif ($salesCountVal->setter_self_gen_type == 2) {
                    // CLOSER -  for self gen position
                    $self_gen_user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->setter_self_gen_type);
                        })->orderby('start_date', 'desc')->first();

                    if (empty($self_gen_user_redline_history_data)) {
                        $value[] = 'Setter '.$setter_full_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'setter_rep_redline';
                        $position = $salesCountVal->setter_self_gen_type;
                    }
                }
            }

        }

        $resultTotal['repRedline'] = (count($value) > 0) ? 1 : 0;

        return response()->json(['ApiName' => 'alert center count', 'status' => true, 'message' => 'Successfully', 'totalCount' => $resultTotal], 200);
    }

    // CHANGING ON THIS FUNCTION WOULD REQUIRE CHANGE ON ReportsAdminController -> global_search() FUNCTION
    public function alert_center_details(Request $request)
    {
        // Artisan::call('generate:alert'); // Generates alert
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        $finalData = [];
        $filter = isset($request->filter) ? $request->filter : 'all';
        $quick_filter = isset($request->quick_filter) ? $request->quick_filter : '';
        $search = isset($request->search) ? $request->search : '';
        $positions_array = ['2' => 'Closer', '3' => 'Setter'];
        if ($filter == 'people') {
            $searchTerm = '%'.$search.'%';
            $user_email_data = DB::select("
    WITH redline_with_core AS (
        SELECT user_id, redline, redline_type
        FROM user_redline_histories
        WHERE core_position_id IS NOT NULL
        AND id IN (
            SELECT MAX(id)
            FROM user_redline_histories
            WHERE core_position_id IS NOT NULL
            GROUP BY user_id
        )
    ),
    redline_without_core AS (
        SELECT user_id, redline AS self_gen_redline, redline_type AS commission_type
        FROM user_redline_histories
        WHERE core_position_id IS NULL
        AND id IN (
            SELECT MAX(id)
            FROM user_redline_histories
            WHERE core_position_id IS NULL
            GROUP BY user_id
        )
    ),
    users_with_email AS (
        SELECT
            uae.user_id,
            u.self_gen_accounts, u.self_gen_type, u.first_name, u.middle_name, u.last_name,
            uae.email, u.state_id, u.city_id, u.location,
            u.position_id, u.sub_position_id, u.is_super_admin, u.is_manager, u.entity_type,
            u.name_of_bank, u.routing_no, u.account_no, u.type_of_account, u.onboardProcess,
            rc.redline, rnc.self_gen_redline, rnc.commission_type
        FROM users_additional_emails uae
        JOIN users u ON u.id = uae.user_id
        LEFT JOIN redline_with_core rc ON rc.user_id = u.id
        LEFT JOIN redline_without_core rnc ON rnc.user_id = u.id
        WHERE u.is_super_admin != 1
    ),
    users_without_email AS (
        SELECT
            u.id AS user_id,
            u.self_gen_accounts, u.self_gen_type, u.first_name, u.middle_name, u.last_name,
            u.email, u.state_id, u.city_id, u.location,
            u.position_id, u.sub_position_id, u.is_super_admin, u.is_manager, u.entity_type,
            u.name_of_bank, u.routing_no, u.account_no, u.type_of_account, u.onboardProcess,
            rc.redline, rnc.self_gen_redline, rnc.commission_type
        FROM users u
        LEFT JOIN redline_with_core rc ON rc.user_id = u.id
        LEFT JOIN redline_without_core rnc ON rnc.user_id = u.id
        WHERE u.is_super_admin != 1
    )

    SELECT * FROM (
        SELECT * FROM users_with_email
        UNION
        SELECT * FROM users_without_email
    ) AS tbl
    WHERE first_name LIKE ?
       OR last_name LIKE ?
       OR CONCAT(first_name, ' ', last_name) LIKE ?
", [$searchTerm, $searchTerm, $searchTerm]);
        } else {
            $user_email_data = DB::select('
                    WITH latest_redline AS (
                        SELECT urh.user_id, urh.redline, urh.redline AS self_gen_redline, urh.redline_type AS commission_type
                        FROM user_redline_histories urh
                        INNER JOIN (
                            SELECT user_id, MAX(id) AS max_id
                            FROM user_redline_histories
                            GROUP BY user_id
                        ) latest ON latest.max_id = urh.id
                    ),
                    users_with_email AS (
                        SELECT
                            uae.user_id,
                            u.self_gen_accounts, u.self_gen_type, u.first_name, u.middle_name, u.last_name,
                            uae.email, u.state_id, u.city_id, u.location,
                            u.position_id, u.sub_position_id, u.is_super_admin, u.is_manager, u.entity_type,
                            u.name_of_bank, u.routing_no, u.account_no, u.type_of_account, u.onboardProcess,
                            lr.redline, lr.self_gen_redline, lr.commission_type
                        FROM users_additional_emails uae
                        JOIN users u ON u.id = uae.user_id
                        LEFT JOIN latest_redline lr ON lr.user_id = u.id
                        WHERE u.is_super_admin != 1
                    ),
                    users_without_email AS (
                        SELECT
                            u.id AS user_id,
                            u.self_gen_accounts, u.self_gen_type, u.first_name, u.middle_name, u.last_name,
                            u.email, u.state_id, u.city_id, u.location,
                            u.position_id, u.sub_position_id, u.is_super_admin, u.is_manager, u.entity_type,
                            u.name_of_bank, u.routing_no, u.account_no, u.type_of_account, u.onboardProcess,
                            lr.redline, lr.self_gen_redline, lr.commission_type
                        FROM users u
                        LEFT JOIN latest_redline lr ON lr.user_id = u.id
                        WHERE u.is_super_admin != 1
                    )

                    SELECT * FROM (
                        SELECT * FROM users_with_email
                        UNION
                        SELECT * FROM users_without_email
                    ) AS tbl
                ');
        }

        $arr = [];
        foreach ($user_email_data as $key => $ued) {
            $arr[] = [
                'id' => $ued->user_id,
                'email' => $ued->email,
                'self_gen_accounts' => $ued->self_gen_accounts,
                'self_gen_type' => $ued->self_gen_type,
                'first_name' => $ued->first_name,
                'middle_name' => $ued->middle_name,
                'last_name' => $ued->last_name,
                'state_id' => $ued->state_id,
                'city_id' => $ued->city_id,
                'location' => $ued->location,
                'position_id' => $ued->position_id,
                'sub_position_id' => $ued->sub_position_id,
                'is_super_admin' => $ued->is_super_admin,
                'is_manager' => $ued->is_manager,
                'entity_type' => $ued->entity_type,
                'name_of_bank' => $ued->name_of_bank,
                'routing_no' => $ued->routing_no,
                'account_no' => $ued->account_no,
                'type_of_account' => $ued->type_of_account,
                'onboardProcess' => $ued->onboardProcess,
                'redline' => $ued->redline,
                'self_gen_redline' => $ued->self_gen_redline,
                'commission_type' => $ued->commission_type,
            ];
        }
        $user_email_data = $arr;
        $user_id_array = array_column($user_email_data, 'id');
        $user_email_array = array_column($user_email_data, 'email');
        $people_keys = ['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'onboardProcess', 'redline', 'self_gen_redline'];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $people_keys = ['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'onboardProcess'];
        }

        $people_keys_msg = [
            'entity_type' => 'Entity type (Individual / Business)',
            'name_of_bank' => 'Bank Name',
            'routing_no' => 'Routing Number',
            'account_no' => 'Bank Account Number',
            'type_of_account' => 'Type of Account ( Cheking / Savings )',
            'onboardProcess' => 'Incomplete Onboarding',
            'redline' => 'Redline',
            'self_gen_redline' => 'Self Gen Redline',
        ];

        $count_data = LegacyApiNullData::selectRaw('count(sales_alert) sales_alert,
            count(missingrep_alert) missingrep_alert,
            count(closedpayroll_alert) closedpayroll_alert,
            count(locationredline_alert) locationredline_alert,
            count(repredline_alert) repredline_alert')
            ->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();

        /***** people alert  ********/
        $people_key_data = [];
        $people_user_data = [];

        $uniqueUserIDs = [];
        foreach ($user_email_data as $user_key => $user_data) {
            if (! in_array($user_data['id'], $uniqueUserIDs)) {
                $uniqueUserIDs[] = $user_data['id'];
                $key = [];
                foreach ($people_keys as $people_key) {
                    if (empty($user_data[$people_key])) {
                        if ($people_key == 'self_gen_redline' && $user_data['self_gen_accounts'] == 0) {
                            continue;
                        }
                        if ($people_key == 'self_gen_redline' && $user_data['commission_type'] == 'per kw') {
                            continue;
                        }
                        $key[] = $people_key;
                    }
                }
                if (! empty($key)) {
                    $people_key_data[$user_key] = $key;
                    $people_user_data[$user_key] = $user_data;
                }
            }
        }

        /***** people alert  ********/
        $redLineAlertCount = LegacyApiNullData::selectRaw('count(repredline_alert) repredline_alert')->whereNotNull('data_source_type')->whereNotNull('repredline_alert')->whereNotNull('setter_id')->orderBy('id', 'desc')->first();
        $resultTotal = [];
        if (! empty($count_data)) {
            $resultTotal['sales'] = $count_data->sales_alert;
            $resultTotal['locationRedline'] = $count_data->locationredline_alert;
            $resultTotal['missingRep'] = $count_data->missingrep_alert;
            $resultTotal['repRedline'] = $redLineAlertCount->repredline_alert;
            $resultTotal['people'] = count($people_key_data);

            // Condition to set closedPayroll based on company type
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $resultTotal['closedPayroll'] = 0;
            } else {
                $resultTotal['closedPayroll'] = $count_data->closedpayroll_alert;
            }
        }

        $finalData = [];
        if ($filter == 'sales') {
            $sales = LegacyApiNullData::whereNotNull('sales_alert')->whereNotNull('data_source_type');
            if (! empty($search)) {
                $sales->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }
            if (! empty($quick_filter)) {
                $sales = $sales->whereNull($quick_filter);
            }
            $sales = $sales->paginate($perpage);

            $sales->getCollection()->transform(function ($row) use ($companyProfile) {
                $alert = $row['sales_alert'];
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $alert = str_replace('customer_signoff', 'sale_date', $row['sales_alert']);
                }

                return [
                    'type_val' => 'Sales',
                    'id' => $row['id'],
                    'pid' => $row['pid'],
                    'alert_summary' => 'Update '.str_replace('_', ' ', $alert),
                    'keys' => explode(',', $row['sales_alert']),
                    'customer_name' => $row['customer_name'],
                ];
            });
            $finalData = $sales;
        } elseif ($filter == 'closedPayroll') {
            $closedPayroll = LegacyApiNullData::whereNotNull('closedpayroll_alert')->whereNotNull('data_source_type');
            if (! empty($quick_filter)) {
                $closedPayroll = $closedPayroll->where('closedpayroll_type', $quick_filter);
            }
            if (! empty($search)) {
                $closedPayroll = $closedPayroll->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }
            $closedPayroll = $closedPayroll->paginate($perpage);

            $closedPayroll->getCollection()->transform(function ($row) {
                return [
                    'type_val' => 'Closed Payroll',
                    'id' => $row['id'],
                    'pid' => $row['pid'],
                    'alert_summary' => 'Update '.str_replace('_', ' ', $row['closedpayroll_alert']),
                    'keys' => explode(',', $row['closedpayroll_alert']),
                    'customer_name' => $row['customer_name'],
                ];
            });
            $finalData = $closedPayroll;
        } elseif ($filter == 'locationRedline') {
            $locationRedline = LegacyApiNullData::whereNotNull('locationredline_alert')->whereNotNull('data_source_type');
            if (! empty($search)) {
                $locationRedline = $locationRedline->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }
            $locationRedline_data = $locationRedline->paginate($perpage);

            $state_all_data = State::get()->toArray();
            $location_all_data = Locations::with('State', 'additionalRedline')->get()->toArray();
            $location_state_id_array = array_column($location_all_data, 'state_id');
            $locationRedline_data->getCollection()->transform(function ($row) use ($state_all_data, $location_all_data, $location_state_id_array) {
                $location = [];
                $state = [];
                foreach ($state_all_data as $state_code_row) {
                    if (! empty($row['customer_state']) && strtolower(trim($state_code_row['state_code'])) == strtolower(trim($row['customer_state']))) {
                        $state = $state_code_row;
                        break;
                    }
                }

                if (config('app.domain_name') == 'flex') {
                    foreach ($location_all_data as $location_row) {
                        $location_row['redline_data'] = $location_row['additional_redline'];
                        if (! empty($row['customer_state']) && $location_row['general_code'] == $row['customer_state']) {
                            $location = $location_row;
                            break;
                        }
                    }
                } else {
                    foreach ($location_all_data as $location_row) {
                        $location_row['redline_data'] = $location_row['additional_redline'];
                        if (! empty($row['location_code']) && strtolower(trim($location_row['general_code'])) == strtolower(trim($row['location_code']))) {
                            $location = $location_row;
                            break;
                        }
                    }
                }

                if (empty($location)) {
                    if (! empty($state)) {
                        $location_id_index = array_search($state['id'], $location_state_id_array);
                        if ($location_id_index != '' && $location_id_index != null) {
                            $location = $location_all_data[$location_id_index];
                        }
                    }
                }

                $approvalDateAlert = isset($row['customer_signoff']) && $row['customer_signoff'] != '' ? ' for sale approval - '.date('m/d/Y', strtotime($row['customer_signoff'])) : '';
                $locationRedlineMessege = explode(',', $row['locationredline_alert']);
                $alert_summary = in_array('Location_redline', $locationRedlineMessege) ? 'Update '.str_replace('_', ' ', $row['locationredline_alert']).$approvalDateAlert : 'Add '.str_replace('_', ' ', $row['locationredline_alert']).$approvalDateAlert;
                $companyProfile = CompanyProfile::first();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $locationRedlineMessege = explode(',', $row['locationredline_alert']);
                    $alert_summary = in_array('Location_redline', $locationRedlineMessege)
                        ? 'Update '.str_replace('_', ' ', $row['locationredline_alert'])
                        : 'Add '.str_replace('_', ' ', $row['locationredline_alert']);
                }

                return [
                    'type_val' => 'location Redline',
                    'id' => $row['id'],
                    'pid' => $row['pid'],
                    'alert_summary' => $alert_summary,
                    'keys' => explode(',', $row['locationredline_alert']),
                    'customer_name' => $row['customer_name'],
                    'location_data' => $location,
                    'state_name' => isset($state['name']) ? $state['name'] : null,
                    'state_data' => $state,
                    'general_code' => isset($row['location_code']) && $row['location_code'] != '' ? $row['location_code'] : null,
                ];
            });
            $finalData = $locationRedline_data;
        } elseif ($filter == 'missingRep') {
            $missingRep = LegacyApiNullData::whereNotNull('missingrep_alert')->whereNotNull('data_source_type');
            if (! empty($search)) {
                $missingRep->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }
            if (! empty($quick_filter)) {
                $missingRep = $missingRep->whereNull($quick_filter);
            }
            $missingRep = $missingRep->orderBy('id', 'desc')->paginate($perpage);

            $missingRep->getCollection()->transform(function ($row) use ($user_email_array, $user_email_data) {
                $key = [];
                $new_rep_email = null;
                $alert_types = explode(',', $row['missingrep_alert']);
                $row['missingrep_alert'] = '';
                if (count($alert_types) > 1) {
                    foreach ($alert_types as $alert_type) {
                        if (count(explode('|', $alert_type)) > 1) {
                            if ($alert_type == 'sales_rep_email_saleapproval') {
                                $closer_id_index = array_search($row['sales_rep_email'], $user_email_array);
                                if ($closer_id_index != '' && $closer_id_index != null && isset($user_email_data[$closer_id_index])) {
                                    $closer_data = $user_email_data[$closer_id_index];
                                    $closer_data['sales_data'] = $row;
                                    $closer_obj = json_decode(json_encode($closer_data));
                                    if ($closer_obj) {
                                        $key[] = 'sales_rep_email';
                                        $row['missingrep_alert'] .= 'sales rep '.$closer_obj->first_name.' '.$closer_obj->last_name.' for sale approval '.date('m/d/Y', strtotime($closer_obj->sales_data->customer_signoff)).',';
                                    }
                                }
                            } elseif ($alert_type == 'sales_setter_email_saleapproval') {
                                $setter_id_index = array_search($row['sales_setter_email'], $user_email_array);
                                if ($setter_id_index != '' && $setter_id_index != null && isset($user_email_data[$setter_id_index])) {
                                    $setter_data = $user_email_data[$setter_id_index];
                                    $setter_data['sales_data'] = $row;
                                    $setter_obj = json_decode(json_encode($setter_data));
                                    if ($setter_obj) {
                                        $key[] = 'sales_setter_email';
                                        $row['missingrep_alert'] .= 'sales setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' for sale approval '.date('m/d/Y', strtotime($setter_obj->sales_data->customer_signoff)).',';
                                    }
                                }
                            } else {
                                $new_rep_email_arr = explode('|', $alert_type);
                                $new_rep_email = isset($new_rep_email_arr[1]) ? $new_rep_email_arr[1] : null;
                                $key[] = isset($new_rep_email_arr[0]) ? preg_replace('/\|.*/', '', $new_rep_email_arr[0]) : null;
                                $row['missingrep_alert'] .= $alert_type.',';
                            }
                        } else {
                            if (isset($alert_type)) {
                                if ($alert_type == 'sales_rep_email_saleapproval') {
                                    $closer_id_index = array_search($row['sales_rep_email'], $user_email_array);
                                    if ($closer_id_index != '' && $closer_id_index != null && isset($user_email_data[$closer_id_index])) {
                                        $closer_data = $user_email_data[$closer_id_index];
                                        $closer_data['sales_data'] = $row;
                                        $closer_obj = json_decode(json_encode($closer_data));
                                        if ($closer_obj) {
                                            $key[] = 'sales_rep_email';
                                            $row['missingrep_alert'] .= 'sales rep '.$closer_obj->first_name.' '.$closer_obj->last_name.' for sale approval '.date('m/d/Y', strtotime($closer_obj->sales_data->customer_signoff)).',';
                                        }
                                    }
                                } elseif ($alert_type == 'sales_setter_email_saleapproval') {
                                    $setter_id_index = array_search($row['sales_setter_email'], $user_email_array);
                                    if ($setter_id_index != '' && $setter_id_index != null && isset($user_email_data[$setter_id_index])) {
                                        $setter_data = $user_email_data[$setter_id_index];
                                        $setter_data['sales_data'] = $row;
                                        $setter_obj = json_decode(json_encode($setter_data));
                                        if ($setter_obj) {
                                            $key[] = 'sales_setter_email';
                                            $row['missingrep_alert'] .= 'sales setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' for sale approval '.date('m/d/Y', strtotime($setter_obj->sales_data->customer_signoff)).',';
                                        }
                                    }
                                } elseif ($alert_type == 'sales_rep_terminated') {
                                    $user = User::where('email', $row->sales_rep_email)->first();
                                    if ($user) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] .= 'Sales rep # '.$row->sales_rep_email.' terminated';
                                        if (isset($row->customer_signoff)) {
                                            $terminateHistory = $user->terminateHistoryOn($row->customer_signoff);
                                            if ($terminateHistory) {
                                                $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($terminateHistory->terminate_effective_date));
                                            }
                                        }
                                        $row['missingrep_alert'] .= ',';
                                    }
                                } elseif ($alert_type == 'sales_rep_contract_ended') {
                                    $user = User::select('dismiss', 'end_date')->where('email', $row->sales_rep_email)->first();
                                    if ($user && isset($user->end_date)) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] = 'Sale rep # '.$row->sales_rep_email.' contract ended on '.$user->end_date;
                                    }
                                } elseif ($alert_type == 'sales_rep_dismissed') {
                                    $user = User::where('email', $row->sales_setter_email)->first();
                                    if ($user) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] .= 'Sales setter # '.$row->sales_setter_email.' dismissed';
                                        if (isset($row->customer_signoff)) {
                                            $dismissHistory = $user->dismissHistoryOn($row->customer_signoff);
                                            if ($dismissHistory) {
                                                $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($dismissHistory->effective_date));
                                            }
                                        }
                                    }
                                } elseif ($alert_type == 'sales_setter_terminated') {
                                    $user = User::where('email', $row->sales_setter_email)->first();
                                    if ($user) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] .= 'Sales setter # '.$row->sales_setter_email.' terminated';
                                        if (isset($row->customer_signoff)) {
                                            $terminateHistory = $user->terminateHistoryOn($row->customer_signoff);
                                            if ($terminateHistory) {
                                                $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($terminateHistory->terminate_effective_date));
                                            }
                                        }
                                    }
                                } elseif ($alert_type == 'sales_setter_contract_ended') {
                                    $user = User::select('dismiss', 'end_date')->where('email', $row->sales_setter_email)->first();
                                    if ($user && isset($user->end_date)) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] = 'Sales setter # '.$row->sales_rep_email.' contract ended on '.$user->end_date;
                                    }
                                } elseif ($alert_type == 'sales_setter_dismissed') {
                                    $row['missingrep_alert'] .= 'dismissed setter,';
                                    $user = User::where('email', $row->sales_setter_email)->first();
                                    if ($user) {
                                        $key[] = preg_replace('/\|.*/', '', $alert_type);
                                        $row['missingrep_alert'] .= 'Sales setter # '.$row->sales_setter_email.' dismissed';
                                        if (isset($row->customer_signoff)) {
                                            $dismissHistory = $user->dismissHistoryOn($row->customer_signoff);
                                            if ($dismissHistory) {
                                                $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($dismissHistory->effective_date));
                                            }
                                        }
                                    }
                                } else {
                                    $key[] = preg_replace('/\|.*/', '', $alert_type);
                                    $row['missingrep_alert'] .= $alert_type.',';
                                }
                            }
                        }
                    }
                } else {
                    if (isset($alert_types[0])) {
                        if ($alert_types[0] == 'sales_rep_email_saleapproval') {
                            $closer_id_index = array_search($row['sales_rep_email'], $user_email_array);
                            if ($closer_id_index != '' && $closer_id_index != null && isset($user_email_data[$closer_id_index])) {
                                $closer_data = $user_email_data[$closer_id_index];
                                $closer_data['sales_data'] = $row;
                                $closer_obj = json_decode(json_encode($closer_data));
                                if ($closer_obj) {
                                    $key[] = 'sales_rep_email';
                                    $row['missingrep_alert'] = 'sales rep '.$closer_obj->first_name.' '.$closer_obj->last_name.' is missing for sale approval '.date('m/d/Y', strtotime($closer_obj->sales_data->customer_signoff));
                                }
                            }
                        } elseif ($alert_types[0] == 'sales_setter_email_saleapproval') {
                            $setter_id_index = array_search($row['sales_setter_email'], $user_email_array);
                            if ($setter_id_index != '' && $setter_id_index != null && isset($user_email_data[$setter_id_index])) {
                                $setter_data = $user_email_data[$setter_id_index];
                                $setter_data['sales_data'] = $row;
                                $setter_obj = json_decode(json_encode($setter_data));
                                if ($setter_obj) {
                                    $key[] = 'sales_setter_email';
                                    $row['missingrep_alert'] .= 'sales setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' for sale approval '.date('m/d/Y', strtotime($setter_obj->sales_data->customer_signoff)).',';
                                }
                            }

                        } elseif ($alert_types[0] == 'sales_rep_dismissed') {
                            $user = User::where('email', $row->sales_rep_email)->first();
                            if ($user) {
                                $key[] = 'sales_rep_email';
                                $row['missingrep_alert'] .= 'Sales rep # '.$row->sales_rep_email.' dismissed';
                                if (isset($row->customer_signoff)) {
                                    $dismissHistory = $user->dismissHistoryOn($row->customer_signoff);
                                    if ($dismissHistory) {
                                        $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($dismissHistory->effective_date));
                                    }
                                }
                            }
                        } elseif ($alert_types[0] == 'sales_rep_terminated') {
                            $user = User::where('email', $row->sales_rep_email)->first();
                            if ($user) {
                                $key[] = 'sales_rep_email';
                                $row['missingrep_alert'] .= 'Sales rep # '.$row->sales_rep_email.' terminated';
                                if (isset($row->customer_signoff)) {
                                    $terminateHistory = $user->terminateHistoryOn($row->customer_signoff);
                                    if ($terminateHistory) {
                                        $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($terminateHistory->terminate_effective_date));
                                    }
                                }
                            }
                        } elseif ($alert_types[0] == 'sales_rep_contract_ended') {
                            $user = User::select('dismiss', 'end_date')->where('email', $row->sales_rep_email)->first();
                            if ($user && isset($user->end_date)) {
                                $key[] = 'sales_rep_email';
                                $row['missingrep_alert'] = 'Sale rep # '.$row->sales_rep_email.' contract ended on '.$user->end_date;
                            }
                        } elseif ($alert_types[0] == 'sales_setter_dismissed') {
                            $user = User::where('email', $row->sales_setter_email)->first();
                            if ($user) {
                                $key[] = 'sales_setter_email';
                                $row['missingrep_alert'] .= 'Sales setter # '.$row->sales_setter_email.' dismissed';
                                if (isset($row->customer_signoff)) {
                                    $dismissHistory = $user->dismissHistoryOn($row->customer_signoff);
                                    if ($dismissHistory) {
                                        $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($dismissHistory->effective_date));
                                    }
                                }
                            }
                        } elseif ($alert_types[0] == 'sales_setter_terminated') {
                            $user = User::where('email', $row->sales_setter_email)->first();
                            if ($user) {
                                $key[] = 'sales_setter_email';
                                $row['missingrep_alert'] .= 'Sales setter # '.$row->sales_setter_email.' terminated';
                                if (isset($row->customer_signoff)) {
                                    $terminateHistory = $user->terminateHistoryOn($row->customer_signoff);
                                    if ($terminateHistory) {
                                        $row['missingrep_alert'] .= ' on '.date('m/d/Y', strtotime($terminateHistory->terminate_effective_date));
                                    }
                                }
                            }
                        } elseif ($alert_types[0] == 'sales_setter_contract_ended') {
                            $user = User::select('dismiss', 'end_date')->where('email', $row->sales_setter_email)->first();
                            if ($user && isset($user->end_date)) {
                                $key[] = 'sales_setter_email';
                                $row['missingrep_alert'] = 'Sales setter # '.$row->sales_rep_email.' contract ended on '.$user->end_date;
                            }
                        } else {
                            $key[] = preg_replace('/\|.*/', '', $alert_types[0]);
                            $row['missingrep_alert'] = $alert_types[0];
                        }
                    }
                }

                $row['missingrep_alert'] = str_replace('|', ' # ', str_replace(',', ', ', rtrim($row['missingrep_alert'], ',')));

                return [
                    'type_val' => 'Missing Rep',
                    'id' => $row['id'],
                    'pid' => $row['pid'],
                    'alert_summary' => 'Update '.str_replace('_', ' ', $row['missingrep_alert']),
                    'keys' => $key,
                    'customer_name' => $row['customer_name'],
                    'new_rep_email' => $new_rep_email,
                ];
            });
            $finalData = $missingRep;
        } elseif ($filter == 'repRedline') {
            $repredline = LegacyApiNullData::whereNotNull('repredline_alert')->whereNotNull('data_source_type')->whereNotNull('setter_id');
            if (! empty($search)) {
                $repredline->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }
            if (! empty($quick_filter)) {
                $repredline = $repredline->whereNull($quick_filter);
            }
            $repredline = $repredline->paginate($perpage);

            $repredline->getCollection()->transform(function ($row) use ($user_email_array, $user_id_array, $user_email_data) {
                $repredline_alert_value_array = [];
                $repredline_alert_key_array = [];
                $closer_id_index = array_search($row['sales_rep_email'], $user_email_array);
                if ($closer_id_index != '' && $closer_id_index != null && isset($user_email_data[$closer_id_index])) {
                    $closer_data = $user_email_data[$closer_id_index];
                    $closer_data['sales_data'] = $row;
                    $closer_obj = json_decode(json_encode($closer_data));

                    $repredline_alert_value_array['repredline_closer_redline_saleapproval'] = 'closer '.$closer_obj->first_name.' '.$closer_obj->last_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($closer_obj->sales_data->customer_signoff));
                    $repredline_alert_value_array['repredline_closer_selfgenredline_saleapproval'] = 'Closer '.$closer_obj->first_name.' '.$closer_obj->last_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($closer_obj->sales_data->customer_signoff));

                    $repredline_alert_key_array['repredline_closer_redline_saleapproval'] = 'closer_rep_redline';
                    $repredline_alert_key_array['repredline_closer_selfgenredline_saleapproval'] = 'closer_self_gen_redline';
                }
                $setter_id_index = array_search($row['setter_id'], $user_id_array);
                if ($setter_id_index != '' && $setter_id_index != null && isset($user_email_data[$setter_id_index])) {
                    $setter_data = $user_email_data[$setter_id_index];
                    $setter_data['sales_data'] = $row;
                    $setter_obj = json_decode(json_encode($setter_data));

                    $repredline_alert_value_array['repredline_setter_redline_saleapproval'] = 'Setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($setter_obj->sales_data->customer_signoff));
                    $repredline_alert_value_array['repredline_setter_selfgenredline_saleapproval'] = 'Setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($setter_obj->sales_data->customer_signoff));

                    $repredline_alert_key_array['repredline_setter_redline_saleapproval'] = 'setter_rep_redline';
                    $repredline_alert_key_array['repredline_setter_selfgenredline_saleapproval'] = 'setter_rep_redline';
                }

                $repredline_alerts = explode(',', $row['repredline_alert']);
                $value = [];
                $key = [];
                foreach ($repredline_alerts as $row_alert) {
                    if (isset($repredline_alert_value_array[$row_alert])) {
                        $value[] = str_replace('_', ' ', $repredline_alert_value_array[$row_alert]);
                        $key[] = $repredline_alert_key_array[$row_alert];
                    }
                }

                return [
                    'type_val' => 'Rep Redline',
                    'id' => $row['id'],
                    'pid' => $row['pid'],
                    'alert_summary' => 'Update '.implode(', ', $value),
                    'keys' => $key,
                    'customer_name' => $row['customer_name'],
                    'setter_id' => isset($setter_data['id']) ? $setter_data['id'] : null,
                    'closer_id' => isset($closer_data['id']) ? $closer_data['id'] : null,
                ];
            });
            $finalData = $repredline;
        } elseif ($filter == 'people') {
            $peopleData = [];
            foreach ($people_key_data as $key => $row) {
                if (array_search($people_user_data[$key]['id'], array_column($peopleData, 'id')) === false) {
                    $summary = [];
                    foreach ($row as $r) {
                        $summary[] = $people_keys_msg[$r];
                    }

                    $peopleData[] = [
                        'type_val' => 'People',
                        'id' => $people_user_data[$key]['id'],
                        'alert_summary' => 'Update '.implode(', ', $summary),
                        'keys' => $row,
                        'user_name' => $people_user_data[$key]['first_name'].' '.$people_user_data[$key]['last_name'],
                        'user_id' => $people_user_data[$key]['id'],
                        'position' => ! empty($people_user_data[$key]['position_id']) ? $positions_array[$people_user_data[$key]['position_id']] : null,
                        'position_id' => $people_user_data[$key]['position_id'],
                        'sub_position_id' => $people_user_data[$key]['sub_position_id'],
                        'is_super_admin' => $people_user_data[$key]['is_super_admin'],
                        'is_manager' => $people_user_data[$key]['is_manager'],
                    ];
                }
            }
            $finalData = $this->paginate($peopleData, $perpage);
        }

        return response()->json(['ApiName' => 'Alert Data Api', 'status' => true, 'message' => 'Successfully', 'totalCount' => $resultTotal, 'data' => $finalData]);
    }

    // addeed
    public function refresh_alert_center_details(): JsonResponse
    {
        Artisan::call('generate:alert');

        return response()->json(['ApiName' => 'alert center refresh', 'status' => true, 'message' => 'Alert center has been refreshed successfully!!']);
    }

    public function index(Request $request)
    {
        $finalData = [];
        $per_page = ! empty($request['page']) ? $request['page'] : 10;
        $result = [];
        $filter = isset($request->filter) ? $request->filter : 'all';
        $quick_filter = isset($request->quick_filter) ? $request->quick_filter : '';
        $search = isset($request->search) ? $request->search : '';

        $sales_keys = ['pid', 'customer_signoff', 'gross_account_value', 'epc', 'net_epc', 'dealer_fee_percentage', 'customer_name', 'customer_state', 'kw'];
        $missingRep_keys = ['sales_rep_email', 'setter_id'];
        $people_keys = ['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'work_email', 'onboardProcess', 'redline', 'self_gen_redline'];
        $sales = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type'); // ->get();

        $state_all_data = State::get()->toArray();
        $location_all_data = Locations::with('State', 'additionalRedline')->get()->toArray();
        $user_email_data = User::where('id', '!=', 1)->get('email')->toArray();
        $user_email_array = array_column($user_email_data, 'email');

        $location_state_id_array = array_column($location_all_data, 'state_id');

        /***** sales alert  ********/

        $sales_query = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type');
        $sales_query = $sales_query->where(function ($query) use ($sales_keys) {
            foreach ($sales_keys as $key) {
                $query->orWhereNull($key)->orWhere($key, '0')->orWhere($key, '');
            }
        });
        $resultTotal['sales'] = $sales_query->count();

        if ($filter == 'sales') {
            if (! empty($search)) {
                $sales_query->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }

            // QUICK FILTERS
            if (! empty($quick_filter)) {
                $sales_query = $sales_query->whereNull($quick_filter);
            }
            $sales_data = $sales_query->PAGINATE(10);
            if (isset($sales_data)) {
                $sales_data->transform(function ($salesCountVal) {
                    $value = [];
                    $keys = [];
                    if (empty($salesCountVal->pid)) {
                        $value[] = 'pid';
                        $keys[] = 'pid';
                    }
                    //    if (empty($salesCountVal->install_partner)) {
                    //        $value[] = 'Install Partner';
                    //        $keys[] = 'install_partner';
                    //    }
                    if (empty($salesCountVal->customer_signoff)) {
                        $value[] = 'Customer Signoff';
                        $keys[] = 'customer_signoff';
                    }
                    if (empty($salesCountVal->gross_account_value)) {
                        $value[] = 'Gross Account Value';
                        $keys[] = 'gross_account_value';
                    }
                    if (empty($salesCountVal->epc)) {
                        $value[] = 'Epc';
                        $keys[] = 'epc';
                    }
                    if (empty($salesCountVal->net_epc)) {
                        $value[] = 'Net Epc';
                        $keys[] = 'net_epc';
                    }
                    if (empty($salesCountVal->dealer_fee_percentage)) {
                        $value[] = 'Dealer Fee Percentage';
                        $keys[] = 'dealer_fee_percentage';
                    }
                    if (empty($salesCountVal->customer_name)) {
                        $value[] = 'Customer Name';
                        $keys[] = 'customer_name';
                    }
                    if (empty($salesCountVal->customer_state)) {
                        $value[] = 'Customer state';
                        $keys[] = 'customer_state';
                    }
                    if (empty($salesCountVal->sales_rep_name)) {
                        $value[] = 'Rep Name';
                        $keys[] = 'sales_rep_name';
                    }
                    // if($salesCountVal->sales_rep_email == null)
                    // {
                    //     $value[] = 'Rep Email';
                    // }
                    // if ($salesCountVal->setter_id==null) {
                    //     $value[] = 'Setter';
                    // }
                    // if ($salesCountVal->sales_setter_email == null) {
                    //     $value[] = 'Setter Email';
                    // }
                    if (empty($salesCountVal->kw)) {
                        $value[] = 'Kw';
                        $keys[] = 'kw';
                    }

                    $update = implode(',', $value);

                    return [
                        'type_val' => 'Sales',
                        'id' => $salesCountVal->id,
                        'pid' => $salesCountVal->pid,
                        // 'heading' => $salesCountVal->pid.'-'.$salesCountVal->sales_rep_name.' - Data Missing',
                        // 'sales_rep_name' => $salesCountVal->sales_rep_name,
                        'alert_summary' => 'Update '.$update,
                        'keys' => $keys,
                        // 'type' => isset($salesCountVal->type)?$salesCountVal->type:'Missing Info',
                        // 'severity' => 'High',
                        // 'status' => ($salesCountVal->onboardProcess==1)?'Resolve':'Pending',
                        'updated' => $salesCountVal->updated_at,
                        'customer_name' => $salesCountVal->customer_name,
                    ];
                });
            }
            $finalData = $sales_data;
        }

        /***** closedPayroll alert  ********/

        $closedPayroll_query = LegacyApiNullData::where('action_status', 0)->where('type', 'Payroll')->where('status', '!=', 'Resolved')->whereNotNull('data_source_type')->orderBy('id', 'DESC');
        $resultTotal['closedPayroll'] = $closedPayroll_query->count();

        if ($filter == 'closedPayroll') {
            // QUICK FILTERS
            if (! empty($quick_filter)) {
                $closedPayroll_query = $closedPayroll_query->where('closedpayroll_type', $quick_filter);
            }

            if (! empty($search)) {
                $closedPayroll_query = $closedPayroll_query->where(function ($query) use ($search) {
                    $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
                });
            }

            $closedPayroll = $closedPayroll_query->paginate(10);

            $closedPayroll->transform(function ($salesCountVal) {
                $value = [];
                $keys = [];
                if ($salesCountVal->type == 'Payroll') {
                    $value[] = $salesCountVal->closedpayroll_type;
                    $keys[] = $salesCountVal->closedpayroll_type;
                }
                $update = implode(',', $value);

                return [
                    'type_val' => 'Closed Payroll',
                    'id' => $salesCountVal->id,
                    'pid' => $salesCountVal->pid,
                    'alert_summary' => 'Update '.$update,
                    'keys' => $keys,
                    'updated' => $salesCountVal->updated_at,
                    'customer_name' => $salesCountVal->customer_name,
                ];
            });
            $finalData = $closedPayroll;

        }

        /***** people alert  ********/
        $people_alert_query = User::where(function ($q) use ($people_keys) {
            foreach ($people_keys as $key) {
                $q->orWhereNull('users.'.$key)->orWhere('users.'.$key, '0')->orWhere('users.'.$key, '');
            }
        })->where('id', '!=', 1)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.redline',
                'users.position_id',
                'users.self_gen_type',
                'users.self_gen_accounts',
                'users.sub_position_id',
                'users.social_sequrity_no',
                'users.tax_information',
                'users.name_of_bank',
                'users.routing_no',
                'users.account_no',
                'users.type_of_account',
                'users.work_email',
                'users.onboardProcess',
                'users.entity_type',
                'users.updated_at',
                'self_gen_redline'
            );
        $resultTotal['people'] = $people_alert_query->count();

        if ($filter == 'people') {
            if (! empty($search)) {
                $people_alert_query = $people_alert_query->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'like', '%'.$search.'%')
                        ->orWhere('users.last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                });
            }

            // QUICK FILTERS
            if (! empty($quick_filter)) {
                $people_alert_query = $people_alert_query->whereNull($quick_filter);
            }

            $people_alert = $people_alert_query->PAGINATE(10);

            if (isset($people_alert)) {
                $people_alert->transform(function ($salesCountVal) {
                    $value = [];
                    $keys = [];
                    if (empty($salesCountVal->entity_type)) {
                        $value[] = 'tax information';
                        $keys[] = 'tax_information';
                    }
                    if (empty($salesCountVal->name_of_bank)) {
                        $value[] = 'name of bank';
                        $keys[] = 'name_of_bank';
                    }
                    if (empty($salesCountVal->routing_no)) {
                        $value[] = 'routing no';
                        $keys[] = 'routing_no';
                    }
                    if (empty($salesCountVal->account_no)) {
                        $value[] = 'account no';
                        $keys[] = 'account_no';
                    }
                    if (empty($salesCountVal->type_of_account)) {
                        $value[] = 'type of account';
                        $keys[] = 'type_of_account';
                    }
                    if (empty($salesCountVal->work_email)) {
                        $value[] = 'work email';
                        $keys[] = 'work_email';
                    }
                    if ($salesCountVal->onboardProcess == 0) {
                        $value[] = 'onboard process';
                        $keys[] = 'onboardProcess';
                    }
                    if ($salesCountVal->self_gen_accounts == 1 && empty($salesCountVal->self_gen_redline)) { // closer
                        $value[] = 'self gen redline';
                        $keys[] = 'self_gen_redline';
                    } elseif (empty($salesCountVal->redline)) { // closer
                        $value[] = 'redline';
                        $keys[] = 'redline';
                    }

                    if ($salesCountVal->self_gen_type == 2) {
                        $position = 'Closer';
                    } else {
                        $position = 'Setter';
                    }
                    $update = implode(',', $value);
                    if (! empty($update) || ($salesCountVal->self_gen_accounts == 1 && empty($salesCountVal->self_gen_redline))) {
                        return [
                            'type_val' => 'People',
                            'id' => $salesCountVal->id,
                            'self_gen_accounts' => $salesCountVal->self_gen_accounts,
                            'pid' => null,
                            // 'pid' => $salesCountVal->pid,
                            'alert_summary' => 'Update '.$update,
                            'keys' => $keys,
                            'updated' => $salesCountVal->updated_at,
                            'position' => $position,
                            'user_name' => $salesCountVal->first_name.' '.$salesCountVal->last_name,
                            'user_id' => $salesCountVal->id,
                        ];
                    }
                });
            }
            $finalData = $people_alert;
        }

        /***** locationRedline alert  ********/
        $location_Redline = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')->get();

        $location_Redline_data = [];

        foreach ($location_Redline as $salesCountVal) {
            $value = [];
            $keys = [];
            $location_data = '';
            $state_data = '';
            $state = [];
            $location = [];
            // geting state

            foreach ($state_all_data as $index => $state_code_row) {
                if (! empty($salesCountVal->customer_state) && strtolower(trim($state_code_row['state_code'])) == strtolower(trim($salesCountVal->customer_state))) {
                    $state = $state_code_row;
                    break;
                }
            }

            // geting locations
            foreach ($location_all_data as $index => $location_row) {
                if (! empty($salesCountVal->customer_state) && strtolower(trim($location_row['general_code'])) == strtolower(trim($salesCountVal->customer_state))) {
                    $location = $location_row;
                    break;
                }
            }

            // logic start
            if (! empty($state)) {
                $state_data = ['state_id' => $state['id'], 'state_name' => $state['name'], 'general_code' => $state['state_code']];
            }

            // geting location data
            if (empty($location)) {
                if (! empty($state)) {
                    // $location = Locations::where('state_id',$state['id'])->first();
                    $location_id_index = array_search($state['id'], $location_state_id_array);
                    if ($location_id_index != '' && $location_id_index != null) {
                        $location = $location_all_data[$location_id_index];
                    }

                    if (empty($location)) {
                        $state_data = ['state_id' => $state['id'], 'state_name' => $state['name'], 'general_code' => $salesCountVal->customer_state];
                    }
                } else {
                    $state_data = ['state_id' => '', 'general_code' => $salesCountVal->customer_state];
                }
            }

            // setting location alert
            if (empty($location)) {
                $value[] = 'location';
                $keys[] = 'Location';
            }

            if (! empty($location) && empty($location['redline_standard'])) {
                $value[] = 'location Redline';
                $keys[] = 'Location_redline';
                $location_data = $location;
                $location_data['redline_data'] = $location['additional_redline'];
                $location_data['effective_date'] = $location['date_effective'];
            }

            $date_found = true;
            if (! empty($location['additional_redline'])) {
                foreach ($location['additional_redline'] as $redlinedata) {
                    if (! empty($redlinedata['effective_date']) && strtotime($redlinedata['effective_date']) <= strtotime($salesCountVal->customer_signoff)) {
                        $date_found = false;
                    }
                }
            }
            if (! empty($location['date_effective']) && ($date_found)) {
                // $value[] = 'Location Redline missing for sale approval -'.$salesCountVal->customer_signoff;
                $value[] = 'Location Redline missing for sale approval - '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                $keys[] = 'Location_redline';
                $location_data = $location;
                $location_data['redline_data'] = $location['additional_redline'];
                $location_data['effective_date'] = $location['date_effective'];
            }

            if (! empty($value)) {
                $update = implode(',', $value);

                $location_Redline_data[] = [
                    'type_val' => 'location Redline',
                    'id' => $salesCountVal->id,
                    'pid' => $salesCountVal->pid,
                    'alert_summary' => 'Update '.$update,
                    'keys' => $keys,
                    'updated' => $salesCountVal->updated_at,
                    'customer_name' => $salesCountVal->customer_name,
                    'location_data' => $location_data,
                    'state_name' => isset($state['name']) ? $state['name'] : null,
                    'state_data' => $state_data,
                ];
            }
        }
        $resultTotal['locationRedline'] = count($location_Redline_data);

        if ($filter == 'locationRedline') {
            if (! empty($search)) {
                $searchData_is = [];
                foreach ($location_Redline_data as $item) {
                    $search_array = [
                        'pid' => $item['pid'],
                        'customer_name' => $item['customer_name'],
                    ];
                    $serializedItem = serialize($search_array);
                    if (stripos($serializedItem, $search) !== false) {
                        $searchData_is[] = $item;
                    }
                }
                $location_Redline_data_is = $searchData_is;
            } else {
                $location_Redline_data_is = $location_Redline_data;
            }
            $finalData = $this->paginate($location_Redline_data_is, 10);
        }

        /***** missingRep alert  ********/
        $missingRep_query = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')->where(function ($query) use ($missingRep_keys) {
            foreach ($missingRep_keys as $key) {
                $query->orWhereNull($key)->orWhere($key, '0')->orWhere($key, '');
            }
        });

        if (! empty($search)) {
            $missingRep_query = $missingRep_query->where(function ($query) use ($search) {
                $query->where('pid', 'like', '%'.$search.'%')->orWhere('customer_name', 'like', '%'.$search.'%');
            });
        }

        // QUICK FILTERS
        if (! empty($quick_filter)) {
            $missingRep_query = $missingRep_query->whereNull($quick_filter);
        }

        $missingRep_data = $missingRep_query->get();

        $missingRep_alert_data = [];
        foreach ($missingRep_data as $salesCountVal) {
            $value = [];
            $keys = [];
            $new_rep_email = null;
            if (empty($salesCountVal->sales_rep_email)) {
                $value[] = 'Sales Rep Email';
                $keys[] = 'sales_rep_email';
            }
            if (empty($salesCountVal->setter_id)) {
                $value[] = 'Setter';
                $keys[] = 'setter_id';
            }
            if ($salesCountVal->sales_rep_email != null || $salesCountVal->sales_rep_email != '') {
                // $user = User::where('email',$salesCountVal->sales_rep_email)->first();
                // if(empty($user))
                if (! in_array($salesCountVal->sales_rep_email, $user_email_array)) {
                    $value[] = 'Closer '.$salesCountVal->sales_rep_email.' not in users';
                    $keys[] = 'sales_rep_new';
                    $new_rep_email = $salesCountVal->sales_rep_email;
                }
            }
            if (! empty($value)) {
                $update = implode(',', $value);
                $missingRep_alert_data[] = [
                    'type_val' => 'Missing Rep',
                    'id' => $salesCountVal->id,
                    'pid' => $salesCountVal->pid,
                    // 'heading' => $salesCountVal->pid.'-'.$salesCountVal->sales_rep_name.' - Data Missing',
                    // 'sales_rep_name' => $salesCountVal->sales_rep_name,
                    'alert_summary' => 'Update '.$update,
                    'keys' => $keys,
                    // 'type' => isset($salesCountVal->type)?$salesCountVal->type:'Missing Info',
                    // 'severity' => 'High',
                    // 'status' => ($salesCountVal->onboardProcess==1)?'Resolve':'Pending',
                    'updated' => $salesCountVal->updated_at,
                    'customer_name' => $salesCountVal->customer_name,
                    'new_rep_email' => $new_rep_email,
                ];
            }
        }

        if (! empty($quick_filter) || ! empty($search)) {
            $resultTotal['missingRep'] = $this->count_missingRep_alert($missingRep_keys, $user_email_array);
        } else {
            $resultTotal['missingRep'] = count($missingRep_alert_data);
        }

        if ($filter == 'missingRep') {
            $finalData = $this->paginate($missingRep_alert_data, 10);
        }

        /***** repRedline alert  ********/
        $repRedline_query = LegacyApiNullData::where('action_status', 0)
            ->leftjoin('users as setter', 'setter.id', '=', 'legacy_api_data_null.setter_id')
            ->leftjoin('users', 'users.email', '=', 'legacy_api_data_null.sales_rep_email')
            ->where('legacy_api_data_null.action_status', 0)
            ->WhereNotNull('legacy_api_data_null.setter_id')
            ->WhereNotNull('legacy_api_data_null.sales_rep_email')
            ->whereNotNull('legacy_api_data_null.data_source_type')
            ->select(
                'setter.id as setter_id',
                'setter.first_name as setter_first_name',
                'setter.last_name as setter_last_name',
                'setter.redline as setter_redline',
                'setter.self_gen_redline as setter_self_gen_redline',
                'setter.redline_effective_date as setter_redline_effective_date',
                'setter.self_gen_redline_effective_date as setter_self_gen_redline_effective_date',
                'setter.position_id as setter_position_id',
                'setter.sub_position_id as setter_sub_position_id',
                'setter.self_gen_accounts as setter_self_gen_accounts',
                'setter.self_gen_type as setter_self_gen_type',
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.redline',
                'users.self_gen_redline',
                'users.redline_effective_date',
                'users.self_gen_redline_effective_date',
                'users.position_id',
                'users.self_gen_accounts',
                'users.self_gen_type',
                'users.sub_position_id',
                'legacy_api_data_null.pid',
                'legacy_api_data_null.updated_at',
                'legacy_api_data_null.customer_name',
                'legacy_api_data_null.customer_signoff'
            )
            ->selectRaw('CONCAT(users.first_name, " ", users.last_name) as full_name')
            ->selectRaw('CONCAT(setter.first_name, " ", setter.last_name) as setter_full_name');

        // if(!empty($search)){
        //     $repRedline_query = $repRedline_query->where(function($query) use($search){
        //         $query->where('pid', 'like', '%' . $search . '%')
        //             ->orWhere('customer_name', 'like', '%' . $search . '%')
        //             ->orWhere('users.first_name', 'like', '%' . $search . '%')
        //             ->orWhere('users.last_name', 'like', '%' . $search . '%')
        //             ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%' . $search . '%']);
        //     });
        // }
        $repRedline_data = $repRedline_query->get();

        $rep_redline_alert_data = [];
        foreach ($repRedline_data as $salesCountVal) {
            $value = [];
            $keys = [];
            $position = '';
            $position_name = ['2' => 'Closer', '3' => 'Setter'];
            $setter_full_name = $salesCountVal->setter_full_name;
            $full_name = $salesCountVal->full_name;

            if ($salesCountVal->self_gen_accounts == null || $salesCountVal->self_gen_accounts == 0) {
                // CLOSER - for main postion not self gen account
                $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                    ->where('start_date', '<=', $salesCountVal->customer_signoff)
                    ->where(function ($query) use ($salesCountVal) {
                        $query->Where('position_type', $salesCountVal->sub_position_id)->orWhere('position_type', $salesCountVal->position_id);
                    })->orderby('start_date', 'desc')->first();

                if (empty($user_redline_history_data)) {
                    $value[] = 'closer '.$full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                    $keys[] = 'closer_rep_redline';
                    $position = ($salesCountVal->sub_position_id == 2) ? $salesCountVal->sub_position_id : $salesCountVal->position_id;
                    // if($salesCountVal->redline == null || $salesCountVal->redline_effective_date == null || $salesCountVal->redline_effective_date > $salesCountVal->customer_signoff)
                    // {
                    //     $value[] = 'Redline is missing for sale approval '.date('m/d/Y',strtotime($salesCountVal->customer_signoff));
                    //     $keys[] = 'rep_redline';
                    //     $position = ($salesCountVal->sub_position_id==2)?$salesCountVal->sub_position_id:$salesCountVal->position_id;
                    // }
                }

            } elseif (($salesCountVal->self_gen_accounts != null || $salesCountVal->self_gen_accounts == 1)) {

                if ($salesCountVal->position_id == 2 || $salesCountVal->sub_position_id == 2) {
                    // CLOSER -  for main postion if has self gen account
                    $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->sub_position_id)->orWhere('position_type', $salesCountVal->position_id);
                        })->orderby('start_date', 'desc')->first();
                    if (empty($user_redline_history_data)) {
                        $value[] = 'Closer '.$full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'closer_rep_redline';
                        $position = ($salesCountVal->sub_position_id == 2) ? $salesCountVal->sub_position_id : $salesCountVal->position_id;
                    }

                } elseif ($salesCountVal->self_gen_type == 2) {
                    // CLOSER -  for self gen position
                    $self_gen_user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->self_gen_type);
                        })->orderby('start_date', 'desc')->first();

                    if (empty($self_gen_user_redline_history_data)) {
                        $value[] = 'Closer '.$full_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'closer_self_gen_redline';
                        $position = $salesCountVal->self_gen_type;
                    }
                }
            }

            // for setter
            if ($salesCountVal->setter_self_gen_accounts == null || $salesCountVal->setter_self_gen_accounts == 0) {
                // SETTER - for main postion not self gen account
                $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                    ->where('start_date', '<=', $salesCountVal->customer_signoff)
                    ->where(function ($query) use ($salesCountVal) {
                        $query->Where('position_type', $salesCountVal->setter_sub_position_id)->orWhere('position_type', $salesCountVal->setter_position_id);
                    })->orderby('start_date', 'desc')->first();

                if (empty($user_redline_history_data)) {
                    $value[] = 'Setter '.$setter_full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                    $keys[] = 'setter_rep_redline';
                    $position = ($salesCountVal->setter_sub_position_id == 2) ? $salesCountVal->setter_sub_position_id : $salesCountVal->setter_position_id;
                }
            } elseif (($salesCountVal->setter_self_gen_accounts != null || $salesCountVal->setter_self_gen_accounts == 1)) {

                if ($salesCountVal->setter_position_id == 2 || $salesCountVal->setter_sub_position_id == 2) {
                    // CLOSER -  for main postion if has self gen account
                    $user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->setter_sub_position_id)->orWhere('position_type', $salesCountVal->setter_position_id);
                        })->orderby('start_date', 'desc')->first();
                    if (empty($user_redline_history_data)) {
                        $value[] = 'Setter '.$setter_full_name.' Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'setter_rep_redline';
                        $position = ($salesCountVal->setter_sub_position_id == 2) ? $salesCountVal->setter_sub_position_id : $salesCountVal->setter_position_id;
                    }

                } elseif ($salesCountVal->setter_self_gen_type == 2) {
                    // CLOSER -  for self gen position
                    $self_gen_user_redline_history_data = UserRedlines::where('user_id', $salesCountVal->setter_id)
                        ->where('start_date', '<=', $salesCountVal->customer_signoff)
                        ->where(function ($query) use ($salesCountVal) {
                            $query->Where('position_type', $salesCountVal->setter_self_gen_type);
                        })->orderby('start_date', 'desc')->first();

                    if (empty($self_gen_user_redline_history_data)) {
                        $value[] = 'Setter '.$setter_full_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y', strtotime($salesCountVal->customer_signoff));
                        $keys[] = 'setter_rep_redline';
                        $position = $salesCountVal->setter_self_gen_type;
                    }
                }
            }

            // setting alert response
            if (! empty($value)) {
                $update = implode(', ', $value);
                $rep_redline_alert_data[] = [
                    'type_val' => 'Rep Redline',
                    'id' => $salesCountVal->id,
                    'pid' => $salesCountVal->pid,
                    'self_gen_accounts' => $salesCountVal->self_gen_accounts,
                    'setter_self_gen_accounts' => $salesCountVal->setter_self_gen_accounts,
                    'alert_summary' => 'Update '.$update,
                    'keys' => $keys,
                    'updated' => $salesCountVal->updated_at,
                    'customer_name' => $salesCountVal->customer_name,
                    'position_name' => ! empty($position) ? $position_name[$position] : null,
                    'rep_name' => $salesCountVal->first_name.' '.$salesCountVal->last_name,
                    'setter_full_name' => $setter_full_name,
                    'closer_full_name' => $full_name,
                    'setter_rep_name' => $salesCountVal->setter_first_name.' '.$salesCountVal->setter_last_name,
                    'setter_id' => $salesCountVal->setter_id,
                    'closer_id' => $salesCountVal->id,

                ];
            }
        }

        $resultTotal['repRedline'] = count($rep_redline_alert_data);

        if ($filter == 'repRedline') {
            if (! empty($search)) {
                $searchData_is = [];
                foreach ($rep_redline_alert_data as $item) {
                    $search_array = [
                        'pid' => $item['pid'],
                        'customer_name' => $item['customer_name'],
                        'rep_name' => $item['rep_name'],
                    ];
                    $serializedItem = serialize($search_array);
                    if (stripos($serializedItem, $search) !== false) {
                        $searchData_is[] = $item;
                    }
                }
                $rep_redline_alert_data_is = $searchData_is;
            } else {
                $rep_redline_alert_data_is = $rep_redline_alert_data;
            }
            $finalData = $this->paginate($rep_redline_alert_data_is, 10);
        }

        return response()->json(['ApiName' => 'Alert Data Api', 'status' => true, 'message' => 'Successfully', 'totalCount' => $resultTotal, 'data' => $finalData], 200);
    }

    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function count_missingRep_alert($missingRep_keys, $user_email_array)
    {
        $sales = LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')
            ->where(function ($query) use ($missingRep_keys) {
                foreach ($missingRep_keys as $key) {
                    $query->orWhereNull($key)->orWhere($key, '0')->orWhere($key, '');
                }
            });
        $sales = $sales->get();
        $data = [];
        foreach ($sales as $salesCountVal) {
            $value = [];
            $keys = [];
            if (empty($salesCountVal->sales_rep_email)) {
                $value[] = 'Sales Rep Email';
                $keys[] = 'sales_rep_email';
            }
            if (empty($salesCountVal->setter_id)) {
                $value[] = 'Setter';
                $keys[] = 'setter_id';
            }
            if ($salesCountVal->sales_rep_email != null || $salesCountVal->sales_rep_email != '') {
                // $user = User::where('email',$salesCountVal->sales_rep_email)->first();
                // if(empty($user))
                if (! in_array($salesCountVal->sales_rep_email, $user_email_array)) {
                    $value[] = 'Closer '.$salesCountVal->sales_rep_email.' not in users';
                    $keys[] = 'sales_rep_email';
                }
            }
            if (! empty($value)) {
                $update = implode(',', $value);
                $data[] = [
                    'type_val' => 'Missing Rep',
                    // 'id' => $salesCountVal->id,
                    // 'pid' => $salesCountVal->pid,
                    // // 'heading' => $salesCountVal->pid.'-'.$salesCountVal->sales_rep_name.' - Data Missing',
                    // // 'sales_rep_name' => $salesCountVal->sales_rep_name,
                    // 'alert_summary' => 'Update '.$update,
                    // 'keys' => $keys,
                    // // 'type' => isset($salesCountVal->type)?$salesCountVal->type:'Missing Info',
                    // // 'severity' => 'High',
                    // // 'status' => ($salesCountVal->onboardProcess==1)?'Resolve':'Pending',
                    // 'updated' => $salesCountVal->updated_at,
                    // 'customer_name' => $salesCountVal->customer_name,
                ];
            }
        }

        return count($data);
    }

    public function adders_by_pid($pid)
    {
        $exceldata = ImportExpord::select('id', 'pid', 'epc', 'net_epc')->where('pid', $pid)->orderBy('id', 'desc')->get();
        $old_netepc = (count($exceldata) > 1) ? $exceldata[1]->net_epc : null;
        $deduction = DeductionAlert::with('users')->where('pid', $pid)->get();
        $value = SalesMaster::with('salesMasterProcess', 'userDetail')->where('pid', '=', $pid)->first();
        // return $value;
        $data = [];
        if (count($deduction) > 0) {

            $deduction->transform(function ($vall) {
                return [
                    // 'id' => $vall->id,
                    'user_name' => $vall->users->first_name.' '.$vall->users->last_name,
                    'user_email' => $vall->users->email.' '.$vall->users->email,
                    'user_position' => ($vall->position_id == 2) ? 'Closer' : 'Setter',
                    'amount' => $vall->amount,
                    'status' => $vall->status,
                    'updated' => $vall->updated_at,
                ];
            });

            $approveDate = $value->customer_signoff;
            $m1_date = $value->m1_date;
            $m2_date = $value->m2_date;

            $closer1_detail = isset($value->salesMasterProcess->closer1_id) ? $value->salesMasterProcess->closer1Detail : null;
            $closer2_detail = isset($value->salesMasterProcess->closer2_id) ? $value->salesMasterProcess->closer2Detail : null;
            $setter1_detail = isset($value->salesMasterProcess->setter1_id) ? $value->salesMasterProcess->setter1Detail : null;
            $setter2_detail = isset($value->salesMasterProcess->setter2_id) ? $value->salesMasterProcess->setter2Detail : null;

            $closer1_m1 = isset($value->salesMasterProcess->closer1_m1) ? $value->salesMasterProcess->closer1_m1 : null;
            $closer1_m2 = isset($value->salesMasterProcess->closer1_m2) ? $value->salesMasterProcess->closer1_m2 : null;
            $closer2_m1 = isset($value->salesMasterProcess->closer2_m1) ? $value->salesMasterProcess->closer2_m1 : null;
            $closer2_m2 = isset($value->salesMasterProcess->closer2_m2) ? $value->salesMasterProcess->closer2_m2 : null;

            $setter1_m1 = isset($value->salesMasterProcess->setter1_m1) ? $value->salesMasterProcess->setter1_m1 : null;
            $setter1_m2 = isset($value->salesMasterProcess->setter1_m2) ? $value->salesMasterProcess->setter1_m2 : null;
            $setter2_m1 = isset($value->salesMasterProcess->setter2_m1) ? $value->salesMasterProcess->setter2_m1 : null;
            $setter2_m2 = isset($value->salesMasterProcess->setter2_m2) ? $value->salesMasterProcess->setter2_m2 : null;

            $pid_status = isset($value->salesMasterProcess->pid_status) ? $value->salesMasterProcess->pid_status : null;
            $total_amount = $value->total_in_period;

            $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
            $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);

            $data = [
                // 'id' => $value->id,
                'pid' => $value->pid,
                'adders' => $deduction,
                'installer' => $value->install_partner,
                'prospect_id' => $value->pid,
                'customer_name' => isset($value->customer_name) ? $value->customer_name : null,
                'customer_address' => $value->customer_address,
                'customer_address_2' => $value->customer_address_2,
                'homeowner_id' => $value->homeowner_id,
                'customer_city' => $value->customer_city,
                'state' => isset($value->customer_state) ? $value->customer_state : null,
                'customer_zip' => $value->customer_zip,
                'customer_email' => $value->customer_email,
                'customer_phone' => $value->customer_phone,
                'proposal_id' => $value->proposal_id,

                'closer1_detail' => $closer1_detail,
                'closer2_detail' => $closer2_detail,
                'setter1_detail' => $setter1_detail,
                'setter2_detail' => $setter2_detail,
                'closer1_m1' => $closer1_m1,
                'closer1_m2' => $closer1_m2,
                'closer2_m1' => $closer2_m1,
                'closer2_m2' => $closer2_m2,

                'setter1_m1' => $setter1_m1,
                'setter1_m2' => $setter1_m2,
                'setter2_m1' => $setter2_m1,
                'setter2_m2' => $setter2_m2,
                'closer1_m1_paid_status' => ($value->salesMasterProcess->closer1_m1_paid_status == '4') ? 'Paid' : null,
                'closer1_m2_paid_status' => ($value->salesMasterProcess->closer1_m2_paid_status == '5') ? 'Paid' : null,
                'setter1_m1_paid_status' => ($value->salesMasterProcess->setter1_m1_paid_status == '4') ? 'Paid' : null,
                'setter1_m2_paid_status' => ($value->salesMasterProcess->setter1_m2_paid_status == '5') ? 'Paid' : null,
                'epc' => isset($value->epc) ? $value->epc : null,
                'old_net_epc' => $old_netepc,
                'net_epc' => isset($value->net_epc) ? $value->net_epc : null,
                'kw' => isset($value->kw) ? $value->kw : null,
                'redline' => isset($value->redline) ? $value->redline : null,
                'date_cancelled' => isset($value->date_cancelled) ? dateToYMD($value->date_cancelled) : null,
                'm1_date' => isset($value->m1_date) ? dateToYMD($value->m1_date) : null,
                'm2_date' => isset($value->m2_date) ? dateToYMD($value->m2_date) : null,
                'approved_date' => $approveDate,
                'last_date_pd' => $value->last_date_pd,
                'product' => $value->product,
                'gross_account_value' => $value->gross_account_value,
                'dealer_fee_percentage' => $value->dealer_fee_percentage,
                'dealer_fee_amount' => $value->dealer_fee_amount,
                'sow_amount' => isset($value->adders) ? $value->adders : null,
                'adders_description' => $value->adders_description,
                'total_amount_for_acct' => $value->total_amount_for_acct,
                'prev_amount_paid' => $value->prev_amount_paid,
                'm1_amount' => $total_m1,
                'm2_amount' => $total_m2,
                'prev_deducted_amount' => $value->prev_deducted_amount,
                'cancel_fee' => $value->cancel_fee,
                'cancel_deduction' => $value->cancel_deduction,
                'adv_pay_back_amount' => $value->adv_pay_back_amount,
                'total_amount_in_period' => $value->total_amount_in_period,
                'created_at' => $value->created_at,
                'updated_at' => $value->updated_at,
            ];

            return response()->json([
                'ApiName' => 'adders_by_pid',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'adders_by_pid',
                'status' => false,
                'message' => 'data not found',
            ], 200);

        }
    }

    public function adJustClawbackSaleData($pid): JsonResponse
    {
        $clawbackAmount = ClawbackSettlement::where('pid', $pid)->sum('clawback_amount');
        $clawbackDate = ClawbackSettlement::select('pid', 'created_at')->where('pid', $pid)->first();
        $closerSetter = SaleMasterProcess::select('pid', 'closer1_id', 'closer2_id', 'setter1_id', 'setter2_id', 'closer1_m1', 'closer2_m1', 'setter1_m1', 'setter2_m1', 'closer1_m2', 'closer2_m2', 'setter1_m2', 'setter2_m2')->with('salesDetail', 'setter1Detail', 'setter2Detail', 'closer1Detail', 'closer2Detail')->where('pid', $pid)->first();
        $data = [
            'customer_name' => $closerSetter->salesDetail->customer_name,
            'pid' => $pid,
            'clawback_amount' => $clawbackAmount,
            'clawback_date' => $clawbackDate->created_at,
            'closer1_detail' => $closerSetter->closer1Detail,
            'closer2_detail' => $closerSetter->closer2Detail,
            'setter1_detail' => $closerSetter->setter1Detail,
            'setter2_detail' => $closerSetter->setter2Detail,

            'closer1_m1' => $closerSetter->closer1_m1,
            'closer1_m2' => $closerSetter->closer1_m2,
            'closer2_m1' => $closerSetter->closer2_m1,
            'closer2_m2' => $closerSetter->closer2_m2,
            'setter1_m1' => $closerSetter->setter1_m1,
            'setter1_m2' => $closerSetter->setter1_m2,
            'setter2_m1' => $closerSetter->setter2_m1,
            'setter2_m2' => $closerSetter->setter2_m2,
            'closer1_m1_paid_status' => ($closerSetter->closer1_m1_paid_status == '4') ? 'Paid' : null,
            'closer1_m2_paid_status' => ($closerSetter->closer1_m2_paid_status == '5') ? 'Paid' : null,
            'setter1_m1_paid_status' => ($closerSetter->setter1_m1_paid_status == '4') ? 'Paid' : null,
            'setter1_m2_paid_status' => ($closerSetter->setter1_m2_paid_status == '5') ? 'Paid' : null,
            'closer1_m1_paid_date' => $closerSetter->closer1_m1_paid_date,
            'closer1_m2_paid_date' => $closerSetter->closer1_m2_paid_date,
            'setter1_m1_paid_date' => $closerSetter->setter1_m1_paid_date,
            'setter1_m2_paid_date' => $closerSetter->setter1_m2_paid_date,
        ];

        return response()->json([
            'ApiName' => 'adders_by_pid',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function salesDetailByPid($id)
    {

        $newData = SalesMaster::where('pid', $id)->get();
        if (! $newData->isEmpty()) {
            $newData->transform(function ($data) {
                // return $data->total_amount_in_period;
                return [
                    'pid' => $data->pid,
                    'installer' => $data->install_partner,
                    'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                    'customer_address' => $data->customer_address,
                    'customer_address2' => $data->customer_address_2,
                    'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                    'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                    'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                    'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                    'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                    'cancel_date' => isset($data->cancel_date) ? $data->cancel_date : null,
                    'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                    'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                    'redline' => isset($data->redline) ? $data->redline : null,
                    'kw' => isset($data->kw) ? $data->kw : null,
                    'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                    'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                    'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                    'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                    'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                    'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                    'm1_amount' => isset($data->m1_amount) ? $data->m1_amount : '',
                    'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                    'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                    'product' => isset($data->product) ? $data->product : '',
                    'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                    'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                    'prev_paid' => isset($data->prev_amount_paid) ? $data->prev_amount_paid : null,
                    'epc' => isset($data->epc) ? $data->epc : null,
                    'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                    'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                    'dealer_fee_amount' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : null,
                    'prev_deducted_amount' => isset($data->prev_deducted_amount) ? $data->prev_deducted_amount : null,
                    'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                    'show' => isset($data->adders) ? $data->adders : null,
                    'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                    'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                    'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                    'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($data->total_amount_in_period) ? $data->total_amount_in_period : null,
                    'data_source_type' => $data->data_source_type,
                ];
            });
        } else {

            $newData = \DB::table('legacy_api_data_null as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.sales_setter_email', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product', 'lad.epc', 'lad.net_epc', 'lad.date_cancelled', 'lad.redline', 'lad.m1_this_week', 'lad.install_m2_this_week', 'lad.total_in_period', 'lad.last_date_pd', 'lad.prev_paid', 'lad.total_for_acct', 'lad.customer_signoff', 'lad.cancel_fee', 'lad.cancel_deduction', 'lad.lead_cost', 'lad.adv_pay_back_amount', 'lad.dealer_fee_dollar', 'lad.prev_deducted')
                ->LEFTJOIN('legacy_excel_raw_data as ld', 'ld.pid', '=', 'lad.pid')
                ->where('lad.pid', $id)
                ->get();

            //  $newData = LegacyApiNullData::where('pid',$id)->get();

            $newData->transform(function ($data) {
                $repId = User::where('email', $data->sales_rep_email)->first();
                $setterrId = User::where('email', $data->sales_setter_email)->first();

                return [

                    'pid' => $data->pid,
                    'installer' => $data->install_partner,
                    'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                    'customer_address' => $data->customer_address,
                    'customer_address2' => $data->customer_address_2,
                    'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                    'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                    'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                    'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                    'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                    'cancel_date' => isset($data->date_cancelled) ? $data->date_cancelled : null,
                    'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                    'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                    'redline' => isset($data->redline) ? $data->redline : null,
                    'kw' => isset($data->kw) ? $data->kw : null,
                    'rep_id' => isset($repId->id) ? $repId->id : null,
                    'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                    'setter_id' => isset($setterrId->id) ? $setterrId->id : null,
                    'setter_email' => isset($data->sales_setter_email) ? $data->sales_setter_email : null,
                    'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                    'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                    'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                    'm1_amount' => isset($data->m1_this_week) ? $data->m1_this_week : '',
                    'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                    'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                    'product' => isset($data->product) ? $data->product : '',
                    'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                    'gross_account_value' => isset($data->gross_account_value) ? round($data->gross_account_value, 3) : null,
                    'prev_paid' => isset($data->prev_paid) ? $data->prev_paid : null,
                    'epc' => isset($data->epc) ? round($data->epc, 3) : null,
                    'net_epc' => isset($data->net_epc) ? round($data->net_epc, 3) : null,
                    'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? round($data->dealer_fee_percentage, 3) : null,
                    'dealer_fee_amount' => isset($data->dealer_fee_dollar) ? round($data->dealer_fee_dollar, 3) : null,
                    'prev_deducted_amount' => isset($data->prev_deducted) ? $data->prev_deducted : null,
                    'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                    'show' => isset($data->adders) ? $data->adders : null,
                    'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                    'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                    'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                    'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($data->total_in_period) ? $data->total_in_period : null,

                ];

            });
        }

        if ($newData) {
            return response()->json(['ApiName' => 'Get Missing sales By Id', 'status' => true, 'data' => $newData], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Data not found'], 404);
        }
    }

    public function closerFilter(Request $request)
    {
        $closer = User::where('first_name', 'LIKE', '%'.$request->input('filter').'%')
            ->where('position_id', 2)
            ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhereHas('additionalEmails', function ($query) use ($request) {
                return $query->where('email', 'like', '%'.$request->input('filter').'%');
            })
            ->get();
        // // Additional condition for email_id in users_additional_emails table
        // $closer->orWhereHas('additionalEmails', function ($query) use ($search) {
        //     $query->where('email', 'like', '%' . $search . '%');
        // });
        if (! empty($closer)) {
            return response()->json(['status' => true, 'data' => $closer], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Data not found'], 404);
        }
    }

    public function setterFilter(Request $request)
    {
        $setter = User::where('first_name', 'LIKE', '%'.$request->input('filter').'%')
            ->where('position_id', 3)
            ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
            ->orWhereHas('additionalEmails', function ($query) use ($request) {
                return $query->where('email', 'like', '%'.$request->input('filter').'%');
            })
            ->get();
        if (! empty($setter)) {
            return response()->json(['status' => true, 'data' => $setter], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Data not found'], 404);
        }
    }

    public function updateMissingData(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $pid = $request->pid;
            $companyProfile = CompanyProfile::first();
            $checked = LegacyApiNullData::where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            // if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            // Update data by previous comparison in Sales_Master
            if (! empty($checked)) {
                $m1_this_week = $checked->m1_this_week;
                $m2_this_week = isset($checked->install_m2_this_week) ? $checked->install_m2_this_week : '';
                $locationCode = $checked->location_code;

                // customer state Id..................................................
                if (isset($request->rep_id[0])) {
                    $closer = User::where('id', $request->rep_id[0])->first();
                } else {
                    $closer = (object) [];
                }

                $netEPC = isset($request->net_epc) ? $request->net_epc : $checked->net_epc;

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $setter = null;
                    $setter2 = null;
                } else {
                    $setter = isset($request->setter_id[0]) ? $request->setter_id[0] : $checked->setter_id;
                    $setter2 = isset($request->setter_id[1]) ? $request->setter_id[1] : null;
                }

                if (config('app.domain_name') == 'flex') {
                    $locationCodeNew = isset($request->location_code) ? $request->location_code : $locationCode;
                    $customerStateNew = isset($request->location_code) ? $request->location_code : $locationCode;
                } else {
                    $locationCodeNew = isset($request->location_code) ? $request->location_code : $locationCode;
                    $location = Locations::with('State')->where('general_code', $locationCodeNew)->first();
                    $customerStateNew = isset($location->State->state_code) ? $location->State->state_code : null;
                }

                $data = [
                    'pid' => $pid,
                    'kw' => isset($request->kw) ? $request->kw : $checked->kw,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : $checked->customer_name,
                    'customer_state' => $customerStateNew,
                    'location_code' => $locationCodeNew,
                    'sales_rep_name' => isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : $checked->sales_rep_name,
                    'setter_id' => $setter,
                    'sales_rep_email' => isset($closer->email) ? strtolower($closer->email) : strtolower($checked->sales_rep_email),
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : $checked->customer_signoff,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $checked->m1_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : $checked->m2_date,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : $checked->gross_account_value,
                    'epc' => isset($request->epc) ? $request->epc : $checked->epc,
                    'net_epc' => $netEPC,
                    'data_source_type' => $checked->data_source_type,
                ];
                $checkedHistory = LegacyApiRawDataHistory::where('pid', $pid)->where('data_source_type', 'hubspot_current_energy')->orderBy('id', 'desc')->first();
                if ($checkedHistory) {
                    $checkedHistoryData = $checkedHistory->toArray();
                    $checkedHistoryData['closer1_id'] = isset($closer->id) ? $closer->id : null;
                    $checkedHistoryData['setter1_id'] = $setter;
                    if (empty($checkedHistoryData['customer_signoff'])) {
                        $checkedHistoryData['customer_signoff'] = isset($request->approved_date) ? $request->approved_date : $checked->customer_signoff;
                    }
                    if (empty($checkedHistoryData['epc'])) {
                        $checkedHistoryData['epc'] = isset($request->epc) ? $request->epc : $checked->epc;
                    }
                    if (empty($checkedHistoryData['net_epc'])) {
                        $checkedHistoryData['net_epc'] = $netEPC;
                    }
                    if (empty($checkedHistoryData['gross_account_value'])) {
                        $checkedHistoryData['gross_account_value'] = isset($request->gross_account_value) ? $request->gross_account_value : $checked->gross_account_value;
                    }
                    if (empty($checkedHistoryData['customer_name'])) {
                        $checkedHistoryData['customer_name'] = isset($request->customer_name) ? $request->customer_name : $checked->customer_name;
                    }
                    if (empty($checkedHistoryData['customer_state'])) {
                        $checkedHistoryData['customer_state'] = $customerStateNew;
                    }
                    if (empty($checkedHistoryData['location_code'])) {
                        $checkedHistoryData['location_code'] = $locationCodeNew;
                    }
                    $saleMaster = LegacyApiRawDataHistory::create($checkedHistoryData);
                }
                LegacyApiNullData::where('pid', $checked->pid)->update($data);

                $stateData = State::where('state_code', $customerStateNew)->first();
                $val = [
                    'pid' => $pid,
                    'kw' => isset($request->kw) ? $request->kw : $checked->kw,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : $checked->customer_name,
                    'customer_state' => $customerStateNew,
                    'state_id' => $stateData?->id,
                    'location_code' => $locationCodeNew,
                    'sales_rep_name' => isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : $checked->sales_rep_name,
                    'setter_id' => $setter,
                    'sales_rep_email' => isset($closer->email) ? strtolower($closer->email) : strtolower($checked->sales_rep_email),
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : $checked->customer_signoff,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $checked->m1_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : $checked->m2_date,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : $checked->gross_account_value,
                    'epc' => isset($request->epc) ? $request->epc : $checked->epc,
                    'net_epc' => $netEPC,
                    'm1_amount' => $m1_this_week,
                    'm2_amount' => $m2_this_week,
                    'data_source_type' => $checked->data_source_type,
                ];

                // CREATE HISTORY
                // $val_obj = json_decode(json_encode($val));
                // $user = User::where('email', $val_obj->sales_rep_email)->first();

                LegacyApiRowData::updateOrCreate(['pid' => $pid], [
                    'kw' => isset($request->kw) ? $request->kw : $checked->kw,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : $checked->customer_name,
                    'sales_rep_name' => isset($closer->first_name) ? $closer->first_name.' '.$closer->last_name : $checked->sales_rep_name,
                    'setter_id' => $setter,
                    'sales_rep_email' => isset($closer->email) ? strtolower($closer->email) : strtolower($checked->sales_rep_email),
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : $checked->customer_signoff,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $checked->m1_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : $checked->m2_date,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : $checked->gross_account_value,
                    'epc' => isset($request->epc) ? $request->epc : $checked->epc,
                    'net_epc' => $netEPC,
                    'data_source_type' => isset($request->data_source_type) ? $request->data_source_type : $checked->data_source_type,
                ]);
                SalesMaster::updateOrCreate(['pid' => $pid], $val);

                $insertData = SalesMaster::where('pid', $pid)->first();
                $closer = $request->rep_id;
                // $setter = $request->setter_id;
                $data = [
                    'sale_master_id' => $insertData->id,
                    'pid' => $checked->pid,
                    'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                    'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                    'setter1_id' => $setter,
                    'setter2_id' => $setter2,
                ];
                SaleMasterProcess::updateOrCreate(['pid' => $pid], $data);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $this->pestSubroutineProcess($pid);
                } else {
                    if ($setter) {
                        // Use silent wrapper for internal calls - ignores JsonResponse return
                        $this->executeSubroutineProcessSilently($pid);
                    }
                }
            }

            // sales alert status update
            DB::commit();
            Artisan::call('generate:alert', ['pid' => $pid]);

            return response()->json(['status' => true, 'Message' => 'Update Data successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info([
                'messege' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['message' => $e], 400);
        }
    }

    public function newSubroutineForExcel($pid)
    {
        $historyData = LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0'])->first();
        if ($historyData) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                if (isset($saleMasters->setter1_id)) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                }
            }
        }
    }

    public function pestSubroutineForExcel($pid)
    {
        $historyData = LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0'])->first();
        if ($historyData) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                if (isset($saleMasters->closer1_id)) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                }
            }
        }
    }

    public function subroutine_process_api_excel($pid): JsonResponse
    {
        $historyData = LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0'])->orderBy('id', 'desc')->first();
        if ($historyData) {
            $closers = [$historyData->closer1_id, $historyData->closer2_id];
            $setters = [$historyData->setter1_id, $historyData->setter2_id];
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();

            if ($saleMasters) {
                $saleMasterData = SalesMaster::where('pid', $pid)->first();
                if (! empty($saleMasterData->m1_date) && empty($historyData->m1_date)) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be removed because the M1 amount has already been paid']);
                    }
                    $this->m1dateSalesData($pid);
                }

                if (! empty($saleMasterData->m1_date) && ! empty($historyData->m1_date) && $saleMasterData->m1_date != $historyData->m1_date) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be changed because the M1 amount has already been paid']);
                    }
                    $this->m1datePayrollData($pid, $historyData->m1_date);
                }

                if (! empty($saleMasterData->m2_date) && empty($historyData->m2_date)) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be changed because the M2 amount has already been paid']);
                    }
                    $this->m2dateSalesData($pid, $saleMasterData->m2_date);
                }

                if (! empty($saleMasterData->m2_date) && ! empty($historyData->m2_date) && $saleMasterData->m2_date != $historyData->m2_date) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be changed because the M2 amount has already been paid']);
                    }
                    $this->m2datePayrollData($pid, $historyData->m2_date);
                }

                if (isset($saleMasters->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasters->closer1_id) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the closer cannot be changed because the M2 amount has already been paid']);
                    }
                    $this->updateSalesData($saleMasters->closer1_id, 2, $pid);
                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasters->closer1_id, 'type' => 'commission'])->first();
                    if (! $clawbackSett) {
                        $this->clawbackSalesData($saleMasters->closer1_id, $checked);
                    }

                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closers[0], 'is_displayed' => '1', 'status' => '1'])->first();
                    // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
                    if ($clawbackSett) {
                        ClawbackSettlement::where(['user_id' => $closers[0], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                        ClawbackSettlement::where(['sale_user_id' => $closers[0], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                    }

                    $saleMasters->setter1_m1_paid_status = null;
                    $saleMasters->closer1_m1 = 0;
                    $saleMasters->job_status = $historyData->job_status ?? null;
                    $saleMasters->save();
                }

                if (isset($saleMasters->setter1_id) && isset($setters[0]) && $setters[0] != $saleMasters->setter1_id) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the setter cannot be changed because the M2 amount has already been paid']);
                    }
                    $this->updateSalesData($saleMasters->setter1_id, 3, $pid);
                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $clawbackSettl = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasters->setter1_id, 'type' => 'commission'])->first();
                    if (! $clawbackSettl) {
                        $this->clawbackSalesData($saleMasters->setter1_id, $checked);
                    }

                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $setters[0], 'is_displayed' => '1', 'status' => '1'])->first();
                    // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
                    if ($clawbackSett) {
                        ClawbackSettlement::where(['user_id' => $setters[0], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                        ClawbackSettlement::where(['sale_user_id' => $setters[0], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                    }

                    $saleMasters->setter1_m1_paid_status = null;
                    $saleMasters->setter1_m1 = 0;
                    $saleMasters->job_status = $historyData->job_status ?? null;
                    $saleMasters->save();
                }
            }

            $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
            if ($check_SalesMaster) {
                if ($setters) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                }
            }
        }
    }

    /**
     * Process subroutine calculations for a sale.
     *
     * This method handles commission/override/upfront recalculations when sale data changes.
     * It sets up the SalesCalculationContext for custom field conversion support.
     *
     * IMPORTANT: When called internally (e.g., from other controller methods), the return
     * value may be ignored. The method will still process the sale and return early if:
     * - Payroll is being finalized
     * - Recon has been finalized for this sale
     * - No closer is assigned to the sale
     *
     * For internal calls where you want to silently skip if no closer is assigned,
     * use executeSubroutineProcessSilently() instead.
     *
     * @param string|int $pid The sale PID to process
     * @return JsonResponse The result of the subroutine processing
     */
    public function subroutine_process($pid): JsonResponse
    {
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        // if (LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->first()) {
        //     return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.'], 400);
        // }

        /* recon finalize condition check */
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where('pid', $pid)->where('status', 'finalize')->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where('pid', $pid)->where('status', 'finalize')->exists();
        $checkReconClawbackFinalizeData = ReconClawbackHistory::where('pid', $pid)->where('status', 'finalize')->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawbackFinalizeData) {
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not updated because the Recon amount has finalized or executed from recon'], 400);
        }

        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        
        // Handle case where sale doesn't exist
        if (!$checked) {
            return response()->json([
                'status' => false,
                'Message' => 'Sale not found for the given PID',
            ], 404);
        }
        
        $dateCancelled = $checked->date_cancelled;
        $m1Date = $checked->m1_date;
        $m2Date = $checked->m2_date;
        $kw = $checked->kw;

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        $saleUsers = [];
        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }
        if ($setterId) {
            $saleUsers[] = $setterId;
        }
        if ($setter2Id) {
            $saleUsers[] = $setter2Id;
        }

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $checked->gross_account_value;
        }

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Only set context when Custom Sales Fields feature is enabled
            // This ensures zero impact on companies without the feature
            if ($isCustomFieldsEnabled) {
                // Set context for custom field conversion (Trick Subroutine approach)
                // This enables auto-conversion of 'custom field' to 'per sale' in model events
                // during commission recalculation when position/employment package is updated
                SalesCalculationContext::set($checked, $companyProfile);
            }

            // Handle case where no closer is assigned
            if (!$closerId) {
                return response()->json([
                    'status' => false,
                    'Message' => 'Sale has no closer assigned',
                ], 400);
            }

            // WHEN CANCEL DATE
            if ($dateCancelled) {
                // // CLAWBACK CALCULATION
                ReconOverrideHistory::where(['pid' => $pid, 'is_ineligible' => '1'])->delete();
                ReconCommissionHistory::where(['pid' => $pid, 'is_ineligible' => '1'])->delete();

                // ClawbackSettlement::where(['pid' => $checked->pid, 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1'])->whereNotIn('clawback_type', ['reconciliation'])->whereIn('sale_user_id', $saleUsers)->delete();
                // ClawbackSettlement::where(['pid' => $checked->pid, 'type' => 'commission', 'status' => '1', 'is_displayed' => '1'])->whereNotIn('clawback_type', ['reconciliation'])->whereIn('user_id', $saleUsers)->delete();
                $this->subroutineFive($checked);
            } else {
                // IF M1 & M2 BOTH DATE IS PRESENT
                if ($m1Date && $m2Date) {
                    if ($m1Date != $m2Date) {
                        // CHECK M1 IS PAID OR NOT
                        $this->SubroutineThree($checked);
                    }

                    $oldKW = $kw;
                    $oldNetEpc = $checked->net_epc;
                    $isM2Paid = false;
                    $m2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                    if ($m2) {
                        $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                        if ($paidM2) {
                            $isM2Paid = true;
                            $oldKW = $paidM2->kw;
                            $oldNetEpc = $paidM2->net_epc;
                        } else {
                            $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                            if ($paidM2) {
                                $isM2Paid = true;
                                $oldKW = $paidM2->kw;
                                $oldNetEpc = $paidM2->net_epc;
                            }
                        }
                    } else {
                        $withheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                        if ($withheld) {
                            $paidwithheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'recon_status' => '3', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                            if ($paidwithheld) {
                                $isM2Paid = true;
                                $oldKW = $paidwithheld->kw;
                                $oldNetEpc = $paidwithheld->net_epc;
                            }
                        }
                    }

                    $isM2Update = false;
                    if ($isM2Paid) {
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            if (isset($oldKW) && $oldKW != $checked->gross_account_value) {
                                $isM2Update = true;
                            }
                        } else {
                            if ((isset($oldNetEpc) && $oldNetEpc != $checked->net_epc) || (isset($oldKW) && $oldKW != $checked->kw)) {
                                $isM2Update = true;
                            }
                        }
                    }

                    if ($isM2Paid && ! $isM2Update) {
                        $commission = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2 update', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->first();
                        $override = UserOverrides::where(['pid' => $pid, 'during' => 'm2 update', 'is_displayed' => '1'])->whereIn('sale_user_id', $saleUsers)->first();
                        if ($commission || $override) {
                            $isM2Update = true;
                        }
                    }

                    if ($isM2Update) {
                        $this->SubroutineEight($checked);
                        $this->subroutineEleven($checked);
                        if ($m2Date) {
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $redline = [
                                    'setter1_redline' => null,
                                    'setter2_redline' => null,
                                    'closer1_redline' => null,
                                    'closer2_redline' => null,
                                ];
                            } else {
                                $redline = $this->subroutineSix($checked);
                            }

                            // REMOVE GENERATED UNPAID ADDERS OVERRIDE
                            if ($setterId && $closerId != $setterId) {
                                UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->delete();
                                UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->delete();
                            }
                            if ($setter2Id) {
                                UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->delete();
                                UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->delete();
                            }
                            if ($closerId) {
                                UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->where('type', '!=', 'Stack')->delete();
                                UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->where('type', '!=', 'Stack')->delete();
                            }
                            if ($closer2Id) {
                                UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->delete();
                                UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->delete();
                            }

                            // GENERATE ADDERS OVERRIDE
                            if ($setterId && $closerId != $setterId) {
                                $this->AddersOverrides($setterId, $checked->pid, $kw, $m2Date, $redline['setter1_redline']);
                            }
                            if ($setter2Id) {
                                $this->AddersOverrides($setter2Id, $checked->pid, $kw, $m2Date, $redline['setter2_redline']);
                            }
                            if ($closerId) {
                                $this->AddersOverrides($closerId, $checked->pid, $kw, $m2Date, $redline['closer1_redline']);
                            }
                            if ($closer2Id) {
                                $this->AddersOverrides($closer2Id, $checked->pid, $kw, $m2Date, $redline['closer2_redline']);
                            }

                            UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2 update', 'is_displayed' => '1'])->delete();
                            UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2 update', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->delete();
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $this->pestAddersStackOverride($closerId, $checked->pid, $m2Date);
                            } else {
                                $this->addersStackOverride($closerId, $checked->pid, $kw, $m2Date);
                            }
                        }
                    } else {
                        $this->SubroutineEight($checked);
                        $this->subroutineNine($checked);
                        $this->m2updateRemoved($checked);
                        if ($m2Date) {
                            UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                            UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'type' => 'Stack', 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'during' => 'm2', 'is_move_to_recon' => '0', 'is_displayed' => '1'])->delete();
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $this->pestStackUserOverride($closerId, $checked->pid, $m2Date);
                            } else {
                                $this->StackUserOverride($closerId, $checked->pid, $kw, $m2Date);
                            }
                        }
                    }
                } elseif ($m1Date) { // IF ONLY M1 DATE IS PRESENT
                    // CHECK M1 IS PAID OR NOT
                    $this->SubroutineThree($checked);
                }
            }

            return response()->json(['status' => true, 'Message' => 'Sale recalculated successfully'], 200);
        } finally {
            // Only clear the context if it was set (feature is enabled)
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }

    /**
     * Execute subroutine process silently (for internal calls).
     *
     * This is a wrapper around subroutine_process() that silently handles
     * cases where processing is skipped (no closer, payroll in progress, etc.).
     * Use this for internal calls where you don't need to handle the JSON response.
     *
     * @param string|int $pid The sale PID to process
     * @return void
     */
    public function executeSubroutineProcessSilently($pid): void
    {
        // Check if sale has a closer before calling subroutine_process
        // This avoids the overhead of the full method when we know it will fail
        $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
        if (!$saleMasterProcess || !$saleMasterProcess->closer1_id) {
            return; // Silently skip - no closer assigned
        }

        // Call the main method and ignore the result
        // The method handles its own context management with try/finally
        $this->subroutine_process($pid);
    }

    public function addManualSaleData(Request $request): JsonResponse
    {

        // 'pay_period_to' => $payFrequency->pay_period_to,
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        if (LegacyApiRawDataHistory::where(['pid' => $request->pid, 'import_to_sales' => '0', 'data_source_type' => 'excel'])->first()) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently importing the excel and this PID is part of that excel. Please try again later. Thank you for your patience.'], 400);
        }

        /* recon finalize condition check */
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where('pid', $request->pid)->where('status', 'finalize')->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where('pid', $request->pid)->where('status', 'finalize')->exists();
        $checkReconClawbackFinalizeData = ReconClawbackHistory::where('pid', $request->pid)->where('status', 'finalize')->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawbackFinalizeData) {
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not updated because the Recon amount has finalized or executed from recon'], 400);
        }

        // PEST Flow STARTS
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'customer_name' => 'required',
                // 'customer_state' => 'required',
                'customer_state' => in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) ? 'nullable' : 'required',
                'state_id' => 'required',
                'gross_account_value' => 'required',
                'approved_date' => 'required',
                'rep_id' => 'required',
                'rep_email' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
            }

            if (empty(array_filter($request->rep_id))) {
                return response()->json(['status' => false, 'message' => 'At least one Sales rep is mandatory.'], 400);
            }
            // DB::beginTransaction();

            $pid = $request->pid;
            $closers = $request->rep_id;

            $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasterProcess) {
                $saleMasterData = SalesMaster::where('pid', $pid)->first();

                if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Initial service date cannot be removed because the Upfront amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Initial service date cannot be removed because the Upfront amount has already been paid'], 400);
                    }
                    $this->m1dateSalesData($pid);
                }
                if (! empty($saleMasterData->m1_date) && ! empty($request->m1_date) && $saleMasterData->m1_date != $request->m1_date) {
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Initial service date cannot be change because the Upfront amount has already been paid'], 400);
                    }
                    if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Initial service date cannot be change because the Upfront amount has finalized or executed from reconciliation'], 400);
                    }
                    $this->m1datePayrollData($pid, $request->m1_date);
                }

                $isM2Paid = false;
                $withHeldPaid = false;
                $m2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                if ($m2) {
                    $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first();
                    if ($paidM2) {
                        $isM2Paid = true;
                    } else {
                        $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                        if ($paidM2) {
                            $isM2Paid = true;
                        }
                    }
                } else {
                    $withheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                    if ($withheld) {
                        $paidwithheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                        if ($paidwithheld) {
                            $withHeldPaid = true;
                        }
                    }
                }

                if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
                    if ($isM2Paid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Service complition date cannot be removed because the Commission amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Service complition date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                    }

                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);
                    $this->clawbackSalesData($saleMasterProcess->closer2_id, $checked);

                    $saleMasterProcess->closer1_m1 = 0;
                    $saleMasterProcess->closer1_m1_paid_status = null;
                    $saleMasterProcess->closer2_m1 = 0;
                    $saleMasterProcess->closer2_m1_paid_status = null;
                }
                if (! empty($saleMasterData->m2_date) && ! empty($request->m2_date) && $saleMasterData->m2_date != $request->m2_date) {
                    if ($isM2Paid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Service complition date cannot be changed because the Commission amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the Service complition date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                    }
                    $this->m2datePayrollData($pid, $request->m2_date);
                }

                if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                    if ($isM2Paid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the sales rep cannot be change because the Commission amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the sales rep cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);

                    $saleMasterProcess->closer1_m1 = 0;
                    $saleMasterProcess->closer1_m1_paid_status = null;
                }

                if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
                    if ($isM2Paid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the sales rep 2 cannot be change because the Commission amount has already been paid'], 400);
                    }
                    if ($withHeldPaid) {
                        return response()->json(['status' => false, 'Message' => 'Apologies, the sales rep 2 cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                    }

                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $this->clawbackSalesData($saleMasterProcess->closer2_id, $checked);

                    $saleMasterProcess->closer2_m1 = 0;
                    $saleMasterProcess->closer2_m1_paid_status = null;
                }
                $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
                $saleMasterProcess->save();
            }

            // CREATE OR UPDATE HISTORY
            $closer = User::whereIn('id', $request->rep_id)->get();
            $historyData = [
                'legacy_data_id' => null,
                'pid' => $request->pid,
                'weekly_sheet_id' => null,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : null,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                'employee_id' => null,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name.' '.$closer[0]->last_name : null,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                'install_partner' => isset($request->installer) ? $request->installer : null,
                'install_partner_id' => null,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
                'scheduled_install' => null,
                'install_complete_date' => null,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                'cash_amount' => null,
                'loan_amount' => null,
                'kw' => isset($request->kw) ? $request->kw : null,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : 0,
                'adders' => isset($request->show) ? $request->show : null,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                'funding_source' => null,
                'financing_rate' => null,
                'financing_term' => null,
                'product' => isset($request->product) ? $request->product : null,
                'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : null,
                'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : null,
                'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : null,
                'service_completed' => isset($request->service_completed) ? $request->service_completed : null,
                'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : null,
                'bill_status' => isset($request->bill_status) ? $request->bill_status : null,
                'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : null,
                'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : null,
                'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : null,
                'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : null,

            ];

            $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
            if ($legacyApiRowData) {
                $historyData['data_source_type'] = 'manual';
                LegacyApiRowData::where('pid', $pid)->update($historyData);
            } else {
                $historyData['data_source_type'] = 'manual';
                LegacyApiRowData::create($historyData);
            }

            $netEPC = isset($request->net_epc) ? $request->net_epc : null;

            // UPDATE SALES MASTER
            $val = [
                'pid' => $pid,
                'kw' => isset($request->kw) ? $request->kw : null,
                'weekly_sheet_id' => null,
                'install_partner' => isset($request->installer) ? $request->installer : null,
                'install_partner_id' => null,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                'customer_address_2' => isset($request->customer_address_2) ? $request->customer_address_2 : null,
                'state_id' => isset($request->state_id) ? $request->state_id : null,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                'customer_state' => isset($request->state_code) ? $request->state_code : null,
                'location_code' => isset($request->customer_state) ? $request->customer_state : null,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name.' '.$closer[0]->last_name : null,
                'employee_id' => null,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                'product' => isset($request->product) ? $request->product : null,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                'epc' => isset($request->epc) ? $request->epc : null,
                'net_epc' => $netEPC,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
                'adders' => isset($request->show) ? $request->show : null,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                'redline' => isset($request->redline) ? $request->redline : null,
                'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
                'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
                'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
                'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
                'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
                'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
                'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
                'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
                'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
                'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
                'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : null,
                'return_sales_date' => null,
                'job_status' => isset($request->job_status) ? $request->job_status : null,
                'length_of_agreement' => isset($request->length_of_agreement) ? $request->length_of_agreement : null,
                'service_schedule' => isset($request->service_schedule) ? $request->service_schedule : null,
                'subscription_payment' => isset($request->subscription_payment) ? $request->subscription_payment : null,
                'service_completed' => isset($request->service_completed) ? $request->service_completed : null,
                'last_service_date' => isset($request->last_service_date) ? $request->last_service_date : null,
                'bill_status' => isset($request->bill_status) ? $request->bill_status : null,
                'initial_service_cost' => isset($request->initial_service_cost) ? $request->initial_service_cost : null,
                'auto_pay' => isset($request->auto_pay) ? $request->auto_pay : null,
                'card_on_file' => isset($request->card_on_file) ? $request->card_on_file : null,
                'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : null,

            ];

            $null_table_val = $val;
            $null_table_val['closer_id'] = isset($closer[0]->id) ? $closer[0]->id : null;
            $null_table_val['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name.' '.$closer[0]->last_name : null;
            $null_table_val['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : null;
            $null_table_val['job_status'] = isset($request->job_status) ? $request->job_status : null;
            unset($null_table_val['state_id']);
            unset($null_table_val['total_amount_for_acct']);
            unset($null_table_val['prev_amount_paid']);
            unset($null_table_val['m1_amount']);
            unset($null_table_val['m2_amount']);
            unset($null_table_val['prev_deducted_amount']);
            unset($null_table_val['lead_cost_amount']);
            unset($null_table_val['total_amount_in_period']);
            LegacyApiNullData::updateOrCreate([
                'pid' => $null_table_val['pid'],
            ], $null_table_val);

            $saleMasterData = SalesMaster::where('pid', $pid)->first();
            if ($saleMasterData) {
                if (empty($request->date_cancelled)) {
                    if (! empty($saleMasterData->date_cancelled) && empty(\request('date_cancelled'))) {
                        // When Clawback Is Paid, Sale Should Act As It's New Therefore
                        salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                        ClawbackSettlement::where(['pid' => $pid, 'is_displayed' => '1', 'status' => '1'])->whereNotIn('user_id', $closers)->delete();
                    }

                    SalesMaster::where('pid', $pid)->update($val);
                    $closer = $request->rep_id;
                    $data = [
                        'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                        'job_status' => isset($request->job_status) ? $request->job_status : null,
                    ];
                    SaleMasterProcess::where('pid', $pid)->update($data);
                } else {
                    if (empty($saleMasterData->date_cancelled) && ! empty(\request('date_cancelled'))) {
                        // When Clawback Is Paid, Sale Should Act As It's New Therefore
                        salesDataChangesClawback($saleMasterProcess->pid);
                    }
                    SalesMaster::where('pid', $pid)->update(['date_cancelled' => $request->date_cancelled]);
                }

                if ($closer) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                    $this->salesDataHistory($pid, 'manual');
                }
            } else {
                $val['data_source_type'] = 'manual';
                $insertData = SalesMaster::create($val);
                $closer = $request->rep_id;
                $data = [
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'pid' => $pid,
                    'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                    'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                    'job_status' => isset($request->job_status) ? $request->job_status : null,
                ];
                SaleMasterProcess::create($data);

                if ($closer) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                    $this->salesDataHistory($pid, 'manual');
                }
            }
            dispatch(new GenerateAlertJob($pid));

            return response()->json(['status' => true, 'message' => 'Add Data successfully']);
        } else {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'customer_name' => 'required',
                'customer_state' => 'required',
                'rep_id' => 'required',
                'setter_id' => 'required',
                'rep_email' => 'required',
                'kw' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
            }

            $pid = $request->pid;
            $closers = $request->rep_id;
            $setters = $request->setter_id;

            if (empty(array_filter($closers)) || empty(array_filter($setters))) {
                return response()->json(['status' => false, 'Message' => 'Select closer or setter field can not be blank'], 400);
            }
            try {
                DB::beginTransaction();
                $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
                if ($saleMasterProcess) {
                    $this->executedSalesData($request);
                    $saleMasterData = SalesMaster::where('pid', $pid)->first();

                    if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
                        if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be removed because the M1 amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be removed because the M1 amount has already been paid'], 400);
                        }
                        $this->m1dateSalesData($pid);
                    }
                    if (! empty($saleMasterData->m1_date) && ! empty($request->m1_date) && $saleMasterData->m1_date != $request->m1_date) {
                        if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be change because the M1 amount has already been paid'], 400);
                        }
                        if (UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M1 date cannot be change because the M1 amount has finalized or executed from reconciliation'], 400);
                        }
                        $this->m1datePayrollData($pid, $request->m1_date);
                    }

                    $isM2Paid = false;
                    $withHeldPaid = false;
                    $m2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                    if ($m2) {
                        $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first();
                        if ($paidM2) {
                            $isM2Paid = true;
                        } else {
                            $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                            if ($paidM2) {
                                $isM2Paid = true;
                            }
                        }
                    } else {
                        $withheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        if ($withheld) {
                            $paidwithheld = UserCommission::where(['pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
                            if ($paidwithheld) {
                                $withHeldPaid = true;
                            }
                        }
                    }

                    if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
                        if ($isM2Paid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be removed because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be removed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                        }

                        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                        $this->clawbackSalesData($saleMasterProcess->setter1_id, $checked);
                        $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);

                        $saleMasterProcess->closer1_m1 = 0;
                        $saleMasterProcess->closer1_m1_paid_status = null;
                        $saleMasterProcess->setter1_m1 = 0;
                        $saleMasterProcess->setter1_m1_paid_status = null;
                    }
                    if (! empty($saleMasterData->m2_date) && ! empty($request->m2_date) && $saleMasterData->m2_date != $request->m2_date) {
                        if ($isM2Paid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be changed because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the M2 date cannot be changed because the reconciliation amount has finalized or executed from reconciliation'], 400);
                        }
                        $this->m2datePayrollData($pid, $request->m2_date);
                    }

                    if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
                        if ($isM2Paid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the closer cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the closer cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                        $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);

                        $saleMasterProcess->closer1_m1 = 0;
                        $saleMasterProcess->closer1_m1_paid_status = null;
                    }

                    if (isset($saleMasterProcess->setter1_id) && isset($setters[0]) && $setters[0] != $saleMasterProcess->setter1_id) {
                        if ($isM2Paid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the setter cannot be change because the M2 amount has already been paid'], 400);
                        }
                        if ($withHeldPaid) {
                            return response()->json(['status' => false, 'Message' => 'Apologies, the setter cannot be change because the reconciliation amount has been finalized or executed from reconciliation'], 400);
                        }

                        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                        $this->clawbackSalesData($saleMasterProcess->setter1_id, $checked, true);

                        $saleMasterProcess->setter1_m1 = 0;
                        $saleMasterProcess->setter1_m1_paid_status = null;
                    }
                    $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
                    $saleMasterProcess->save();
                }

                $closer = User::whereIn('id', $request->rep_id)->get();
                $setter = User::whereIn('id', $request->setter_id)->get();

                $apiData = [
                    'legacy_data_id' => null,
                    'pid' => $request->pid,
                    'weekly_sheet_id' => null,
                    'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                    'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                    'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                    'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
                    'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                    'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
                    'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                    'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                    'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                    'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
                    'employee_id' => null,
                    'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
                    'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                    'install_partner' => isset($request->installer) ? $request->installer : null,
                    'install_partner_id' => null,
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
                    'scheduled_install' => null,
                    'install_complete_date' => null,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                    'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                    'cash_amount' => null,
                    'loan_amount' => null,
                    'kw' => isset($request->kw) ? $request->kw : null,
                    'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : 0,
                    'adders' => isset($request->show) ? $request->show : null,
                    'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                    'funding_source' => null,
                    'financing_rate' => null,
                    'financing_term' => null,
                    'product' => isset($request->product) ? $request->product : null,
                    'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : null,
                ];
                $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
                if ($legacyApiRowData) {
                    LegacyApiRowData::where('pid', $pid)->update($apiData);
                } else {
                    $apiData['data_source_type'] = 'manual';
                    LegacyApiRowData::create($apiData);
                }

                $netEPC = isset($request->net_epc) ? $request->net_epc : null;

                // Update data by previous comparison in Sales_Master
                $val = [
                    'pid' => $pid,
                    'kw' => isset($request->kw) ? $request->kw : null,
                    'weekly_sheet_id' => null,
                    'install_partner' => isset($request->installer) ? $request->installer : null,
                    'install_partner_id' => null,
                    'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                    'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                    'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
                    'state_id' => isset($request->state_id) ? $request->state_id : null,
                    'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                    'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
                    'location_code' => isset($request->customer_state) ? $request->customer_state : null,
                    'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                    'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                    'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                    'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                    'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                    'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
                    'employee_id' => null,
                    'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                    'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
                    'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                    'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                    'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                    'product' => isset($request->product) ? $request->product : null,
                    'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                    'epc' => isset($request->epc) ? $request->epc : null,
                    'net_epc' => $netEPC,
                    'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
                    'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
                    'adders' => isset($request->show) ? $request->show : null,
                    'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                    'redline' => isset($request->redline) ? $request->redline : null,
                    'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
                    'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
                    'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
                    'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
                    'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
                    'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
                    'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
                    'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
                    'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
                    'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
                    'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : null,
                    'return_sales_date' => null,
                    'job_status' => isset($request->job_status) ? $request->job_status : null,
                    'milestone_trigger' => isset($request->milestone_trigger) ? json_encode($request->milestone_trigger) : null,
                ];

                $null_table_val = $val;
                $null_table_val['setter_id'] = isset($setter[0]->id) ? $setter[0]->id : null;
                $null_table_val['closer_id'] = isset($closer[0]->id) ? $closer[0]->id : null;
                $null_table_val['sales_rep_name'] = isset($closer[0]->first_name) ? $closer[0]->first_name.' '.$closer[0]->last_name : null;
                $null_table_val['sales_rep_email'] = isset($closer[0]->email) ? $closer[0]->email : null;
                $null_table_val['sales_setter_name'] = isset($setter[0]->first_name) ? $setter[0]->first_name.' '.$setter[0]->last_name : null;
                $null_table_val['sales_setter_email'] = isset($setter[0]->email) ? $setter[0]->email : null;
                $null_table_val['job_status'] = isset($request->job_status) ? $request->job_status : null;
                unset($null_table_val['state_id']);
                unset($null_table_val['total_amount_for_acct']);
                unset($null_table_val['prev_amount_paid']);
                unset($null_table_val['m1_amount']);
                unset($null_table_val['m2_amount']);
                unset($null_table_val['prev_deducted_amount']);
                unset($null_table_val['lead_cost_amount']);
                unset($null_table_val['total_amount_in_period']);
                LegacyApiNullData::updateOrCreate(['pid' => $null_table_val['pid']], $null_table_val);

                $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
                if ($check_SalesMaster) {
                    if (empty($request->date_cancelled)) {
                        if (! empty($check_SalesMaster->date_cancelled) && empty(\request('date_cancelled'))) {
                            // WHEN CLAWBACK IS PAID, SALE SHOULD ACT AS IT'A NEW THEREFORE
                            salesDataChangesBasedOnClawback($saleMasterProcess->pid);
                        }
                        SalesMaster::where('pid', $pid)->update($val);
                        $closer = $request->rep_id;
                        $setter = $request->setter_id;
                        $data = [
                            'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                            'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                            'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                            'setter2_id' => isset($setter[1]) ? $setter[1] : null,
                            'job_status' => isset($request->job_status) ? $request->job_status : null,
                        ];
                        SaleMasterProcess::where('pid', $pid)->update($data);
                    } else {
                        if (empty($check_SalesMaster->date_cancelled) && ! empty(\request('date_cancelled'))) {
                            // WHEN CLAWBACK IS PAID, SALE SHOULD ACT AS IT'A NEW THEREFORE
                            salesDataChangesClawback($saleMasterProcess->pid);
                        }
                        SalesMaster::where('pid', $pid)->update(['date_cancelled' => $request->date_cancelled]);
                    }

                    if ($setter) {
                        // Use silent wrapper for internal calls - ignores JsonResponse return
                        $this->executeSubroutineProcessSilently($pid);
                        $this->salesDataHistory($pid, 'manual');
                    }
                } else {
                    $val['data_source_type'] = 'manual';
                    $insertData = SalesMaster::create($val);
                    $closer = $request->rep_id;
                    $setter = $request->setter_id;
                    $data = [
                        'sale_master_id' => $insertData->id,
                        'weekly_sheet_id' => $insertData->weekly_sheet_id,
                        'pid' => $pid,
                        'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                        'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                        'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                        'setter2_id' => isset($setter[1]) ? $setter[1] : null,
                        'job_status' => isset($request->job_status) ? $request->job_status : null,
                    ];
                    SaleMasterProcess::create($data);
                    if ($setter) {
                        // Use silent wrapper for internal calls - ignores JsonResponse return
                        $this->executeSubroutineProcessSilently($pid);
                        $this->salesDataHistory($pid, 'manual');
                    }
                }

                DB::commit();
                dispatch(new GenerateAlertJob($pid));

                return response()->json(['status' => true, 'Message' => 'Add Data successfully']);
            } catch (\Exception $e) {
                DB::rollBack();

                return response()->json(['status' => false, 'Message' => 'Error while adding data. Please try again later.', 'error' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]], 400);
            }
        }
    }

    /**
     * @OA\Post(
     *     path="/create-sale",
     *     summary="Create or update sale",
     *     description="Create or update a sale record",
     *     operationId="createSale",
     *     tags={"Sales"},
     *     security={
     *         {"api_key":{}}
     *     },
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Sale data",
     *
     *         @OA\JsonContent(
     *             required={"pid", "setter_email", "closer_email"},
     *
     *             @OA\Property(property="api-key", type="string", description="API secret key which you got from sequifi"),
     *             @OA\Property(property="pid", type="string", example="P12345", description="Unique identifier for the sale"),
     *             @OA\Property(property="setter_email", type="string", format="email", example="setter@example.com", description="Setter's email address"),
     *             @OA\Property(property="closer_email", type="string", format="email", example="closer@example.com", description="Closer's email address"),
     *             @OA\Property(property="setter_2_email", type="string", format="email", example="setter2@example.com", description="Second setter's email address (optional)"),
     *             @OA\Property(property="solar_system_size", type="number", format="float", example=5.5, description="Size of the solar system in kW")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sale data saved successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="API key is missing")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation exception",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="setter_email",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The setter email does not matches with our records.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server exception",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while processing your request.")
     *         )
     *     )
     * )
     */
    // Added By Jay M. => For Swagger API
    public function addSaleDataBySwagger(SwaggerSaleDataRequest $request): JsonResponse
    {
        try {
            $pid = $request->pid;

            $setter = User::where('email', $request->setter_email)->first();
            if (! $setter) {
                $setter = UsersAdditionalEmail::where('email', $request->setter_email)->first();
                $setter = User::where('id', $setter->user_id)->first();
            }

            if (! $setter) {
                return response()->json(['message' => 'Setter email not found', 'errors' => ['setter_email' => 'The setter email does not matches with our records.']], 422);
            }

            $closer = User::where('email', $request->closer_email)->first();
            if (! $closer) {
                $closer = UsersAdditionalEmail::where('email', $request->closer_email)->first();
                $closer = User::where('id', $closer->user_id)->first();
            }

            if (! $closer) {
                return response()->json(['message' => 'Closer email not found', 'errors' => ['closer_email' => 'The closer email does not matches with our records.']], 422);
            }

            if ($request->setter_2_email) {
                $setter2 = User::where('email', $request->setter_2_email)->first();
                if (! $setter2) {
                    $setter2 = UsersAdditionalEmail::where('email', $request->setter_2_email)->first();
                    if ($setter2) {
                        $setter2 = User::where('id', $setter2->user_id)->first();
                    }
                }

                if (! $setter2) {
                    return response()->json(['message' => 'Setter 2 email not found', 'errors' => ['setter_2_email' => 'The setter 2 email does not matches with our records.']], 422);
                }
            }

            if ($request->closer_2_email) {
                $closer2 = User::where('email', $request->closer_2_email)->first();
                if (! $closer2) {
                    $closer2 = UsersAdditionalEmail::where('email', $request->closer_2_email)->first();
                    if ($closer2) {
                        $closer2 = User::where('id', $closer2->user_id)->first();
                    }
                }

                if (! $closer2) {
                    return response()->json(['message' => 'Closer 2 email not found', 'errors' => ['closer_2_email' => 'The closer 2 email does not matches with our records.']], 422);
                }
            }

            $state = State::where('name', $request->customer_state)->first();
            if (! $state) {
                return response()->json(['message' => 'Customer state not found', 'errors' => ['customer_state' => 'The customer state does not matches with our records.']], 422);
            }
            $request->state_id = $state->id;
            $location = Locations::where(['general_code' => $request->general_code, 'state_id' => $state->id])->first();
            if (! $location) {
                return response()->json(['message' => 'General code not found', 'errors' => ['general_code' => 'The General code does not matches with our records.']], 422);
            }
            $request->customer_state = $location->general_code;

            $closer = $closer->id;
            $setter = $setter->id;
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                $request['rep_id'] = [$closer, @$closer2->id];
                $request['setter_id'] = [$setter, @$setter2->id];
                $request['date_cancelled'] = null;
                $this->executedSalesData($request);

                // update sale with m1-m2 date
                $saleMasterData = SalesMaster::where('pid', $pid)->first();

                if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
                    $m1comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3])->first();
                    if ($m1comm) {
                        return response()->json(['status' => false, 'Message' => 'This sale payroll is executed']);
                    }
                    $this->m1dateSalesData($pid);
                }

                if (! empty($saleMasterData->m1_date) && ! empty($request->m1_date) && $saleMasterData->m1_date != $request->m1_date) {
                    $this->m1datePayrollData($pid, $request->m1_date);
                }

                if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
                    $m2comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
                    if ($m2comm) {
                        return response()->json(['status' => false, 'Message' => 'This sale payroll is executed']);
                    }
                    $this->m2dateSalesData($pid, $saleMasterData->m2_date);
                }

                if (! empty($saleMasterData->m2_date) && ! empty($request->m2_date) && $saleMasterData->m2_date != $request->m2_date) {
                    $this->m2datePayrollData($pid, $request->m2_date);
                }
                // end update sale with m1-m2 date

                if (isset($saleMasters->closer1_id) && isset($closer) && $closer != $saleMasters->closer1_id) {
                    $this->updateSalesData($saleMasters->closer1_id, 2, $pid);
                    // changes 12-12-2023
                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasters->closer1_id, 'type' => 'commission'])->first();
                    if (! $clawbackSett) {
                        $this->clawbackSalesData($saleMasters->closer1_id, $checked);
                    }

                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closer, 'is_displayed' => '1', 'status' => '1'])->first();
                    // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER SETTER SELECTED AND CLAWBACK HASN'T PAID YET
                    if ($clawbackSett) {
                        ClawbackSettlement::where(['user_id' => $closer, 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                        ClawbackSettlement::where(['sale_user_id' => $closer, 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                    }

                    // end changes 12-12-2023
                    $saleMasters->setter1_m1_paid_status = null;
                    $saleMasters->closer1_m1 = 0;
                    $saleMasters->job_status = isset($request->job_status) ? $request->job_status : null;
                    $saleMasters->save();
                }

                if (isset($saleMasters->setter1_id) && isset($setter) && $setter != $saleMasters->setter1_id) {
                    $this->updateSalesData($saleMasters->setter1_id, 3, $pid);
                    // changes 12-12-2023
                    $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                    $clawbackSettl = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasters->setter1_id, 'type' => 'commission'])->first();
                    if (! $clawbackSettl) {
                        $this->clawbackSalesData($saleMasters->setter1_id, $checked);
                    }

                    $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $setter, 'is_displayed' => '1', 'status' => '1'])->first();
                    // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER SETTER SELECTED AND CLAWBACK HASN'T PAID YET
                    if ($clawbackSett) {
                        ClawbackSettlement::where(['user_id' => $setter, 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                        ClawbackSettlement::where(['sale_user_id' => $setter, 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                    }

                    // End changes 12-12-2023

                    $saleMasters->setter1_m1_paid_status = null;
                    $saleMasters->setter1_m1 = 0;
                    $saleMasters->save();
                }
            }

            $closer = User::where('id', $closer)->first();
            $setter = User::where('id', $setter)->first();

            $apiData = [
                'pid' => $request->pid,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                'setter_id' => isset($setter->id) ? $setter->id : null,
                'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
                'sales_rep_email' => isset($closer->email) ? $closer->email : null,
                'install_partner' => isset($request->installer) ? $request->installer : null,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                'kw' => isset($request->kw) ? $request->kw : null,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : 0,
                'adders' => isset($request->show) ? $request->show : null,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                'product' => isset($request->product) ? $request->product : null,
            ];

            $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
            if ($legacyApiRowData) {
                LegacyApiRowData::where('pid', $pid)->update($apiData);
            } else {
                $apiData['data_source_type'] = 'swagger';
                LegacyApiRowData::create($apiData);
            }

            $LegacyApiNullData = LegacyApiNullData::where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            if (! empty($LegacyApiNullData)) {
                $null_data = [
                    'setter_id' => isset($setter->id) ? $setter->id : $LegacyApiNullData->setter_id,
                    'sales_rep_email' => isset($request->rep_email) ? $request->rep_email : $LegacyApiNullData->sales_rep_email,
                    'job_status' => isset($request->job_status) ? $request->job_status : null,
                ];
                LegacyApiNullData::where('pid', $pid)->update($null_data);
            }

            $netEPC = isset($request->net_epc) ? $request->net_epc : null;

            // Update data by previous comparison in Sales_Master
            $val = [
                'pid' => $pid,
                'kw' => isset($request->kw) ? $request->kw : null,
                'install_partner' => isset($request->installer) ? $request->installer : null,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
                'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
                'state_id' => isset($request->state_id) ? $request->state_id : null,
                'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
                'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
                'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
                'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
                'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
                'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
                'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
                'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
                'sales_rep_email' => isset($closer->email) ? $closer->email : null,
                'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
                'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
                'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
                'product' => isset($request->product) ? $request->product : null,
                'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
                'epc' => isset($request->epc) ? $request->epc : null,
                'net_epc' => $netEPC, // isset($request->net_epc) ? $request->net_epc : null,
                'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
                'adders' => isset($request->show) ? $request->show : null,
                'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
                'redline' => isset($request->redline) ? $request->redline : null,
                'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
                'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
                'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
                'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
                'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
                'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
                'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
                'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
                'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
                'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
                'return_sales_date' => isset($request->return_sales_date) ? $request->return_sales_date : null,
                'job_status' => isset($request->job_status) ? $request->job_status : null,
            ];

            $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
            if ($check_SalesMaster) {
                SalesMaster::where('pid', $pid)->update($val);
                $data = [
                    'closer1_id' => isset($closer->id) ? $closer->id : null,
                    'closer2_id' => isset($closer2->id) ? $closer2->id : null,
                    'setter1_id' => isset($setter->id) ? $setter->id : null,
                    'setter2_id' => isset($setter2->id) ? $setter2->id : null,
                    'job_status' => isset($request->job_status) ? $request->job_status : null,
                ];
                SaleMasterProcess::where('pid', $pid)->update($data);
                if ($setter) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                    $this->salesDataHistory($pid, 'swagger');
                }
            } else {
                $val['data_source_type'] = 'swagger';
                $insertData = SalesMaster::create($val);
                $data = [
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'pid' => $pid,
                    'closer1_id' => isset($closer->id) ? $closer->id : null,
                    'closer2_id' => isset($closer2->id) ? $closer2->id : null,
                    'setter1_id' => isset($setter->id) ? $setter->id : null,
                    'setter2_id' => isset($setter2->id) ? $setter2->id : null,
                    'job_status' => isset($request->job_status) ? $request->job_status : null,
                ];
                SaleMasterProcess::create($data);
                if ($setter) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                    $this->salesDataHistory($pid, 'swagger');
                }
            }

            return response()->json(['status' => true, 'Message' => 'Add Data successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'Message' => 'Something went wrong while creating or updating sale.']);
        }
    }

    public function addManualSaleData_old(ApiMissingDataValidatedRequest $request)
    {
        // return $request;
        $pid = $request->pid;

        $closer = User::whereIn('id', $request->rep_id)->get();
        $setter = User::whereIn('id', $request->setter_id)->get();
        // return $setter[0]->id;
        $excelData = [
            'ct' => '',
            'weekly_sheet_id' => null,
            'affiliate' => $request->affiliate,
            'pid' => $request->pid,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'cancel_date' => isset($request->cancel_date) ? $request->cancel_date : null,
            'approved_date' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'state' => isset($request->customer_state) ? $request->customer_state : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_dollar' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'show' => isset($request->show) ? $request->show : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_this_week' => isset($request->m1_amount) ? $request->m1_amount : null,
            'install_m2_this_week' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            'inactive_date' => null,
        ];
        $insertExcelData = ImportExpord::create($excelData);

        $apiData = [
            'legacy_data_id' => null,
            'pid' => $request->pid,
            'weekly_sheet_id' => null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
            'employee_id' => null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'scheduled_install' => null,
            'install_complete_date' => null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'date_cancelled' => isset($request->cancel_date) ? $request->cancel_date : null,
            'return_sales_date' => null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'cash_amount' => null,
            'loan_amount' => null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'funding_source' => null,
            'financing_rate' => null,
            'financing_term' => null,
            'product' => isset($request->product) ? $request->product : null,
        ];

        $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
        if ($legacyApiRowData) {
            $updateApiData = LegacyApiRowData::where('pid', $pid)->update($apiData);
        } else {
            $insertApiData = LegacyApiRowData::create($apiData);
        }

        // Update data by previous comparison in Sales_Master

        $val = [
            'pid' => $pid,
            'kw' => isset($request->kw) ? $request->kw : null,
            'weekly_sheet_id' => null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
            'employee_id' => null,
            'date_cancelled' => null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'date_cancelled' => isset($request->cancel_date) ? $request->cancel_date : null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
            'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            // 'funding_source' => isset($request->funding_source)?$request->funding_source:null,
            // 'financing_rate' => isset($request->financing_rate)?$request->financing_rate:null,
            // 'financing_term' => isset($request->financing_term)?$request->financing_term:null,
            // 'scheduled_install' => isset($request->scheduled_install)?$request->scheduled_install:null,
            // 'install_complete_date' => isset($request->install_complete_date)?$request->install_complete_date:null,
            // 'return_sales_date' => isset($request->return_sales_date)?$request->return_sales_date:null,
            // 'cash_amount' => isset($request->cash_amount)?$request->cash_amount:null,
            // 'loan_amount' => isset($request->loan_amount)?$request->loan_amount:null,
        ];

        $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
        if ($check_SalesMaster) {

            $updateData = SalesMaster::where('pid', $pid)->update($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::where('pid', $pid)->update($data);

            if ($setter) {
                // Use silent wrapper for internal calls - ignores JsonResponse return
                $this->executeSubroutineProcessSilently($pid);
            }

        } else {

            $insertData = SalesMaster::create($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'sale_master_id' => $insertData->id,
                'weekly_sheet_id' => $insertData->weekly_sheet_id,
                'pid' => $pid,
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::create($data);

            if ($setter) {
                // Use silent wrapper for internal calls - ignores JsonResponse return
                $this->executeSubroutineProcessSilently($pid);
            }

        }

        return response()->json(['status' => true, 'Message' => 'Add Data successfully'], 200);
    }

    public function disableLoginStatus(Request $request)
    {
        $pass = Auth()->user()->password;
        $superAdmin = Auth()->user()->is_super_admin;
        if (! Hash::check($request->password, $pass)) {
            return response()->json(['success' => false, 'message' => 'Password does not match with our record.'], 400);
        }
        if ($superAdmin == 1) {
            $newData = SalesMaster::where('pid', $request->pid)->get();
            if (! $newData->isEmpty()) {
                $newData->transform(function ($data) {
                    return [
                        'pid' => $data->pid,
                        'installer' => $data->install_partner,
                        'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                        'customer_address' => $data->customer_address,
                        'customer_address2' => $data->customer_address_2,
                        'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                        'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                        'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                        'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                        'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                        'cancel_date' => isset($data->cancel_date) ? $data->cancel_date : null,
                        'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                        'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                        'redline' => isset($data->redline) ? $data->redline : null,
                        'kw' => isset($data->kw) ? $data->kw : null,
                        'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                        'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                        'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                        'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                        'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                        'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                        'm1_amount' => isset($data->m1_amount) ? $data->m1_amount : '',
                        'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                        'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                        'product' => isset($data->product) ? $data->product : '',
                        'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                        'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                        'prev_paid' => isset($data->prev_amount_paid) ? $data->prev_amount_paid : null,
                        'epc' => isset($data->epc) ? $data->epc : null,
                        'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                        'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                        'dealer_fee_amount' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : null,
                        'prev_deducted_amount' => isset($data->prev_deducted_amount) ? $data->prev_deducted_amount : null,
                        'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                        'show' => isset($data->adders) ? $data->adders : null,
                        'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                        'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                        'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                        'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                        'total_amount_in_period' => isset($data->total_amount_in_period) ? $data->total_amount_in_period : null,
                    ];
                });
            } else {
                return response()->json([
                    'ApiName' => 'get sales data by super admin',
                    'status' => true,
                    'message' => 'Data not found',
                ], 400);
            }

            return response()->json([
                'ApiName' => 'get sales data by super admin',
                'status' => true,
                'message' => 'Successfully',
                'data' => $newData,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get sales data by super admin',
                'status' => false,
                'message' => 'You do not have access ',

            ], 400);

        }

    }

    public function MissingSalesDetailByPid($id)
    {

        $newData = ImportExpord::where('pid', $id)->get();
        if (! $newData->isEmpty()) {
            $newData->transform(function ($data) {
                // return $data->apiNullData->epc;die;
                return [
                    'pid' => $data->pid,
                    'installer' => $data->install_partner,
                    'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                    'customer_address' => $data->customer_address,
                    'customer_address2' => $data->customer_address_2,
                    'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                    'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                    'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                    'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                    'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                    'cancel_date' => isset($data->cancel_date) ? $data->cancel_date : null,
                    'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                    'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                    'redline' => isset($data->redline) ? $data->redline : null,
                    'kw' => isset($data->kw) ? $data->kw : null,
                    'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                    'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                    'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                    'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                    'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                    'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                    'm1_amount' => isset($data->m1_amount) ? $data->m1_amount : '',
                    'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                    'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                    'product' => isset($data->product) ? $data->product : '',
                    'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                    'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                    'prev_paid' => isset($data->prev_amount_paid) ? $data->prev_amount_paid : null,
                    'epc' => isset($data->epc) ? $data->epc : null,
                    'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                    'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                    'dealer_fee_amount' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : null,
                    'prev_deducted_amount' => isset($data->prev_deducted_amount) ? $data->prev_deducted_amount : null,
                    'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                    'show' => isset($data->adders) ? $data->adders : null,
                    'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                    'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                    'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                    'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($data->total_amount_in_period) ? $data->total_amount_in_period : null,
                ];
            });
        } else {
            // echo"ASD";die;

            $newData = \DB::table('legacy_api_data_null as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product', 'lad.epc', 'lad.net_epc', 'ld.cancel_date', 'ld.redline', 'ld.m1_this_week', 'ld.install_m2_this_week', 'ld.total_in_period', 'ld.last_date_pd', 'ld.prev_paid', 'ld.total_for_acct', 'ld.approved_date', 'ld.cancel_fee', 'ld.cancel_deduction', 'ld.lead_cost', 'ld.adv_pay_back_amount', 'ld.dealer_fee_dollar', 'ld.prev_deducted')
                ->LEFTJOIN('legacy_excel_raw_data as ld', 'ld.pid', '=', 'lad.pid')
                ->where('lad.pid', $id)
                ->get();

            $newData->transform(function ($data) {
                return [

                    'pid' => $data->pid,
                    'installer' => $data->install_partner,
                    'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                    'customer_address' => $data->customer_address,
                    'customer_address2' => $data->customer_address_2,
                    'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                    'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                    'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                    'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                    'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                    'cancel_date' => isset($data->cancel_date) ? $data->cancel_date : null,
                    'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                    'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                    'redline' => isset($data->redline) ? $data->redline : null,
                    'kw' => isset($data->kw) ? $data->kw : null,
                    'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                    'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                    'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                    'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                    'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                    'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                    'm1_amount' => isset($data->m1_this_week) ? $data->m1_this_week : '',
                    'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                    'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                    'product' => isset($data->product) ? $data->product : '',
                    'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                    'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                    'prev_paid' => isset($data->prev_paid) ? $data->prev_paid : null,
                    'epc' => isset($data->epc) ? $data->epc : null,
                    'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                    'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                    'dealer_fee_amount' => isset($data->dealer_fee_dollar) ? $data->dealer_fee_dollar : null,
                    'prev_deducted_amount' => isset($data->prev_deducted) ? $data->prev_deducted : null,
                    'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                    'show' => isset($data->adders) ? $data->adders : null,
                    'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                    'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                    'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                    'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($data->total_in_period) ? $data->total_in_period : null,

                ];

            });
        }

        if ($newData) {
            return response()->json(['ApiName' => 'Get Missing sales By Id', 'status' => true, 'data' => $newData], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Data not found'], 404);
        }
    }

    public function create_raw_data_history($val)
    {

        $data = [
            'pid' => isset($val->prospect_id) ? $val->prospect_id : (isset($val->pid) ? $val->pid : null),
            'weekly_sheet_id' => null,
            'homeowner_id' => isset($val->homeowner_id) ? $val->homeowner_id : null,
            'proposal_id' => isset($val->proposal_id) ? $val->proposal_id : null,
            'customer_name' => isset($val->customer_name) ? $val->customer_name : null,
            'customer_address' => isset($val->customer_address) ? $val->customer_address : null,
            'customer_address_2' => isset($val->customer_address_2) ? $val->customer_address_2 : null,
            'customer_city' => isset($val->customer_city) ? $val->customer_city : null,
            'customer_state' => isset($val->customer_state) ? $val->customer_state : null,
            'customer_zip' => isset($val->customer_zip) ? $val->customer_zip : null,
            'customer_email' => isset($val->customer_email) ? $val->customer_email : null,
            'customer_phone' => isset($val->customer_phone) ? $val->customer_phone : null,
            'setter_id' => isset($val->setter_id) ? $val->setter_id : null,
            'employee_id' => isset($val->employee_id) ? $val->employee_id : null,
            'sales_rep_name' => isset($val->rep_name) ? $val->rep_name : (isset($val->sales_rep_name) ? $val->sales_rep_name : null),
            'sales_rep_email' => isset($val->rep_email) ? $val->rep_email : (isset($val->sales_rep_email) ? $val->sales_rep_email : null),
            'install_partner' => isset($val->install_partner) ? $val->install_partner : null,
            'install_partner_id' => isset($val->install_partner_id) ? $val->install_partner_id : null,
            'customer_signoff' => isset($val->customer_signoff) ? $val->customer_signoff : null,
            'm1_date' => isset($val->m1) ? $val->m1 : (isset($val->m1_date) ? $val->m1_date : null),
            'm2_date' => isset($val->m2) ? $val->m2 : (isset($val->m2_date) ? $val->m2_date : null),
            'scheduled_install' => isset($val->scheduled_install) ? $val->scheduled_install : null,
            'install_complete_date' => isset($val->install_complete) ? $val->install_complete : null,
            'date_cancelled' => isset($val->date_cancelled) ? $this->get_date_only($val->date_cancelled) : null,
            // 'return_sales_date' => isset($val->return_sales_date)?$val->return_sales_date:NULL,
            'return_sales_date' => null,
            'gross_account_value' => isset($val->gross_account_value) ? $val->gross_account_value : null,
            'cash_amount' => isset($val->cash_amount) ? $val->cash_amount : null,
            'loan_amount' => isset($val->loan_amount) ? $val->loan_amount : null,
            'kw' => isset($val->kw) ? $val->kw : null,
            'dealer_fee_percentage' => isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null,
            'dealer_fee_amount' => isset($val->dealer_fee_amount) ? $val->dealer_fee_amount : null,
            'adders' => isset($val->adders) ? $val->adders : null,
            'cancel_fee' => isset($val->cancel_fee) ? $val->cancel_fee : null,
            'adders_description' => isset($val->adders_description) ? $val->adders_description : null,
            'redline' => isset($val->redline) ? $val->redline : null,
            'total_amount_for_acct' => isset($val->total_amount_for_acct) ? $val->total_amount_for_acct : null,
            'prev_amount_paid' => isset($val->prev_amount_paid) ? $val->prev_amount_paid : null,
            'last_date_pd' => isset($val->last_date_pd) ? $val->last_date_pd : null,
            'm1_amount' => isset($val->m1_amount) ? $val->m1_amount : null,
            'm2_amount' => isset($val->m2_amount) ? $val->m2_amount : null,
            'prev_deducted_amount' => isset($val->prev_deducted_amount) ? $val->prev_deducted_amount : null,
            'cancel_deduction' => isset($val->cancel_deduction) ? $val->cancel_deduction : null,
            'lead_cost_amount' => isset($val->lead_cost_amount) ? $val->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($val->adv_pay_back_amount) ? $val->adv_pay_back_amount : null,
            'total_amount_in_period' => isset($val->total_amount_in_period) ? $val->total_amount_in_period : null,
            'funding_source' => isset($val->funding_source) ? $val->funding_source : null,
            'financing_rate' => isset($val->financing_rate) ? $val->financing_rate : null,
            'financing_term' => isset($val->financing_term) ? $val->financing_term : null,
            'product' => isset($val->product) ? $val->product : null,
            'epc' => isset($val->epc) ? $val->epc : null,
            'net_epc' => isset($val->net_epc) ? $val->net_epc : null,
            'closer1_id' => isset($val->closer1_id) ? $val->closer1_id : null,
            'closer2_id' => isset($val->closer2_id) ? $val->closer2_id : null,
            'setter1_id' => isset($val->setter1_id) ? $val->setter1_id : null,
            'setter2_id' => isset($val->setter2_id) ? $val->setter2_id : null,
            'closer1_m1' => isset($val->closer1_m1) ? $val->closer1_m1 : 0,
            'closer2_m1' => isset($val->closer2_m1) ? $val->closer2_m1 : 0,
            'setter1_m1' => isset($val->setter1_m1) ? $val->setter1_m1 : 0,
            'setter2_m1' => isset($val->setter2_m1) ? $val->setter2_m1 : 0,
            'closer1_m2' => isset($val->closer1_m2) ? $val->closer1_m2 : 0,
            'closer2_m2' => isset($val->closer2_m2) ? $val->closer2_m2 : 0,
            'setter1_m2' => isset($val->setter1_m2) ? $val->setter1_m2 : 0,
            'setter2_m2' => isset($val->setter2_m2) ? $val->setter2_m2 : 0,
            'closer1_commission' => isset($val->closer1_commission) ? $val->closer1_commission : 0,
            'closer2_commission' => isset($val->closer2_commission) ? $val->closer2_commission : 0,
            'setter1_commission' => isset($val->setter1_commission) ? $val->setter1_commission : 0,
            'setter2_commission' => isset($val->setter2_commission) ? $val->setter2_commission : 0,
            'closer1_m1_paid_status' => isset($val->closer1_m1_paid_status) ? $val->closer1_m1_paid_status : null,
            'closer2_m1_paid_status' => isset($val->closer2_m1_paid_status) ? $val->closer2_m1_paid_status : null,
            'setter1_m1_paid_status' => isset($val->setter1_m1_paid_status) ? $val->setter1_m1_paid_status : null,
            'setter2_m1_paid_status' => isset($val->setter2_m1_paid_status) ? $val->setter2_m1_paid_status : null,
            'closer1_m2_paid_status' => isset($val->closer1_m2_paid_status) ? $val->closer1_m2_paid_status : null,
            'closer2_m2_paid_status' => isset($val->closer2_m2_paid_status) ? $val->closer2_m2_paid_status : null,
            'setter1_m2_paid_status' => isset($val->setter1_m2_paid_status) ? $val->setter1_m2_paid_status : null,
            'setter2_m2_paid_status' => isset($val->setter2_m2_paid_status) ? $val->setter2_m2_paid_status : null,
            'closer1_m1_paid_date' => isset($val->closer1_m1_paid_date) ? $val->closer1_m1_paid_date : null,
            'closer2_m1_paid_date' => isset($val->closer2_m1_paid_date) ? $val->closer2_m1_paid_date : null,
            'setter1_m1_paid_date' => isset($val->setter1_m1_paid_date) ? $val->setter1_m1_paid_date : null,
            'setter2_m1_paid_date' => isset($val->setter2_m1_paid_date) ? $val->setter2_m1_paid_date : null,
            'closer1_m2_paid_date' => isset($val->closer1_m2_paid_date) ? $val->closer1_m2_paid_date : null,
            'closer2_m2_paid_date' => isset($val->closer2_m2_paid_date) ? $val->closer2_m2_paid_date : null,
            'setter1_m2_paid_date' => isset($val->setter1_m2_paid_date) ? $val->setter1_m2_paid_date : null,
            'setter2_m2_paid_date' => isset($val->setter2_m2_paid_date) ? $val->setter2_m2_paid_date : null,
            'mark_account_status_id' => isset($val->mark_account_status_id) ? $val->mark_account_status_id : null,
            'pid_status' => isset($val->pid_status) ? $val->pid_status : null,
            'source_created_at' => isset($val->created) ? date('Y-m-d H:i:s', strtotime($val->created)) : null,
            'source_updated_at' => isset($val->modified) ? date('Y-m-d H:i:s', strtotime($val->modified)) : null,
            'data_source_type' => 'api',
        ];

        $create_raw_data_history = LegacyApiRawDataHistory::create($data);
    }

    public function recalculateSales(): JsonResponse
    {
        $sales = SalesMaster::get();
        foreach ($sales as $sale) {
            $pid = $sale->pid;
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
            }

            $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasterProcess) {
                if ($saleMasterProcess->setter1_id) {
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                }
            }
        }
    }

    // subroutine for Hubspot CurrentEnergy API Data
    public function subroutineForHubspotCurrentEnergy($pid)
    {
        Log::info(['function_called' => 'subroutineForHubspotCurrentEnergy']);
        $historyData = LegacyApiRawDataHistory::where(['pid' => $pid, 'import_to_sales' => '0'])->first();
        if ($historyData) {
            Log::info(['historyData' => 'found', 'pid' => $pid]);
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                Log::info(['saleMasters' => 'found']);
                if (isset($saleMasters->setter1_id)) {
                    Log::info(['saleMasters->setter1_id' => 'found']);
                    // Use silent wrapper for internal calls - ignores JsonResponse return
                    $this->executeSubroutineProcessSilently($pid);
                }
            }
        }
    }

    /**
     * Retrieve integration missing sales records with filtering, sorting and pagination
     */
    public function integrationMissingSalesRecord(Request $request): JsonResponse
    {
        try {
            // Check access permissions
            if (! auth()->check()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized to view these records',
                ], 403);
            }

            // Validate the request parameters
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'filter_key' => 'nullable|string',
                'date_from' => 'nullable|date_format:Y-m-d',
                'date_to' => 'nullable|date_format:Y-m-d',
                'sort_by' => 'nullable|string|in:pid,customer_name,import_status_description,data_source_type',
                'sort_dir' => 'nullable|string|in:asc,desc',
                'include_relationships' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get validated data with defaults
            $data = array_merge([
                'page' => 1,
                'per_page' => 15,
                'sort_by' => 'pid',
                'sort_dir' => 'desc',
                'include_relationships' => false,
            ], $validator->validated());

            // Start building the query using the Eloquent model
            $query = SaleMasterExcluded::query();

            // Apply universal filter across multiple fields
            if (! empty($data['filter_key'])) {
                $filterValue = $data['filter_key'];
                $query->where(function ($q) use ($filterValue) {
                    $q->where('pid', 'like', '%'.$filterValue.'%')
                        ->orWhere('customer_name', 'like', '%'.$filterValue.'%')
                        ->orWhere('import_status_description', 'like', '%'.$filterValue.'%')
                        ->orWhere('data_source_type', 'like', '%'.$filterValue.'%');
                });
            }

            // Date range filtering
            if (! empty($data['date_from'])) {
                $query->whereDate('created_at', '>=', $data['date_from']);
            }

            if (! empty($data['date_to'])) {
                $query->whereDate('created_at', '<=', $data['date_to']);
            }

            // Add eager loading if relationships are requested
            if ($data['include_relationships']) {
                $query->with(['user', 'filter', 'saleMaster']);
            }

            // Sorting
            $query->orderBy($data['sort_by'], $data['sort_dir']);

            // Use Laravel's built-in pagination with specific columns only
            $paginator = $query->paginate($data['per_page'], ['pid', 'customer_name', 'import_status_description', 'data_source_type'], 'page', $data['page']);

            // Log this activity
            activity()
                ->causedBy(auth()->user())
                ->withProperties([
                    'filters' => array_filter([
                        'filter_key' => $data['filter_key'] ?? null,
                        'date_range' => ! empty($data['date_from']) ?
                            $data['date_from'].' to '.($data['date_to'] ?? 'now') : null,
                    ]),
                    'pagination' => [
                        'page' => $data['page'],
                        'per_page' => $data['per_page'],
                    ],
                    'total_records' => $paginator->total(),
                ])
                ->log('Retrieved integration missing sales records');

            // Get records - no date formatting needed for selected fields
            $records = $paginator->items();

            // Format the response without using resource class
            return response()->json([
                'status' => true,
                'total_count' => $paginator->total(),
                'message' => 'Integration missing sales records retrieved successfully',
                'data' => [
                    'records' => $records,
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem() ?? 0,
                        'to' => $paginator->lastItem() ?? 0,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            // Log the error
            Log::error('Integration Missing Sales Record Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving integration missing sales records',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
