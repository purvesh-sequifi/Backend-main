<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BaseController extends Controller
{
    public function successResponse(string $message, string $apiName = '', string|array|object $data = '', int $status = 200)
    {
        $response = [
            'status' => true,
            'ApiName' => $apiName,
            'message' => $message,
        ];

        if (! empty($data)) {
            $response['data'] = $data;
        }

        abort(response()->json($response, $status));
    }

    public function errorResponse(string $message, string $apiName = '', string|array|object $data = '', int $status = 500)
    {
        $response = [
            'status' => false,
            'ApiName' => $apiName,
            'message' => $message,
        ];

        if (! empty($data)) {
            $response['data'] = $data;
        }

        abort(response()->json($response, $status));
    }

    public function checkValidations($data, array $rules, array $message = [])
    {
        $validator = Validator::make($data, $rules, $message);

        if ($validator->fails()) {
            $backtrace = debug_backtrace();
            $callingFunction = $backtrace[1]['function'] ?? 'Unknown';

            $response = [
                'status' => false,
                'message' => $validator->errors()->first(), // to show error in FE
                // 'message' => 'Validator Failed!!',
                'ApiName' => $callingFunction,
                'data' => $validator->errors(),
            ];

            abort(response()->json($response, 400));
        }
    }
}
