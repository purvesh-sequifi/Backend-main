<?php

namespace App\Http\Controllers\API\Permission;

use App\Http\Controllers\Controller;
use App\Models\GroupMaster;
use App\Models\GroupPermissions;
use App\Models\GroupPolicies;
use App\Models\Notification;
use App\Models\Permissions;
use App\Models\PoliciesTabs;
use App\Models\Positions;
use App\Models\ProfileAccessPermission;
use App\Models\Roles;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GroupPermissionsController extends Controller
{
    use EmailNotificationTrait;
    use PushNotificationTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {

        $data = Roles::with(['grouppolicy' => function ($query) {
            $query->filterPolicies(); // Apply the custom scope here
        }])->orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'group_permission',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'group_permission',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function addGroupPermission(Request $request)
    {
        // return $request;
        $group_name = $request->group_name;
        $permissions = $request->group_data;
        $positionId = isset($request->position_ids) ? $request->position_ids : null;
        $profileAccessFor = isset($request->profile_access_for) ? $request->profile_access_for : null;
        $accountControl = isset($request->employee_account_control) ? $request->employee_account_control : null;

        $check = GroupMaster::where('name', $group_name)->get();
        if (count($check) > 0) {
            return response()->json([
                'ApiName' => 'add_permission',
                'status' => false,
                'message' => 'This group already exists.',
            ], 400);
        }

        $groupInsert = GroupMaster::create(['name' => $group_name]);
        $groupId = $groupInsert->id;

        // Profile Access Permission start
        if ($groupId) {

            if (! empty($positionId)) {
                foreach ($positionId as $pkey => $position_id) {
                    $profileaccess = [
                        'group_id' => $groupId,
                        'role_id' => 3,
                        'position_id' => $position_id,
                        'type' => 'position_access',
                    ];
                    ProfileAccessPermission::create($profileaccess);
                }
            }

            if (! empty($profileAccessFor)) {
                $profileaccess = [
                    'group_id' => $groupId,
                    'role_id' => 3,
                    'profile_access_for' => $profileAccessFor,
                    'type' => 'profile_access',
                ];
                ProfileAccessPermission::create($profileaccess);
            }

            if (! empty($accountControl)) {
                $profileaccess = [
                    'group_id' => $groupId,
                    'role_id' => 3,
                    'payroll_history' => isset($accountControl['payroll_history']) ? $accountControl['payroll_history'] : 0,
                    'reset_password' => isset($accountControl['reset_password']) ? $accountControl['reset_password'] : 0,
                    'type' => 'account_access',
                ];
                ProfileAccessPermission::create($profileaccess);
            }

        }
        // End Profile Access Permission

        foreach ($permissions as $key => $value) {
            $role_id = $value['role_id'];

            foreach ($value['group_policy'] as $key1 => $policy) {
                $policy_id = $policy['policy_id'];

                foreach ($policy['policy_tabs'] as $key2 => $tab) {
                    $policy_tab_id = $tab['policy_tab_id'];

                    if (count($tab['permission_id']) > 0) {
                        foreach ($tab['permission_id'] as $key3 => $permission_id) {
                            $data = GroupPermissions::create(
                                [
                                    'group_id' => $groupId,
                                    'role_id' => $role_id,
                                    'group_policies_id' => $policy_id,
                                    'policies_tabs_id' => $policy_tab_id,
                                    'permissions_id' => $permission_id,
                                ]
                            );
                        }
                    }
                }
            }
        }
        $check = GroupMaster::where('id', $groupId)->first();
        $superAdmin = User::where('is_super_admin', 1)->first();
        if ($superAdmin) {
            $data = Notification::create([
                'user_id' => $superAdmin->id,
                'type' => 'Group Permissions',
                'description' => 'Group Permissions Data by '.$superAdmin->first_name,
                'is_read' => 0,
            ]);

            $notificationData = [
                'user_id' => $superAdmin->id,
                'device_token' => $superAdmin->device_token,
                'title' => 'Group Permissions Data.',
                'sound' => 'sound',
                'type' => 'Group Permissions',
                'body' => 'Group Permissions Data by '.auth()->user()->first_name,
            ];
            $this->sendNotification($notificationData);
        }

        return response()->json([
            'ApiName' => 'add_group_permission',
            'status' => true,
            'message' => 'add Successfully.',
            // 'data' => $data,
        ], 200);
    }

    public function updateGroupPermission(Request $request)
    {
        // return $request;
        $groupId = $request->group_id;
        $group_name = $request->group_name;
        $permissions = $request->group_data;
        $positionId = isset($request->position_ids) ? $request->position_ids : null;
        $profileAccessFor = isset($request->profile_access_for) ? $request->profile_access_for : null;
        $accountControl = isset($request->employee_account_control) ? $request->employee_account_control : null;

        $check = GroupMaster::where('name', $group_name)->where('id', '!=', $groupId)->get();
        if (count($check) > 0) {
            return response()->json([
                'ApiName' => 'add_permission',
                'status' => false,
                'message' => 'This group already exists.',
            ], 400);
        }

        $data1 = GroupMaster::find($groupId);
        $data1->name = $group_name;
        $data1->save();

        // Profile Access Permission start
        if ($groupId) {
            ProfileAccessPermission::where(['group_id' => $groupId])->delete();

            if (! empty($positionId)) {
                foreach ($positionId as $pkey => $position_id) {
                    $profileaccess = [
                        'group_id' => $groupId,
                        'role_id' => 3,
                        'position_id' => $position_id,
                        'type' => 'position_access',
                    ];
                    ProfileAccessPermission::create($profileaccess);
                }
            }

            if (! empty($profileAccessFor)) {
                $profileaccess = [
                    'group_id' => $groupId,
                    'role_id' => 3,
                    'profile_access_for' => $profileAccessFor,
                    'type' => 'profile_access',
                ];
                ProfileAccessPermission::create($profileaccess);
            }

            if (! empty($accountControl)) {
                $profileaccess = [
                    'group_id' => $groupId,
                    'role_id' => 3,
                    'payroll_history' => isset($accountControl['payroll_history']) ? $accountControl['payroll_history'] : 0,
                    'reset_password' => isset($accountControl['reset_password']) ? $accountControl['reset_password'] : 0,
                    'type' => 'account_access',
                ];
                ProfileAccessPermission::create($profileaccess);
            }

        }
        // End Profile Access Permission

        $data2 = GroupPermissions::where('group_id', $groupId)->delete();

        // $groupInsert = GroupMaster::create(['name' => $group_name]);
        // $groupId = $groupInsert->id;

        foreach ($permissions as $key => $value) {
            $role_id = $value['role_id'];

            foreach ($value['group_policy'] as $key1 => $policy) {
                $policy_id = $policy['policy_id'];

                foreach ($policy['policy_tabs'] as $key2 => $tab) {
                    $policy_tab_id = $tab['policy_tab_id'];

                    if (count($tab['permission_id']) > 0) {
                        foreach ($tab['permission_id'] as $key3 => $permission_id) {
                            $data = GroupPermissions::create(
                                [
                                    'group_id' => $groupId,
                                    'role_id' => $role_id,
                                    'group_policies_id' => $policy_id,
                                    'policies_tabs_id' => $policy_tab_id,
                                    'permissions_id' => $permission_id,
                                ]
                            );
                        }
                    }
                }
            }
        }

        return response()->json([
            'ApiName' => 'update_permission',
            'status' => true,
            'message' => 'add Successfully.',
            // 'data' => $data,
        ], 200);
    }

    public function updateUserGroup(Request $request): JsonResponse
    {
        // echo"dasd";die;
        $group_id = $request->group_id;
        $user_id = $request->user_id;
        $data = User::where('id', $user_id)->first();
        if (! empty($data)) {
            $data->group_id = $group_id;
            $data->save();
        }

        return response()->json([
            'ApiName' => 'updateUserGroup',
            'status' => true,
            'message' => 'update user group Successfully.',
        ], 200);
    }

    public function delete_permission($id): JsonResponse
    {
        $position = Positions::where('group_id', $id)->first();
        if ($position) {
            return response()->json([
                'ApiName' => 'delete_permission',
                'status' => false,
                'message' => 'Sorry, you cannot delete this group as it\'s assigned to a position; please change the  position\'s group and try again.',
            ], 400);
        } else {
            $data1 = GroupMaster::find($id)->delete();
            $data = GroupPermissions::where('group_id', $id)->delete();

            return response()->json([
                'ApiName' => 'delete_permission',
                'status' => true,
                'message' => 'delete Successfully.',
            ], 200);
        }
    }

    public function get_permission($id)
    {
        $groupId = $id;
        $roledata = GroupPermissions::distinct()->select('role_id')->where('group_id', $groupId)->get();
        $sClearance = DB::table('crms')->where('name', 'S-Clearance')->first();
        $sequiCrm = DB::table('crms')->where('name', 'SequiCRM')->first();
        $data = [];
        foreach ($roledata as $key => $role) {
            $datarole = Roles::where('id', $role->role_id)->first();
            $data[$key] = $datarole;
            // Position Access Permission
            // if ($role->role_id == 3) {
            //     $positionAccess = ProfileAccessPermission::where(['group_id'=> $groupId, 'role_id'=> $role->role_id, 'type'=> 'position_access'])->pluck('position_id')->toArray();
            //     if (!empty($positionAccess)) {
            //         $data[$key]['position_ids'] = $positionAccess;
            //     }else{
            //         $data[$key]['position_ids'] = [];
            //     }

            //     $profileAccess = ProfileAccessPermission::where(['group_id'=> $groupId, 'role_id'=> $role->role_id, 'type'=> 'profile_access'])->first();
            //     $data[$key]['profile_access_for'] = isset($profileAccess['profile_access_for']) ? $profileAccess['profile_access_for'] : null;

            //     $accountAccess = ProfileAccessPermission::where(['group_id'=> $groupId, 'role_id'=> $role->role_id, 'type'=> 'account_access'])->first();
            //     $data[$key]['employee_account_control'] = [
            //             'payroll_history' => isset($accountAccess['payroll_history']) ? $accountAccess['payroll_history'] : 0,
            //             'reset_password' => isset($accountAccess['reset_password']) ? $accountAccess['reset_password'] : 0,
            //         ];

            // }
            // End Position Access Permission
            $group_policies = GroupPermissions::select('group_policies_id')->where('group_id', $groupId)->where('role_id', $role->role_id)->groupBy('group_policies_id')->get();
            $moduleData = [];

            foreach ($group_policies as $key1 => $module) {
                $module_id = $module->group_policies_id;
                $grouppolicies = GroupPolicies::where('id', $module_id)->first();

                if ($grouppolicies->policies == 'Sequi-CRM' && $sequiCrm?->status != 1) {
                    continue;
                } elseif ($grouppolicies->policies == 'S-Clearance' && $sClearance?->status != 1) {
                    continue;
                }

                $moduleData[$key1] = $grouppolicies;
                $data[$key]['groupPolicy'] = $moduleData;

                $tabData = GroupPermissions::select('policies_tabs_id')->where('group_id', $groupId)->where('group_policies_id', $module_id)->groupBy('policies_tabs_id')->get();

                $moduleData1 = [];
                foreach ($tabData as $key2 => $tab) {
                    $tab_id = $tab->policies_tabs_id;
                    $moduleTabData = PoliciesTabs::where('id', $tab_id)->first();
                    $moduleData1[$key2] = $moduleTabData;
                    $moduleData[$key1]['policyTab'] = $moduleData1;
                    $subData = GroupPermissions::where('group_id', $groupId)->where('policies_tabs_id', $tab_id)->get();

                    $moduleData2 = [];
                    foreach ($subData as $key3 => $sub) {
                        $submodule_id = $sub->permissions_id;
                        $submoduleData = Permissions::where('id', $submodule_id)->first();
                        if ($submoduleData) {
                            $moduleData2[$key3] = $submoduleData;
                        }
                        $moduleData1[$key2]['submodule'] = $moduleData2;
                    }
                }
            }

        }

        // Position Access Permission
        $data1 = [];
        if ($groupId) {
            $positionAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'position_access'])->pluck('position_id')->toArray();
            if (! empty($positionAccess)) {
                $data1['position_ids'] = $positionAccess;
            } else {
                $data1['position_ids'] = [];
            }

            $profileAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'profile_access'])->first();
            $data1['profile_access_for'] = isset($profileAccess['profile_access_for']) ? $profileAccess['profile_access_for'] : null;

            $accountAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'account_access'])->first();
            $data1['employee_account_control'] = [
                'payroll_history' => isset($accountAccess['payroll_history']) ? $accountAccess['payroll_history'] : 0,
                'reset_password' => isset($accountAccess['reset_password']) ? $accountAccess['reset_password'] : 0,
            ];

        }

        // End Position Access Permission
        // return $data;
        return response()->json([
            'ApiName' => 'get_permission',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'profile_access_config' => $data1,
        ], 200);
    }

    public function group_list()
    {

        $groupData = GroupPermissions::select('group_id')->with('groupmaster')->groupBy('group_id')->get();
        // return $groupData;
        $data = [];
        foreach ($groupData as $key => $group) {
            $memberTotal = User::where('group_id', $group->group_id)->count();
            $roleData = GroupPermissions::select('group_id', 'role_id')->with('grouprole')->where('group_id', $group->group_id)->groupBy('role_id')->get();

            $moduleData = [];
            if (count($roleData) > 0) {
                foreach ($roleData as $key1 => $role) {
                    $moduleData[$role->grouprole->name] = GroupPermissions::select('group_policies_id')->with('grouppolicydata')->where(['group_id' => $group->group_id, 'role_id' => $role->role_id])->groupBy('group_policies_id')->get();
                }
            }

            // return $moduleData;
            $data[$key] = [
                'group_id' => $group->group_id,
                'group_name' => $group->groupmaster->name,
                'members_count' => $memberTotal,
                'policies' => $moduleData,
            ];
        }

        return response()->json([
            'ApiName' => 'group_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function policies_list(Request $request)
    {
        // $data = GroupPolicies::with('policytabdata')->where('policies','<>','Dashboard')->get();
        $result = GroupPolicies::with('policytabdata');
        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('policies', 'LIKE', '%'.$request->input('search').'%');
            });
        }
        $data = $result->where('policies', '<>', 'Dashboard')->get();

        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'policies' => isset($data->policies) ? $data->policies : 'NA',
                'permissions_for' => isset($data->policytabdata) ? $data->policytabdata : 'NA',
            ];
        });

        return response()->json([
            'ApiName' => 'policies_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function old_show_permission($id): JsonResponse
    {
        // $permission = UserPermissions::with('permissionModule.permissionTab')->where('position_id', $id)->first();
        // $permission = UserPermissions::with('permissionModule','permissionTab')->where('position_id', $id)->first();

        $permission = UserPermissions::where('position_id', $id)->groupBy('module_id')->get();
        $data = [];
        foreach ($permission as $key => $module) {
            $module_id = $module->module_id;
            $moduleData = PermissionModules::where('id', $module_id)->first();
            $data[$key] = $moduleData;

            $tabData = UserPermissions::where('module_id', $module_id)->groupBy('tab_id')->get();

            $moduleData1 = [];
            foreach ($tabData as $key1 => $tab) {
                $tab_id = $tab->tab_id;
                $moduleTabData = PermissionTabs::where('id', $tab_id)->first();
                $moduleData1[$key1] = $moduleTabData;
                $data[$key]['tab'] = $moduleData1;
                $subData = UserPermissions::where('tab_id', $tab_id)->get();

                $moduleData2 = [];
                foreach ($subData as $key2 => $sub) {
                    $submodule_id = $sub->submodule_id;
                    $submoduleData = PermissionsubModules::where('id', $submodule_id)->first();
                    $moduleData2[$key2] = $submoduleData;
                    $moduleData1[$key1]['submodule'] = $moduleData2;
                }
            }
        }

        return response()->json([
            'ApiName' => 'show_permissions',
            'status' => true,
            'message' => 'get Successfully.',
            'data' => $data,
        ], 200);
    }

    public function role_list()
    {

        $data = Role::orderBy('id', 'asc')->get();
        // return $data;
        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'role_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'role_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    public function groupByUserList(Request $request): JsonResponse
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $id = $request->id;
        $datas = User::where('group_id', $id)->orderBy('first_name', 'ASC')->get();
        foreach ($datas as $key => $d) {
            if (isset($d->image) && $d->image != null) {
                $datas[$key]['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$d->image);
            } else {
                $datas[$key]['image_s3'] = null;
            }
        }
        $data = json_decode($datas);
        $data = paginate($data, $perpage);

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'group By User List',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'group By User List',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }
}
