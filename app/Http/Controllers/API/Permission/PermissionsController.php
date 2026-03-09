<?php

namespace App\Http\Controllers\API\Permission;

use App\Http\Controllers\Controller;
use App\Models\PermissionModules;
use App\Models\PermissionsubModules;
use App\Models\PermissionTabs;
use App\Models\Positions;
use App\Models\UserPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {

        $data = Positions::where('position_name', '!=', 'Super Admin')->orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'positions_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'positions_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // dd($request);
        $position_id = $request->position_id;
        $permissions = $request->permissions;

        $check = UserPermissions::where('position_id', $position_id)->get();
        if (count($check) > 0) {
            return response()->json([
                'ApiName' => 'permissions_add',
                'status' => false,
                'message' => 'This permission already exists.',
            ], 200);
        }

        foreach ($permissions as $key => $value) {
            $module_id = $value['module_id'];

            foreach ($value['access_id'] as $key1 => $tab) {

                $sub_module_id = $tab['sub_module_id'];
                foreach ($tab['permission_id'] as $key2 => $permission_id) {
                    $data = UserPermissions::create(
                        [
                            'position_id' => $position_id,
                            'module_id' => $module_id,
                            'sub_module_id' => $sub_module_id,
                            'parmission_id' => $permission_id,
                            'status' => 1,
                        ]
                    );

                }

            }

        }

        return response()->json([
            'ApiName' => 'permissions_add',
            'status' => true,
            'message' => 'add Successfully.',
            // 'data' => $data,
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     */
    public function show($id): JsonResponse
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

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Models\User  $user
     */
    public function update(Request $request, $id): JsonResponse
    {
        $position_id = $request->position_id;
        $permissions = $request->permissions;

        $delete = UserPermissions::where('position_id', $id)->delete();

        foreach ($permissions as $key => $value) {
            $module_id = $value['module_id'];

            foreach ($value['access_id'] as $key1 => $tab) {

                $sub_module_id = $tab['sub_module_id'];
                foreach ($tab['permission_id'] as $key2 => $permissionId) {
                    $data = UserPermissions::create(
                        [
                            'position_id' => $position_id,
                            'module_id' => $module_id,
                            'sub_module_id' => $sub_module_id,
                            'parmission_id' => $permissionId,
                            'status' => 1,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'ApiName' => 'permissions_update',
            'status' => true,
            'message' => 'update Successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     */
    public function destroy($id): JsonResponse
    {
        $data = UserPermissions::where('position_id', $id)->delete();

        return response()->json([
            'ApiName' => 'permissions_delete',
            'status' => true,
            'message' => 'delete Successfully.',
            'data' => $data,
        ], 200);
    }

    public function postion_list(): JsonResponse
    {

        $data = Positions::where('position_name', '!=', 'Super Admin')->orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'positions_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'positions_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    public function module_list(): JsonResponse
    {

        $data = PermissionModules::orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'module_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'module_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    public function submodule_list(Request $request): JsonResponse
    {
        $module_id = $request->module_id;
        $data = PermissionTabs::where('module_id', $module_id)->orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'submodule_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'submodule_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    public function permissionmodule_list(Request $request)
    {
        $module_id = $request->module_id;
        $submodule_id = $request->submodule_id;
        $data = PermissionsubModules::where('module_id', $module_id)->where('module_tab_id', $submodule_id)->orderBy('id', 'asc')->get();

        if (count($data) > 0) {

            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'submodule' => $data->submodule,
                    'action' => $data->action,
                ];
            });

            return response()->json([
                'ApiName' => 'permissionmodule_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'permissionmodule_list',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }
    }

    public function sendOnesignalPushNotificationios11(Request $request)
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

    public function sendOnesignalPushNotificationios12(Request $request)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{"app_id":"string","included_segments":["string"],"external_id":"string","contents":{"en":"English or Any Language Message","es":"Spanish Message"},"name":"INTERNAL_CAMPAIGN_NAME","send_after":"string","delayed_option":"string","delivery_time_of_day":"string","throttle_rate_per_minute":0}',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic YOUR_REST_API_KEY',
                'accept: application/json',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:'.$err;
        } else {
            echo $response;
        }

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
            CURLOPT_MAXREDIRS => 10,
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
        // return $response;die;
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo 'cURL Error #:'.$err;
        } else {
            echo $response;
        }

    }
}
