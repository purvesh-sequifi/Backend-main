<?php

namespace App\Http\Controllers\API\Dropdown;

use App\Models\User;
use App\Models\State;
use App\Models\Teams;
use App\Models\Cities;
use App\Models\Status;
use App\Models\Payroll;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\HiringStatus;
use Illuminate\Http\Request;
use App\Models\FrequencyType;
use App\Models\ManagementTeam;
use App\Models\PayrollHistory;
use Illuminate\Http\JsonResponse;
use App\Models\PositionCommission;
use App\Models\UserManagerHistory;
use App\Models\WeeklyPayFrequency;
use Illuminate\Support\Facades\DB;
use App\Models\AdditionalLocations;
use App\Models\MonthlyPayFrequency;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ManagementTeamMember;
use App\Models\PositionPayFrequency;
use App\Models\UserIsManagerHistory;
use App\Models\AdditionalPayFrequency;
use App\Models\DailyPayFrequency;
use App\Models\ReconciliationSchedule;
use App\Models\UserOrganizationHistory;
use Illuminate\Support\Facades\Validator;
use App\Models\SequiDocsTemplateCategories;
use App\Models\NewSequiDocsTemplate;
use App\Models\SalesMaster;
use App\Models\CompanyProfile;
use App\Models\payFrequencySetting;
use App\Core\Traits\PayFrequencyTrait;
use App\Services\StateService;
class DropdownController extends Controller
{
    use PayFrequencyTrait;

    protected StateService $stateService;

    public function __construct(StateService $stateService)
    {
        $this->stateService = $stateService;
    }


//  // commission
 public function commission($id)
 {
      $comp = PositionCommission::select('id','position_id','commission_parentage','commission_parentag_hiring_locked','commission_structure_type','commission_parentag_type_hiring_locked')->where('position_id' , $id)->paginate(config('app.paginate', 15));
      return response()->json([
         'ApiName' => 'list-department-CompensationPlan',
         'status' => true,
         'message' => 'Successfully.',
         'data' => $comp,
     ], 200);
 }

 public function positionByDepartmentID($id)
 {
      $positions = Positions::with('Commission')->select('id','parent_id','position_name')->where('department_id' , $id)->where('setup_status' ,1)->where('position_name', '!=', 'Super Admin')->get();

      return response()->json([
         'ApiName' => 'positionByDepartmentID',
         'status' => true,
         'message' => 'Successfully.',
         'data' => $positions,
     ], 200);
 }

    public function usersBypositionID(Request $request, $id)
    {
        if(!empty($request->perpage)){
            $perpage = $request->perpage;
        }else{
            $perpage = 10;
        }
        $positions = User::where('sub_position_id', $id)->with('office.State')->where('dismiss', 0);

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $positions->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $search . '%']);
            });
        }

        $data = $positions->orderBy('first_name', 'ASC')->paginate($perpage)->toArray();

        foreach ($data['data'] as $key => $user_img) {
            if (isset($user_img['image']) && $user_img['image'] != null) {
                $data['data'][$key]['image_s3'] = s3_getTempUrl(config('app.domain_name') . '/' . $user_img['image']);
            } else {
                $data['data'][$key]['image_s3'] = null;
            }

            if (isset($data['data'][$key]['office'])) {
                $data['data'][$key]['office_name'] = @$user_img['office']['office_name'];
                $data['data'][$key]['state_name'] = @$user_img['office']['state']['name'];
                unset($data['data'][$key]['office']);
            } else {
                $data['data'][$key]['office_name'] = '';
                $data['data'][$key]['state_name'] = '';
            }
        }
        return response()->json([
            'ApiName' => 'usersBypositionID',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

 public function hiringstatus()
 {
      $status = HiringStatus::select('id','status')->get();
      return response()->json([
         'ApiName' => 'Status-list',
         'status' => true,
         'message' => 'Successfully.',
         'data' => $status,
     ], 200);
 }

   /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function department()
    {
         $dept = Department::select('id','name')->where('parent_id', '')->get();
         return response()->json([
            'ApiName' => 'Department-dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $dept,
        ], 200);
    }

    public function manager($id)
    {

        $manager = User::select('id','first_name','last_name')->where('position_id',$id)->where('dismiss',0)->get();
        return response()->json([
            'ApiName' => 'Manager-list-by-position-dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $manager,
        ], 200);
    }
    public function managerList(Request $request)
    {

       if($request->input('office_id')!='all')
       {
            $additionalUser = AdditionalLocations::where('office_id',$request->input('office_id'))->pluck('user_id');
            $additionalManager = User::select('id','first_name','last_name','state_id','dismiss')->where('is_manager',1)->where('dismiss',0)->whereIn('id',$additionalUser)->get();

            $managers = User::select('id','first_name','last_name','state_id','dismiss')->where('dismiss',0)->where('is_manager',1)->where('office_id',$request->input('office_id'))->get();
            foreach($managers as $m){
                    $additionalManager[] = $m;
                }
        }
       else{

            $additionalManager = User::select('id','first_name','last_name','state_id','dismiss')->where('dismiss',0)->where('is_manager',1)->get();
       }
        return response()->json([
            'ApiName' => 'Manager-list-by-position-dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $additionalManager,
        ], 200);
    }

    public function managerList_by_effective_date(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'effective_date' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Manager-list-by-position-dropdown',
                'status' => false,
                'message' => $validator->errors(),
                'data' => []
            ], 400);
        }

        $effectiveDate = request()->input('effective_date');
        $subQuery = AdditionalLocations::select('user_id', DB::raw('MAX(effective_date) as latest_effective_date'))
            ->where('effective_date', '<=', $effectiveDate)
            ->groupBy('user_id');
        $additionalOffice = AdditionalLocations::joinSub($subQuery, 'latest_dates', function ($join) {
            $join->on('additional_locations.user_id', '=', 'latest_dates.user_id')
            ->on('additional_locations.effective_date', '=', 'latest_dates.latest_effective_date');
        })
        ->when($request->office_id != 'all', function ($q) {
            $q->where(function ($q) {
                $q->where('office_id', request()->input('office_id'));
            });
        })->pluck('additional_locations.user_id')->toArray();

        $office = User::when($request->office_id != 'all', function ($q) {
            $q->where(function ($q) {
                $q->where('office_id', request()->input('office_id'));
            });
        })->pluck('id')->toArray();
        $userIds = array_merge($additionalOffice, $office);

        $subQuery = UserIsManagerHistory::select('user_id', DB::raw('MAX(effective_date) as latest_effective_date'))
        ->whereIn('user_id', $userIds)
            ->where('effective_date', '<=', $effectiveDate)
            ->groupBy('user_id');
        $managerIds = UserIsManagerHistory::joinSub($subQuery, 'latest_dates', function ($join) {
            $join->on('user_is_manager_histories.user_id', '=', 'latest_dates.user_id')
            ->on('user_is_manager_histories.effective_date', '=', 'latest_dates.latest_effective_date');
        })->select('user_is_manager_histories.is_manager', 'user_is_manager_histories.user_id', 'user_is_manager_histories.effective_date')
            ->where('user_is_manager_histories.is_manager', '1')->pluck('user_id');

        $managers = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id', 'dismiss')->whereIn('id', $managerIds)->where(['users.dismiss' => '0'])->get();

        /**
         * Add office type classification to each manager record
         * 
         * Business Logic:
         * - 'additional': Manager is assigned to this office via AdditionalLocations table (temporary/secondary assignment)
         * - 'current': Manager's primary office assignment via User table office_id field
         * 
         * This classification helps frontend distinguish between primary vs additional office assignments
         * for proper display and business rule processing.
         * 
         * Performance: Using array_flip() for O(1) lookup efficiency instead of O(n) in_array()
         * Edge Case Handling: Defensive coding for empty/null data scenarios
         */
        if ($managers && $managers->isNotEmpty()) {
            // Ensure $additionalOffice is array and not null to prevent array_flip() errors
            $additionalOfficeArray = is_array($additionalOffice) ? $additionalOffice : [];
            $additionalOfficeMap = array_flip($additionalOfficeArray);
            
            $managers->transform(function ($manager) use ($additionalOfficeMap) {
                $manager->office = isset($additionalOfficeMap[$manager->id]) ? 'additional' : 'current';
                return $manager;
            });
        }

        // $managers = UserIsManagerHistory::select('users.id', 'users.first_name', 'users.last_name', 'users.state_id', 'users.office_id', 'users.dismiss', 'user_is_manager_histories.is_manager', 'addOffice.effective_date as office_effective_date', 'user_is_manager_histories.effective_date as manager_effective_date')
        //     ->whereIn('user_is_manager_histories.id', function($query) {
        //         $query->select(DB::raw('max(id)'))
        //             ->from('user_is_manager_histories')
        //             ->where('effective_date', '<=', request()->input('effective_date'))
        //             ->orderBy('effective_date')
        //             ->groupBy('user_id');
        //     })
        //     ->leftJoin('users', 'users.id', 'user_is_manager_histories.user_id')
        //     ->leftJoin('additional_locations as addOffice', 'addOffice.user_id', 'user_is_manager_histories.user_id')
        //     ->when($request->office_id != 'all', function ($q) {
        //         $q->where(function ($q) {
        //             $q->where('addOffice.office_id', request()->input('office_id'))
        //             ->orWhere('users.office_id', request()->input('office_id'));
        //         });
        //     })
        //     // ->where('addOffice.effective_date', '<=', request()->input('effective_date'))
        //     ->where(function ($q) use ($effective_date) {
        //         $q->whereNull('addOffice.deleted_at')
        //         ->orWhereRaw("date(addOffice.deleted_at) > ?", [$effective_date]);
        //     })
        //     ->where(['users.dismiss' => '0', 'user_is_manager_histories.is_manager' => '1'])
        //     ->groupBy(['user_is_manager_histories.user_id'])->get();

        // $managers = UserIsManagerHistory::select('users.id', 'users.first_name', 'users.last_name', 'users.state_id', 'users.office_id', 'users.dismiss', 'user_is_manager_histories.is_manager', 'addOffice.override_effective_date', 'user_is_manager_histories.effective_date as manager_effective_date')
        //     ->whereIn('user_is_manager_histories.id', function($query) {
        //         $query->select(DB::raw('max(id)'))
        //             ->from('user_is_manager_histories')
        //             ->where('effective_date', '<=', request()->input('effective_date'))
        //             ->orderBy('effective_date')
        //             ->groupBy('user_id');
        //     })
        //     // ->where('user_transfer_history.tran`sfer_effective_date', '<=', $request->effective_date)
        //     ->leftJoin('user_transfer_history', 'user_transfer_history.user_id', 'user_is_manager_histories.user_id')
        //     ->leftJoin('users', 'users.id', 'user_is_manager_histories.user_id')
        //     ->leftJoin('user_additional_office_override_histories as addOffice', function ($q) {
        //         $q->on('addOffice.user_id', '=', 'user_is_manager_histories.user_id');
        //         // ->on('addOffice.override_effective_date', '<=', 'user_is_manager_histories.effective_date');
        //     })
        //     ->when($request->office_id != 'all', function ($q) {
        //         $q->where(function ($q) {
        //             $q->where('addOffice.office_id', request()->input('office_id'))->orWhere('users.office_id', request()->input('office_id'));
        //         });
        //     })->where(['users.dismiss' => '0', 'user_is_manager_histories.is_manager' => '1'])->groupBy(['user_is_manager_histories.user_id'])->get();

        // $max_id_data = UserOrganizationHistory::select(DB::raw('max(id) as id'))->where('effective_date', '<=', $request->effective_date)->groupBy('user_id')->pluck('id');
        // $user_ids = UserOrganizationHistory::whereIn('id', $max_id_data)->where('is_manager', 1)->pluck('user_id');

        // if (isset($request->office_id) && $request->office_id != 'all') {
        //     $additionalUser = AdditionalLocations::where('office_id', $request->office_id)->pluck('user_id');
        //     $merge = array_merge($userIds->toArray(), $additionalUser->toArray());
        //     $managers = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id', 'dismiss')
        //         ->where('dismiss', 0)->whereIn('id', $merge)->get();

        //     // $managers = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id', 'dismiss')
        //     //     ->where('dismiss', 0)
        //     //     ->where('office_id', $request->input('office_id'))
        //     //     ->whereIn('id', $userIds)->get();

        //     foreach ($managers as $m) {
        //         $additionalManager[] = $m;
        //     }
        // } else {
        //     $additionalManager = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id', 'dismiss')
        //     ->where('dismiss', 0)
        //     ->whereIn('id', $userIds)
        //         ->get();
        // }

        return response()->json([
            'ApiName' => 'Manager-list-by-position-dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $managers
        ]);
    }

    public function TemplateCategories()
    {
        $categories = SequiDocsTemplateCategories::get();
        return response()->json([
            'ApiName' => 'Template-categories-dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $categories,
        ], 200);
    }

       // CompensationPlan by position id
        // public function compensationPlanByPosition($id)
        // {
        //     $comp = CompensationPlan::select('id','compensation_plan_name')->where('position_id' , $id)->get();
        //     return response()->json([
        //         'ApiName' => 'CompensationPlan-list-by-position_id',
        //         'status' => true,
        //         'message' => 'Successfully.',
        //         'data' => $comp,
        //     ], 200);
        // }
        // CompensationPlan by position id
        public function redlineByState($id)
        {

            $location = Locations::select('id','state_id','city_id','installation_partner','redline_min','redline_standard','redline_max','marketing_deal_person_id')->with('State','Cities')->where('state_id' , $id)->whereNull('archived_at')->paginate(config('app.paginate', 15));
            return response()->json([
                'ApiName' => 'redline-list-by-state_id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $location,
            ], 200);
        }

        public function overridesByState($id)
        {
            $comp = Locations::select('id','state_id','city_id','installation_partner','redline_min','redline_standard','redline_max','marketing_deal_person_id')->with('State','Cities')->where('state_id' , $id)->whereNull('archived_at')->paginate(config('app.paginate', 15));
            return response()->json([
                'ApiName' => 'overrides-list-by-state_id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $comp,
            ], 200);
        }

        public function frequencylist()
        {
            $data = FrequencyType::get()->map(function ($val) {
                $val->name = isset($val->name) 
                    ? ($val->name === 'Daily-pay' ? 'Daily (on demand)' : $val->name) 
                    : '-';
                return $val;
            });
            return response()->json([
                'ApiName' => 'frequency-drop-down',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

        public function state()
        {
            return "test";
            $data = State::orderby('name','asc')->get();
            return response()->json([
                'ApiName' => 'State List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }

        public function getAllUserState()
        {
            $userState = User::groupBy('state_id')->where('dismiss',0)->pluck('state_id')->toArray();

            $data = State::whereIn('id',$userState)->get();
            return response()->json([
                'ApiName' => 'Get All User State List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }


        public function teamList(Request $request)
        {
            $office_id = $request->office_id;
            if($office_id){
                $data = ManagementTeam::where('office_id',$office_id)->get();
            }else{
                $data = ManagementTeam::get();
            }
            return response()->json([
                'ApiName' => 'Teams List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

        public function citiesByStateID($id)
        {
            $data = Cities::where('state_id',$id)->get();
            return response()->json([
                'ApiName' => 'City List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }
        public function locationsByStateID($id)
        {
            $data = Locations::where('state_id',$id)->whereNull('archived_at')->get();
            return response()->json([
                'ApiName' => 'location List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

    public function costCenter()
    {
        $data = CostCenter::get();
        return response()->json([
            'ApiName' => 'CostCenter List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function positions(Request $request)
    {

        $allData = Positions::where('parent_id',NULL)->where('id', '!=' , 1)->get();
        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $allData,
        ], 200);
    }

    public function recoPositionsDropdown(Request $request)
    {
        $data = Positions::select('id', 'position_name','setup_status')->with(['payFrequency' => function ($query) {
            $query->select('position_id', 'frequency_type_id')->with(['frequencyType' => function ($query) {
                $query->select('id', 'name');  // Assuming 'name' is the relevant data in frequencyType
            }]);
        }])->where('setup_status', '1')->where('position_name', '!=', 'Super Admin')->whereHas('reconciliation', function ($q) {
            $q->where('status', '1');
        });
        if($request->has('search') && $request->input('search'))
        {
            $search  = $request->input('search');
            $data->where('position_name', 'like', '%' . $search . '%');
        }
        $data  = $data->get();
        
        if (sizeof($data) > 0) {
            return response()->json([
                'ApiName' => 'reconciliation-position-dropdown',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'ApiName' => 'reconciliation-position-dropdown',
                'status' => false,
                'message' => 'Position Data Not Found!!',
                'data' => []
            ]);
        }
    }

    public function recoOfficerDropdown(Request $request)
    {
        $data = Locations::select('id', 'office_name')->where('type', 'Office')->whereNull('archived_at');
        if($request->has('search') && $request->input('search'))
        {
            $search  = $request->input('search');
            $data->where('office_name', 'like', '%' . $search . '%');
        }
        $data  = $data->get();
        
        if (sizeof($data) > 0) {
            return response()->json([
                'ApiName' => 'reconciliation-position-dropdown',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'ApiName' => 'reconciliation-office-dropdown',
                'status' => false,
                'message' => 'Position Data Not Found!!',
                'data' => []
            ]);
        }
    }

    public function ParentCostCenter()
    {
        $costCenters = CostCenter::with('chields')->where('parent_id',NULL)->where('status',1)->get();
        //return $costCenters;
        return response()->json([
            'ApiName' => 'parent-child-cost-center',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $costCenters,
        ], 200);
    }
    public function ParentCostCenterList()
    {
        $costCenters = CostCenter::where('parent_id',null)->where('status',1)->orderBy('id','ASC')->get();
        //return $costCenters;
        return response()->json([
            'ApiName' => 'parent-cost-center',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $costCenters,
        ], 200);
    }
    public function stateCity()
    {
        $data = State::with('cities')
        ->orderBy('name', 'ASC')
        ->get();
        return response()->json([
            'ApiName' => 'State List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }
    public function AllUseLocation()
    {
        $state_ids = Locations::groupBy('state_id')->pluck('state_id')->whereNull('archived_at')->toArray();
        $locations = Locations::with('State', 'Cities')->whereIn('state_id', $state_ids)->whereNull('archived_at')->groupBy('state_id')->orderBy('id', 'DESC')->get();
        $locations->transform(function ($location) {
            $city_ids = Locations::where('state_id',$location->state_id)->whereNull('archived_at')->groupBy('city_id')->pluck('city_id')->toArray();
            $Cities = Cities::whereIn('id', $city_ids)->orderBy('id', 'DESC')->get();
            return [
                'state_id' => isset($location->State->id) ? $location->state->id : NULL,
                'state_name' => isset($location->State->name) ? $location->state->name : NULL,
                'state_code' => isset($location->State->state_code) ? $location->state->state_code : NULL,
                'cities' => isset($Cities) ? $Cities : NULL,
            ];
        });

        return response()->json(['status' => 'success', 'locations' => $locations], 200);
    }
    public function getGeneralCodeListByStateID(Request $request)
    {
        $locations = Locations::where('state_id', $request->id)->whereNull('archived_at')->orderBy('id', 'DESC')->get();
        if(empty($locations)){
            return response()->json(['status' => 'success', 'general_codes' => []], 200);
        }
        $locations->transform(function ($location) {
            return [
                // 'state_id' => isset($location->State->id) ? $location->state->id : NULL,
                // 'state_name' => isset($location->State->name) ? $location->state->name : NULL,
                'general_code' => isset($location->general_code) ? $location->general_code : NULL
            ];
        });

        return response()->json(['status' => 'success', 'general_codes' => $locations], 200);
    }

    public function AllGeneralCodeList()
    {

        $state_ids = Locations::whereNull('archived_at')->groupBy('state_id')->pluck('state_id')->toArray();
        $locations = Locations::with('State', 'Cities','additionalRedline')->whereIn('state_id', $state_ids)->whereNull('archived_at')->orderBy('id', 'DESC')->get();
        $locations->transform(function ($location) {
            return [
                'general_code' => isset($location->general_code) ? $location->general_code : NULL,
                'redline_min' => isset($location->redline_min) ? $location->redline_min : NULL,
                'work_site_id' => isset($location->work_site_id) ? $location->work_site_id : NULL,
                'redline_standard' => isset($location->redline_standard) ? $location->redline_standard : NULL,
                'redline_max' => isset($location->redline_max) ? $location->redline_max : NULL,
                'state_id' => isset($location->State->id) ? $location->state->id : NULL,
                'state_name' => isset($location->State->name) ? $location->state->name : NULL,
                'state_code' => isset($location->State->state_code) ? $location->state->state_code : NULL,
                'effective_date' => isset($location->date_effective) ? $location->date_effective : NULL,
                'redline_data'  => $location->additionalRedline
            ];
        });

        return response()->json(['status' => 'success', 'locations' => $locations], 200);
    }

    public function locationsOfficeByStateID($id)
    {
        $data = Locations::where('state_id',$id)->where('office_name','!=','')->whereNull('archived_at')->get();
        if(count($data)>0){
            return response()->json([
                'ApiName' => 'location by state',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'location by state',
                'status' => false,
                'message' => 'State id not found',
            ], 200);
        }

    }

    public function getLocationOffice()
    {
        $data = Locations::where('office_name','!=','')->whereNull('archived_at')->get();
        if(count($data)>0){
            return response()->json([
                'ApiName' => 'Location Office Dropdown List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'Location Office Dropdown List',
                'status' => false,
                'message' => 'No List Found.'
            ], 400);
        }


    }

    public function getDataFromLocation(Request $request)
    {
        // $data = Locations::where($request->toArray())->first();
        if($request->office_id){
            $data = Locations::with('redline_data')->where(['id'=>$request->office_id])->whereNull('archived_at')->first();
        }else{
            $data = Locations::with('redline_data')->where(['state_id'=>$request->state_id])->whereNull('archived_at')->first();
        }

        if( $data){
            return response()->json([
                'ApiName' => 'State Office Dropdown List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'State Office Dropdown List',
                'status' => false,
                'message' => 'No List Found.',
                'data' => array(),
            ], 200);
        }


    }


    public function managerOfficeDropdown(Request $request)
    {
        //dd($request->id);
        $user = User::where('id',$request->id)->where('dismiss',0)->first();
        if(isset($user->office_id)){
            $additional_location = AdditionalLocations::where('user_id',$request->id)->where('office_id','!=',null)->get();
            //print_r($additional_location); die();
            $data = [];
            foreach($additional_location as $value){
                if($value->office_id){
                    $data[] = Locations::select('id','office_name')->whereNull('archived_at')->where('id',$value->office_id)->get();
                }

            }

            return response()->json([
                'ApiName' => 'Manager Office Dropdown List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'State Office Dropdown List',
                'status' => false,
                'message' => 'No List Found.',
                'data' => array(),
            ], 404);
        }


    }

    public function weeklyPayFrequencyDropdown(Request $request)
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        $weeklyPayFrequency = WeeklyPayFrequency::where(function ($query) use ($workerType) {
            if ($workerType == 'w2' || $workerType == 'W2') {
                $query->where('w2_closed_status', '0')->orWhere('w2_open_status_from_bank', '1');
            } else {
                $query->where('closed_status', '0')->orWhere('open_status_from_bank', '1');
            }
        })->whereBetween('pay_period_from', [date('2023-01-05'), (date('Y') + 1) . '-12-31'])->get();

        $weeklyPayFrequency->transform(function ($value) use ($workerType) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();
            $closeStatus = isset($value->closed_status) ? $value->closed_status : NULL;
            $openStatusFromBank = isset($value->open_status_from_bank) ? $value->open_status_from_bank : NULL;
            if ($workerType == 'w2' || $workerType == 'W2') {
                $closeStatus = isset($value->w2_closed_status) ? $value->w2_closed_status : NULL;
                $openStatusFromBank = isset($value->w2_open_status_from_bank) ? $value->w2_open_status_from_bank : NULL;
            }

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'closed_status' => $closeStatus,
                'open_status_from_bank' => $openStatusFromBank,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'weeklyPayFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $weeklyPayFrequency
        ]);
    }

    public function monthlyPayFrequencyDropdown(Request $request)
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        $monthlyPayFrequency = MonthlyPayFrequency::where(function ($query) use ($workerType) {
            if ($workerType == 'w2' || $workerType == 'W2') {
                $query->where('w2_closed_status', '0')->orWhere('w2_open_status_from_bank', '1');
            } else {
                $query->where('closed_status', '0')->orWhere('open_status_from_bank', '1');
            }
        })->whereBetween('pay_period_from', [date('2023-01-01'), (date('Y') + 1) . '-12-31'])->get();

        $monthlyPayFrequency->transform(function ($value) use ($workerType) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();
            $closeStatus = isset($value->closed_status) ? $value->closed_status : NULL;
            $openStatusFromBank = isset($value->open_status_from_bank) ? $value->open_status_from_bank : NULL;
            if ($workerType == 'w2' || $workerType == 'W2') {
                $closeStatus = isset($value->w2_closed_status) ? $value->w2_closed_status : NULL;
                $openStatusFromBank = isset($value->w2_open_status_from_bank) ? $value->w2_open_status_from_bank : NULL;
            }

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'closed_status' => $closeStatus,
                'open_status_from_bank' => $openStatusFromBank,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'monthlyPayFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $monthlyPayFrequency
        ]);
    }

    public function weeklyPayFrequencyExecutedDropdown(Request $request)
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        $weeklyPayFrequency = WeeklyPayFrequency::when(($workerType == 'w2' || $workerType == 'W2'), function ($q) {
            $q->where('w2_closed_status', '1');
        })->when(($workerType == '1099'), function ($q) {
            $q->where('closed_status', '1');
        })->whereBetween('pay_period_from', [date('2023-01-05'), (date('Y') + 1) . '-12-31'])->get();
        $weeklyPayFrequency->transform(function ($value) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'weeklyPayFrequencyExecutedDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $weeklyPayFrequency
        ]);
    }

    public function monthlyPayFrequencyExecutedDropdown(Request $request)
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        $monthlyPayFrequency = MonthlyPayFrequency::when(($workerType == 'w2' || $workerType == 'W2'), function ($q) {
            $q->where('w2_closed_status', '1');
        })->when(($workerType == '1099'), function ($q) {
            $q->where('closed_status', '1');
        })->whereBetween('pay_period_from', [date('2023-01-01'), (date('Y') + 1) . '-12-31'])->get();
        $monthlyPayFrequency->transform(function ($value) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'monthlyPayFrequencyExecutedDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $monthlyPayFrequency
        ]);
    }

    public function weeklyPayFrequencyDropdownAll()
    {
        $weeklyPayFrequency = WeeklyPayFrequency::whereBetween('pay_period_from', [date('2023-01-05'), (date('Y') + 1) . '-12-31'])->get();
        $weeklyPayFrequency->transform(function ($value) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'weeklyPayFrequencyDropdownAll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $weeklyPayFrequency
        ]);
    }

    public function monthlyPayFrequencyDropdownAll()
    {
        $monthlyPayFrequency = MonthlyPayFrequency::whereBetween('pay_period_from', [date('2023-01-01'), (date('Y') + 1) . '-12-31'])->get();
        $monthlyPayFrequency->transform(function ($value) {
            $finalizeStatus = Payroll::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 2])->first();
            $executeStatus = PayrollHistory::select('status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'status' => 3, 'is_onetime_payment' => 0])->first();
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')->where(['pay_period_from' => $value->pay_period_from, 'pay_period_to' => $value->pay_period_to, 'everee_payment_status' => 2, 'is_onetime_payment' => 0])->first();

            return [
                'id' => isset($value->id) ? $value->id : NULL,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'finalize' => isset($finalizeStatus) ? 1 : 0,
                'execute' => isset($executeStatus) ? 1 : 0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 : 0
            ];
        });

        return response()->json([
            'ApiName' => 'monthlyPayFrequencyDropdownAll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $monthlyPayFrequency
        ]);
    }

    public function biWeeklyFrequencyDropdown(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'biWeeklyFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::BI_WEEKLY_TYPE, 'normal', $workerType)
        ]);
    }

    public function biWeeklyFrequencyDropdownAll(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'biWeeklyFrequencyDropdownAll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::BI_WEEKLY_TYPE, '', $workerType)
        ]);
    }

    public function biWeeklyExecutedFrequencyDropdown(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'biWeeklyExecutedFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::BI_WEEKLY_TYPE, 'executed', $workerType)
        ]);
    }

    public function semiMonthlyFrequencyDropdown(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'semiMonthlyFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::SEMI_MONTHLY_TYPE, 'normal', $workerType)
        ]);
    }

    public function semiMonthlyFrequencyDropdownAll(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'semiMonthlyFrequencyDropdownAll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::SEMI_MONTHLY_TYPE, '', $workerType)
        ]);
    }

    public function semiMonthlyExecutedFrequencyDropdown(Request $request): JsonResponse
    {
        $workerType = $request->worker_type ? $request->worker_type : '1099';
        return response()->json([
            'ApiName' => 'semiMonthlyExecutedFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $this->getAdditionalFrequencyDropdown(AdditionalPayFrequency::SEMI_MONTHLY_TYPE, 'executed', $workerType)
        ]);
    }



    public function dailyPayFrequencyDropdown(Request $request)
    {
       
        $weeklyPayFrequencyData = [];
        $payFrequencySetting = payFrequencySetting::where('frequency_type_id',FrequencyType::DAILY_PAY_ID)->first();
        if ($payFrequencySetting) {
            $pay_period_start_date = date('Y-m-d',strtotime($payFrequencySetting->first_day));
        }
        else{
            $pay_period_start_date = date('Y-m-d');
        }
        // return $pay_period_start_date;
        if($request->api_type == 'normal'){
            $dailyPayFrequency = DailyPayFrequency::where(function($query) {
                $query->where('closed_status', '0')
                     ->orWhere('open_status_from_bank', '1');
            })->whereBetween('pay_period_from',[$pay_period_start_date,date('Y-m-d')])->get();
        }
        elseif($request->api_type == 'all'){
            $dailyPayFrequency = DailyPayFrequency::whereBetween('pay_period_from',[$pay_period_start_date,date('Y-m-d')])->get();
        }
        elseif($request->api_type == 'executed'){
            $dailyPayFrequency = DailyPayFrequency::where('closed_status', '1')->whereBetween('pay_period_from',[$pay_period_start_date,date('Y-m-d')])->get();
        }
        $dailyPayFrequency->transform(function ($value) {
            $pay_period_type = null;
            $finalizeStatus = Payroll::select('status')
            ->whereBetween('pay_period_from',[$value->pay_period_from,$value->pay_period_to])
            ->whereBetween('pay_period_to',[$value->pay_period_from,$value->pay_period_to])
            ->where('status',2)->first();
                
            $executeStatus = PayrollHistory::select('status')
            ->whereBetween('pay_period_from',[$value->pay_period_from,$value->pay_period_to])
            ->whereBetween('pay_period_to',[$value->pay_period_from,$value->pay_period_to])
            ->where('status',3)
            ->where('is_onetime_payment', 0)->first();
            
            $executeEvereeStatus = PayrollHistory::select('everee_payment_status')
            ->whereBetween('pay_period_from',[$value->pay_period_from,$value->pay_period_to])
            ->whereBetween('pay_period_to',[$value->pay_period_from,$value->pay_period_to])
            ->where('everee_payment_status',2)->where('is_onetime_payment', 0)->first();
            if ($value->pay_period_from <= date('Y-m-d') && $value->pay_period_to >= date('Y-m-d')){
                $pay_period_type = "Current Payroll";
            }
            return [
                'id' => isset($value->id) ? $value->id : $pay_period_type,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : NULL,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : NULL,
                'pay_period_type' => $pay_period_type === null ? date('m/d/Y', strtotime($value->pay_period_from)) . ' to ' . date('m/d/Y', strtotime($value->pay_period_to)) : $pay_period_type,
                'closed_status' => isset($value->closed_status) ? $value->closed_status : NULL,
                'open_status_from_bank' => isset($value->open_status_from_bank) ? $value->open_status_from_bank : NULL,
                'finalize' => isset($finalizeStatus) ? 1 :0,
                'execute' => isset($executeStatus) ? 1 :0,
                'everee_payment_status' => isset($executeEvereeStatus) ? 1 :0,
            ];
        });

        $weeklyPayFrequencyData = $dailyPayFrequency->toArray();
        if($request->api_type != 'executed'){
            if($payFrequencySetting){
                $weeklyPayFrequencyData = array_merge($dailyPayFrequency->toArray(),$this->daily_pay_period_date());
            } 
        }
        return response()->json([
            'ApiName' => 'dailyPayFrequencyDropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $weeklyPayFrequencyData,
        ], 200);
    }

    protected function getAdditionalFrequencyDropdown($type, $apiType = '', $workerType = '1099'): array
    {
        $frequencies = AdditionalPayFrequency::selectRaw('COUNT(CASE WHEN payrolls.status = 2 THEN 1 END) AS payroll_status_2_count,
            COUNT(CASE WHEN payroll_history.status = 3 AND payroll_history.is_onetime_payment = 0 THEN 1 END) AS payroll_history_status_3_count,
            COUNT(CASE WHEN payroll_history.everee_payment_status = 2 AND payroll_history.is_onetime_payment = 0 THEN 1 END) AS payroll_history_everee_payment_status_2_count,
            additional_pay_frequencies.id, additional_pay_frequencies.pay_period_from, additional_pay_frequencies.pay_period_to, additional_pay_frequencies.closed_status, additional_pay_frequencies.open_status_from_bank, additional_pay_frequencies.w2_closed_status, additional_pay_frequencies.w2_open_status_from_bank')
            ->leftJoin('payrolls', function ($join) {
                $join->on('payrolls.pay_period_from', 'additional_pay_frequencies.pay_period_from')
                    ->on('payrolls.pay_period_to', 'additional_pay_frequencies.pay_period_to');
            })->leftJoin('payroll_history', function ($join) {
                $join->on('payroll_history.pay_period_from', 'additional_pay_frequencies.pay_period_from')
                    ->on('payroll_history.pay_period_to', 'additional_pay_frequencies.pay_period_to');
            });

        if($workerType == 'w2' | $workerType == 'W2'){
            if ($apiType == 'normal') {
                $frequencies->where(function ($query) {
                    $query->where('w2_closed_status', '0')->orWhere('w2_open_status_from_bank', '1');
                });
            } else if ($apiType == 'executed') {
                $frequencies->where('w2_closed_status', '1');
            }
        } else {
            if ($apiType == 'normal') {
                $frequencies->where(function ($query) {
                    $query->where('closed_status', '0')->orWhere('open_status_from_bank', '1');
                });
            } else if ($apiType == 'executed') {
                $frequencies->where('closed_status', '1');
            }
        }
        $frequencies = $frequencies->where(['type' => $type])->groupBy('additional_pay_frequencies.id')->get();

        $response = [];
        foreach ($frequencies as $frequency) {
            $closeStatus = isset($frequency->closed_status) ? $frequency->closed_status : NULL;
            $openStatusFromBank = isset($frequency->open_status_from_bank) ? $frequency->open_status_from_bank : NULL;
            if ($workerType == 'w2' || $workerType == 'W2') {
                $closeStatus = isset($frequency->w2_closed_status) ? $frequency->w2_closed_status : NULL;
                $openStatusFromBank = isset($frequency->w2_open_status_from_bank) ? $frequency->w2_open_status_from_bank : NULL;
            }

            $response[] = [
                'id' => isset($frequency->id) ? $frequency->id : NULL,
                'pay_period_from' => isset($frequency->pay_period_from) ? $frequency->pay_period_from : NULL,
                'pay_period_to' => isset($frequency->pay_period_to) ? $frequency->pay_period_to : NULL,
                'closed_status' => $closeStatus,
                'open_status_from_bank' => $openStatusFromBank,
                'finalize' => $frequency->payroll_status_2_count ? 1 : 0,
                'execute' => $frequency->payroll_history_status_3_count ? 1 : 0,
                'everee_payment_status' => $frequency->payroll_history_everee_payment_status_2_count ? 1 : 0
            ];
        }

        return $response;
    }

    public function getUserByOfficeID(Request $request)
    {
        //dd($request->id);

        if(isset($request->id)){
            if($request->id == 'all'){
                $data = User::select('id', 'first_name', 'last_name', 'image', 'sub_position_id', 'team_id')->with('subpositionDetail','teamsDetail')->where('dismiss',0)->where('is_super_admin',0)->get();

            }else{
                // $data = User::with('subpositionDetail','teamsDetail')->where('dismiss',0)->where('office_id',$request->id)->get();
                $additional_location = AdditionalLocations::where('office_id',$request->id)->pluck('user_id')->toArray();
                $data = User::select('id', 'first_name', 'last_name', 'image', 'sub_position_id', 'team_id')->with('subpositionDetail','teamsDetail')->where('dismiss',0)->where('office_id',$request->id)->orWhereIn('id',$additional_location)->get();

            }

            // Add computed columns
            $data = $data->map(function ($user) {
                $userArray = $user->toArray();
                $userArray['dismiss'] = isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0;
                $userArray['terminate'] = isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0;
                $userArray['contract_ended'] = isUserContractEnded($user->id) ? 1 : 0;
                return $userArray;
            });

            return response()->json([
                'ApiName' => 'user  List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'State Office Dropdown List',
                'status' => false,
                'message' => 'No List Found.',
                'data' => array(),
            ], 404);
        }


    }

    public function getUserByOfficeIDForTeamMember(Request $request)
    {
        //dd($request->id);

        if(isset($request->id)){
            if($request->id == 'all'){
                 $data = User::where('is_super_admin',0)->where('dismiss',0)->get();
            }else{
                $memberId =  ManagementTeamMember::orderBy('id','desc')->pluck('team_member_id');
            //     $data = User::where('office_id',$request->id)->where('dismiss',0)->whereNotIn('id',$memberId)->where('team_lead_status',0)->get();
                $additional_location = AdditionalLocations::where('office_id',$request->id)->pluck('user_id')->toArray();
                $data = User::where('office_id',$request->id)->where('dismiss',0)->whereNotIn('id',$memberId)->where('team_lead_status',0)->orWhereIn('id',$additional_location)->get();
            }

            // Add computed columns
            $data = $data->map(function ($user) {
                return [
                    ...$user->toArray(),
                    'dismiss' => isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($user->id) ? 1 : 0,
                ];
            });

            return response()->json([
                'ApiName' => 'Team Member List Api',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'Team Member List Api',
                'status' => false,
                'message' => 'No List Found.',
                'data' => array(),
            ], 404);
        }
    }


    public function getUserByOfficeIDForTeamLead(Request $request)
    {
        //dd($request->id);

        if(isset($request->id)){
            if($request->id == 'all'){
                $memberId =  ManagementTeamMember::orderBy('id','desc')->pluck('team_lead_id');
                 $data = User::where('is_super_admin',0)->whereNotIn('id',$memberId)->where('team_lead_status',1)->get();
            }else{
                $memberId =  ManagementTeamMember::orderBy('id','desc')->pluck('team_lead_id');
                // $data = User::where('office_id',$request->id)->whereNotIn('id',$memberId)->where('dismiss',0)->where('team_lead_status',1)->get();
                $additional_location = AdditionalLocations::where('office_id',$request->id)->pluck('user_id')->toArray();
                $data = User::where('office_id',$request->id)->whereNotIn('id',$memberId)->where('dismiss',0)->where('team_lead_status',1)->orWhereIn('id',$additional_location)->get();
            }

            // Add computed columns
            $data = $data->map(function ($user) {
                return [
                    ...$user->toArray(),
                    'dismiss' => isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($user->id) ? 1 : 0,
                ];
            });

            return response()->json([
                'ApiName' => 'Team Leader List Api',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'Team Leader List Api',
                'status' => false,
                'message' => 'No List Found.',
                'data' => array(),
            ], 404);
        }
    }
    public function getAllStateWithOffice(Request $request)
    {
        try {
            // Get user ID for caching
            $userId = auth()->id();
            
            // Check if cache should be bypassed (for debugging)
            $useCache = !$request->has('no_cache');
            
            // Get data using service with caching
            $states = $this->stateService->getStatesWithOffices($userId, $useCache);
            
            // Format response directly
            $response = [
                'ApiName' => 'get All State With Office',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $states
            ];
            
            // Add performance headers
            return response()->json($response, 200)
                ->header('X-Cache-Status', $useCache ? 'enabled' : 'disabled');
                
        } catch (\Exception $e) {
            Log::error('Error in getAllStateWithOffice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'ApiName' => 'get All State With Office',
                'status' => false,
                'message' => 'An error occurred while fetching data',
                'data' => [],
            ], 500);
        }
    }

    public function checkSettingStatus(Request $request)
    {
        $data = [];
        $locations = Locations::orderBy('id','desc')->first();
        if($locations){
            $location = $data['locations']['status'] = 1;
        }else{
            $location =$data['locations']['status'] = 0;

        }

        $position = Positions::orderBy('id','desc')->first();
        if($position){
             $position = $data['position']['status'] = 1;
        }else{
            $position  = $data['position']['status'] = 0;

        }

        $team = ManagementTeam::orderBy('id','desc')->first();
        if($team){
             $team = $data['team']['status'] = 1;
        }else{
            $team = $data['team']['status'] = 0;

        }

        $payFrequency = PositionPayFrequency::orderBy('id','desc')->first();
        if($payFrequency){
             $payFrequency = $data['payFrequency']['status'] = 1;
        }else{
             $payFrequency = $data['payFrequency']['status'] = 0;

        }

        $reconciliation = ReconciliationSchedule::orderBy('id','desc')->first();
        if($reconciliation){
             $reconciliation =$data['reconciliation']['status'] = 1;
        }else{
            $reconciliation = $data['reconciliation']['status'] = 0;

        }

        return response()->json([
            'ApiName' => 'check Status ',
            'status' => true,
            'message' => 'Successfully.',
            'location' => $location,
            'position' => $position,
            'team' => $team,
            'payFrequency' => $payFrequency,
            'reconciliation' => $reconciliation,
        ], 200);


    }

    public function usersByOfficeID(Request $request, $id)
    {
        $office = User::where('office_id', $id)->with('positionDetail');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $office->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $search . '%']);
            });
        }

        $data = $office->orderBy('first_name', 'ASC')->paginate(config('app.paginate', 15))->toArray();

        foreach ($data['data'] as $key => $user_img) {
            if (isset($user_img['image']) && $user_img['image'] != null) {
                $data['data'][$key]['image_s3'] = s3_getTempUrl(config('app.domain_name') . '/' . $user_img['image']);
            } else {
                $data['data'][$key]['image_s3'] = null;
            }

            if (isset($data['data'][$key]['position_detail'])) {
                $data['data'][$key]['sub_position_name'] = $user_img['position_detail']['position_name'];
                unset($data['data'][$key]['position_detail']);
            } else {
                $data['data'][$key]['sub_position_name'] = '';
            }
        }
        return response()->json([
            'ApiName' => 'usersByOfficeID',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // public function setter_closer_list_by_effective_date(Request $request)
    // {
    //     $Validator = Validator::make($request->all(),
    //     [
    //         'effective_date' => 'required',
    //         'user_type' => 'required',
    //     ]);
    //     $data = [];
    //     $user_ids = UserOrganizationHistory::where('effective_date','<=',$request->effective_date)
    //     ->where('position_id',$request->user_type)
    //     ->pluck('user_id');


    //     $user_type = $request->user_type;
    //     if(count($user_ids)>0){
    //         //'additionalRedline','upfront','positionpayfrequencies'
    //         $data = User::with('office','reconciliations')
    //         ->where('dismiss',0)
    //         ->whereIn('id',$user_ids)
    //         ->where(function ($query) use ($user_type) {
    //             $query->where('position_id', $user_type)
    //             ->orWhere('self_gen_type',$user_type);
    //         })
    //         ->select('id','email','first_name','last_name','office_id','sub_position_id')
    //         ->get();
    //     }

    //     return response()->json([
    //         'ApiName' => 'setter_closer_list_by_effective_date',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $data,
    //     ], 200);
    // }

    public function setter_closer_list_by_effective_date(Request $request)
    {
        $Validator = Validator::make($request->all(),
        [
            'effective_date' => 'required|date|nullable',
            'user_type' => 'required',
        ]);
        
        if (is_null($request->effective_date)) {
            return response()->json(['error' => 'Effective date is required.'], 422);
        }
        
        $max_id_data = UserOrganizationHistory::select(DB::raw('max(id) as id'))
        ->where('effective_date','<=',$request->effective_date)
        ->groupBy('user_id')->pluck('id');

        $data = [];
        $users = [];
        $user_type = $request->user_type;
        $user_ids = UserOrganizationHistory::whereIn('id',$max_id_data)
        ->where(function ($query) use($user_type){
            $query->where('position_id',$user_type)
            ->orWhere('self_gen_accounts',1);
        })
        ->pluck('user_id');

        if(count($user_ids)>0){
            $data = User::with('office','reconciliations','positionpayfrequencies','additionalRedline','upfront')
            ->where('dismiss',0)
            ->whereIn('id',$user_ids)
            ->select('id','email','first_name','last_name','office_id','sub_position_id')
            ->get();
        }

        return response()->json([
            'ApiName' => 'setter_closer_list_by_effective_date',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function offerLetterList(Request $request)
    {
        $allData = NewSequiDocsTemplate::select('id','template_name as offer_letter_name')->where(['category_id' => 1, 'is_template_ready' => 1])->get();
        return response()->json([
            'ApiName' => 'list-offer-letter',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $allData,
        ], 200);
    }

    public function installpartner(Request $request)
    {
        $allData = SalesMaster::select('install_partner') ->whereNotNull('install_partner') // Exclude null values
        ->where('install_partner', '!=', '')->distinct()->get();
        // Map the data into an array with 'name' and 'value'
        $values = $allData->map(function($item) {
            if($item->install_partner != 'null'){
                return [
                    'name' => $item->install_partner,
                    'value' => $item->install_partner
                ];
            }
            
        })->toArray();
        return response()->json([
            'ApiName' => 'install-partner',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $values,
        ], 200);
    }
    public function jobstatus(Request $request)
    {
        try {
            // Cache company profile and job statuses to avoid repeated DB calls
            static $companyProfile = null;
            static $cachedJobStatuses = null;
            
            if ($companyProfile === null) {
                $companyProfile = CompanyProfile::first();
            }
            
            if ($cachedJobStatuses === null) {
                $cachedJobStatuses = SalesMaster::select('job_status')
                    ->whereNotNull('job_status')
                    ->where('job_status', '!=', '')
                    ->distinct()
                    ->orderBy('job_status')
                    ->pluck('job_status')
                    ->toArray();
            }
            
            if ($companyProfile && in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // Pre-define status mapping for better performance
                $statusMap = [];
                foreach ($cachedJobStatuses as $status) {
                    $statusLower = strtolower($status);
                    if ($statusLower == 'serviced' || $statusLower == 'completed') {
                        $statusMap['serviced'] = ['name' => 'serviced', 'value' => 'serviced'];
                    } elseif ($statusLower == 'clawback') {
                        $statusMap['clawback'] = ['name' => 'Clawback', 'value' => 'clawback'];
                    } elseif ($statusLower == 'cancelled') {
                        $statusMap['cancelled'] = ['name' => 'Cancelled', 'value' => 'cancelled'];
                    } else {
                        $statusMap['pending'] = ['name' => 'Pending', 'value' => 'pending'];
                    }
                }
                $values = array_values($statusMap);
            } else {
                // For non-pest companies: direct mapping
                $values = array_map(function($status) {
                    return ['name' => $status, 'value' => $status];
                }, $cachedJobStatuses);
            }

            return response()->json([
                'ApiName' => 'job-status',
                'status' => true,
                'message' => 'Job statuses retrieved successfully.',
                'data' => $values,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'job-status',
                'status' => false,
                'message' => 'Failed to retrieve job statuses.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get available data source types for filtering
     */
    public function dataSourceTypes(Request $request)
    {
        try {
            // Get distinct data source types from sales_masters table
            $dataSourceTypes = SalesMaster::select('data_source_type')
                ->whereNotNull('data_source_type')
                ->where('data_source_type', '!=', '')
                ->distinct()
                ->orderBy('data_source_type')
                ->pluck('data_source_type')
                ->toArray();

            // Format the response similar to job-status API
            $values = array_map(function($sourceType) {
                return [
                    'name' => $sourceType,
                    'value' => $sourceType
                ];
            }, $dataSourceTypes);

            return response()->json([
                'ApiName' => 'data-source-types',
                'status' => true,
                'message' => 'Data source types retrieved successfully.',
                'data' => $values,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'data-source-types',
                'status' => false,
                'message' => 'Failed to retrieve data source types.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get available product IDs for filtering
     */
    public function productIds(Request $request)
    {
        try {
            // Get distinct product IDs from sales_masters table
            $productIds = SalesMaster::select('product_id')
                ->whereNotNull('product_id')
                ->distinct()
                ->orderBy('product_id')
                ->pluck('product_id')
                ->toArray();

            // Format the response
            $values = array_map(function($productId) {
                return [
                    'name' => "Product ID: {$productId}",
                    'value' => $productId
                ];
            }, $productIds);

            // Add option for unassigned products
            array_unshift($values, [
                'name' => 'Unassigned Products',
                'value' => 'unassigned'
            ]);

            return response()->json([
                'ApiName' => 'product-ids',
                'status' => true,
                'message' => 'Product IDs retrieved successfully.',
                'data' => $values,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'product-ids',
                'status' => false,
                'message' => 'Failed to retrieve product IDs.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get available product names for filtering
     */
    public function productNames(Request $request)
    {
        try {
            // Get distinct product names from various fields that contribute to product_name
            $productNames = collect();

            // Get from product field
            $productField = SalesMaster::select('product')
                ->whereNotNull('product')
                ->where('product', '!=', '')
                ->distinct()
                ->pluck('product');

            // Get from sale_product_name field
            $saleProductNames = SalesMaster::select('sale_product_name')
                ->whereNotNull('sale_product_name')
                ->where('sale_product_name', '!=', '')
                ->distinct()
                ->pluck('sale_product_name');

            // Combine and get unique values
            $productNames = $productField->merge($saleProductNames)
                ->unique()
                ->filter()
                ->sort()
                ->values();

            // Format the response
            $values = $productNames->map(function($productName) {
                return [
                    'name' => $productName,
                    'value' => $productName
                ];
            })->toArray();

            return response()->json([
                'ApiName' => 'product-names',
                'status' => true,
                'message' => 'Product names retrieved successfully.',
                'data' => $values,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'product-names',
                'status' => false,
                'message' => 'Failed to retrieve product names.',
                'data' => [],
            ], 500);
        }
    }

}
