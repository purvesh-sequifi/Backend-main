<?php

namespace App\Http\Controllers\API\Hiring;

use App\Http\Controllers\Controller;
use App\Models\SeasonalUsersLog;
use App\Models\UserDismissHistory;
use App\Models\UserTerminateHistory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TerminateEmployeeController extends Controller
{
    public function terminateEmployee(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|exists:users,id',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();
            $userId = $request->userId;
            $effectiveDate = $request->effective_date;

            $terminated = checkTerminateFlag($userId, $effectiveDate);
            if ($terminated && $terminated->is_terminate) {
                return response()->json([
                    'ApiName' => 'terminateEmployee',
                    'success' => false,
                    'message' => 'Employee have been already terminated',
                ], 400);
            }

            UserTerminateHistory::updateOrCreate(['user_id' => $userId, 'terminate_effective_date' => $effectiveDate], ['is_terminate' => 1]);
            Artisan::call('ApplyHistoryOnUsersV2:update', ['user_id' => $userId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employee terminated.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            $SeasonalUsersLog = new SeasonalUsersLog;
            $SeasonalUsersLog->api = 'hiring/terminate-employee';
            $SeasonalUsersLog->response = $e;
            $SeasonalUsersLog->col1 = 'Exception';
            $SeasonalUsersLog->save();

            return response()->json([
                'success' => false,
                'message' => 'Employee can\'t terminate.',
            ], 400);
        }
    }

    public function dismissEmployee(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();
            $message = null;
            $userId = $request->userId;
            $effectiveDate = date('Y-m-d');
            
            // Check if user has ANY dismiss records (not using checkDismissFlag as it checks historical status)
            $existingDismissRecord = UserDismissHistory::where('user_id', $userId)->first();
            
            if ($existingDismissRecord) {
                // Employee has dismiss record(s), so enable them by deleting all records
                $message = 'Employee enabled.';
                UserDismissHistory::where('user_id', $userId)->delete();
            } else {
                // No dismiss record exists, so dismiss them by creating one
                $message = 'Employee disabled.';
                UserDismissHistory::create(['user_id' => $userId, 'effective_date' => $effectiveDate, 'dismiss' => UserDismissHistory::DISMISSED]);
            }
            
            Artisan::call('ApplyHistoryOnUsersV2:update', ['user_id' => $userId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            $SeasonalUsersLog = new SeasonalUsersLog;
            $SeasonalUsersLog->api = 'hiring/dismiss-employee';
            $SeasonalUsersLog->response = $e;
            $SeasonalUsersLog->col1 = 'Exception';
            $SeasonalUsersLog->save();

            return response()->json([
                'success' => false,
                'message' => 'Employee can\'t dismiss.',
            ], 400);
        }
    }
}
