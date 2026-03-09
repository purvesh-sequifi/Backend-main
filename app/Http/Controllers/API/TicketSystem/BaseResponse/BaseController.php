<?php

namespace App\Http\Controllers\API\TicketSystem\BaseResponse;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class BaseController extends Controller
{
    public function successResponse(string $message, string $apiName = '', string|array|object $data = '', int $status = 200)
    {
        $response = [
            'ApiName' => $apiName,
            'status' => true,
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
            'ApiName' => $apiName,
            'status' => false,
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
            abort($this->errorResponse('Validator Failed!!', 'Priority Store', $validator->errors(), 422));
        }
    }
}
