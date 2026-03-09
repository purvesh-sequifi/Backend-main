<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\Sales\SaleMasterJobAwsLambda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AwsLambdaApiController extends Controller
{
    /**
     * Dispatch SaleMasterJobAwsLambda to the parlley queue
     */
    public function dispatchJobForSaleProcessFromAwsLambda(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'data_source_type' => 'required|string',
                'batch_size' => 'integer|nullable',
                'worker_queue' => 'string|nullable',
                'include_closer1_id_null' => 'boolean|nullable',
            ]);

            $workerQueue = $request->input('worker_queue', 'sales-process');
            $includeCloser1IdNull = $request->input('include_closer1_id_null', false);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data_source_type = $request->input('data_source_type');
            $batch_size = $request->input('batch_size', 100);

            // Log the job dispatch attempt
            Log::info("API: Dispatching SaleMasterJobAwsLambda for data source: {$data_source_type}");

            // Dispatch the job to the parlley queue
            dispatch((new SaleMasterJobAwsLambda($data_source_type, $batch_size, $workerQueue, $includeCloser1IdNull))->onQueue($workerQueue));

            Log::info("API: SaleMasterJobAwsLambda dispatched successfully for data source: {$data_source_type}");

            return response()->json([
                'status' => true,
                'message' => 'SaleMasterJobAwsLambda dispatched successfully',
                'data' => [
                    'data_source_type' => $data_source_type,
                    'batch_size' => $batch_size,
                    'queue' => $workerQueue,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("API: Error dispatching SaleMasterJobAwsLambda: {$e->getMessage()}", [
                'data_source_type' => $request->input('data_source_type', 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to dispatch job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
