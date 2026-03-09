<?php

namespace App\Http\Controllers\API\management;

use App\Core\Traits\PermissionCheckTrait;
use App\Exports\StandardEmployeeExport;
use App\Exports\UserExport;
use App\Exports\UserManagementMultiSheetExport;
use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crms;
use App\Models\EmployeeIdSetting;
use App\Models\ManagementTeamMember;
use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use App\Models\MilestoneSchemaTrigger;
use App\Models\NewSequiDocsDocument;
// use Maatwebsite\Excel\Excel;
use App\Models\Notification;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\Positions;
use App\Models\Products;
use App\Models\State;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserDeductionHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

use function PHPSTORM_META\override;

class EmployeeManagementController extends Controller
{
    use PermissionCheckTrait;

    public function __construct()
    {
        // $routeName = Route::currentRouteName();
        //  $user = auth('api')->user()->position_id;
        //  $roleId = $user;
        //  $result = $this->checkPermission($roleId, '5', $routeName);

        //  if ($result == false)
        //  {
        //     $response = [
        //          'status' => false,
        //          'message' => 'this module not access permission.',
        //      ];
        //      print_r(json_encode($response));die();
        //  }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // return $request->location;
        if ($request->location == null) {
            // echo $request->location;die;
            // echo"DADS";die;
            $user = auth('api')->user();

            $your_location = State::where('id', $user->state_id)->first();
            // dd($your_location);
            $data = User::with('positionDetail', 'state', 'city')->where('state_id', $user->state_id)->paginate(10);
            // $data = User::with('positionDetail', 'state', 'city')->where('manager_id', $user->id)->paginate(10);
            $position = Positions::where('position_name', '!=', 'Super Admin')->get();
            $data3 = [];
            foreach ($position as $key => $value) {
                $data2 = User::where('position_id', $value->id)->where('state_id', $user->state_id)->count();
                $data3[$value->position_name] = $data2;
            }
            // dd($data3);

            $data->transform(function ($data) {
                $dataUser = User::with('positionDetail', 'state', 'city')->where('id', $data->id)->first();

                return [
                    'id' => $data->id,
                    'image' => isset($data->image) ? $data->image : null,
                    'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : null,
                    // 'last_name'      => isset($data->last_name) ? $data->last_name: null,
                    'position' => isset($dataUser->positionDetail->position_name) ? $dataUser->positionDetail->position_name : null,
                    'location' => isset($data->city->name, $data->state->name) ? $data->city->name.','.$data->state->name : null, // isset($data->city->name) ? $data->city->name : null,
                    'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                    'email' => isset($data->email) ? $data->email : null,
                ];
            });
        } else {
            // echo"DASD";die;
            $user = auth('api')->user();
            $your_location = State::where('id', $request->location)->first();
            // dd($request->location);
            //     $location = User::where('state_id',$request->location)->get();
            //     foreach( $location as $users)
            //     {
            //         // dd($users);
            //     $data = User::with('positionDetail', 'state', 'city')->where('state_id',$request->location)->paginate(10);
            //         // dd($data);
            //     $position = Positions::get();
            //     $data3 = array();
            //     foreach ($position as $key => $value) {
            //         $data2 = User::where('position_id', $value->id)->where('id',$users->id)->count();
            //         $data3[$value->position_name] = $data2;
            //     }
            // }
            // $location = AdditionalLocations::where('state_id',$request->location)->first();
            // if(!$location)
            // {
            //     return response()->json([
            //         'ApiName' => 'list-management-employee',
            //         'status' => false,
            //         'message' => '',
            //         'your_location' => $your_location->name,
            //     ], 200);

            // }
            $data = User::with('positionDetail', 'state', 'city')->where('state_id', $request->location)->paginate(10);
            if (! $data) {
                return response()->json([
                    'ApiName' => 'list-management-employee',
                    'status' => false,
                    'message' => '',
                    'your_location' => 'Successfully',
                ], 200);

            }
            $position = Positions::where('position_name', '!=', 'Super Admin')->get();
            $data3 = [];
            foreach ($position as $key => $value) {
                $data2 = User::where('position_id', $value->id)->where('id', $user->id)->count();
                $data3[$value->position_name] = $data2;
            }
            // dd($data);

            $data->transform(function ($data) {
                $dataUser = User::with('positionDetail', 'state', 'city')->where('id', $data->id)->first();

                // dd($dataUser->positionDetail->position_name);
                return [
                    'id' => $data->id,
                    'image' => isset($data->image) ? $data->image : null,
                    'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : null,
                    // 'last_name'      => isset($data->last_name) ? $data->last_name: null,
                    'position' => isset($dataUser->positionDetail->position_name) ? $dataUser->positionDetail->position_name : null,
                    'location' => isset($data->city->name, $data->state->name) ? $data->city->name.','.$data->state->name : null, // isset($data->city->name) ? $data->city->name : null,
                    'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                    'email' => isset($data->email) ? $data->email : null,
                ];
            });

        }

        return response()->json([
            'ApiName' => 'list-management-employee',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => $your_location->name,
            'position' => $data3,
            'data' => $data,
        ], 200);
    }

    public function managementEmployeeList(Request $request)
    {
        $perPage = 10;
        $search = $request->filter;
        $officeId = $request->office_id;
        if (! empty($request->perpage)) {
            $perPage = $request->perpage;
        }

        $data = User::query()->with('positionDetail', 'state', 'city', 'office')->where('is_super_admin', '!=', 1)
            ->where(['dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0]);
        if ($officeId == 'all') {
            if (! empty($search)) {
                $data->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%$search%")
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%$search%"])
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%")
                        ->orWhere('mobile_no', 'like', "%$search%")
                        ->orWhereHas('positionDetail', function ($q) use ($search) {
                            $q->where('position_name', 'like', "%$search%")
                                ->where('position_name', '!=', 'Super Admin');
                        })->orWhereHas('additionalEmails', function ($q) use ($search) {
                            $q->where('email', 'like', "%$search%")
                                ->where('email', '!=', '');
                        });
                });
            }

            $count_data = $data->get();
        } else {
            $additionalUsers = AdditionalLocations::where('office_id', $officeId)->pluck('user_id');
            if (! empty($search)) {
                $data->where(function ($query) use ($search, $officeId, $additionalUsers) {
                    $query->where(function ($q) use ($search, $additionalUsers) {
                        $q->where('first_name', 'like', "%$search%")
                            ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%$search%"])
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('mobile_no', 'like', "%$search%")
                            ->orWhereHas('positionDetail', function ($q2) use ($search) {
                                $q2->where('position_name', 'like', "%$search%")
                                    ->where('position_name', '!=', 'Super Admin');
                            })->orWhereHas('additionalEmails', function ($q2) use ($search) {
                                $q2->where('email', 'like', "%$search%")
                                    ->where('email', '!=', '');
                            })->orWhereIn('id', $additionalUsers);
                    })->where('office_id', $officeId);
                });
            }

            $data->where(function ($q) use ($officeId, $additionalUsers) {
                $q->where('office_id', $officeId)->orWhereIn('id', $additionalUsers);
            });
            $count_data = $data->get();
        }

        $positionCount = [
            'Closer' => $count_data->where('is_manager', '!=', 1)->where('position_id', 2)->count(),
            'Setter' => $count_data->where('is_manager', '!=', 1)->where('position_id', 3)->count(),
            'Manager' => $count_data->where('is_manager', 1)->count(),
        ];

        $data->orderBy('first_name')->orderBy('last_name');
        $data = $data->paginate($perPage);
        foreach ($data as $k => $d) {
            $data[$k]['image_s3'] = ! empty($d->image)
                ? Cache::remember('s3_image_'.md5($d->image), now()->addMinutes(60), function () use ($d) {
                    return s3_getTempUrl(config('app.domain_name').'/'.$d->image);
                })
                : null;
        }

        return response()->json([
            'ApiName' => 'list-management-employee',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => '',
            'position' => $positionCount,
            'data' => $data,
        ]);
    }

    public function userManagementList(Request $request)
    {
        $perPage = 10;
        $today = date('Y-m-d');
        if ($request->filled('perpage')) {
            $perPage = $request->perpage;
        }
        $users = User::with('positionDetail', 'office', 'lastLogiingTime:tokenable_id,created_at')
            ->when($request->filled('office_filter') && $request->input('office_filter') != 'all', function ($q) {
                $q->where('office_id', request()->input('office_filter'));
            })->when($request->filled('position_filter'), function ($q) {
                $q->where('sub_position_id', request()->input('position_filter'));
            })->when($request->filled('showAdmin_filter'), function ($q) {
                $q->where('is_super_admin', request()->input('showAdmin_filter'));
            })->when($request->filled('status_filter') && in_array($request->status_filter, [0, 1]), function ($q) use ($request) {
                $status = $request->status_filter == 1 ? 1 : 0;
                if ($status == 1) {
                    // Inactive: any of the status fields is 1
                    $q->where(function ($sub) {
                        $sub->where('dismiss', 1)
                            ->orWhere('terminate', 1)
                            ->orWhere('contract_ended', 1);
                    });
                } else {
                    // Active: all of the status fields must be 0
                    $q->where(function ($sub) {
                        $sub->where('dismiss', 0)
                            ->where('terminate', 0)
                            ->where('contract_ended', 0);
                    });
                }
            })->when($request->filled('status_filter') && in_array($request->status_filter, [2, 3, 4]), function ($query) use ($request, $today) {
                switch ((int) $request->status_filter) {
                    case 2: // Dismissed
                        $query->whereExists(function ($subQuery) use ($today) {
                            $subQuery->selectRaw(1)
                                ->from('user_dismiss_histories as udh1')
                                ->join(DB::raw('(
                                SELECT user_id, MAX(effective_date) AS max_effective_date
                                FROM user_dismiss_histories
                                WHERE effective_date <= "'.$today.'"
                                GROUP BY user_id
                            ) as udh2'), function ($join) {
                                    $join->on('udh1.user_id', '=', 'udh2.user_id')
                                        ->on('udh1.effective_date', '=', 'udh2.max_effective_date');
                                })
                                ->whereColumn('udh1.user_id', 'users.id')
                                ->where('udh1.dismiss', 1);
                        });
                        break;

                    case 3: // Terminated
                        $query->whereIn('id', function ($sub) use ($today) {
                            $sub->select('uth1.user_id')   // add table alias prefix here
                                ->from('user_terminate_histories as uth1')
                                ->join(DB::raw('(
                                SELECT user_id, MAX(terminate_effective_date) as max_date
                                FROM user_terminate_histories
                                WHERE terminate_effective_date <= "'.$today.'"
                                GROUP BY user_id
                            ) as uth2'), function ($join) {
                                    $join->on('uth1.user_id', '=', 'uth2.user_id')
                                        ->on('uth1.terminate_effective_date', '=', 'uth2.max_date');
                                })
                                ->where('uth1.is_terminate', 1);
                        });
                        break;

                    case 4: // Contract Ended: latest contract record ended on or before today
                        // Use the same logic as contractEndedUsers() function
                        $contractEndedUserIds = contractEndedUsers($today);
                        if (! empty($contractEndedUserIds)) {
                            $query->whereIn('id', $contractEndedUserIds);
                        } else {
                            // No users have ended contracts
                            $query->whereRaw('1 = 0'); // Return no results
                        }
                        break;
                }
            })->when($request->filled('filter'), function ($q) {
                $search = request()->input('filter');
                $q->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile_no', 'like', '%'.$search.'%')
                    ->orWhereHas('additionalEmails', function ($query) use ($search) {
                        $query->where('email', 'like', '%'.$search.'%');
                    });
            })->orderBy('id', 'DESC')->paginate($perPage);

        $crmData = Crms::where(['id' => 3, 'status' => 1])->first();
        $users->transform(function ($item) use ($crmData) {
            $item['image_s3'] = null;
            if (! empty($item->image)) {
                $item['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$item->image);
            }

            if ($crmData && $item->worker_type == '1099' && isset($item) && ! empty($item->everee_workerId)) {
                $item['everee_onboarding_process'] = 1;
            } elseif ($crmData && ($item->worker_type == 'w2' || $item->worker_type == 'W2') && isset($item) && ! empty($item->everee_workerId) && $item->everee_embed_onboard_profile == 1) {
                $item['everee_onboarding_process'] = 1;
            } else {
                $item['everee_onboarding_process'] = 0;
            }

            $item['last_login'] = isset($item->lastLogiingTime->created_at) ? date('Y-m-d H:i:s', strtotime($item?->lastLogiingTime?->created_at)) : null;
            unset($item->lastLogiingTime);

            return $item;
        });

        $userCount = User::selectRaw('
            COUNT(*) as totalUsers,
            COUNT(CASE WHEN dismiss = 0 AND terminate = 0 AND contract_ended = 0 THEN 1 END) as totalActiveUsers,
            COUNT(CASE WHEN dismiss = 1 OR terminate = 1 OR contract_ended = 1 THEN 1 END) as totalInActiveUsers,
            COUNT(CASE WHEN is_super_admin = 1 AND dismiss = 0 AND terminate = 0 AND contract_ended = 0 THEN 1 END) as totalAdminActiveUsers,
            COUNT(CASE WHEN is_super_admin = 1 AND (dismiss = 1 OR terminate = 1 OR contract_ended = 1) THEN 1 END) as totalAdminInActiveUsers
        ')->first();

        return response()->json([
            'ApiName' => 'list-management-employee',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $users,
            'totalUsers' => $userCount->totalUsers ?? 0,
            'totalActiveUsers' => $userCount->totalActiveUsers ?? 0,
            'totalInActiveUsers' => $userCount->totalInActiveUsers ?? 0,
            'totalAdminActiveUsers' => $userCount->totalAdminActiveUsers ?? 0,
            'totalAdminInActiveUsers' => $userCount->totalAdminInActiveUsers ?? 0,
        ]);
    }

    // make_user_group_admin Make Group Admin
    public function make_user_group_admin(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'user_id' => 'required',
            ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $is_super_admin = $request->is_super_admin;
        if ($is_super_admin == true) {
            $val = 1;
        } elseif ($is_super_admin == false) {
            $val = 0;
        }
        $update_user = User::where('id', '=', $request->user_id)->where('id', '!=', 1)->Update(['is_super_admin' => $val]);

        return response()->json([
            'ApiName' => 'make_user_group_admin',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $update_user,
        ], 200);
    }

    // suspend user access
    public function suspend_user_access(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'user_id' => 'required',
            ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $update_user = User::where('id', $request->user_id)->where('id', '!=', 1)->update(['status_id' => 6, 'dismiss' => 1, 'disable_login' => 1]);

        return response()->json([
            'ApiName' => 'suspend_user_access',
            'status' => true,
            'message' => 'User access suspend Successfully.',
        ], 200);
    }

    public function getmanagementEmployeeListByOfficeID(Request $request): JsonResponse
    {
        $officeId = $request->office_id;
        $search = $request->filter;
        $data = User::query()
            ->when($request->filter, function ($query, $search) {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile_no', 'like', '%'.$search.'%');
            })
            ->orWhereHas('positionDetail', function ($query) use ($search) {
                $query->where('position_name', 'like', '%'.$search.'%');
            })
            ->orWhereHas('additionalEmails', function ($query) use ($search) {
                $query->where('email', 'like', '%'.$search.'%');
            })
            ->with('positionDetail', 'state', 'city', 'office')
            ->whereNotIn('is_super_admin', [1])
            ->where('office_id', $officeId)
            ->where('dismiss', 0)
            // ->paginate(10);
            ->get();

        return response()->json([
            'ApiName' => 'list-management-employee',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function managementEmployeeListByStateIds(Request $request)
    {
        $state_ids = $request->locations;

        $user = auth('api')->user();
        // \DB::enableQueryLog();
        // $your_location = State::whereIn('id',$state_ids)->pluck('name');
        // dd(\DB::getQueryLog());
        // dd($your_location);
        if (isset($request->office_id)) {
            $office_id = $request->office_id;
            $data = User::with('positionDetail', 'state', 'city')->where('dismiss', 0)->whereIn('office_id', [$office_id])->paginate(10);
        } else {
            $data = User::with('positionDetail', 'state', 'city')->where('dismiss', 0)->whereIn('state_id', $state_ids)->paginate(10);
        }
        // dd($data);
        if (! $data) {
            return response()->json([
                'ApiName' => 'list-management-employee',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);

        }
        $position = Positions::where('position_name', '!=', 'Super Admin')->get();
        $positions = [];
        foreach ($position as $key => $value) {
            // dd($value);
            if (isset($request->office_id)) {
                $userPosition = User::where('position_id', $value->id)->where('dismiss', 0)->whereIn('office_id', [$office_id])->count();
            } else {
                $userPosition = User::where('position_id', $value->id)->where('dismiss', 0)->whereIn('state_id', $state_ids)->count();
            }
            $positions[$value->position_name] = $userPosition;
        }

        $data->transform(function ($data) {
            $dataUser = User::with('positionDetail', 'state', 'city')->where('id', $data->id)->first();

            return [
                'id' => $data->id,
                'state_id' => $data->state_id,
                'image' => isset($data->image) ? $data->image : null,
                'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : null,
                'position' => isset($dataUser->positionDetail->position_name) ? $dataUser->positionDetail->position_name : null,
                'location' => isset($data->state->name) ? $data->state->name : null, // isset($data->city->name) ? $data->city->name : null,
                'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                'email' => isset($data->email) ? $data->email : null,
                'office_id' => isset($data->office_id) ? $data->office_id : null,
            ];
        });

        return response()->json([
            'ApiName' => 'list-management-employee',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => '',
            'position' => $positions,
            'data' => $data,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function search($name)
    {
        //    return $name;
        $user = auth('api')->user();
        $result = user::where('first_name', 'LIKE', '%'.$name.'%')->where('dismiss', 0)->orWhere('last_name', 'LIKE', '%'.$name.'%')
            // ->where('manager_id', $user->id)
            ->get();
        if (count($result)) {
            return response()->json([
                'ApiName' => 'search-management-employee',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'search-management-employee',
                'status' => false,
                'message' => 'Data not found',
                // 'data' => null,
            ], 400);
        }
    }

    public function filter(Request $request, User $user)
    {
        $data1 = auth('api')->user();
        $your_location = $user->location;
        $position = Positions::where('position_name', '!=', 'Super Admin')->get();
        $data3 = [];
        foreach ($position as $key => $value) {
            $data2 = User::where('position_id', $value->id)->where('dismiss', 0)->count();
            $data3[$value->position_name] = $data2;
        }
        // \DB::enableQueryLog();
        // $user = $user->newQuery();
        // //$user->with('positionDetail', 'state', 'city')->where('manager_id' , $data1->id);
        // $user->with('positionDetail', 'state', 'city');

        // if ($request->has('filter') && !empty($request->input('filter'))) {
        //     //echo"DASD";die;
        //     $user->where(function ($query) use ($request) {
        //         return $query->where('first_name', 'LIKE', '%' . $request->input('filter') . '%')
        //             ->orWhere('email', 'LIKE', '%' . $request->input('filter') . '%')
        //             ->orWhere('first_name', 'LIKE', '%' . $request->input('filter') . '%')
        //             ->orWhere('mobile_no', 'LIKE', '%' . $request->input('filter') . '%');
        //     });
        // }
        // $data = $user->paginate(10);
        $search = $request->input('filter');
        if (! empty($request->input('office_id'))) {
            $office = $request->input('office_id');
        }

        $data = User::query()
            ->when($request->filter, function ($query, $search) {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile_no', 'like', '%'.$search.'%');
            })
            ->orWhereHas('positionDetail', function ($query) use ($search) {
                $query->where('position_name', 'like', '%'.$search.'%');
            })
            ->orWhereHas('additionalEmails', function ($query) use ($search) {
                $query->where('email', 'like', '%'.$search.'%');
            })
            ->when($request->office_id, function ($query, $office) {
                $query->where('office_id', 'like', '%'.$office.'%');

            })
            ->orWhereHas('state', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->with('positionDetail', 'state', 'city')
            ->where('dismiss', 0)
            ->get();

        // dd(\DB::getQueryLog());
        // return $data;die;
        $data->transform(function ($data) {
            return [
                // dd($data->id),
                'id' => $data->id,
                'image' => isset($data->image) ? $data->image : null,
                'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : null,
                // 'last_name'      => isset($data->last_name) ? $data->last_name: null,
                'position' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                'location' => isset($data->state->name) ? $data->state->name : null, // isset($data->city->name) ? $data->city->name : null,
                'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                'email' => isset($data->email) ? $data->email : null,
            ];
        });

        return response()->json([
            'ApiName' => 'filter',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => $your_location,
            'position' => $data3,
            'data' => $data,
        ], 200);
    }

    public function teamMemberList(Request $request)
    {

        $data2 = User::where('dismiss', 0)->count();

        $search = $request->input('filter');
        if (! empty($request->input('office_id'))) {
            $office = $request->input('office_id');
        }

        $data = User::query()
            ->when($request->filter, function ($query, $search) {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile_no', 'like', '%'.$search.'%');
            })
            ->orWhereHas('positionDetail', function ($query) use ($search) {
                $query->where('position_name', 'like', '%'.$search.'%');
            })
            ->orWhereHas('additionalEmails', function ($query) use ($search) {
                $query->where('email', 'like', '%'.$search.'%');
            })
            ->when($request->office_id, function ($query, $office) {
                $query->where('office_id', 'like', '%'.$office.'%');

            })
            ->orWhereHas('state', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->with('positionDetail', 'state', 'city')
            ->get();

        // dd(\DB::getQueryLog());
        // return $data;die;
        $data->transform(function ($data) {
            return [
                // dd($data->id),
                'id' => $data->id,
                'image' => isset($data->image) ? $data->image : null,
                'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : null,
                // 'last_name'      => isset($data->last_name) ? $data->last_name: null,
                'position' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                'location' => isset($data->state->name) ? $data->state->name : null, // isset($data->city->name) ? $data->city->name : null,
                'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                'email' => isset($data->email) ? $data->email : null,
            ];
        });

        return response()->json([
            'ApiName' => 'filter',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => $your_location,
            'position' => $data3,
            'data' => $data,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        return $id;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateImage(Request $request)
    {
        // Check if file upload failed at PHP level (e.g., file too large for upload_max_filesize)
        $file = $request->file('image');

        // Check if file exists but is invalid (PHP upload error)
        if ($file && !$file->isValid()) {
            $errorCode = $file->getError();
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The image size exceeds the maximum allowed size. Maximum allowed size is 2MB (2048 KB). Please compress or resize your image before uploading.',
                UPLOAD_ERR_FORM_SIZE => 'The image size exceeds the maximum allowed size. Maximum allowed size is 2MB (2048 KB). Please compress or resize your image before uploading.',
                UPLOAD_ERR_PARTIAL => 'The image file was only partially uploaded. Please try uploading again.',
                UPLOAD_ERR_NO_FILE => 'No image file was uploaded. Please select an image file to upload.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: Missing temporary folder. Please contact support.',
                UPLOAD_ERR_CANT_WRITE => 'Server error: Failed to write file to disk. Please contact support.',
                UPLOAD_ERR_EXTENSION => 'Server error: A PHP extension stopped the file upload. Please contact support.',
            ];

            $errorMessage = $errorMessages[$errorCode] ?? 'The image failed to upload. Please try again or contact support if the problem persists.';
            return response()->json(['message' => $errorMessage], 400);
        }

        $Validator = Validator::make($request->all(),
            [
                'user_id' => 'required',
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',

            ],
            [
                'user_id.required' => 'User ID is required. Please provide a valid user ID.',
                'image.required' => 'Please select an image file to upload.',
                'image.image' => 'The uploaded file must be an image. Please select a valid image file.',
                'image.mimes' => 'The image must be in one of these formats: JPG, PNG, JPEG, GIF, or SVG. Please convert your image to a supported format.',
                'image.max' => 'The image size exceeds the maximum allowed size. Maximum allowed size is 2MB (2048 KB). Please compress or resize your image before uploading.',
            ]);

        if ($Validator->fails()) {
            $errorMessage = $Validator->errors()->first();
            return response()->json(['message' => $errorMessage], 400);
        }

        if (isset($file) && $file != null && $file != '') {
            // s3 bucket
            $img_path = time().$file->getClientOriginalName();
            $img_path = str_replace(' ', '_', $img_path);
            $awsPath = config('app.domain_name').'/'.'Employee_profile/'.$img_path;
            s3_upload($awsPath, file_get_contents($file), false);
            // s3 bucket end

            $ex = $file->getClientOriginalExtension();
            $destinationPath = 'Employee_profile';
            $image_path = $file->move($destinationPath, $img_path);
            $data = User::find($request->user_id);
            $data->image = $image_path;
            $data->save();
        }

        return response()->json([
            'ApiName' => 'update-user-image',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
        //
    }

    public function updateDeviceToken(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                'device_token' => 'required',

            ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $user = auth('api')->user();
        $data = User::find($user->id);
        $data->device_token = $request->device_token;
        $data->save();

        return response()->json([
            'ApiName' => 'update-user-device_token',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function getNotificationList(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $notifications = Notification::where('user_id', '=', $user->id)->get();
        if (count($notifications) > 0) {

            return response()->json(['ApiName' => 'notifications Api', 'status' => true, 'message' => 'Found Data', 'data' => $notifications], 200);
        } else {
            return response()->json(['ApiName' => 'notifications Api', 'status' => false, 'message' => 'Not Fond Data', 'data' => []], 200);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        //
    }

    public function UpdateEmployeeStatus(Request $request)
    {
        // return $request;
        $data = User::where('id', $request->user_id)->first();

        // if(!$data == NULL && !$request == NULL)
        // {

        //     $data->team_lead_id   = $request['team_lead_id'];
        //     $data->save();
        //     ManagementTeamMember::create(
        //         [
        //             'team_id' => $data->id,
        //             'team_lead_id'  => $request->team_lead_id,
        //             'team_member_id'  => $request->team_lead_id,
        //         ]
        //         );
        //     foreach($request->team_members as $value)
        //     {
        //         ManagementTeamMember::create(
        //             [
        //                 'team_id'  =>$data->id,
        //                 'team_lead_id' => $request->team_lead_id,
        //                 'team_member_id'  => $value,
        //             ]
        //             );
        //     }
        // }
    }

    public function sendOnesignalPushNotificationios_s(Request $request)
    {       // echo"DAS";die;
        $message = 'Hello users';
        $header = 'New plan subscription';
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
          "app_id": "fc460012-2bcb-45d3-9f54-25ce038a8d1e",
          "data": {"foo": "bar"},
          "include_player_ids":["22ae461f-536c-4fd6-b99c-ab487de77d63"],
          "contents": {"en": "'.$message.'"},
          "headings":{"en":"'.$header.'"},
          "email_subject": "test",
          "email_body": "test",
        }',

            CURLOPT_HTTPHEADER => [
                'Authorization: Basic MDUyNzU1ZGMtYTA5ZS00ZWVjLWJmMmYtMzI0NDYxMWVlMTI3',
                'Content-Type: application/json; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        print_r($response);
        exit;
    }

    public function sendOnesignalPushNotificationios(Request $request)
    {
        $fields = [
            'app_id' => 'fc460012-2bcb-45d3-9f54-25ce038a8d1e',
            'email_subject' => 'Welcome to Cat Facts!',
            'email_body' => '<html><head>Welcome to Cat Facts</head><body><h1>Welcome to Cat Facts<h1><h4>Learn more about everyone favorite furry companions!</h4><hr/><p>Hi Nick,</p><p>Thanks for subscribing to Cat Facts! We can not wait to surprise you with funny details about your favorite animal.</p><h5>Today Cat Fact (March 27)</h5><p>In tigers and tabbies, the middle of the tongue is covered in backward-pointing spines, used for breaking off and gripping meat.</p><p><small>(c) 2018 Cat Facts, inc</small></p><p><small>Unsubscribe</a></small></p></body></html>',
             'include_email_tokens' => [],
        ];
        $fields = json_encode($fields);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 100,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic MDUyNzU1ZGMtYTA5ZS00ZWVjLWJmMmYtMzI0NDYxMWVlMTI3',
                'accept: application/json',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($curl);

        return $response;
        exit;
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:'.$err;
        } else {
            echo $response;
        }
    }

    public function employee_network($id)
    {
        $user = User::where('id', $id)->first();
        $additional = User::select('id', 'recruiter_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager')->with('childs', 'childs1', 'childs2')->where('recruiter_id', $id)->get();
        // ->orWhere('additional_recruiter_id1',$id)->orWhere('additional_recruiter_id2',$id)
        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['position_id'] = $user['position_id'];
        $data['sub_position_id'] = $user['sub_position_id'];
        $data['is_super_admin'] = $user['is_super_admin'];
        $data['is_manager'] = $user['is_manager'];
        $data['childs'] = $additional;

        // return $data;
        return response()->json([
            'ApiName' => 'employee_network',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function my_overrides_old($id)
    {
        $user = User::where('id', $id)->first();
        // $additional = User::select('id','recruiter_id','additional_recruiter_id1','additional_recruiter_id2','first_name','last_name','image')->with('childs')->where('recruiter_id',$id)->get();
        $directs = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'direct_overrides_amount', 'direct_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('recruiter_id', $id)->orWhere('additional_recruiter_id1', $id)->orWhere('additional_recruiter_id2', $id)->get();
        $totalDirects = User::with('positionDetail', 'recruiter')->where('recruiter_id', $id)->orWhere('additional_recruiter_id1', $id)->orWhere('additional_recruiter_id2', $id)->count();

        // $totalAccountDirect = UserOverrides::where('user_id',$user->id)->where('type','direct')->count();

        $direct = [];
        $indirect = [];
        $office = [];
        $manual = [];

        if (count($directs) > 0) {
            foreach ($directs as $key => $value) {

                $totalAccountDirect = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $value->id])->where('type', 'Direct')->count();

                $totalOverrideDirect = DB::table('user_overrides')
                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                    ->where(['user_id' => $user->id, 'sale_user_id' => $value->id])
                    ->where('type', 'Direct')
                    ->first();

                $direct[] = [
                    'id' => $value->id,
                    'recruiter_id' => $value->recruiter_id,
                    'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                    'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                    'first_name' => $value->first_name,
                    'last_name' => $value->last_name,
                    'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                    'override' => $value->direct_overrides_amount.'/'.$value->direct_overrides_type,
                    'totalOverrides' => $totalOverrideDirect->overridesTotal,
                    'account' => $totalAccountDirect,
                    'kwInstalled' => $totalOverrideDirect->totalKw,
                    'image' => $value->image,
                ];

                $additional = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'indirect_overrides_amount', 'indirect_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('recruiter_id', $value->id)->orWhere('additional_recruiter_id1', $value->id)->orWhere('additional_recruiter_id2', $value->id)->get();
                $indirectCount = User::with('positionDetail', 'recruiter')->where('recruiter_id', $value->id)->count();

                // $totalOverrideIndirect = DB::table('user_overrides')
                // ->select('user_id',DB::raw('SUM(user_overrides.amount) AS overridesTotal'),DB::raw('SUM(user_overrides.kw) AS totalKw'))
                // ->where('user_id',$user->id)
                // ->where('type','Indirect')
                // ->first();
                // $totalAccountIndirect = UserOverrides::where('user_id',$user->id)->where('type','Indirect')->count();

                $additionals = [];
                if (count($additional) > 0) {
                    foreach ($additional as $key1 => $val) {

                        $totalAccountIndirect = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $val->id])->where('type', 'Indirect')->count();

                        $totalOverrideIndirect = DB::table('user_overrides')
                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                            ->where(['user_id' => $user->id, 'sale_user_id' => $val->id])
                            ->where('type', 'Indirect')
                            ->first();

                        $additionals = [
                            'id' => $val->id,
                            'recruiter_id' => $val->recruiter_id,
                            'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                            'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                            'first_name' => $val->first_name,
                            'last_name' => $val->last_name,
                            'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                            'override' => $val->indirect_overrides_amount.'/'.$val->indirect_overrides_type,
                            'totalOverrides' => $totalOverrideIndirect->overridesTotal,
                            'account' => $totalAccountIndirect,
                            'kwInstalled' => $totalOverrideIndirect->totalKw,
                            'image' => $val->image,

                        ];
                        $indirect[] = $additionals;
                        $totalIndirect = $indirectCount;
                    }
                }

            }
        }

        $additionals1 = [];
        $officeUsers = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('office_id', $user->office_id)->get();
        $officeCount = User::with('positionDetail', 'recruiter')->where('office_id', $user->office_id)->count();

        if (count($officeUsers) > 0) {
            foreach ($officeUsers as $key2 => $vall) {

                $totalOverrideOffice = DB::table('user_overrides')
                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                    ->where(['user_id' => $user->id, 'sale_user_id' => $vall->id])
                    ->where('type', 'Office')
                    ->first();

                $totalAccountOffice = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $vall->id])->where('type', 'Office')->count();
                // dd($totalAccountOffice)  ;die;
                $office_overrides_amount = isset($vall->office_overrides_amount) ? $vall->office_overrides_amount : '0';
                $office_overrides_type = isset($vall->office_overrides_type) ? $vall->office_overrides_type : 'per kw';
                $additionals1 = [
                    'id' => $vall->id,
                    'recruiter_id' => $vall->recruiter_id,
                    'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                    'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                    'first_name' => $vall->first_name,
                    'last_name' => $vall->last_name,
                    'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                    'override' => $office_overrides_amount.' /'.$office_overrides_type,
                    'totalOverrides' => $totalOverrideOffice->overridesTotal,
                    'account' => $totalAccountOffice,
                    'kwInstalled' => $totalOverrideOffice->totalKw,
                    'image' => $vall->image,

                ];
                $office[] = $additionals1;
                $totalOffice = $officeCount;

            }

        }

        $manualData = ManualOverrides::where('user_id', $user->id)->get();
        $manualCount = ManualOverrides::where('user_id', $user->id)->count();

        $manualOverride = [];
        if ($manualCount > 0) {
            foreach ($manualData as $key3 => $manual) {

                $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('id', $manual->manual_user_id)->first();

                $totalAccountManual = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])->where('type', 'Manual')->count();

                $totalOverrideManual = DB::table('user_overrides')
                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                    ->where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])
                    ->where('type', 'Manual')
                    ->first();
                $manualOverride[] = [
                    'id' => $vall->id,
                    'recruiter_id' => $vall->recruiter_id,
                    'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                    'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                    'first_name' => $vall->first_name,
                    'last_name' => $vall->last_name,
                    'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                    'override' => $manualData[$key3]->overrides_amount.'/'.$manualData[$key3]->overrides_type,

                    'totalOverrides' => $totalOverrideManual->overridesTotal,
                    'account' => $totalAccountManual,
                    'kwInstalled' => $totalOverrideManual->totalKw,
                    'image' => $vall->image,

                ];

                $totalmanual = $manualCount;

            }

        }
        // return $manual;

        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['totalDirects'] = isset($totalDirects) ? ($totalDirects) : 0;
        $data['totalIndirect'] = isset($totalIndirect) ? ($totalIndirect) : 0;
        $data['totalOffice'] = isset($totalOffice) ? ($totalOffice) : 0;
        $data['totalmanual'] = isset($totalmanual) ? ($totalmanual) : 0;
        $data['direct'] = $direct;
        $data['indirect'] = $indirect;
        $data['office'] = $office;
        $data['manual'] = $manualOverride;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function my_overrides($id, Request $request): JsonResponse
    {
        $search = $request->input('search');
        $user = User::where('id', $id)->first();
        if (! $user) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'User not found!!',
                'data' => [],
            ], 400);
        }

        $overrideCheck = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
        if (! $overrideCheck) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'Overrides are disabled!!',
                'data' => [],
            ], 400);
        }

        $date = date('Y-m-d');
        $userOverride = UserOverrideHistory::where('user_id', $id)->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->first();
        if (! $userOverride) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'No override history available for the specified user. Please check the user ID and try again.!!',
                'data' => [],
            ], 400);
        }

        // $organization = UserOrganizationHistory::where('user_id', $id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
        $directOverrides = [];
        $inDirectOverrides = [];
        $officeOverrides = [];
        $manualOverrides = [];
        $stackOverrides = [];

        // $subPosition = $user->sub_position_id;
        // if ($organization) {
        //     $subPosition = $organization->sub_position_id;
        // }

        // $positionOverride = PositionOverride::where(['position_id' => $subPosition, 'override_id' => '1', 'status' => '1'])->first();
        // if ($positionOverride) {
        // $positionInDirectOverride = PositionOverride::where(['position_id' => $subPosition, 'override_id' => '2', 'status' => '1'])->first();

        $subQuery = UserOverrideHistory::select(
            'id',
            'user_id',
            'override_effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
        )->where('override_effective_date', '<=', $date);

        // Main query to get the IDs where rn = 1
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

        // Final query to get the user_override_history records with the selected IDs
        $directs = UserOverrideHistory::select(
            'users.id',
            // 'override_status.status',
            'users.position_id',
            'users.recruiter_id',
            'users.sub_position_id',
            'users.first_name',
            'users.last_name',
            'users.image',
            'users.is_super_admin',
            'users.is_manager',
            DB::raw('MAX(override_status.effective_date) as latest_effective_date') // Select the latest effective date
        )
            ->whereIn('user_override_history.id', $results->pluck('id'))
            ->join('users', 'users.id', 'user_override_history.user_id')
            // ->leftJoin('override_status', function ($join) use ($user) {
            //     $join->on('override_status.user_id', 'users.id')->where('override_status.recruiter_id', $user->id)->where('override_status.type', 'Direct')->whereNotNull('override_status.effective_date')->where('override_status.effective_date','<=',date('Y-m-d'))->orderBy('override_status.effective_date','DESC');
            // })
            ->leftJoin('override_status', function ($join) use ($user) {
                $join->on('override_status.user_id', 'users.id')
                    ->where('override_status.recruiter_id', $user->id)
                    ->where('override_status.type', 'Direct')
                    ->whereNotNull('override_status.effective_date')
                    ->where('override_status.effective_date', '<=', date('Y-m-d'));
            })->where(function ($query) use ($user) {
                $query->where('users.recruiter_id', $user->id)
                    ->orWhere('users.additional_recruiter_id1', $user->id)
                    ->orWhere('users.additional_recruiter_id2', $user->id);
            })
            ->where(function ($query) use ($search) {
                $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
            })
            ->where('users.dismiss', 0)->get();

        $userIds = $directs->pluck('id');
        $totalKwSubQuery = DB::table('sale_masters as ism')
            ->selectRaw('SUM(ism.kw)')
            ->whereIn('ism.pid', function ($query) {
                $query->select('iuo.pid')
                    ->from('user_overrides as iuo')
                    ->whereColumn('iuo.user_id', 'user_overrides.user_id')
                    ->where('iuo.type', 'Direct')
                    ->whereColumn('iuo.sale_user_id', 'user_overrides.sale_user_id');
            });
        $totalAccountDirect = UserOverrides::selectRaw('SUM(user_overrides.amount) as amount, COUNT(user_overrides.id) as count, user_overrides.sale_user_id')
            ->selectSub($totalKwSubQuery, 'totalKw')
            ->where(['user_overrides.user_id' => $user->id, 'user_overrides.type' => 'Direct'])->whereIn('user_overrides.sale_user_id', $userIds)
            ->groupBy('user_overrides.user_id', 'user_overrides.sale_user_id', 'user_overrides.type')->get();

        // DIRECT OVERRIDES START
        foreach ($directs as $value) {
            $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $value->id)->where('recruiter_id', $user->id)->where('type', 'Direct')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
            $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $value->id)->where('recruiter_id', $user->id)->where('type', 'Direct')->orderBy('effective_date', 'DESC')->first();
            $overrideCount = $totalAccountDirect->where('sale_user_id', $value->id)->sum('count') ?? 0;
            $overridesTotal = $totalAccountDirect->where('sale_user_id', $value->id)->sum('amount') ?? 0;
            $kwTotal = $totalAccountDirect->where('sale_user_id', $value->id)->sum('totalKw') ?? 0;
            if (isset($value->image) && $value->image != null) {
                $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$value->image);
            } else {
                $s3_image = null;
            }

            $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $value->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
            $directOverrides[] = [
                'id' => $value->id,
                'recruiter_id' => $user->id,
                'recruiter_name' => isset($user->first_name) ? $user->first_name.' '.$user->last_name : null,
                'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                'first_name' => $value->first_name,
                'last_name' => $value->last_name,
                // 'status' => $value->status ? $value->status : 0,
                'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                'override' => $userOverride->direct_overrides_amount,
                'override_type' => $userOverride->direct_overrides_type,
                'override_custom_sales_field_id' => $userOverride->direct_custom_sales_field_id ?? null,
                'totalOverrides' => $overridesTotal,
                'account' => $overrideCount,
                'kwInstalled' => $kwTotal,
                'image' => $value->image,
                'image_s3' => $s3_image,
                'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                'is_super_admin' => isset($value->is_super_admin) ? $value->is_super_admin : null,
                'is_manager' => isset($value->is_manager) ? $value->is_manager : null,
                'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
            ];
            // DIRECT OVERRIDES END

            // INDIRECT OVERRIDES START
            // if ($positionInDirectOverride) {
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $date);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $inDirects = UserOverrideHistory::select(
                'users.id',
                // 'override_status.status',
                'users.position_id',
                'users.recruiter_id',
                'users.sub_position_id',
                'users.first_name',
                'users.last_name',
                'users.image',
                'users.is_super_admin',
                'users.is_manager',
                DB::raw('MAX(override_status.effective_date) as latest_effective_date')
            )
                ->whereIn('user_override_history.id', $results->pluck('id'))
                ->join('users', 'users.id', 'user_override_history.user_id')
                ->leftJoin('override_status', function ($join) use ($value) {
                    $join->on('override_status.user_id', 'users.id')
                        ->where('override_status.recruiter_id', $value->id)
                        ->where('override_status.type', 'Indirect')
                        ->whereNotNull('override_status.effective_date')
                        ->where('override_status.effective_date', '<=', date('Y-m-d'));
                })->where(function ($query) use ($value) {
                    $query->where('users.recruiter_id', $value->id)
                        ->orWhere('users.additional_recruiter_id1', $value->id)
                        ->orWhere('users.additional_recruiter_id2', $value->id);
                })
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->where('users.dismiss', 0)->get();

            $userIds = $inDirects->pluck('id');
            $totalKwSubQuery = DB::table('sale_masters as ism')
                ->selectRaw('SUM(ism.kw)')
                ->whereIn('ism.pid', function ($query) {
                    $query->select('iuo.pid')
                        ->from('user_overrides as iuo')
                        ->whereColumn('iuo.user_id', 'user_overrides.user_id')
                        ->where('iuo.type', 'Indirect')
                        ->whereColumn('iuo.sale_user_id', 'user_overrides.sale_user_id');
                });
            $totalAccountInDirect = UserOverrides::selectRaw('SUM(user_overrides.amount) as amount, COUNT(user_overrides.id) as count, user_overrides.sale_user_id')
                ->selectSub($totalKwSubQuery, 'totalKw')
                ->where(['user_overrides.user_id' => $user->id, 'user_overrides.type' => 'Indirect'])->whereIn('user_overrides.sale_user_id', $userIds)
                ->groupBy('user_overrides.user_id', 'user_overrides.sale_user_id', 'user_overrides.type')->get();

            foreach ($inDirects as $inDirect) {
                $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $inDirect->id)->where('recruiter_id', $user->id)->where('type', 'Indirect')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $inDirect->id)->where('recruiter_id', $user->id)->where('type', 'Indirect')->orderBy('effective_date', 'DESC')->first();
                $overrideCount = $totalAccountInDirect->where('sale_user_id', $inDirect->id)->sum('count') ?? 0;
                $overridesTotal = $totalAccountInDirect->where('sale_user_id', $inDirect->id)->sum('amount') ?? 0;
                $kwTotal = $totalAccountInDirect->where('sale_user_id', $inDirect->id)->sum('totalKw') ?? 0;
                if (isset($inDirect->image) && $inDirect->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$inDirect->image);
                } else {
                    $s3_image = null;
                }

                $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $inDirect->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $inDirectOverrides[] = [
                    'id' => $inDirect->id,
                    'recruiter_id' => $value->id,
                    'recruiter_name' => isset($value->first_name) ? $value->first_name.' '.$value->last_name : null,
                    'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                    'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                    'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                    'first_name' => $inDirect->first_name,
                    'last_name' => $inDirect->last_name,
                    // 'status' => $inDirect->status ? $inDirect->status : 0,
                    'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                    'override' => $userOverride->indirect_overrides_amount,
                    'override_type' => $userOverride->indirect_overrides_type,
                    'override_custom_sales_field_id' => $userOverride->indirect_custom_sales_field_id ?? null,
                    'totalOverrides' => $overridesTotal,
                    'account' => $overrideCount,
                    'kwInstalled' => $kwTotal,
                    'image' => $inDirect->image,
                    'image_s3' => $s3_image,
                    'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                    'is_super_admin' => isset($inDirect->is_super_admin) ? $inDirect->is_super_admin : null,
                    'is_manager' => isset($inDirect->is_manager) ? $inDirect->is_manager : null,
                    'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                ];
            }
            // }
            // INDIRECT OVERRIDES END
        }
        // }

        // OFFICE OVERRIDES START
        // $positionOverride = PositionOverride::where(['position_id' => $subPosition, 'override_id' => '3', 'status' => '1'])->first();
        // if ($positionOverride) {
        $office_id = $user->office_id;
        $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
        if ($userTransferHistory) {
            $office_id = $userTransferHistory->office_id;
        }
        $userIdArr1 = [$office_id];
        $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();
        // $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

        if (count($userIdArr1) > 0) {
            $userIdArr = $userIdArr1;

            $officeOverridesAmount = isset($userOverride->office_overrides_amount) ? $userOverride->office_overrides_amount : '0';
            $officeOverridesType = isset($userOverride->office_overrides_type) ? $userOverride->office_overrides_type : 'per kw';

            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $date);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $offices = UserOverrideHistory::select(
                'users.id',
                'users.position_id',
                'users.recruiter_id',
                'users.sub_position_id',
                'users.first_name',
                'users.last_name',
                'users.image',
                'users.is_super_admin',
                'users.is_manager',
                DB::raw('MAX(override_status.effective_date) as latest_effective_date')
            )
                ->whereIn('user_override_history.id', $results->pluck('id'))
                ->join('users', 'users.id', 'user_override_history.user_id')
                ->whereIn('users.office_id', $userIdArr)
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->where('users.dismiss', 0)->get();

            $userIds = $offices->pluck('id');
            $totalKwSubQuery = DB::table('sale_masters as ism')
                ->selectRaw('SUM(ism.kw)')
                ->whereIn('ism.pid', function ($query) {
                    $query->select('iuo.pid')
                        ->from('user_overrides as iuo')
                        ->whereColumn('iuo.user_id', 'user_overrides.user_id')
                        ->where('iuo.type', 'Office')
                        ->whereColumn('iuo.sale_user_id', 'user_overrides.sale_user_id');
                });
            $totalAccountOffice = UserOverrides::selectRaw('SUM(user_overrides.amount) as amount, COUNT(user_overrides.id) as count, user_overrides.sale_user_id')
                ->selectSub($totalKwSubQuery, 'totalKw')
                ->where(['user_overrides.user_id' => $user->id, 'user_overrides.type' => 'Office'])->whereIn('user_overrides.sale_user_id', $userIds)
                ->groupBy('user_overrides.user_id', 'user_overrides.sale_user_id', 'user_overrides.type')->get();

            foreach ($offices as $office) {
                $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                $overrideCount = $totalAccountOffice->where('sale_user_id', $office->id)->sum('count') ?? 0;
                $overridesTotal = $totalAccountOffice->where('sale_user_id', $office->id)->sum('amount') ?? 0;
                $kwTotal = $totalAccountOffice->where('sale_user_id', $office->id)->sum('totalKw') ?? 0;
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                if (isset($office->image) && $office->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$office->image);
                } else {
                    $s3_image = null;
                }

                $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $office->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $officeOverrides[] = [
                    'id' => $office->id,
                    'recruiter_id' => null,
                    'recruiter_name' => null,
                    'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                    'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                    'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                    'first_name' => $office->first_name,
                    'last_name' => $office->last_name,
                    // 'status' => $office->status ? $office->status : 0,
                    'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                    'override' => $officeOverridesAmount,
                    'override_type' => $officeOverridesType,
                    'override_custom_sales_field_id' => $userOverride->office_custom_sales_field_id ?? null,
                    'totalOverrides' => $overridesTotal,
                    'account' => $overrideCount,
                    'kwInstalled' => $kwTotal,
                    'image' => $office->image,
                    'image_s3' => $s3_image,
                    'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                    'is_super_admin' => isset($office->is_super_admin) ? $office->is_super_admin : null,
                    'is_manager' => isset($office->is_manager) ? $office->is_manager : null,
                    'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                ];
            }

        }
        if (count($userIdArr2) > 0) {
            $userIdArr = $userIdArr2;

            // Final query to get the user_override_history records with the selected IDs
            $offices = User::select(
                'users.id',
                'users.position_id',
                'users.recruiter_id',
                'users.sub_position_id',
                'users.first_name',
                'users.last_name',
                'users.image',
                'users.is_super_admin',
                'users.office_id',
                'users.is_manager',
                DB::raw('MAX(override_status.effective_date) as latest_effective_date')
            )
            // ->leftJoin('override_status', function ($join) use ($user) {
            //     $join->on('override_status.user_id', 'users.id')->where('override_status.recruiter_id', $user->id)->where('override_status.type', 'Office')->whereNotNull('override_status.effective_date')->where('override_status.effective_date','<=',date('Y-m-d'))->orderBy('override_status.effective_date','DESC');
            // })
                ->whereIn('users.office_id', $userIdArr)
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->where('users.dismiss', 0)->get();

            $userIds = $offices->pluck('id');

            $totalKwSubQuery = DB::table('sale_masters as ism')
                ->selectRaw('SUM(ism.kw)')
                ->whereIn('ism.pid', function ($query) {
                    $query->select('iuo.pid')
                        ->from('user_overrides as iuo')
                        ->whereColumn('iuo.user_id', 'user_overrides.user_id')
                        ->where('iuo.type', 'Office')
                        ->whereColumn('iuo.sale_user_id', 'user_overrides.sale_user_id');
                });
            $totalAccountOffice = UserOverrides::selectRaw('SUM(user_overrides.amount) as amount, COUNT(user_overrides.id) as count, user_overrides.sale_user_id')
                ->selectSub($totalKwSubQuery, 'totalKw')
                ->where(['user_overrides.user_id' => $user->id, 'user_overrides.type' => 'Office'])->whereIn('user_overrides.sale_user_id', $userIds)
                ->groupBy('user_overrides.user_id', 'user_overrides.sale_user_id', 'user_overrides.type')->get();

            foreach ($offices as $office) {
                $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                // $userOverride = AdditionalLocations::where(['user_id'=> $id, 'office_id'=> $office->office_id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $userAdditionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $id, 'office_id' => $office->office_id])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->first();

                $officeOverridesAmount = isset($userOverride->overrides_amount) ? $userOverride->overrides_amount : '0';
                $officeOverridesType = isset($userOverride->overrides_type) ? $userOverride->overrides_type : 'per kw';

                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                $overrideCount = $totalAccountOffice->where('sale_user_id', $office->id)->sum('count') ?? 0;
                $overridesTotal = $totalAccountOffice->where('sale_user_id', $office->id)->sum('amount') ?? 0;
                $kwTotal = $totalAccountOffice->where('sale_user_id', $office->id)->sum('totalKw') ?? 0;
                if (isset($office->image) && $office->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$office->image);
                } else {
                    $s3_image = null;
                }

                $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $office->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $officeOverrides[] = [
                    'id' => $office->id,
                    'recruiter_id' => null,
                    'recruiter_name' => null,
                    'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                    'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                    'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                    'first_name' => $office->first_name,
                    'last_name' => $office->last_name,
                    // 'status' => $office->status ? $office->status : 0,
                    'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                    'override' => $officeOverridesAmount,
                    'override_type' => $officeOverridesType,
                    'override_custom_sales_field_id' => $userAdditionalOverride->custom_sales_field_id ?? null,
                    'totalOverrides' => $overridesTotal,
                    'account' => $overrideCount,
                    'kwInstalled' => $kwTotal,
                    'image' => $office->image,
                    'image_s3' => $s3_image,
                    'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                    'is_super_admin' => isset($office->is_super_admin) ? $office->is_super_admin : null,
                    'is_manager' => isset($office->is_manager) ? $office->is_manager : null,
                    'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                ];
            }
        }
        // OFFICE OVERRIDES END

        // MANUAL OVERRIDES START
        $manualSystemSetting = overrideSystemSetting::where('allow_manual_override_status', 1)->first();
        if ($manualSystemSetting) {
            $manualData = ManualOverrides::where('user_id', $user->id)->with('ManualOverridesHistory')->get();
            foreach ($manualData as $manual) {
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $manual->manual_user_id)->where('recruiter_id', $id)->where('type', 'Manual')->orderBy('effective_date', 'DESC')->first();
                // $overrideStatus = OverrideStatus::where('user_id', $manual->manual_user_id)->where('recruiter_id', $id)->where('type', 'Manual')->first();
                $overrideStatus = OverrideStatus::where('user_id', $manual->manual_user_id)->where('recruiter_id', $id)->where('type', 'Manual')->whereNotNull('effective_date')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'is_super_admin', 'is_manager')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                    ->where('id', $manual->manual_user_id)
                    ->where(function ($query) use ($search) {
                        $query->where('first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                    })->first();

                if (! empty($vall)) {
                    $totalAccountManual = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])->where('type', 'Manual')->count();

                    $totalOverrideManual = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id, 'type' => 'Manual'])->first();

                    if (isset($vall->image) && $vall->image != null) {
                        $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$vall->image);
                    } else {
                        $s3_image = null;
                    }

                    $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $manual->manual_user_id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    $manualOverrides[] = [
                        'manual_overrides_id' => $manual->id,
                        'id' => $vall->id,
                        'recruiter_id' => null,
                        'recruiter_name' => null,
                        'manual_user_id' => $manual->manual_user_id,
                        'user_id' => $manual->user_id,
                        'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                        'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                        'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $manual->overrides_amount,
                        'override_type' => $manual->overrides_type,
                        'override_custom_sales_field_id' => $manual->custom_sales_field_id ?? null,
                        'totalOverrides' => $totalOverrideManual->overridesTotal,
                        'account' => $totalAccountManual,
                        'kwInstalled' => $totalOverrideManual->totalKw,
                        'overrides_amount' => $manual->overrides_amount,
                        'overrides_type' => $manual->overrides_type,
                        'effective_date' => $manual->effective_date,
                        'image' => $vall->image,
                        'image_s3' => $s3_image,
                        'history' => $manual->ManualOverridesHistory,
                        'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                        'is_super_admin' => isset($vall->is_super_admin) ? $vall->is_super_admin : null,
                        'is_manager' => isset($vall->is_manager) ? $vall->is_manager : null,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                    ];
                }
            }
        }
        // MANUAL OVERRIDES END

        // STACK OVERRIDES START
        // $positionOverrideStack = PositionOverride::where(['position_id' => $subPosition, 'override_id' => '4', 'status' => '1'])->first();
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $userStack = $userOverride->office_stack_overrides_amount;
        if ($userStack && $stackSystemSetting) {
            $office_id = $user->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }
            $userIdArr1 = [$office_id];
            $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $date);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $stacks = UserOverrideHistory::select(
                'users.id',
                // 'override_status.status',
                'users.position_id',
                'users.recruiter_id',
                'users.sub_position_id',
                'users.first_name',
                'users.last_name',
                'users.image',
                'users.is_super_admin',
                'users.is_manager',
                DB::raw('MAX(override_status.effective_date) as latest_effective_date')
            )
                ->whereIn('user_override_history.id', $results->pluck('id'))
                ->join('users', 'users.id', 'user_override_history.user_id')
                // ->leftJoin('override_status', function ($join) use ($user) {
                //     $join->on('override_status.user_id', 'users.id')->where('override_status.recruiter_id', $user->id)->where('override_status.type', 'Stack')->whereNotNull('override_status.effective_date')->where('override_status.effective_date','<=',date('Y-m-d'))->orderBy('override_status.effective_date','DESC');
                // })
                ->whereIn('users.office_id', $userIdArr)
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->where('users.dismiss', 0)->get();

            $userIds = $stacks->pluck('id');
            $totalKwSubQuery = DB::table('sale_masters as ism')
                ->selectRaw('SUM(ism.kw)')
                ->whereIn('ism.pid', function ($query) {
                    $query->select('iuo.pid')
                        ->from('user_overrides as iuo')
                        ->whereColumn('iuo.user_id', 'user_overrides.user_id')
                        ->where('iuo.type', 'Stack')
                        ->whereColumn('iuo.sale_user_id', 'user_overrides.sale_user_id');
                });
            $totalAccountStack = UserOverrides::selectRaw('SUM(user_overrides.amount) as amount, COUNT(user_overrides.id) as count, user_overrides.sale_user_id')
                ->selectSub($totalKwSubQuery, 'totalKw')
                ->where(['user_overrides.user_id' => $user->id, 'user_overrides.type' => 'Stack'])->whereIn('user_overrides.sale_user_id', $userIds)
                ->groupBy('user_overrides.user_id', 'user_overrides.sale_user_id', 'user_overrides.type')->get();
            foreach ($stacks as $stack) {
                $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $stack->id)->where('recruiter_id', $user->id)->where('type', 'Stack')->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $stack->id)->where('recruiter_id', $user->id)->where('type', 'Stack')->orderBy('effective_date', 'DESC')->first();
                $overrideCount = $totalAccountStack->where('sale_user_id', $stack->id)->sum('count') ?? 0;
                $overridesTotal = $totalAccountStack->where('sale_user_id', $stack->id)->sum('amount') ?? 0;
                $kwTotal = $totalAccountStack->where('sale_user_id', $stack->id)->sum('totalKw') ?? 0;
                if (isset($stack->image) && $stack->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$stack->image);
                } else {
                    $s3_image = null;
                }

                $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $stack->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $stackOverrides[] = [
                    'id' => $stack->id,
                    'recruiter_id' => null,
                    'recruiter_name' => null,
                    'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                    'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                    'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                    'first_name' => $stack->first_name,
                    'last_name' => $stack->last_name,
                    // 'status' => $stack->status ? $stack->status : 0,
                    'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                    'override' => $userStack,
                    'override_type' => 'per sale',
                    'totalOverrides' => $overridesTotal,
                    'account' => $overrideCount,
                    'kwInstalled' => $kwTotal,
                    'image' => $stack->image,
                    'image_s3' => $s3_image,
                    'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                    'is_super_admin' => isset($stack->is_super_admin) ? $stack->is_super_admin : null,
                    'is_manager' => isset($stack->is_manager) ? $stack->is_manager : null,
                    'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                ];
            }
        }
        // STACK OVERRIDES END

        if (isset($user['image']) && $user['image'] != null) {
            $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$user['image']);
        } else {
            $s3_image = null;
        }
        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['image_s3'] = $s3_image;
        $data['totalDirects'] = count($directOverrides);
        $data['totalIndirect'] = count($inDirectOverrides);
        $data['totalOffice'] = count($offices);
        $data['totalmanual'] = count($manualOverrides);
        $data['totalStack'] = count($stackOverrides);
        $data['direct'] = $directOverrides;
        $data['indirect'] = $inDirectOverrides;
        $data['office'] = $officeOverrides;
        $data['manual'] = $manualOverrides;
        $data['stack'] = $stackOverrides;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function mysale_overrides_old($id): JsonResponse
    {
        $user = User::where('id', $id)->first();
        $recruiter_id_data = User::where('id', $id)->first();
        if ($recruiter_id_data->recruiter_id) {

            if (! empty($recruiter_id_data->additional_recruiter_id1) && empty($recruiter_id_data->additional_recruiter_id2)) {

                $recruiter_ids = $recruiter_id_data->recruiter_id.','.$recruiter_id_data->additional_recruiter_id1;

            } elseif (! empty($recruiter_id_data->additional_recruiter_id1) && ! empty($recruiter_id_data->additional_recruiter_id2)) {
                $recruiter_ids = $recruiter_id_data->recruiter_id.','.$recruiter_id_data->additional_recruiter_id1.','.$recruiter_id_data->additional_recruiter_id2;
            } else {
                $recruiter_ids = $recruiter_id_data->recruiter_id;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'direct_overrides_amount', 'direct_overrides_type')->with('recruiter')->whereIn('id', $idsArr)->get();

            $direct = [];
            $indirect = [];

            if (count($directs) > 0) {
                foreach ($directs as $key => $value) {

                    $totalOverrideDirect = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $value->id, 'sale_user_id' => $id])
                        ->where('type', 'Direct')
                        ->first();

                    $totalAccountDirect = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $id])->where('type', 'direct')->count();

                    // $directsAmount = UserOverrides::where(['user_id'=> $value->id,'type'=> 'Direct'])->sum('amount');
                    $direct[] = [
                        'id' => $value->id,
                        'recruiter_id' => $value->recruiter_id,
                        'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                        'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                        'first_name' => $value->first_name,
                        'last_name' => $value->last_name,
                        'image' => $value->image,
                        // 'override'=>$value->direct_overrides_amount.'/'.$value->direct_overrides_type,
                        'override' => $user->direct_overrides_amount.'/'.$user->direct_overrides_type,
                        'totalOverrides' => $totalOverrideDirect->overridesTotal,
                        'account' => $totalAccountDirect,
                        'kwInstalled' => $totalOverrideDirect->totalKw,
                    ];
                    $indirect_recruiter = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id')->with('positionDetail')->where('id', $value->id)->first();
                    if ($indirect_recruiter->recruiter_id) {

                        if (! empty($indirect_recruiter->additional_recruiter_id1) && empty($indirect_recruiter->additional_recruiter_id2)) {

                            $recruiter_ids = $indirect_recruiter->recruiter_id.','.$indirect_recruiter->additional_recruiter_id1;

                        } elseif (! empty($indirect_recruiter->additional_recruiter_id1) && ! empty($indirect_recruiter->additional_recruiter_id2)) {
                            $recruiter_ids = $indirect_recruiter->recruiter_id.','.$indirect_recruiter->additional_recruiter_id1.','.$indirect_recruiter->additional_recruiter_id2;
                        } else {
                            $recruiter_ids = $indirect_recruiter->recruiter_id;
                        }
                        $idsArr = explode(',', $recruiter_ids);
                        // dd($idsArr);die;
                        $additional = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'indirect_overrides_amount', 'indirect_overrides_type')->with('positionDetail', 'recruiter')->whereIn('id', $idsArr)->get();

                        $additionals = [];
                        if (count($additional) > 0) {
                            foreach ($additional as $key1 => $val) {
                                $totalOverrideDirect = DB::table('user_overrides')
                                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                                    ->where(['user_id' => $val->id, 'sale_user_id' => $id])
                                    ->where('type', 'Indirect')
                                    ->first();

                                $totalAccountDirect = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $id])->where('type', 'Indirect')->count();
                                $additionals = [
                                    'id' => $val->id,
                                    'recruiter_id' => $val->recruiter_id,
                                    'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                                    'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                                    'first_name' => $val->first_name,
                                    'last_name' => $val->last_name,
                                    'image' => $val->image,
                                    // 'override'=>$val->indirect_overrides_amount.'/'.$val->indirect_overrides_type,
                                    'override' => $user->indirect_overrides_amount.'/'.$user->indirect_overrides_type,
                                    'totalOverrides' => $totalOverrideDirect->overridesTotal,
                                    'account' => $totalAccountDirect,
                                    'kwInstalled' => $totalOverrideDirect->totalKw,

                                ];
                                $indirect[] = $additionals;

                            }
                            // indirect 2
                            $indirect_recruiter2 = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id')->with('positionDetail')->where('id', $val->id)->first();
                            if ($indirect_recruiter2->recruiter_id) {

                                if (! empty($indirect_recruiter2->additional_recruiter_id1) && empty($indirect_recruiter2->additional_recruiter_id2)) {

                                    $recruiter_ids = $indirect_recruiter2->recruiter_id.','.$indirect_recruiter2->additional_recruiter_id1;

                                } elseif (! empty($indirect_recruiter2->additional_recruiter_id1) && ! empty($indirect_recruiter2->additional_recruiter_id2)) {
                                    $recruiter_ids = $indirect_recruiter2->recruiter_id.','.$indirect_recruiter2->additional_recruiter_id1.','.$indirect_recruiter2->additional_recruiter_id2;
                                } else {
                                    $recruiter_ids = $indirect_recruiter2->recruiter_id;
                                }
                                $idsArr = explode(',', $recruiter_ids);
                                $additionalindirect2 = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'indirect_overrides_amount', 'indirect_overrides_type')->with('recruiter')->whereIn('id', $idsArr)->get();

                                $indirect2 = [];
                                if (count($additionalindirect2) > 0) {
                                    foreach ($additionalindirect2 as $key1 => $val) {

                                        $totalOverrideDirect = DB::table('user_overrides')
                                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                                            ->where(['user_id' => $val->id, 'sale_user_id' => $id])
                                            ->where('type', 'Indirect')
                                            ->first();

                                        $totalAccountDirect = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $id])->where('type', 'Indirect')->count();

                                        $indirect2 = [
                                            'id' => $val->id,
                                            'recruiter_id' => $val->recruiter_id,
                                            'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                                            'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                                            'first_name' => $val->first_name,
                                            'last_name' => $val->last_name,
                                            'image' => $val->image,
                                            // 'override'=>$val->indirect_overrides_amount.'/'.$val->indirect_overrides_type,
                                            'override' => $user->indirect_overrides_amount.'/'.$user->indirect_overrides_type,
                                            'totalOverrides' => $totalOverrideDirect->overridesTotal,
                                            'account' => $totalAccountDirect,
                                            'kwInstalled' => $totalOverrideDirect->totalKw,

                                        ];
                                        $indirect[] = $indirect2;

                                    }
                                }
                            }
                        }
                    } else {
                        $indirect = [];
                    }

                }
            }

            $additionals1 = [];
            $officeUsers = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('office_id', $user->office_id)->get();
            $officeCount = User::with('positionDetail', 'recruiter')->where('office_id', $user->office_id)->count();

            if (count($officeUsers) > 0) {
                foreach ($officeUsers as $key2 => $vall) {

                    $totalOverrideOffice = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $user->id, 'sale_user_id' => $vall->id])
                        ->where('type', 'Office')
                        ->first();

                    $totalAccountOffice = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $vall->id])->where('type', 'Office')->count();
                    // dd($totalAccountOffice)  ;die;
                    $additionals1 = [
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                        'override' => $vall->office_overrides_amount.'/'.$vall->office_overrides_type,
                        'totalOverrides' => $totalOverrideOffice->overridesTotal,
                        'account' => $totalAccountOffice,
                        'kwInstalled' => $totalOverrideOffice->totalKw,
                        'image' => $vall->image,

                    ];
                    $office[] = $additionals1;
                    $totalOffice = $officeCount;

                }

            }

            $manualData = ManualOverrides::where('manual_user_id', $user->id)->get();
            $manualCount = ManualOverrides::where('manual_user_id', $user->id)->count();

            $manualOverride = [];
            if ($manualCount > 0) {
                foreach ($manualData as $key3 => $manual) {

                    $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('id', $manual->user_id)->first();

                    $totalAccountManual = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $manual->user_id])->where('type', 'Manual')->count();

                    $totalOverrideManual = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $user->id, 'sale_user_id' => $manual->user_id])
                        ->where('type', 'Manual')
                        ->first();
                    $manualOverride[] = [
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                        'override' => $manualData[$key3]->overrides_amount.'/'.$manualData[$key3]->overrides_type,

                        'totalOverrides' => $totalOverrideManual->overridesTotal,
                        'account' => $totalAccountManual,
                        'image' => $vall->image,

                    ];

                    $totalmanual = $manualCount;

                }

            }

            $data['id'] = $user['id'];
            $data['first_name'] = $user['first_name'];
            $data['last_name'] = $user['last_name'];
            $data['image'] = $user['image'];
            $data['direct'] = $direct;
            $data['indirect'] = $indirect;
            $data['office'] = $office;
            $data['manual'] = $manualOverride;

            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {

            $data['id'] = $user['id'];
            $data['first_name'] = $user['first_name'];
            $data['last_name'] = $user['last_name'];
            $data['image'] = $user['image'];
            $data['direct'] = [];
            $data['indirect'] = [];
            $data['office'] = [];
            $data['manual'] = [];

            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }

    }

    public function get_mysale_overrides_old($id): JsonResponse
    {
        $data = ManualOverrides::with('user', 'manualUser', 'ManualOverridesHistory')->where('id', $id)->first();

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function get_mysale_overrides(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'recruiter_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        if ($request->type == 'Manual') {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
        }
        $data = ManualOverrides::with('user', 'manualUser', 'ManualOverridesHistory')->where('id', $request->id)->first();

        $override_status_history = OverrideStatus::with('updated_by:id,first_name,last_name,image,is_manager,is_super_admin,position_id,sub_position_id')->where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('type', $request->type)->orderBy('effective_date', 'ASC')->get();
        $override_since = OverrideStatus::where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('type', $request->type)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();

        $user = User::select('first_name', 'last_name')->where('id', $request->user_id)->first();
        $recruiter = User::select('first_name', 'last_name')->where('id', $request->recruiter_id)->first();
        $current_status = 'Enabled';
        $status_since = null;
        if ($override_since) {
            if ($override_since->status == 1) {
                $current_status = 'Disabled';
                $status_since = $override_since->effective_date;
            } elseif ($override_since->status == 0) {
                $current_status = 'Enabled';
                $status_since = $override_since->effective_date;
            }
        }

        // Process the override status history to include 'old_status'
        $override_status_history_with_old_status = [];
        $previous_status = null;
        $start_date = null;
        $start_date = User::where('id', $request->user_id)->first('period_of_agreement_start_date');

        foreach ($override_status_history as $status) {
            if ($status->status == 1) {
                $status->status = 'Disable';
            } else {
                $status->status = 'Enable';
            }
            $status_with_old = $status->toArray();
            $status_with_old['old_status'] = $previous_status;
            $status_with_old['start_date'] = $start_date->period_of_agreement_start_date;
            $override_status_history_with_old_status[] = $status_with_old;
            $previous_status = $status->status;
        }

        // Determine the sort direction (default to 'asc' if not provided)
        // $sortDirection = $request->input('sort_direction', 'asc');

        // // Sort the processed history according to the requested sort direction
        // usort($override_status_history_with_old_status, function($a, $b) use ($sortDirection) {
        //     if ($sortDirection === 'asc') {
        //         return strcmp($a['effective_date'], $b['effective_date']);
        //     } else {
        //         return strcmp($b['effective_date'], $a['effective_date']);
        //     }
        // });

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => [
                'status_since' => $status_since,
                'current_status' => $current_status,
                'override_status' => [
                    'user' => $user,
                    'recruiter' => $recruiter,
                ],
                'data' => $data,
                'override_status_history' => $override_status_history_with_old_status,
            ],
        ], 200);
    }

    public function mysale_overrides($id, Request $request): JsonResponse
    {
        $search = $request->input('search');
        $user = User::where('id', $id)->where('dismiss', 0)->first();
        $recruiter_id_data = User::where('id', $id)->where('dismiss', 0)->first();
        $direct = [];
        $indirect = [];
        $current_date = date('Y-m-d');

        if ($recruiter_id_data->recruiter_id) {

            if (! empty($recruiter_id_data->additional_recruiter_id1) && empty($recruiter_id_data->additional_recruiter_id2)) {

                $recruiter_ids = $recruiter_id_data->recruiter_id.','.$recruiter_id_data->additional_recruiter_id1;

            } elseif (! empty($recruiter_id_data->additional_recruiter_id1) && ! empty($recruiter_id_data->additional_recruiter_id2)) {
                $recruiter_ids = $recruiter_id_data->recruiter_id.','.$recruiter_id_data->additional_recruiter_id1.','.$recruiter_id_data->additional_recruiter_id2;
            } else {
                $recruiter_ids = $recruiter_id_data->recruiter_id;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'direct_overrides_amount', 'direct_overrides_type', 'period_of_agreement_start_date')->with('recruiter', 'override_status', 'subpositionDetail')
                ->where(function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->whereIn('id', $idsArr)->get();

            if (count($directs) > 0) {
                foreach ($directs as $key => $value) {
                    $positionOverride = PositionOverride::where('position_id', $value->sub_position_id)->where('override_id', '1')->where('status', 1)->first();
                    if ($positionOverride) {
                        // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$value->id)->where('type','Direct')->first();
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $value->id)->where('type', 'Direct')->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $id)->where('recruiter_id', $value->id)->where('type', 'Direct')->orderBy('effective_date', 'DESC')->first();

                        $totalOverrideDirect = DB::table('user_overrides')
                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                            ->where(['user_id' => $value->id, 'sale_user_id' => $id])
                            ->where('type', 'Direct')
                            ->first();

                        $totalAccountDirect = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $id])->where('type', 'direct')->count();

                        $direct[] = [
                            'id' => $value->id,
                            'recruiter_id' => $value->recruiter_id,
                            'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                            'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                            'sub_position_id' => isset($value->sub_position_id) ? $value->sub_position_id : null,
                            'sub_position_name' => isset($value->subpositionDetail->position_name) ? $value->subpositionDetail->position_name : null,
                            'first_name' => $value->first_name,
                            'last_name' => $value->last_name,
                            'image' => $value->image,
                            // 'override'=>$value->direct_overrides_amount.'/'.$value->direct_overrides_type,
                            'override' => $value->direct_overrides_amount,
                            'override_type' => $value->direct_overrides_type,
                            'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                            'totalOverrides' => $totalOverrideDirect->overridesTotal,
                            'account' => $totalAccountDirect,
                            'kwInstalled' => $totalOverrideDirect->totalKw,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'start' => $value->period_of_agreement_start_date,
                        ];

                        $indirect_recruiter = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id')->with('positionDetail', 'subpositionDetail')->where('id', $value->id)->first();
                        if ($indirect_recruiter->recruiter_id) {

                            if (! empty($indirect_recruiter->additional_recruiter_id1) && empty($indirect_recruiter->additional_recruiter_id2)) {

                                $recruiter_ids = $indirect_recruiter->recruiter_id.','.$indirect_recruiter->additional_recruiter_id1;

                            } elseif (! empty($indirect_recruiter->additional_recruiter_id1) && ! empty($indirect_recruiter->additional_recruiter_id2)) {
                                $recruiter_ids = $indirect_recruiter->recruiter_id.','.$indirect_recruiter->additional_recruiter_id1.','.$indirect_recruiter->additional_recruiter_id2;
                            } else {
                                $recruiter_ids = $indirect_recruiter->recruiter_id;
                            }
                            $idsArr = explode(',', $recruiter_ids);
                            // dd($idsArr);die;
                            $additional = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'additional_recruiter_id1', 'additional_recruiter_id2', 'indirect_overrides_amount', 'indirect_overrides_type', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')->whereIn('id', $idsArr)->get();

                            $additionals = [];
                            if (count($additional) > 0) {
                                foreach ($additional as $key1 => $val) {
                                    $positionOverride2 = PositionOverride::where('position_id', $val->sub_position_id)->where('override_id', '2')->where('status', 1)->first();
                                    if ($positionOverride2) {
                                        // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$val->id)->where('type','Indirect')->first();
                                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $val->id)->where('type', 'Indirect')->orderBy('effective_date', 'DESC')->first();
                                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $id)->where('recruiter_id', $val->id)->where('type', 'Indirect')->orderBy('effective_date', 'DESC')->first();

                                        $totalOverrideDirect = DB::table('user_overrides')
                                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                                            ->where(['user_id' => $val->id, 'sale_user_id' => $id])
                                            ->where('type', 'Indirect')
                                            ->first();

                                        $totalAccountDirect = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $id])->where('type', 'Indirect')->count();

                                        $additionals = [
                                            'id' => $val->id,
                                            'recruiter_id' => $val->recruiter_id,
                                            'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                                            'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                                            'sub_position_id' => isset($val->sub_position_id) ? $val->sub_position_id : null,
                                            'sub_position_name' => isset($val->subpositionDetail->position_name) ? $val->subpositionDetail->position_name : null,
                                            'first_name' => $val->first_name,
                                            'last_name' => $val->last_name,
                                            'image' => $val->image,
                                            'override' => $val->indirect_overrides_amount,
                                            'override_type' => $val->indirect_overrides_type,
                                            'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                                            'totalOverrides' => $totalOverrideDirect->overridesTotal,
                                            'account' => $totalAccountDirect,
                                            'kwInstalled' => $totalOverrideDirect->totalKw,
                                            'last_verride_Status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                                            'start_date' => $val->period_of_agreement_start_date,

                                        ];
                                        $indirect[] = $additionals;

                                    }

                                }
                                // indirect 2
                                // $indirect_recruiter2 = User::select('id','recruiter_id','first_name','last_name','image','position_id','sub_position_id')->with('positionDetail','subpositionDetail')->where('id',$val->id)->first();
                                // if($indirect_recruiter2->recruiter_id){

                                //     if(!empty($indirect_recruiter2->additional_recruiter_id1) && empty($indirect_recruiter2->additional_recruiter_id2)){

                                //         $recruiter_ids= $indirect_recruiter2->recruiter_id.','.$indirect_recruiter2->additional_recruiter_id1;

                                //     }elseif (!empty($indirect_recruiter2->additional_recruiter_id1) && !empty($indirect_recruiter2->additional_recruiter_id2)) {
                                //         $recruiter_ids = $indirect_recruiter2->recruiter_id.','.$indirect_recruiter2->additional_recruiter_id1.','.$indirect_recruiter2->additional_recruiter_id2;
                                //     }else{
                                //         $recruiter_ids = $indirect_recruiter2->recruiter_id;
                                //     }
                                //     $idsArr = explode(',',$recruiter_ids);
                                //     $additionalindirect2 = User::select('id','recruiter_id','first_name','last_name','image','position_id','sub_position_id','additional_recruiter_id1','additional_recruiter_id2','indirect_overrides_amount','indirect_overrides_type')->with('recruiter','override_status','subpositionDetail')->whereIn('id',$idsArr)->get();

                                //     $indirect2 = [];
                                //     if (count($additionalindirect2) > 0) {
                                //         foreach ($additionalindirect2 as $key1 => $val) {
                                //             $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$val->id)->where('type','Indirect')->first();
                                //             $totalOverrideDirect = DB::table('user_overrides')
                                //                 ->select('user_id',DB::raw('SUM(user_overrides.amount) AS overridesTotal'),DB::raw('SUM(user_overrides.kw) AS totalKw'))
                                //                 ->where(['user_id'=>$val->id, 'sale_user_id' => $id])
                                //                 ->where('type','Indirect')
                                //                 ->first();

                                //             $totalAccountDirect = UserOverrides::where(['user_id'=>$val->id, 'sale_user_id' => $id])->where('type','Indirect')->count();

                                //                 $indirect2 = [
                                //                     'id' => $val->id,
                                //                     'recruiter_id' => $val->recruiter_id,
                                //                     'recruiter_name' => isset($value->recruiter->first_name)?$value->recruiter->first_name:null,
                                //                     'position' => isset($val->positionDetail->position_name)?$val->positionDetail->position_name:null,
                                //                     'sub_position_id'  => isset($val->sub_position_id) ? $val->sub_position_id : null,
                                //                     'sub_position_name'  => isset($val->subpositionDetail->position_name) ? $val->subpositionDetail->position_name : null,
                                //                     'first_name' => $val->first_name,
                                //                     'last_name' => $val->last_name,
                                //                     'image' => $val->image,
                                //                     // 'override'=>$val->indirect_overrides_amount.'/'.$val->indirect_overrides_type,
                                //                     'override'=>$val->indirect_overrides_amount,
                                //                     'override_type'=>$val->indirect_overrides_type,
                                //                     'status' => isset($overrideStatus)?$overrideStatus->status:0,
                                //                     'totalOverrides'=>$totalOverrideDirect->overridesTotal,
                                //                     'account'=>$totalAccountDirect,
                                //                     'kwInstalled'=>$totalOverrideDirect->totalKw,

                                //                 ];
                                //                 // $indirect[] = $indirect2;

                                //         }
                                //     }
                                // }
                            }
                        }
                    }

                }
            }
        }

        $userIdArr1 = User::where('office_id', $user->office_id)->where('id', '<>', $id)->pluck('id')->toArray();
        $userIdArr2 = AdditionalLocations::where('office_id', $user->office_id)->where('user_id', '<>', $id)->pluck('id')->toArray();
        $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

        // $officeUsers = User::select('id','recruiter_id','first_name','last_name','image','position_id','sub_position_id','office_overrides_amount','office_overrides_type')->with('positionDetail','recruiter','override_status','subpositionDetail')->whereIn('id',$userIdArr)->get();
        // $officeCount = User::with('positionDetail','recruiter')->whereIn('id',$userIdArr)->count();
        $office = [];
        if (count($userIdArr1) > 0) {
            $additionals1 = [];
            $officeUsers = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                ->where(function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->whereIn('id', $userIdArr1)->get();
            $officeCount = count($officeUsers); // User::with('positionDetail','recruiter')->whereIn('id',$userIdArr1)->count();
            if (! empty($officeUsers)) {
                foreach ($officeUsers as $key2 => $vall) {
                    // $positionOverride = PositionOverride::where('position_id',$vall->sub_position_id)->orderby('id','desc')->first();
                    $positionOverride = PositionOverride::where('position_id', $vall->sub_position_id)->where('override_id', '3')->first();

                    if (! empty($positionOverride) && $positionOverride->status == 1) {
                        // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$vall->id)->where('type','Office')->first();
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();

                        $totalOverrideOffice = DB::table('user_overrides')
                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                            ->where(['user_id' => $vall->id, 'sale_user_id' => $id])
                            ->where('type', 'Office')
                            ->first();

                        $totalAccountOffice = UserOverrides::where(['user_id' => $vall->id, 'sale_user_id' => $id])->where('type', 'Office')->count();

                        $additionals1 = [
                            'id' => $vall->id,
                            'recruiter_id' => $vall->recruiter_id,
                            'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                            'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                            'sub_position_id' => isset($vall->sub_position_id) ? $vall->sub_position_id : null,
                            'sub_position_name' => isset($vall->subpositionDetail->position_name) ? $vall->subpositionDetail->position_name : null,
                            'first_name' => $vall->first_name,
                            'last_name' => $vall->last_name,
                            'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                            // 'override'=>$vall->office_overrides_amount .'/'. $vall->office_overrides_type,
                            'override' => $vall->office_overrides_amount,
                            'override_type' => $vall->office_overrides_type,
                            'totalOverrides' => $totalOverrideOffice->overridesTotal,
                            'account' => $totalAccountOffice,
                            'kwInstalled' => $totalOverrideOffice->totalKw,
                            'image' => $vall->image,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'start_date' => $vall->period_of_agreement_start_date,

                        ];
                        $office[] = $additionals1;
                        $totalOffice = $officeCount;

                    }
                }
            }

        }

        if (count($userIdArr2) > 0) {
            $additionals2 = [];
            $officeUsers2 = AdditionalLocations::whereIn('id', $userIdArr2)->get();
            $officeCount2 = AdditionalLocations::whereIn('id', $userIdArr2)->count();
            foreach ($officeUsers2 as $key2 => $addval) {
                $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                    ->where(function ($query) use ($search) {
                        $query->where('first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                    })
                    ->where('id', $addval->user_id)->first();

                if (! empty($vall)) {
                    // $positionOverride = PositionOverride::where('position_id',$vall->sub_position_id)->orderby('id','desc')->first();
                    $positionOverride = PositionOverride::where('position_id', $vall->sub_position_id)->where('override_id', '3')->first();

                    if (! empty($positionOverride) && $positionOverride->status == 1) {
                        // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$vall->id)->where('type','Office')->first();
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Office')->orderBy('effective_date', 'DESC')->first();

                        $totalOverrideOffice = DB::table('user_overrides')
                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                            ->where(['user_id' => $vall->id, 'sale_user_id' => $id])
                            ->where('type', 'Office')
                            ->first();

                        $totalAccountOffice = UserOverrides::where(['user_id' => $vall->id, 'sale_user_id' => $id])->where('type', 'Office')->count();

                        $additionals2 = [
                            'id' => $vall->id,
                            'recruiter_id' => $vall->recruiter_id,
                            'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                            'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                            'sub_position_id' => isset($vall->sub_position_id) ? $vall->sub_position_id : null,
                            'sub_position_name' => isset($vall->subpositionDetail->position_name) ? $vall->subpositionDetail->position_name : null,
                            'first_name' => $vall->first_name,
                            'last_name' => $vall->last_name,
                            'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                            // 'override'=>$vall->office_overrides_amount .'/'. $vall->office_overrides_type,
                            'override' => $addval->overrides_amount,
                            'override_type' => $addval->overrides_type,
                            'totalOverrides' => $totalOverrideOffice->overridesTotal,
                            'account' => $totalAccountOffice,
                            'kwInstalled' => $totalOverrideOffice->totalKw,
                            'image' => $vall->image,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'start_date' => $vall->period_of_agreement_start_date,

                        ];
                        $office[] = $additionals2;
                        // $totalOffice = ($officeCount + $officeCount2);
                    }

                }
            }

        }

        $manualData = ManualOverrides::where('manual_user_id', $user->id)->with('ManualOverridesHistory')->get();
        $manualCount = ManualOverrides::where('manual_user_id', $user->id)->count();
        $manualOverride = [];
        if ($manualCount > 0) {
            foreach ($manualData as $key3 => $manual) {
                // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$manual->user_id)->where('type','Manual')->first();
                $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $manual->user_id)->where('type', 'Manual')->orderBy('effective_date', 'DESC')->first();
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $id)->where('recruiter_id', $manual->user_id)->where('type', 'Manual')->orderBy('effective_date', 'DESC')->first();

                $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                    ->where(function ($query) use ($search) {
                        $query->where('first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                    })
                    ->where('id', $manual->user_id)->first();

                if (! empty($vall)) {
                    $totalAccountManual = UserOverrides::where(['user_id' => $manual->user_id, 'sale_user_id' => $user->id])->where('type', 'Manual')->count();

                    $totalOverrideManual = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $manual->user_id, 'sale_user_id' => $user->id])
                        ->where('type', 'Manual')
                        ->first();
                    $manualOverride[] = [
                        'manual_overrides_id' => $manual->id,
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'manual_user_id' => $manual->manual_user_id,
                        'user_id' => $manual->user_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'sub_position_id' => isset($vall->sub_position_id) ? $vall->sub_position_id : null,
                        'sub_position_name' => isset($vall->subpositionDetail->position_name) ? $vall->subpositionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        // 'override'=>$manualData[$key3]->overrides_amount .'/'. $manualData[$key3]->overrides_type,
                        'override' => $manualData[$key3]->overrides_amount,
                        'override_type' => $manualData[$key3]->overrides_type,
                        'overrides_amount' => $manual->overrides_amount,
                        'overrides_type' => $manual->overrides_type,
                        'effective_date' => $manual->effective_date,
                        'totalOverrides' => $totalOverrideManual->overridesTotal,
                        'kwInstalled' => $totalOverrideManual->totalKw,
                        'account' => $totalAccountManual,
                        'image' => $vall->image,
                        'history' => $manual->ManualOverridesHistory,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'start_date' => $vall->period_of_agreement_start_date,

                    ];
                }
                $totalmanual = $manualCount;

            }

        }

        // stack override
        $userIdStack1 = User::where('office_id', $user->office_id)->where('id', '<>', $id)->whereNotNull('office_stack_overrides_amount')->pluck('id')->toArray();
        $additionalUserId = AdditionalLocations::where('office_id', $user->office_id)->where('user_id', '<>', $id)->pluck('user_id')->toArray();
        $userIdStack2 = User::whereIn('id', $additionalUserId)->whereNotNull('office_stack_overrides_amount')->pluck('id')->toArray();
        $stack = [];
        if (count($userIdStack1) > 0) {
            $additionals1 = [];
            $officeUsers = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'office_stack_overrides_amount', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                ->where(function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->whereIn('id', $userIdStack1)->get();
            foreach ($officeUsers as $key2 => $vall) {
                // $positionOverride = PositionOverride::where('position_id',$vall->sub_position_id)->orderby('id','desc')->first();
                $positionOverride = PositionOverride::where('position_id', $vall->sub_position_id)->where('override_id', '4')->first();

                if (! empty($positionOverride) && $positionOverride->status == 1) {
                    // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$vall->id)->where('type','Stack')->first();
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Stack')->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Stack')->orderBy('effective_date', 'DESC')->first();

                    $totalOverrideOffice = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $vall->id, 'sale_user_id' => $id])
                        ->whereIn('type', ['Stack'])
                        ->first();

                    $totalAccountOffice = UserOverrides::where(['user_id' => $vall->id, 'sale_user_id' => $id])->whereIn('type', ['Stack'])->count();

                    $additionals1 = [
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'sub_position_id' => isset($vall->sub_position_id) ? $vall->sub_position_id : null,
                        'sub_position_name' => isset($vall->subpositionDetail->position_name) ? $vall->subpositionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $vall->office_stack_overrides_amount,
                        'override_type' => 'per sale',
                        'totalOverrides' => $totalOverrideOffice->overridesTotal,
                        'account' => $totalAccountOffice,
                        'kwInstalled' => $totalOverrideOffice->totalKw,
                        'image' => $vall->image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'start_date' => $vall->period_of_agreement_start_date,

                    ];
                    $stack[] = $additionals1;

                }
            }

        }

        if (count($userIdStack2) > 0) {
            $additionals2 = [];
            $officeUsers2 = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'office_stack_overrides_amount', 'period_of_agreement_start_date')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                ->where(function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                })
                ->whereIn('id', $userIdStack2)->get();
            foreach ($officeUsers2 as $key2 => $vall) {

                $positionOverride = PositionOverride::where('position_id', $vall->sub_position_id)->where('override_id', '4')->first();

                if (! empty($positionOverride) && $positionOverride->status == 1) {
                    // $overrideStatus = OverrideStatus::where('user_id',$id)->where('recruiter_id',$vall->id)->where('type','Stack')->first();
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Stack')->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('effective_date', '<=', $current_date)->where('user_id', $id)->where('recruiter_id', $vall->id)->where('type', 'Stack')->orderBy('effective_date', 'DESC')->first();

                    $totalOverrideOffice = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $vall->id, 'sale_user_id' => $id])
                        ->whereIn('type', ['Stack'])
                        ->first();

                    $totalAccountOffice = UserOverrides::where(['user_id' => $vall->id, 'sale_user_id' => $id])->whereIn('type', ['Stack'])->count();

                    $additionals2 = [
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'sub_position_id' => isset($vall->sub_position_id) ? $vall->sub_position_id : null,
                        'sub_position_name' => isset($vall->subpositionDetail->position_name) ? $vall->subpositionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $vall->office_stack_overrides_amount,
                        'override_type' => 'per sale',
                        'totalOverrides' => $totalOverrideOffice->overridesTotal,
                        'account' => $totalAccountOffice,
                        'kwInstalled' => $totalOverrideOffice->totalKw,
                        'image' => $vall->image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'start_date' => $vall->period_of_agreement_start_date,

                    ];
                    $stack[] = $additionals2;

                }
            }

        }
        // end stack override

        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['direct'] = $direct;
        $data['indirect'] = $indirect;
        $data['office'] = $office;
        $data['manual'] = $manualOverride;
        $data['stack'] = $stack;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function OverridesEnableDisable_old(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'recruiter_id' => 'required',
            'user_id' => 'required',
            'type' => 'required',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Over ride status',
                'status' => false,
                'message' => $validator->errors(),
            ], 400);
        }
        $status = $request->status;
        $recruiter_id = $request->recruiter_id;
        $user_id = $request->user_id;
        $type = $request->type;
        $effective_date = $request->effective_date;
        //   $over_ride = OverrideStatus::where('user_id',$user_id)->where('recruiter_id',$recruiter_id)->where('type',$type)->first();
        $over_ride = OverrideStatus::where('user_id', $user_id)->where('recruiter_id', $recruiter_id)->where('type', $type)->whereNotNull('effective_date')->orderBy('effective_date', 'DESC')->first();

        if ($over_ride) {

            if ($over_ride->effective_date > date('Y-m-d')) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Cannot add more then one override status for future date.',
                ], 400);
            }
            if ($over_ride->effective_date >= $effective_date) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'effective date must be greater than  '.$over_ride->effective_date,
                ], 400);
            }

            if ($over_ride->status == 1 && $status == 1) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already disabled',
                ], 400);
            }
            if ($over_ride->status == 0 && $status == 0) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already enabled',
                ], 400);
            }
        }
        $over_ride = OverrideStatus::updateOrCreate([
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'type' => $type,
            'effective_date' => $effective_date,
        ], [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'status' => $status,
            'type' => $type,
            'effective_date' => $effective_date,
            'updated_by' => Auth::user()->id,
        ]);

        return response()->json([
            'ApiName' => 'Over ride status',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

        //   if(isset($over_ride)){
        //     //   $over_ride->status = $status;
        //     //   $over_ride->update();

        //       $over_ride = OverrideStatus::find($over_ride->id)->delete();

        //     return response()->json([
        //         'ApiName' => 'Over ride status',
        //         'status' => true,
        //         'message' => 'Successfully.'
        //     ], 200);
        //   }else{
        //         $over_ride =   OverrideStatus::create([
        //             'user_id' => $user_id,
        //             'recruiter_id' => $recruiter_id,
        //             'status' => $status,
        //             'type' => $type
        //         ]);
        //     return response()->json(['ApiName' => 'Over ride status','status' => true, 'message' => 'Successfully.'], 200);
        //   }

    }

    public function OverridesEnableDisable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'recruiter_id' => 'required',
            'user_id' => 'required',
            'type' => 'required',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Over ride status',
                'status' => false,
                'message' => $validator->errors(),
            ], 400);
        }
        $status = $request->status;
        $recruiter_id = $request->recruiter_id;
        $user_id = $request->user_id;
        $type = $request->type;
        $effective_date = $request->effective_date;
        //   $over_ride = OverrideStatus::where('user_id',$user_id)->where('recruiter_id',$recruiter_id)->where('type',$type)->first();
        $over_ride = OverrideStatus::where('user_id', $user_id)->where('recruiter_id', $recruiter_id)->where('type', $type)->whereNotNull('effective_date')->orderBy('effective_date', 'DESC')->first();

        if ($over_ride) {

            if ($over_ride->effective_date > date('Y-m-d')) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Cannot add more then one override status for future date.',
                ], 400);
            }
            if ($over_ride->effective_date >= $effective_date) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'effective date must be greater than  '.$over_ride->effective_date,
                ], 400);
            }

            if ($over_ride->status == 1 && $status == 1) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already disabled',
                ], 400);
            }
            if ($over_ride->status == 0 && $status == 0) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already enabled',
                ], 400);
            }
        }
        $over_ride = OverrideStatus::updateOrCreate([
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'type' => $type,
            'effective_date' => $effective_date,
        ], [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'status' => $status,
            'type' => $type,
            'effective_date' => $effective_date,
            // 'updated_by' => Auth::user()->id,
            'updated_by' => auth()->user()->id,
        ]);

        return response()->json([
            'ApiName' => 'Over ride status',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

        //   if(isset($over_ride)){
        //     //   $over_ride->status = $status;
        //     //   $over_ride->update();

        //       $over_ride = OverrideStatus::find($over_ride->id)->delete();

        //     return response()->json([
        //         'ApiName' => 'Over ride status',
        //         'status' => true,
        //         'message' => 'Successfully.'
        //     ], 200);
        //   }else{
        //         $over_ride =   OverrideStatus::create([
        //             'user_id' => $user_id,
        //             'recruiter_id' => $recruiter_id,
        //             'status' => $status,
        //             'type' => $type
        //         ]);
        //     return response()->json(['ApiName' => 'Over ride status','status' => true, 'message' => 'Successfully.'], 200);
        //   }

    }

    public function delete_manual_overrides_old($id): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'is_override_status' => 'required',
            'recruiter_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        if ($request->type == 'Manual') {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
            if ($request->is_override_status == 0) {
                $OverridesHistory = ManualOverridesHistory::where('id', $request->id)->first();
                $manualuserid = $OverridesHistory->manual_user_id;
                if (! empty($OverridesHistory)) {
                    $old_overrides = ManualOverridesHistory::where('manual_user_id', $manualuserid)
                        ->where('effective_date', '<', $OverridesHistory->effective_date)
                        ->orderBy('effective_date', 'DESC')->first();
                    $next_overrides = ManualOverridesHistory::where('manual_user_id', $manualuserid)
                        ->where('effective_date', '>', $OverridesHistory->effective_date)
                        ->orderBy('effective_date', 'ASC')
                        ->first();
                    if (! empty($old_overrides) && ! empty($next_overrides)) {
                        $next_overrides->old_overrides_amount = $old_overrides->overrides_amount;
                        $next_overrides->old_overrides_type = $old_overrides->overrides_type;
                        $next_overrides->save();
                    } elseif (empty($old_overrides) && ! empty($next_overrides)) {
                        $next_overrides->old_overrides_amount = 0;
                        $next_overrides->old_overrides_type = null;
                        $next_overrides->save();
                    }
                    $deleteOverrides = ManualOverridesHistory::find($request->id)->delete();
                }
            }
        }
        if ($request->is_override_status == 1) {
            $override_status_history = OverrideStatus::where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('type', $request->type)->where('effective_date', '>', date('Y-m-d'))->delete();
        }

        return response()->json(['ApiName' => 'Delete manual overrides', 'status' => true, 'message' => 'Delete Successfully.'], 200);
    }

    public function delete_manual_overrides(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'is_override_status' => 'required',
            'recruiter_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        if ($request->type == 'Manual') {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
            if ($request->is_override_status == 0) {
                $OverridesHistory = ManualOverridesHistory::where('id', $request->id)->first();
                $manualuserid = $OverridesHistory->manual_user_id;
                if (! empty($OverridesHistory)) {
                    $old_overrides = ManualOverridesHistory::where('manual_user_id', $manualuserid)
                        ->where('effective_date', '<', $OverridesHistory->effective_date)
                        ->orderBy('effective_date', 'DESC')->first();
                    $next_overrides = ManualOverridesHistory::where('manual_user_id', $manualuserid)
                        ->where('effective_date', '>', $OverridesHistory->effective_date)
                        ->orderBy('effective_date', 'ASC')
                        ->first();
                    if (! empty($old_overrides) && ! empty($next_overrides)) {
                        $next_overrides->old_overrides_amount = $old_overrides->overrides_amount;
                        $next_overrides->old_overrides_type = $old_overrides->overrides_type;
                        $next_overrides->save();
                    } elseif (empty($old_overrides) && ! empty($next_overrides)) {
                        $next_overrides->old_overrides_amount = 0;
                        $next_overrides->old_overrides_type = null;
                        $next_overrides->save();
                    }
                    $deleteOverrides = ManualOverridesHistory::find($request->id)->delete();
                }
            }
        }
        if ($request->is_override_status == 1) {
            $override_status_history = OverrideStatus::where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('type', $request->type)->where('effective_date', '>', date('Y-m-d'))->delete();
        }

        return response()->json(['ApiName' => 'Delete manual overrides', 'status' => true, 'message' => 'Delete Successfully.'], 200);
    }

    public function manual_overrides(Request $request)
    {
        $data = [];
        // return $request;
        $user_id = $request->user_id;
        $manual_user_id = $request->manual_user_id;
        $overrides_amount = $request->overrides_amount;
        $overrides_type = $request->overrides_type;
        $effective_date = isset($request->effective_date) ? $request->effective_date : null;
        // if(isset($user_id) && $user_id!=null){
        //     ManualOverrides::where('manual_user_id',$user_id)->delete();
        //  }
        if (count($manual_user_id) > 0) {
            foreach ($manual_user_id as $key => $value) {
                $manualOverrides = ManualOverrides::where(['manual_user_id' => $user_id, 'user_id' => $value])->first();
                if (empty($manualOverrides)) {

                    $data['manual_user_id'] = $user_id;
                    $data['user_id'] = $value;
                    $data['overrides_amount'] = $overrides_amount;
                    $data['overrides_type'] = $overrides_type;
                    $data['effective_date'] = $effective_date;
                    $inserted = ManualOverrides::create($data);

                    $datas['manual_user_id'] = $user_id;
                    $datas['user_id'] = $value;
                    $datas['updated_by'] = Auth()->user()->id;
                    $datas['manual_overrides_id'] = $inserted->id;
                    $datas['old_overrides_amount'] = 0.0;
                    $datas['overrides_amount'] = $overrides_amount;
                    $datas['overrides_type'] = $overrides_type;
                    $datas['effective_date'] = $effective_date;

                    $inserted = ManualOverridesHistory::create($datas);
                    OverrideStatus::create([
                        'user_id' => $user_id,
                        'recruiter_id' => $value,
                        'status' => 0,
                        'type' => 'Manual',
                        'effective_date' => $effective_date,
                        'updated_by' => Auth::user()->id,
                    ]);
                }

            }
        }

        return response()->json([
            'ApiName' => 'manual_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function edit_manual_overrides(Request $request)
    {
        $data = [];
        // return $request;
        $id = $request->id;
        $user_id = $request->user_id;
        $manual_user_id = $request->manual_user_id;
        $overrides_amount = $request->overrides_amount;
        $overrides_type = $request->overrides_type;
        $effective_date = isset($request->effective_date) ? $request->effective_date : null;
        // if(isset($user_id) && $user_id!=null){
        //     ManualOverrides::where('manual_user_id',$user_id)->delete();
        //  }

        $manualOverrides = ManualOverrides::where('id', $id)->first();
        $currentDate = Carbon::now()->format('Y-m-d');
        if ($currentDate >= $effective_date) {

            $manualOverrides['overrides_amount'] = $overrides_amount;
            $manualOverrides['overrides_type'] = $overrides_type;
            $manualOverrides['effective_date'] = $effective_date;
            $manualOverrides->save();
        }

        $manualOverridesHistory = ManualOverridesHistory::where('manual_overrides_id', $id)->where('effective_date', $effective_date)->first();
        if (! empty($manualOverridesHistory)) {

            $manualOverridesHistory['manual_user_id'] = $manual_user_id;
            $manualOverridesHistory['manual_overrides_id'] = $id;
            $manualOverridesHistory['user_id'] = $user_id;
            $manualOverridesHistory['updated_by'] = Auth()->user()->id;
            $manualOverridesHistory['overrides_amount'] = $overrides_amount;
            $manualOverridesHistory['overrides_type'] = $overrides_type;
            $data['effective_date'] = $effective_date;
            $manualOverridesHistory->save();
        } else {

            $oldAmount = ManualOverridesHistory::where('manual_user_id', $manual_user_id)->where('user_id', $user_id)->orderBy('id', 'desc')->first();

            $data['manual_user_id'] = $manual_user_id;
            $data['user_id'] = $user_id;
            $data['manual_overrides_id'] = $id;
            $data['updated_by'] = Auth()->user()->id;
            $data['overrides_amount'] = $overrides_amount;
            $data['old_overrides_amount'] = isset($oldAmount->overrides_amount) ? $oldAmount->overrides_amount : 0.0;
            $data['overrides_type'] = $overrides_type;
            $data['effective_date'] = $effective_date;
            $inserteds = ManualOverridesHistory::create($data);

        }

        return response()->json([
            'ApiName' => 'manual_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function manual_overrides_from(Request $request): JsonResponse
    {
        $data = [];
        $datas = [];
        $user_id = $request->user_id;
        $manual_user_id = $request->manual_user_id;
        $overrides_amount = $request->overrides_amount;
        $overrides_type = $request->overrides_type;
        $effective_date = isset($request->effective_date) ? $request->effective_date : null;
        // if(isset($user_id) && $user_id!=null){
        //    ManualOverrides::where('user_id',$user_id)->delete();
        // }
        foreach ($manual_user_id as $value) {

            $manualOverrides = ManualOverrides::where(['manual_user_id' => $value, 'user_id' => $user_id])->orderBy('id', 'desc')->first();
            if (empty($manualOverrides)) {

                $data['manual_user_id'] = $value;
                $data['user_id'] = $user_id;
                $data['overrides_amount'] = $overrides_amount;
                $data['overrides_type'] = $overrides_type;
                $data['effective_date'] = $effective_date;
                $inserted = ManualOverrides::create($data);

                $datas['manual_user_id'] = $value;
                $datas['user_id'] = $user_id;
                $datas['updated_by'] = Auth()->user()->id;
                $datas['manual_overrides_id'] = $inserted->id;
                $data['old_overrides_amount'] = 0.0;
                $datas['overrides_amount'] = $overrides_amount;
                $datas['overrides_type'] = $overrides_type;
                $datas['effective_date'] = $effective_date;

                $inserted = ManualOverridesHistory::create($datas);
                OverrideStatus::create([
                    'user_id' => $value,
                    'recruiter_id' => $user_id,
                    'status' => 0,
                    'type' => 'Manual',
                    'effective_date' => $effective_date,
                    'updated_by' => Auth::user()->id,
                ]);
            }

        }

        return response()->json([
            'ApiName' => 'manual_overrides_from',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function edit_manual_overrides_from(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'user_id' => 'required',
            'manual_user_id' => 'required',
            'overrides_amount' => 'required',
            'overrides_type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = [];
        $id = $request->id;
        $user_id = $request->user_id;
        $manual_user_id = $request->manual_user_id;
        $overrides_amount = $request->overrides_amount;
        $overrides_type = $request->overrides_type;
        $effective_date = isset($request->effective_date) ? $request->effective_date : null;

        $currentDate = Carbon::now()->format('Y-m-d');
        $manual_data = ManualOverrides::where('user_id', $user_id)->where('id', $id)->first();

        $manualOverridesHistory = ManualOverridesHistory::where('manual_overrides_id', $id)->where('effective_date', '<=', date('Y-m-d', strtotime($effective_date)))->orderBy('effective_date', 'desc')->first();

        $manualOverridesNext = ManualOverridesHistory::where('manual_overrides_id', $id)->where('effective_date', '>', date('Y-m-d', strtotime($effective_date)))->orderBy('effective_date', 'ASC')->first();

        $data = (object) [];
        if (empty($manualOverridesHistory) && empty($manualOverridesNext)) {
            if ($manual_data != '') {
                $data = $manual_data;
            } else {
                $data = '';
            }
        } elseif (empty($manualOverridesHistory) && ! empty($manualOverridesNext)) {
            $data->overrides_amount = $manualOverridesNext->old_overrides_amount;
            $data->overrides_amount = $manualOverridesNext->old_overrides_type;
            $manualOverridesNext->old_overrides_amount = $overrides_amount;
            $manualOverridesNext->old_overrides_type = $overrides_type;
            $manualOverridesNext->save();

        } elseif (! empty($manualOverridesHistory) && empty($manualOverridesNext)) {
            $data->overrides_amount = $manualOverridesHistory->overrides_amount;
            $data->overrides_type = $manualOverridesHistory->overrides_type;
        } elseif (! empty($manualOverridesHistory) && ! empty($manualOverridesNext)) {
            $data->overrides_amount = $manualOverridesHistory->overrides_amount;
            $data->overrides_type = $manualOverridesHistory->overrides_type;
            $manualOverridesNext->old_overrides_amount = $overrides_amount;
            $manualOverridesNext->old_overrides_type = $overrides_type;
            $manualOverridesNext->save();
        }
        $checkdata = ManualOverridesHistory::updateOrCreate(
            [
                'manual_user_id' => $manual_user_id,
                'user_id' => $user_id,
                'effective_date' => date('Y-m-d', strtotime($effective_date)),
            ],
            [
                'manual_user_id' => $manual_user_id,
                'manual_overrides_id' => $id,
                'user_id' => $user_id,
                'updated_by' => Auth()->user()->id,
                'overrides_amount' => $overrides_amount,
                'overrides_type' => $overrides_type,
                'old_overrides_amount' => isset($data->overrides_amount) ? $data->overrides_amount : '',
                'old_overrides_type' => isset($data->overrides_type) ? $data->overrides_type : '',
                'effective_date' => date('Y-m-d', strtotime($effective_date)),
            ]
        );
        $manualOverrides_history = ManualOverridesHistory::where('user_id', $user_id)->where('effective_date', '<=', $currentDate)->orderBy('effective_date', 'desc')->first();

        if ($manualOverrides_history) {
            $manual_data->overrides_amount = $manualOverrides_history->overrides_amount;
            $manual_data->overrides_type = $manualOverrides_history->overrides_type;
            $manual_data->effective_date = $manualOverrides_history->effective_date;
            $manual_data->save();
        }

        return response()->json([
            'ApiName' => 'manual_overrides_from',
            'status' => true,
            'message' => 'Edit manual overrides successfully.',
        ], 200);
    }

    public function getAllUsersByManager($id)
    {
        if (isset($id)) {
            $user = User::with('positionDetail')->where('manager_id', $id)->where('dismiss', 0)->get();

            $data = [];
            $user->transform(function ($data) {
                return [
                    'user_id' => $data->id,
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'position_id ' => ($data->position_id) ? $data->position_id : null,
                    'position_name ' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                ];
            });
            $data['user'] = $user;

            return response()->json([
                'ApiName' => 'get-all-users-by-managerId',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get-all-users-by-managerId',
                'status' => false,
                'message' => 'Manager Id not found.',
            ], 400);
        }
    }

    public function updateUserManager(Request $request): JsonResponse
    {
        $old_manager_id = $request->old_manager_id;
        $allData = $request->users;
        $count = 0;
        foreach ($allData as $key => $value) {
            $user = User::where('id', $value['user_id'])->update(['manager_id' => $value['manager_id']]);
            // $is_manager = User::where('manager_id',$value['manager_id'])->update(['is_manager' => 1]);
            $count++;
        }
        $manager = User::where('manager_id', $old_manager_id)->update(['is_manager' => 0]);
        $data['total_count'] = $count;

        return response()->json([
            'ApiName' => 'get-all-users-by-mangerId',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function my_overrides_filter(Request $request, User $User): JsonResponse
    {
        $id = $request->id;
        $filter = $request->filter;

        $User = $User->newQuery();

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
                $startDate = date('Y-m-d', strtotime(\Carbon\Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(\Carbon\Carbon::now()->subMonths(1)->endOfMonth()));

            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');

                $ApprovalsAndRequest->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $ApprovalsAndRequest->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(\Carbon\Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(\Carbon\Carbon::now()->subYears(0)->endOfYear()));
            }
        }
        $user = User::where('id', $id)->first();
        // $additional = User::select('id','recruiter_id','additional_recruiter_id1','additional_recruiter_id2','first_name','last_name','image')->with('childs')->where('recruiter_id',$id)->get();
        $directs = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'direct_overrides_amount', 'direct_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('recruiter_id', $id)->orWhere('additional_recruiter_id1', $id)->orWhere('additional_recruiter_id2', $id)->whereBetween('created_at', [$startDate, $endDate])->get();
        $totalDirects = User::with('positionDetail', 'recruiter')->where('recruiter_id', $id)->orWhere('additional_recruiter_id1', $id)->orWhere('additional_recruiter_id2', $id)->whereBetween('created_at', [$startDate, $endDate])->count();

        $direct = [];
        $indirect = [];
        $office = [];
        $manual = [];

        if (count($directs) > 0) {
            foreach ($directs as $key => $value) {

                $totalAccountDirect = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $value->id])->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->count();

                $totalOverrideDirect = DB::table('user_overrides')
                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                    ->where(['user_id' => $user->id, 'sale_user_id' => $value->id])
                    ->where('type', 'Direct')
                    ->first();

                $direct[] = [
                    'id' => $value->id,
                    'recruiter_id' => $value->recruiter_id,
                    'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                    'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                    'first_name' => $value->first_name,
                    'last_name' => $value->last_name,
                    'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                    'override' => $user->direct_overrides_amount.'/'.$user->direct_overrides_type,
                    'totalOverrides' => $totalOverrideDirect->overridesTotal,
                    'account' => $totalAccountDirect,
                    'kwInstalled' => $totalOverrideDirect->totalKw,
                    'image' => $value->image,
                ];

                $additional = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'indirect_overrides_amount', 'indirect_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('recruiter_id', $value->id)->orWhere('additional_recruiter_id1', $value->id)->orWhere('additional_recruiter_id2', $value->id)->whereBetween('created_at', [$startDate, $endDate])->get();
                $indirectCount = User::with('positionDetail', 'recruiter')->where('recruiter_id', $value->id)->orWhere('additional_recruiter_id1', $value->id)->orWhere('additional_recruiter_id2', $value->id)->whereBetween('created_at', [$startDate, $endDate])->count();

                $additionals = [];
                if (count($additional) > 0) {
                    foreach ($additional as $key1 => $val) {

                        $totalAccountIndirect = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $val->id])->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->count();

                        $totalOverrideIndirect = DB::table('user_overrides')
                            ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                            ->where(['user_id' => $user->id, 'sale_user_id' => $val->id])
                            ->where('type', 'Indirect')
                            ->first();

                        $additionals = [
                            'id' => $val->id,
                            'recruiter_id' => $val->recruiter_id,
                            'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                            'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                            'first_name' => $val->first_name,
                            'last_name' => $val->last_name,
                            'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                            'override' => $user->indirect_overrides_amount.'/'.$user->indirect_overrides_type,
                            'totalOverrides' => $totalOverrideIndirect->overridesTotal,
                            'account' => $totalAccountIndirect,
                            'kwInstalled' => $totalOverrideIndirect->totalKw,
                            'image' => $val->image,

                        ];
                        $indirect[] = $additionals;
                        $totalIndirect = $indirectCount;
                    }
                }

            }
        }

        $additionals1 = [];
        $officeUsers = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type')->with('positionDetail', 'recruiter', 'override_status')->where('office_id', $user->office_id)->whereBetween('created_at', [$startDate, $endDate])->where('dismiss', 0)->get();
        $officeCount = User::with('positionDetail', 'recruiter')->where('office_id', $user->office_id)->whereBetween('created_at', [$startDate, $endDate])->where('dismiss', 0)->count();

        if (count($officeUsers) > 0) {
            foreach ($officeUsers as $key2 => $vall) {

                $totalOverrideOffice = DB::table('user_overrides')
                    ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                    ->where(['user_id' => $user->id, 'sale_user_id' => $vall->id])
                    ->where('type', 'Office')
                    ->first();

                $totalAccountOffice = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $vall->id])->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->count();
                // dd($totalAccountOffice)  ;die;
                $office_overrides_amount = isset($user->office_overrides_amount) ? $user->office_overrides_amount : '0';
                $office_overrides_type = isset($user->office_overrides_type) ? $user->office_overrides_type : 'per kw';
                $additionals1 = [
                    'id' => $vall->id,
                    'recruiter_id' => $vall->recruiter_id,
                    'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                    'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                    'first_name' => $vall->first_name,
                    'last_name' => $vall->last_name,
                    'status' => isset($vall->override_status->status) ? $vall->override_status->status : 0,
                    'override' => $office_overrides_amount.' /'.$office_overrides_type,
                    'totalOverrides' => $totalOverrideOffice->overridesTotal,
                    'account' => $totalAccountOffice,
                    'kwInstalled' => $totalOverrideOffice->totalKw,
                    'image' => $vall->image,

                ];
                $office[] = $additionals1;
                $totalOffice = $officeCount;

            }

        }

        $manualData = ManualOverrides::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->get();
        $manualCount = ManualOverrides::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->count();

        $manualOverride = [];
        if ($manualCount > 0) {
            foreach ($manualData as $key3 => $manual) {

                $vall = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'office_overrides_amount', 'office_overrides_type', 'dismiss')->where('dismiss', 0)->with('positionDetail', 'recruiter', 'override_status')->where('id', $manual->manual_user_id)->whereBetween('created_at', [$startDate, $endDate])->first();
                if (isset($vall) && $vall != null) {
                    $totalAccountManual = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->count();

                    $totalOverrideManual = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])
                        ->where('type', 'Manual')
                        ->first();
                    $manualOverride[] = [
                        'id' => $vall->id,
                        'recruiter_id' => $vall->recruiter_id,
                        'position' => isset($vall->recruiter->first_name) ? $vall->recruiter->first_name : null,
                        'position' => isset($vall->positionDetail->position_name) ? $vall->positionDetail->position_name : null,
                        'first_name' => $vall->first_name,
                        'last_name' => $vall->last_name,
                        'status' => isset($value->override_status->status) ? $value->override_status->status : 0,
                        'override' => $manualData[$key3]->overrides_amount.'/'.$manualData[$key3]->overrides_type,

                        'totalOverrides' => $totalOverrideManual->overridesTotal,
                        'account' => $totalAccountManual,
                        'kwInstalled' => $totalOverrideManual->totalKw,
                        'image' => $vall->image,

                    ];

                    $totalmanual = $manualCount;
                }

            }

        }

        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['totalDirects'] = isset($totalDirects) ? ($totalDirects) : 0;
        $data['totalIndirect'] = isset($totalIndirect) ? ($totalIndirect) : 0;
        $data['totalOffice'] = isset($totalOffice) ? ($totalOffice) : 0;
        $data['totalmanual'] = isset($totalmanual) ? ($totalmanual) : 0;
        $data['direct'] = $direct;
        $data['indirect'] = $indirect;
        $data['office'] = $office;
        $data['manual'] = $manualOverride;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function overrideSettingStatus(Request $request): JsonResponse
    {
        $remove_existing_manual_override = $request->remove_existing_manual_override;
        $data = overrideSystemSetting::first();
        $allow_manual_override_status = isset($request->allow_manual_override_status) ? $request->allow_manual_override_status : 0;
        $allow_office_stack_override_status = isset($request->allow_office_stack_override_status) ? $request->allow_office_stack_override_status : 0;
        $pay_type = isset($request->pay_type) ? $request->pay_type : 1;
        $data1 = [
            'allow_manual_override_status' => $allow_manual_override_status,
            'allow_office_stack_override_status' => $allow_office_stack_override_status,
            'pay_type' => $pay_type,
        ];
        if (empty($data)) {
            $response_status = overrideSystemSetting::create($data1);
            $action = 'Override System Added';
        } else {
            // $response_status = overrideSystemSetting::where('id',$data->id)->update($data1);
            $overrideSetting = overrideSystemSetting::where('id', $data->id)->first(); // added for activity log
            $overrideSetting->allow_manual_override_status = $allow_manual_override_status;
            $overrideSetting->allow_office_stack_override_status = $allow_office_stack_override_status;
            $overrideSetting->pay_type = $pay_type;
            $response_status = $overrideSetting->save();
            $action = 'Override System Updated';
        }
        if ($response_status) {
            $page = 'Setting';
            $description = 'Allow manual override status =>'.$allow_manual_override_status.', '.'Allow office stack override status =>'.$allow_office_stack_override_status.', '.'Pay type =>'.$pay_type;
            user_activity_log($page, $action, $description);
        }

        // if($remove_existing_manual_override ==1){
        //     $payroll = Payroll::where('status',$remove_existing_manual_override)->get();
        //     $payroll->transform(function ($data){
        //        $manualOverride = ManualOverrides::where('user_id',$data['user_id'])->delete();
        //     });

        // }

        return response()->json([
            'ApiName' => 'update override System Setting ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => ! empty($data) ? $data->allow_manual_override_status : 0,

        ], 200);

    }

    public function getOverrideSettingStatus(Request $request): JsonResponse
    {

        $data = overrideSystemSetting::first();

        if ($data) {

            return response()->json([
                'ApiName' => 'get override System Setting ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        }

        return response()->json([
            'ApiName' => 'get override System Setting ',
            'status' => false,
            'message' => 'No override system setting found.',
            'data' => null,
        ], 404);

    }

    public function exportUsersData(Request $request)
    {
        $officeFilter = $request->office_filter;
        $position_filter = $request->position_filter;
        $showAdmin_filter = $request->showAdmin_filter;
        $status_filter = $request->status_filter;
        $filter = $request->filter;
        $file_name = 'users_'.date('Y_m_d_H_i_s').'.csv';

        return Excel::download(new UserExport($officeFilter, $position_filter, $showAdmin_filter, $status_filter, $filter), $file_name);

    }

    public function exportStandardEmployeeExport(Request $request)
    {
        $office = $request->input('office_id');
        $filter = $request->input('filter');
        $file_name = 'users_'.date('Y_m_d_H_i_s').'.xlsx';

        Excel::store(new StandardEmployeeExport($office, $filter),
            'exports/management/employee/'.$file_name,
            'public',
            \Maatwebsite\Excel\Excel::XLSX
        );

        // Get the URL for the stored file
        $url = getStoragePath('exports/management/employee/'.$file_name);
        // $url = getExportBaseUrl().'storage/exports/management/employee/' . $file_name;

        // Return the URL in the API response
        return response()->json(['url' => $url]);

        return Excel::download(new StandardEmployeeExport($office, $filter), $file_name);

    }

    public function exportUserManagement(Request $request)
    {
        $today = date('Y-m-d');

        $validator = Validator::make($request->all(), [
            'type' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $type = $request->type;
        $companyProfile = CompanyProfile::first();
        $data = User::with(['positionDetail', 'office.State', 'departmentDetail', 'State', 'managerDetail', 'teamsDetail', 'recruiter', 'additionalRecruiterOne', 'additionalRecruiterTwo', 'statusDetail'])
            ->when($request->filled('office_filter') && $request->input('office_filter') != 'all', function ($q) {
                $q->where('office_id', request()->input('office_filter'));
            })->when($request->filled('position_filter'), function ($q) {
                $q->where('sub_position_id', request()->input('position_filter'));
            })->when($request->filled('showAdmin_filter'), function ($q) {
                $q->where('is_super_admin', request()->input('showAdmin_filter'));
            })->when($request->filled('status_filter') && in_array($request->status_filter, [0, 1]), function ($q) {
                $statusFilter = request()->input('status_filter') == 1 ? 1 : 0;
                if ($statusFilter == 1) {
                    // Inactive: any of the status fields is 1
                    $q->where(function ($query) {
                        $query->where('dismiss', 1)
                            ->orWhere('terminate', 1)
                            ->orWhere('contract_ended', 1);
                    });
                } else {
                    // Active: all of the status fields must be 0
                    $q->where(function ($query) {
                        $query->where('dismiss', 0)
                            ->where('terminate', 0)
                            ->where('contract_ended', 0);
                    });
                }
            })->when($request->filled('status_filter') && in_array($request->status_filter, [2, 3, 4]), function ($query) use ($request, $today) {
                switch ((int) $request->status_filter) {
                    case 2: // Dismissed
                        $query->whereExists(function ($subQuery) use ($today) {
                            $subQuery->selectRaw(1)
                                ->from('user_dismiss_histories as udh1')
                                ->join(DB::raw('(
                                SELECT user_id, MAX(effective_date) AS max_effective_date
                                FROM user_dismiss_histories
                                WHERE effective_date <= "'.$today.'"
                                GROUP BY user_id
                            ) as udh2'), function ($join) {
                                    $join->on('udh1.user_id', '=', 'udh2.user_id')
                                        ->on('udh1.effective_date', '=', 'udh2.max_effective_date');
                                })
                                ->whereColumn('udh1.user_id', 'users.id')
                                ->where('udh1.dismiss', 1);
                        });
                        break;

                    case 3: // Terminated
                        $query->whereIn('id', function ($sub) use ($today) {
                            $sub->select('uth1.user_id')   // add table alias prefix here
                                ->from('user_terminate_histories as uth1')
                                ->join(DB::raw('(
                                SELECT user_id, MAX(terminate_effective_date) as max_date
                                FROM user_terminate_histories
                                WHERE terminate_effective_date <= "'.$today.'"
                                GROUP BY user_id
                            ) as uth2'), function ($join) {
                                    $join->on('uth1.user_id', '=', 'uth2.user_id')
                                        ->on('uth1.terminate_effective_date', '=', 'uth2.max_date');
                                })
                                ->where('uth1.is_terminate', 1);
                        });
                        break;

                    case 4: // Contract Ended: latest contract record ended on or before today
                        // Use the same logic as contractEndedUsers() function
                        $contractEndedUserIds = contractEndedUsers($today);
                        if (! empty($contractEndedUserIds)) {
                            $query->whereIn('id', $contractEndedUserIds);
                        } else {
                            // No users have ended contracts
                            $query->whereRaw('1 = 0'); // Return no results
                        }
                        break;
                }
            })->when($request->filled('filter'), function ($q) {
                $search = request()->input('filter');
                $q->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile_no', 'like', '%'.$search.'%')
                    ->orWhereHas('additionalEmails', function ($query) use ($search) {
                        $query->where('email', 'like', '%'.$search.'%');
                    });
            })->orderBy('id', 'DESC')->get();

        if (count($data) == 0) {
            return response()->json([
                'status' => false,
                'ApiName' => 'exportUserManagement',
                'message' => 'No Data Found!',
            ], 400);
        }

        $result = [];
        foreach ($data as $record) {
            $result[] = [
                $record->employee_id,
                $record->first_name,
                $record->last_name,
                $record?->departmentDetail?->name,
                $record?->positionDetail?->position_name,
                $record?->State?->name,
                $record?->office?->office_name,
                $record->email,
                $record->mobile_no,
                $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
            ];
        }

        $response['basic'] = $result;
        $response['personal'] = [];
        $response['organization'] = [];
        $response['commission'] = [];
        $response['tax'] = [];
        $response['banking'] = [];
        if (count($request->type) != 0) {
            foreach ($request->type as $type) {
                if ($type == 'personal' && count($response['personal']) == 0) {
                    $additionalInfoAndGetStartedData = EmployeeIdSetting::with([
                        'AdditionalInfoForEmployeeToGetStarted' => fn ($query) => $query->where('is_deleted', 0),
                        'EmployeePersonalDetail' => fn ($query) => $query->where('is_deleted', 0),
                    ])->find(1);
                    $getStarted = $additionalInfoAndGetStartedData->AdditionalInfoForEmployeeToGetStarted->pluck('field_name')->toArray();
                    $additionalInfo = $additionalInfoAndGetStartedData->EmployeePersonalDetail->pluck('field_name')->toArray();

                    // Get admin-only fields configuration
                    $adminOnlyFields = \App\Models\EmployeeAdminOnlyFields::where('is_deleted', 0)->pluck('field_name')->toArray();


                    $personal = [];
                    foreach ($data as $record) {
                        $work_emails = UsersAdditionalEmail::where('user_id', $record->id)->pluck('email')->implode(",\n ");
                        $mainPersonalInfoUser = [
                            $record->employee_id ?? '',
                            $record->first_name ?? '',
                            $record->last_name ?? '',
                            $record->email ?? '',
                            $record->mobile_no ?? '',
                            $record->middle_name ?? '',
                            $record->sex ?? '',
                            $record->dob ?? '',
                            $record->mobile_no ?? '',
                            $record->email ?? '',
                            $work_emails ?? '',
                            $record->home_address ?? '',
                            $record->emergency_phone ?? '',
                            $record->emergency_contact_name ?? '',
                            $record->emergency_contact_relationship ?? '',
                            $record->emergrncy_contact_address ?? '',
                        ];

                        // Columns that should always be at the end
                        $endColumns = [
                            $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                            isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                            isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                            isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                            $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
                        ];

                        $additionalInfoUser = [];
                        if (! empty($record->employee_personal_detail)) {
                            $decodedData = json_decode($record->employee_personal_detail, true);
                            if (is_array($decodedData)) {
                                $dbPersonalInfo = collect($decodedData);
                                $additionalInfoUser = array_map(function ($col) use ($dbPersonalInfo) {
                                    $match = $dbPersonalInfo->firstWhere('field_name', $col);
                                    $val = $match['value'] ?? '';

                                    return ($col === 'Housing Allowance') ? '$ '.number_format((float) $val, 2, '.', '') : $val;
                                }, $additionalInfo);
                            }
                        }

                        // Ensure we have the same number of additional info columns as configured
                        $additionalInfoUser = array_pad($additionalInfoUser, count($additionalInfo), "");

                        $getStartedUser = [];
                        if (! empty($record->additional_info_for_employee_to_get_started)) {
                            $decodedData = json_decode($record->additional_info_for_employee_to_get_started, true);
                            if (is_array($decodedData)) {
                                $dbGetStarted = collect($decodedData);
                                $getStartedUser = array_map(function ($col) use ($dbGetStarted) {
                                    $match = $dbGetStarted->firstWhere('field_name', $col);
                                    $val = $match['value'] ?? '';

                                    return $val;
                                }, $getStarted);
                            }
                        }

                        // Ensure we have the same number of get started columns as configured
                        $getStartedUser = array_pad($getStartedUser, count($getStarted), "");
                        $adminOnlyFieldsUser = [];
                        if (!empty($adminOnlyFields) && !empty($record->employee_admin_only_fields)) {
                            $decodedData = json_decode($record->employee_admin_only_fields, true);
                            if (is_array($decodedData)) {
                                $dbAdminOnlyFields = collect($decodedData);
                                $adminOnlyFieldsUser = array_map(function ($col) use ($dbAdminOnlyFields) {
                                    $match = $dbAdminOnlyFields->firstWhere('field_name', $col);
                                    $val = $match['value'] ?? "";
                                    return $val;
                                }, $adminOnlyFields);
                            }
                        }

                        // Ensure we have the same number of admin-only fields as configured
                        $adminOnlyFieldsUser = array_pad($adminOnlyFieldsUser, count($adminOnlyFields), "");


                        $personal[] = array_merge($mainPersonalInfoUser, $additionalInfoUser, $getStartedUser, $adminOnlyFieldsUser, $endColumns);
                    }
                    $response['personal'] = $personal;
                } elseif ($type == 'organization' && count($response['organization']) == 0) {
                    $organization = [];
                    foreach ($data as $record) {
                        $organizationInfo = $this->userOrganizationDetails($record->id);
                        $smartText = $this->customSmartFieldsDetailByUserId($record->id);
                        $smartFields = collect($smartText[0] ?? [])->map(function ($value, $key) {
                            return trim($key, '[]').': '.$value;
                        })->implode("\n");

                        $deductions = collect($organizationInfo['deduction'])->map(function ($deduction) {
                            return $deduction['cost_center_name'].': '.$deduction['deduction_type'].$deduction['ammount_par_paycheck'];
                        })->implode("\n");

                        $organization[] = [
                            $record->employee_id ?? '',
                            @$organizationInfo['organization']['state_name'] ?? '',
                            @$organizationInfo['organization']['office_name'] ?? '',
                            @$organizationInfo['organization']['department_name'] ?? '',
                            @$organizationInfo['organization']['position_name'] ?? '',
                            @$organizationInfo['organization']['is_manager'] ? 'Yes' : 'No',
                            @$organizationInfo['organization']['self_gen_accounts'] ? 'Yes' : 'No',
                            @$organizationInfo['organization']['manager_name'] ?? '',
                            @$organizationInfo['organization']['team_name'] ?? '',
                            @$organizationInfo['organization']['recruiter_name'] ?? '',
                            @$organizationInfo['user_wages']['pay_type'] ?? '',
                            @$organizationInfo['user_wages']['pay_rate'] ? '$ '.@$organizationInfo['user_wages']['pay_rate'].' Per '.@$organizationInfo['user_wages']['pay_rate_type'] : '',
                            @$organizationInfo['user_wages']['pto_hours'] ? @$organizationInfo['user_wages']['pto_hours'].' Per Month' : '',
                            @$organizationInfo['user_wages']['unused_pto_expires'] ?? '',
                            @$organizationInfo['user_wages']['expected_weekly_hours'] ?? '',
                            @$organizationInfo['agreement']['hired_date'] ? Carbon::parse(@$organizationInfo['agreement']['hired_date'])->format('m/d/Y') : '',
                            @$organizationInfo['agreement']['probation_period'] ?? '',
                            @$organizationInfo['agreement']['offer_include_bonus'] ? 'Yes' : 'No',
                            @$organizationInfo['agreement']['period_of_agreement_start_date'] ? Carbon::parse($organizationInfo['agreement']['period_of_agreement_start_date']).' - '.Carbon::parse($organizationInfo['agreement']['end_date']) : '',
                            @$organizationInfo['agreement']['offer_expiry_date'] ? Carbon::parse(@$organizationInfo['agreement']['offer_expiry_date'])->format('m/d/Y') : '',
                            @$organizationInfo['agreement']['hiring_by'] ?? '',
                            @$organizationInfo['agreement']['hiring_signature'] ?? '',
                            @$organizationInfo['deductionEffectiveDate'] ? Carbon::parse(@$organizationInfo['deductionEffectiveDate'])->format('m/d/Y') : '',
                            $deductions,
                            $smartFields,
                            $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                            isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                            isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                            isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                            $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
                        ];
                    }
                    $response['organization'] = $organization;
                } elseif ($type == 'commission' && count($response['commission']) == 0) {
                    $commission = [];
                    $products = Products::orderBy('id', 'DESC')->with('currentProductMilestoneHistories')->get();
                    foreach ($products as $productId => $product) {
                        $proCommissionArr = [];
                        $productName = $product->name;
                        $productId = $product->id;
                        $milestone = $product->currentProductMilestoneHistories;
                        $milestone_schema_id = $milestone?->milestone_schema_id;
                        $mileStonesNames = MilestoneSchemaTrigger::where('milestone_schema_id', $milestone_schema_id)->pluck('name')->toArray();
                        foreach ($data as $record) {
                            $position = '';
                            $additionalOffice = [];
                            $selfGenCommission = '';
                            $selfGenEffectiveDate = '';
                            $setterCommission = '';
                            $setterCommissionEffectiveDate = '';
                            $closerCommission = '';
                            $closerCommissionEffectiveDate = '';
                            $setterRedLine = '';
                            $setterRedLineType = '';
                            $setterRedLineEffectiveDate = '';
                            $closerRedLine = '';
                            $closerRedLineType = '';
                            $closerRedLineEffectiveDate = '';
                            $setterUpFront = [];
                            $closerUpFront = [];
                            $setterWithHeld = '';
                            $setterWithHeldEffectiveDate = '';
                            $closerWithHeld = '';
                            $closerWithHeldEffectiveDate = '';

                            $override_effective_date = '';
                            $direct_overrides_amount = '';
                            $direct_overrides_type = '';
                            $indirect_overrides_amount = '';
                            $indirect_overrides_type = '';
                            $office_overrides_amount = '';
                            $office_overrides_type = '';
                            $office_stack_overrides_amount = '';

                            $userOrganization = UserOrganizationHistory::where(['user_id' => $record->id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (! $userOrganization) {
                                $userOrganization = UserOrganizationHistory::where(['user_id' => $record->id])->where('effective_date', '>=', date('Y-m-d'))->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                            }
                            $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $record->id, 'effective_date' => $userOrganization?->effective_date])->pluck('product_id')->toArray();
                            if (in_array($productId, $userOrganizationHistory)) {
                                $userCommissionDetails = $this->userCompensationDetails($record->id, $productId);
                                $additionalOffice = $userCommissionDetails['organization']['additional_locations'] ?? [];
                                if ($userCommissionDetails['main_role'] == 1) {
                                    $position = 'Both';
                                    foreach ($userCommissionDetails['employee_compensation'] as $employee_compensation) {
                                        if ($employee_compensation['core_position_id'] == 2) {
                                            if (isset($employee_compensation['commission'])) {
                                                $closerCommission = @$employee_compensation['commission']['commission'].' '.@$employee_compensation['commission']['commission_type'];
                                                $closerCommissionEffectiveDate = @$employee_compensation['commission']['commission_effective_date'] ? date('m/d/Y', strtotime(@$employee_compensation['commission']['commission_effective_date'])) : '';
                                            }

                                            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                                if (isset($employee_compensation['commission']['commission_type']) && $employee_compensation['commission']['commission_type'] == 'percent') {
                                                    if (isset($employee_compensation['redline'])) {
                                                        $closerRedLine = @$employee_compensation['redline']['redline'].' '.@$employee_compensation['redline']['redline_type'];
                                                        $closerRedLineType = @$employee_compensation['redline']['redline_amount_type'];
                                                        $closerRedLineEffectiveDate = @$employee_compensation['redline']['redline_effective_date'] ? date('m/d/Y', strtotime($employee_compensation['redline']['redline_effective_date'])) : '';
                                                    }
                                                }
                                            }

                                            if (isset($employee_compensation['upfront']) && isset($employee_compensation['upfront'][0])) {
                                                $closerUpFront = @$employee_compensation['upfront'];
                                            }

                                            if (isset($userCommissionDetails['withheld'])) {
                                                $closerWithHeld = @$userCommissionDetails['withheld']['withheld_amount'].' '.@$userCommissionDetails['withheld']['withheld_type'];
                                                $closerWithHeldEffectiveDate = @$userCommissionDetails['withheld']['withheld_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['withheld']['withheld_effective_date'])) : '';
                                            }
                                        } elseif ($employee_compensation['core_position_id'] == 3) {
                                            if (isset($employee_compensation['commission'])) {
                                                $setterCommission = @$employee_compensation['commission']['commission'].' '.@$employee_compensation['commission']['commission_type'];
                                                $setterCommissionEffectiveDate = @$employee_compensation['commission']['commission_effective_date'] ? date('m/d/Y', strtotime(@$employee_compensation['commission']['commission_effective_date'])) : '';
                                            }

                                            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                                if (isset($employee_compensation['commission']['commission_type']) && $employee_compensation['commission']['commission_type'] == 'percent') {
                                                    if (isset($employee_compensation['redline'])) {
                                                        $setterRedLine = @$employee_compensation['redline']['redline'].' '.@$employee_compensation['redline']['redline_type'];
                                                        $setterRedLineType = @$employee_compensation['redline']['redline_amount_type'];
                                                        $setterRedLineEffectiveDate = @$employee_compensation['redline']['redline_effective_date'] ? date('m/d/Y', strtotime($employee_compensation['redline']['redline_effective_date'])) : '';
                                                    }
                                                }
                                            }

                                            if (isset($employee_compensation['upfront']) && isset($employee_compensation['upfront'][0])) {
                                                $setterUpFront = @$employee_compensation['upfront'];
                                            }

                                            if (isset($userCommissionDetails['withheld'])) {
                                                $setterWithHeld = @$userCommissionDetails['withheld']['withheld_amount'].' '.@$userCommissionDetails['withheld']['withheld_type'];
                                                $setterWithHeldEffectiveDate = @$userCommissionDetails['withheld']['withheld_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['withheld']['withheld_effective_date'])) : '';
                                            }
                                        } else {
                                            $selfGenCommission = @$employee_compensation['commission']['commission'].' '.@$employee_compensation['commission']['commission_type'];
                                            $selfGenEffectiveDate = @$employee_compensation['commission']['commission_effective_date'] ? date('m/d/Y', strtotime(@$employee_compensation['commission']['commission_effective_date'])) : '';
                                        }
                                    }
                                } elseif ($userCommissionDetails['main_role'] == 3) {
                                    $position = 'Setter';

                                    if (isset($userCommissionDetails['employee_compensation'][0]['commission'])) {
                                        $setterCommission = @$userCommissionDetails['employee_compensation'][0]['commission']['commission'].' '.@$userCommissionDetails['employee_compensation'][0]['commission']['commission_type'];
                                        $setterCommissionEffectiveDate = @$userCommissionDetails['employee_compensation'][0]['commission']['commission_effective_date'] ? date('m/d/Y', strtotime(@$userCommissionDetails['employee_compensation'][0]['commission']['commission_effective_date'])) : '';
                                    }

                                    if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                        if (isset($userCommissionDetails['employee_compensation'][0]['commission']['commission_type']) && $userCommissionDetails['employee_compensation'][0]['commission']['commission_type'] == 'percent') {
                                            if (isset($userCommissionDetails['employee_compensation'][0]['redline'])) {
                                                $setterRedLine = @$userCommissionDetails['employee_compensation'][0]['redline']['redline'].' '.@$userCommissionDetails['employee_compensation'][0]['redline']['redline_type'];
                                                $setterRedLineType = @$userCommissionDetails['employee_compensation'][0]['redline']['redline_amount_type'];
                                                $setterRedLineEffectiveDate = @$userCommissionDetails['employee_compensation'][0]['redline']['redline_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['employee_compensation'][0]['redline']['redline_effective_date'])) : '';
                                            }
                                        }
                                    }

                                    if (isset($userCommissionDetails['employee_compensation'][0]['upfront']) && isset($userCommissionDetails['employee_compensation'][0]['upfront'][0])) {
                                        $setterUpFront = @$userCommissionDetails['employee_compensation'][0]['upfront'];
                                    }

                                    if (isset($userCommissionDetails['withheld'])) {
                                        $setterWithHeld = @$userCommissionDetails['withheld']['withheld_amount'].' '.@$userCommissionDetails['withheld']['withheld_type'];
                                        $setterWithHeldEffectiveDate = @$userCommissionDetails['withheld']['withheld_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['withheld']['withheld_effective_date'])) : '';
                                    }
                                } elseif ($userCommissionDetails['main_role'] == 2) {
                                    $position = 'Closer';

                                    if (isset($userCommissionDetails['employee_compensation'][0]['commission'])) {
                                        $closerCommission = @$userCommissionDetails['employee_compensation'][0]['commission']['commission'].' '.@$userCommissionDetails['employee_compensation'][0]['commission']['commission_type'];
                                        $closerCommissionEffectiveDate = @$userCommissionDetails['employee_compensation'][0]['commission']['commission_effective_date'] ? date('m/d/Y', strtotime(@$userCommissionDetails['employee_compensation'][0]['commission']['commission_effective_date'])) : '';
                                    }

                                    if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                        if (isset($userCommissionDetails['employee_compensation'][0]['commission']['commission_type']) && $userCommissionDetails['employee_compensation'][0]['commission']['commission_type'] == 'percent') {
                                            if (isset($userCommissionDetails['employee_compensation'][0]['redline'])) {
                                                $closerRedLine = @$userCommissionDetails['employee_compensation'][0]['redline']['redline'].' '.@$userCommissionDetails['employee_compensation'][0]['redline']['redline_type'];
                                                $closerRedLineType = @$userCommissionDetails['employee_compensation'][0]['redline']['redline_amount_type'];
                                                $closerRedLineEffectiveDate = @$userCommissionDetails['employee_compensation'][0]['redline']['redline_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['employee_compensation'][0]['redline']['redline_effective_date'])) : '';
                                            }
                                        }
                                    }

                                    if (isset($userCommissionDetails['employee_compensation'][0]['upfront']) && isset($userCommissionDetails['employee_compensation'][0]['upfront'][0])) {
                                        $closerUpFront = @$userCommissionDetails['employee_compensation'][0]['upfront'];
                                    }

                                    if (isset($userCommissionDetails['withheld'])) {
                                        $closerWithHeld = @$userCommissionDetails['withheld']['withheld_amount'].' '.@$userCommissionDetails['withheld']['withheld_type'];
                                        $closerWithHeldEffectiveDate = @$userCommissionDetails['withheld']['withheld_effective_date'] ? date('m/d/Y', strtotime($userCommissionDetails['withheld']['withheld_effective_date'])) : '';
                                    }
                                }

                                if (isset($userCommissionDetails['override'])) {
                                    $override_effective_date = @$userCommissionDetails['override']['override_effective_date'];
                                    $direct_overrides_amount = @$userCommissionDetails['override']['direct_overrides_amount'];
                                    $direct_overrides_type = @$userCommissionDetails['override']['direct_overrides_type'];
                                    $indirect_overrides_amount = @$userCommissionDetails['override']['indirect_overrides_amount'];
                                    $indirect_overrides_type = @$userCommissionDetails['override']['indirect_overrides_type'];
                                    $office_overrides_amount = @$userCommissionDetails['override']['office_overrides_amount'];
                                    $office_overrides_type = @$userCommissionDetails['override']['office_overrides_type'];
                                    $office_stack_overrides_amount = @$userCommissionDetails['override']['office_stack_overrides_amount'];
                                }
                            }

                            $proCommission = [
                                $record->employee_id,
                                $record->first_name,
                                $record->last_name,
                                $record->email,
                                $record->mobile_no,
                                $record?->office?->State?->name ?? '',
                                $record?->office?->office_name ?? '',
                                $record?->departmentDetail?->name ?? '',
                                $record?->positionDetail?->position_name ?? '',
                                $userOrganization?->effective_date ? date('m/d/Y', strtotime($userOrganization?->effective_date)) : '',
                                $record?->managerDetail?->first_name.' '.$record?->managerDetail?->last_name,
                                $record?->teamsDetail?->team_name ?? '',
                                $userOrganization?->is_manager == 1 ? 'Yes' : 'No',
                                $position,
                                $userOrganization?->effective_date ? date('m/d/Y', strtotime($userOrganization?->effective_date)) : '',
                                $record?->recruiter?->first_name.' '.$record?->recruiter?->last_name,
                                $record?->additionalRecruiterOne?->first_name.' '.$record?->additionalRecruiterOne?->last_name,
                                $record?->additionalRecruiterTwo?->first_name.' '.$record?->additionalRecruiterTwo?->last_name,
                                implode(',', $additionalOffice),
                                $selfGenEffectiveDate,
                                $selfGenCommission,
                                $setterCommissionEffectiveDate,
                                $setterCommission,
                            ];

                            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                $redlinesData = [
                                    $setterRedLineEffectiveDate,
                                    $setterRedLine,
                                    $setterRedLineType,
                                ];
                                $proCommission = array_merge($proCommission, $redlinesData);
                            }

                            foreach ($mileStonesNames as $mileStone) {
                                $matchedUpfront = collect($setterUpFront)->first(fn ($upfront) => $upfront['name'] == $mileStone);
                                if ($matchedUpfront) {
                                    $setterUpFrontVal = ($matchedUpfront['upfront_pay_amount'] ?? '').' '.($matchedUpfront['upfront_sale_type'] ?? '');
                                    $setterUpFrontValEffectiveDate = ! empty($matchedUpfront['upfront_effective_date'])
                                        ? date('m/d/Y', strtotime($matchedUpfront['upfront_effective_date']))
                                        : '';
                                } else {
                                    $setterUpFrontVal = ' ';
                                    $setterUpFrontValEffectiveDate = ' ';
                                }
                                $proCommission = array_merge($proCommission, [$setterUpFrontValEffectiveDate, $setterUpFrontVal]);
                            }

                            $proCommission2 = [
                                $setterWithHeldEffectiveDate,
                                $setterWithHeld,
                                $closerCommissionEffectiveDate,
                                $closerCommission,
                            ];
                            $proCommission = array_merge($proCommission, $proCommission2);

                            if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                                $redlinesData = [
                                    $closerRedLineEffectiveDate,
                                    $closerRedLine,
                                    $closerRedLineType,
                                ];
                                $proCommission = array_merge($proCommission, $redlinesData);
                            }

                            foreach ($mileStonesNames as $mileStone) {
                                $matchedUpfront = collect($closerUpFront)->first(fn ($upfront) => $upfront['name'] == $mileStone);
                                if ($matchedUpfront) {
                                    $closerUpFrontVal = ($matchedUpfront['upfront_pay_amount'] ?? '').' '.($matchedUpfront['upfront_sale_type'] ?? '');
                                    $closerUpFrontValEffectiveDate = ! empty($matchedUpfront['upfront_effective_date'])
                                        ? date('m/d/Y', strtotime($matchedUpfront['upfront_effective_date']))
                                        : '';
                                } else {
                                    $closerUpFrontVal = ' ';
                                    $closerUpFrontValEffectiveDate = ' ';
                                }
                                $proCommission = array_merge($proCommission, [$closerUpFrontValEffectiveDate, $closerUpFrontVal]);
                            }

                            $proCommission3 = [
                                $closerWithHeldEffectiveDate,
                                $closerWithHeld,
                                @$override_effective_date ? date('m/d/Y', strtotime($override_effective_date)) : '',
                                @$direct_overrides_amount ? $direct_overrides_amount.' '.$direct_overrides_type : '',
                                @$indirect_overrides_amount ? $indirect_overrides_amount.' '.$indirect_overrides_type : '',
                                @$office_overrides_amount ? $office_overrides_amount.' '.$office_overrides_type : '',
                                @$office_stack_overrides_amount ? $office_stack_overrides_amount : '',
                                $record->created_at ? date('m/d/Y', strtotime($record->created_at)) : '',
                                ($record->probation_period && strtolower($record->probation_period) != 'none') ? $record->probation_period.' Days' : '',
                                $record->hiring_bonus_amount > 0 ? 'Yes' : 'No',
                                $record->period_of_agreement_start_date ? date('m/d/Y', strtotime($record->period_of_agreement_start_date)).($record->end_date ? ' - '.$record->end_date : '') : '',
                                $record->offer_expiry_date ? date('m/d/Y', strtotime($record->offer_expiry_date)) : '',
                            ];
                            $proCommission = array_merge($proCommission, $proCommission3);

                            // Adding fields in the last of excel by maintaining separate array
                            $proCommission4 = [
                                $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                                isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                                isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                                isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                                $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
                            ];
                            $proCommission = array_merge($proCommission, $proCommission4);

                            $proCommissionArr[] = $proCommission;
                        }
                        $proCommissionArr[] = $mileStonesNames;
                        $commission['Commission-'.$productName] = $proCommissionArr;
                    }
                    $response['commission'] = $commission;
                } elseif ($type == 'tax' && count($response['tax']) == 0) {
                    $tax = [];
                    foreach ($data as $record) {
                        $tax[] = [
                            $record->employee_id,
                            $record->first_name,
                            $record->last_name,
                            $record->email,
                            $record->mobile_no,
                            $record->entity_type,
                            $record->business_name,
                            $record->business_type,
                            $record->business_ein,
                            $record->social_sequrity_no,
                            $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                            isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                            isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                            isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                            $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
                        ];
                    }
                    $response['tax'] = $tax;
                } elseif ($type == 'banking' && count($response['banking']) == 0) {
                    $banking = [];
                    foreach ($data as $record) {
                        $banking[] = [
                            $record->employee_id,
                            $record->first_name,
                            $record->last_name,
                            $record->email,
                            $record->mobile_no,
                            $record->name_of_bank,
                            $record->account_no,
                            $record->routing_no,
                            $record->account_name,
                            $record->type_of_account,
                            $record?->stop_payroll && $record?->stop_payroll == 1 ? 'Disabled' : 'Enabled',
                            isUserDismisedOn($record->id, date('Y-m-d')) ? 'Disabled' : 'Active',
                            isUserTerminatedOn($record->id, date('Y-m-d')) ? 'Terminated' : 'Active',
                            isUserContractEnded($record->id) ? 'Contract Completed' : 'Active',
                            $record?->disable_login && $record?->disable_login == 1 ? 'Disabled' : 'Enabled',
                        ];
                    }
                    $response['banking'] = $banking;
                }
            }
        }

        return (new UserManagementMultiSheetExport($type, $response))->download('invoices.xlsx');
    }

    private function userOrganizationDetails($id)
    {
        $user = User::with('state', 'office', 'departmentDetail', 'positionDetail', 'subPositionDetail', 'recruiter', 'additionalDetail')->withoutGlobalScopes()->find($id);

        $userId = $user?->id;
        $effectiveDate = date('Y-m-d');
        $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $isManager) {
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $manager) {
            $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $userOrganization = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        $additionalOffice = [];
        foreach ($additionalLocations as $additionalLocation) {
            $officeId = $additionalLocation?->office?->id;
            $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'office_id' => $officeId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $additionalOverride) {
                $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'office_id' => $officeId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $additionalOffice[] = [
                'state_name' => $additionalLocation?->state?->name,
                'office_name' => $additionalLocation?->office?->office_name,
                'effective_date' => $additionalLocation->effective_date,
                'overrides_amount' => $additionalOverride?->office_overrides_amount ?? null,
                'overrides_type' => $additionalOverride?->office_overrides_type ?? null,
            ];
        }

        $additionalRecruiter = $user?->additionalDetail?->map(function ($recruiter) {
            return [
                'recruiter_first_name' => $recruiter?->additionalRecruiterDetail?->first_name,
                'recruiter_last_name' => $recruiter?->additionalRecruiterDetail?->last_name,
            ];
        })?->toArray();

        $wages = null;
        $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userWagesHistory) {
            $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($userWagesHistory) {
            $positionFrequency = PositionPayFrequency::with('frequencyType')->where('position_id', $userOrganization?->sub_position_id)->first();
            $frequency = $userWagesHistory?->pay_rate_type;
            if ($positionFrequency) {
                $frequency = $positionFrequency?->frequencyType?->name;
            }

            $wages = [
                'pay_type' => $userWagesHistory?->pay_type,
                'pay_rate' => $userWagesHistory?->pay_rate,
                'pay_rate_type' => $userWagesHistory?->pay_rate_type,
                'frequency_name' => $frequency,
                'pto_hours' => $userWagesHistory?->pto_hours,
                'unused_pto_expires' => $userWagesHistory?->unused_pto_expires,
                'expected_weekly_hours' => $userWagesHistory?->expected_weekly_hours,
            ];
        }

        $userAgreement = UserAgreementHistory::with('hiringBy')->where(['user_id' => $userId])->where('period_of_agreement', '<=', $effectiveDate)->orderBy('period_of_agreement', 'DESC')->orderBy('created_at', 'DESC')->first();
        if (! $userAgreement) {
            $userAgreement = UserAgreementHistory::with('hiringBy')->where(['user_id' => $userId])->where('period_of_agreement', '>=', $effectiveDate)->orderBy('period_of_agreement', 'ASC')->orderBy('created_at', 'DESC')->first();
        }
        $agreement = [
            'hired_date' => date('Y-m-d', strtotime($user?->created_at)),
            'probation_period' => ($userAgreement && $userAgreement?->probation_period != 'None') ? $userAgreement?->probation_period : null,
            'hiring_bonus_amount' => $userAgreement?->hiring_bonus_amount,
            'date_to_be_paid' => ($userAgreement && $userAgreement?->date_to_be_paid) ? $userAgreement?->date_to_be_paid : null,
            'period_of_agreement_start_date' => ($userAgreement && $userAgreement?->period_of_agreement) ? $userAgreement?->period_of_agreement : null,
            'end_date' => ($userAgreement && $userAgreement?->end_date) ? $userAgreement?->end_date : null,
            'offer_include_bonus' => $userAgreement?->offer_include_bonus,
            'offer_expiry_date' => $userAgreement?->offer_expiry_date,
            'is_background_verificaton' => $userAgreement?->is_background_verificaton,
            'hiring_signature' => $userAgreement?->hiring_signature,
            'hiring_by' => $userAgreement?->hiringBy ? $userAgreement?->hiringBy?->first_name.' '.$userAgreement?->hiringBy?->last_name : null,
        ];

        $deductions = [];
        $deductionEffectiveDate = null;
        $deductionHistory = UserDeductionHistory::select('user_id', 'effective_date')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
        if (! $deductionHistory) {
            $deductionHistory = UserDeductionHistory::select('user_id', 'effective_date')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->first();
        }
        if ($deductionHistory) {
            $deductionEffectiveDate = $deductionHistory?->effective_date;
            $userDeductions = UserDeductionHistory::with('costCenter')->where(['user_id' => $userId, 'effective_date' => $deductionHistory->effective_date])->get();
            $deductionPayPeriod = PayrollDeductions::select('user_id', 'pay_period_from', 'pay_period_to')->where('user_id', $userId)->orderBy('pay_period_from', 'DESC')->first();
            $costCenterIds = PositionCommissionDeduction::where('position_id', $user->sub_position_id)->pluck('cost_center_id')->toArray();
            foreach ($userDeductions as $userDeduction) {
                if ($deductionPayPeriod) {
                    $outstanding = PayrollDeductions::select('outstanding')->where(['user_id' => $userId, 'cost_center_id' => $userDeduction->cost_center_id, 'pay_period_from' => $deductionPayPeriod->pay_period_from, 'pay_period_to' => $deductionPayPeriod->pay_period_to])->first();
                }

                $checkOutstanding = isset($outstanding->outstanding) ? $outstanding->outstanding : 0;
                if (in_array($userDeduction->cost_center_id, $costCenterIds)) {
                    $isDelete = 0;
                } else {
                    $isDelete = 1;
                }
                if ($isDelete == 1 && $checkOutstanding == 0) {
                    continue;
                }

                $deductions[] = [
                    'deduction_type' => $userDeduction?->deduction_type ? $userDeduction?->deduction_type : '$',
                    'cost_center_name' => $userDeduction?->costCenter?->name,
                    'ammount_par_paycheck' => $userDeduction?->amount_par_paycheque ?? 0,
                    'outstanding' => $checkOutstanding,
                    'is_deleted' => $isDelete,
                ];
            }
        }

        $response = [
            'id' => $userId,
            'organization' => [
                'state_name' => $user?->state?->name,
                'office_name' => $user?->office?->office_name,
                'department_name' => $user?->departmentDetail?->name,
                'position_name' => $user?->positionDetail?->position_name,
                'sub_position_name' => $user?->subPositionDetail?->position_name,
                'is_manager' => $isManager?->is_manager ?? 0,
                'manager_name' => $manager?->user ? $manager?->user?->first_name.' '.$manager?->user?->last_name : null,
                'team_name' => $manager?->team?->team_name,
                'recruiter_name' => $user?->recruiter ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : null,
                'additional_recruter' => $additionalRecruiter,
                'self_gen_accounts' => $userOrganization?->self_gen_accounts ?? 0,
                'additional_locations' => $additionalOffice,
            ],
            'user_wages' => $wages,
            'agreement' => $agreement,
            'deduction' => $deductions,
            'deductionEffectiveDate' => $deductionEffectiveDate,
        ];

        return $response;
    }

    public function userCompensationDetails($id, $productId)
    {
        $userId = $id;
        $effectiveDate = date('Y-m-d');
        $employeeCompensation = [];
        $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $position = Positions::where('id', $userOrganization?->sub_position_id)->first();
        $corePositions = [];
        if ($position?->is_selfgen == '1') {
            $corePositions = [2, 3, null];
        } elseif ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
            $corePositions = [$position?->is_selfgen];
        } elseif ($position?->is_selfgen == '0') {
            $corePositions = [2];
        }

        foreach ($corePositions as $corePosition) {
            $upFronts = [];
            $userUpfront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userUpfront) {
                $userUpfront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $upfrontHistories = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition, 'upfront_effective_date' => $userUpfront?->upfront_effective_date])->get();
            foreach ($upfrontHistories as $upfrontHistory) {
                $upFronts[] = [
                    'name' => $upfrontHistory?->schema?->name,
                    'upfront_pay_amount' => $upfrontHistory?->upfront_pay_amount,
                    'upfront_sale_type' => $upfrontHistory?->upfront_sale_type,
                    'upfront_effective_date' => $upfrontHistory?->upfront_effective_date,
                ];
            }

            $redLine = null;
            $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $redLineHistory) {
                $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '>=', $effectiveDate)->orderBy('start_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            if ($redLineHistory) {
                $redLine = [
                    'redline' => $redLineHistory->redline,
                    'redline_type' => $redLineHistory->redline_type,
                    'redline_amount_type' => $redLineHistory->redline_amount_type,
                    'redline_effective_date' => $redLineHistory->start_date,
                ];
            }

            $userCommissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userCommissionHistory) {
                $userCommissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '>=', $effectiveDate)->orderBy('commission_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $employeeCompensation[] = [
                'core_position_id' => $corePosition,
                'redline' => $redLine,
                'commission' => [
                    'commission' => $userCommissionHistory?->commission,
                    'commission_type' => $userCommissionHistory?->commission_type,
                    'commission_effective_date' => $userCommissionHistory?->commission_effective_date,
                ],
                'upfront' => $upFronts,
            ];
        }

        $override = null;
        $additionalOffice = [];
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        foreach ($additionalLocations as $additionalLocation) {
            $officeId = $additionalLocation?->office?->id;
            $additionalOverride = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'product_id' => $productId, 'office_id' => $officeId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $additionalOverride) {
                $additionalOverride = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'product_id' => $productId, 'office_id' => $officeId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $additionalOffice[] = $additionalLocation?->office?->office_name;
        }

        $overrideHistory = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $overrideHistory) {
            $overrideHistory = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($overrideHistory) {
            $override = [
                'override_effective_date' => $overrideHistory?->override_effective_date,
                'direct_overrides_amount' => $overrideHistory?->direct_overrides_amount,
                'direct_overrides_type' => $overrideHistory?->direct_overrides_type,
                'indirect_overrides_amount' => $overrideHistory?->indirect_overrides_amount,
                'indirect_overrides_type' => $overrideHistory?->indirect_overrides_type,
                'office_overrides_amount' => $overrideHistory?->office_overrides_amount,
                'office_overrides_type' => $overrideHistory?->office_overrides_type,
                'office_stack_overrides_amount' => $overrideHistory?->office_stack_overrides_amount,
            ];
        }

        $withheld = null;
        $withheldHistory = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $withheldHistory) {
            $withheldHistory = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '>=', $effectiveDate)->orderBy('withheld_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($withheldHistory) {
            $withheld = [
                'withheld_amount' => $withheldHistory?->withheld_amount,
                'withheld_type' => $withheldHistory?->withheld_type,
                'withheld_effective_date' => $withheldHistory?->withheld_effective_date,
            ];
        }

        $effectiveSince = getLastEffectiveDates($userId, $effectiveDate, $productId);
        $effectiveDate = Carbon::parse($effectiveDate);
        $closestDate = null;
        $minDiff = PHP_INT_MAX;
        foreach ($effectiveSince as $date) {
            if ($date) {
                $currentDate = Carbon::parse($date);
                $diff = $effectiveDate->diffInSeconds($currentDate);

                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestDate = $date;
                }
            }
        }

        $response = [
            'id' => $userId,
            'effective_date' => $closestDate,
            'main_role' => $position?->is_selfgen,
            'sub_position_id' => $position?->id,
            'employee_compensation' => $employeeCompensation,
            'organization' => [
                'additional_locations' => $additionalOffice,
            ],
            'override' => $override,
            'withheld' => $withheld,
        ];

        return $response;
    }

    private function customSmartFieldsDetailByUserId($id)
    {
        $user_id = '';
        if (auth()->user()->is_super_admin) {
            $data = User::find($id);
        } else {
            $data = User::withoutGlobalScope('notTerminated')->find($id);
        }
        $user_id = $data->id;

        $documents = NewSequiDocsDocument::where([
            'category_id' => 101,
            'user_id' => $user_id,
            'is_active' => 1,
            'user_id_from' => 'users',
        ])->groupBy('user_id')->get();
        $documents->transform(function ($document) {
            $data = json_decode($document->smart_text_template_fied_keyval, true);

            return $data['placeholders'] ?? []; // Return only the placeholders array
        });

        return $documents->toArray();
    }
}

