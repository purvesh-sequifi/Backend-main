<?php

namespace App\Http\Controllers;

use App\Models\BackendSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyGlobalController extends Controller
{
    public function backendshow($id): JsonResponse
    {

        $status = BackendSetting::first();
        if ($status->status == '1') {
            return response()->json([
                'ApiName' => 'Login',
                'status' => true,
                'message' => 'User Logged In Successfully.',
                'data' => $status,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Login',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }

    }

    public function backendupdate(Request $request, $id)
    {
        // return $request;
        $status = BackendSetting::first();
        if ($status->status == '1') {
            $status->commission_withheld = $request['commission_withheld'];
            $status->maximum_withheld = $request['maximum_withheld'];
            $status->commission_type = $request['commission_type'];
            $status->save();

            return response()->json([
                'ApiName' => 'Login',
                'status' => true,
                'message' => 'changes Successfully.',
                'data' => $status,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'Login',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }
}
