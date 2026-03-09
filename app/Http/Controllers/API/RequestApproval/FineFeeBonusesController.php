<?php

namespace App\Http\Controllers\API\RequestApproval;

use App\Http\Controllers\Controller;
use App\Models\FineFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FineFeeBonusesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // lis of request
    public function index(): JsonResponse
    {

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getfinefee()
    {
        $data = FineFee::with('user')->where('type', 'Fine')->orWhere('type', 'Fee')->get();
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'name' => isset($data->user->first_name,$data->user->last_name) ? $data->user->first_name.' '.$data->user->last_name : 'NA',
                'image' => isset($data->user->image) ? $data->user->image : 'NA',
                'type' => isset($data->type) ? $data->type : 'NA',
                'amount' => isset($data->amount) ? $data->amount : 'NA',
                'date' => isset($data->date) ? $data->date : 'NA',
                'description' => isset($data->description) ? $data->description : 'NA',
            ];
        });

        return response()->json([
            'ApiName' => 'list-fine/fee',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getbonuses()
    {
        $data = FineFee::with('user')->where('type', 'Bonuses')->orWhere('type', 'Incentive')->get();
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'name' => isset($data->user->first_name,$data->user->last_name) ? $data->user->first_name.' '.$data->user->last_name : 'NA',
                'image' => isset($data->user->image) ? $data->user->image : 'NA',
                'type' => isset($data->type) ? $data->type : 'NA',
                'amount' => isset($data->amount) ? $data->amount : 'NA',
                'date' => isset($data->date) ? $data->date : 'NA',
                'description' => isset($data->description) ? $data->description : 'NA',
            ];
        });

        return response()->json([
            'ApiName' => 'list-fine/fee',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    // store request
    public function store(Request $request)
    {
        // return $request;
        FineFee::create([
            'employee_id' => $request->employee_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'date' => $request->date,
            'description' => $request->description,
        ]);

        return response()->json([
            'ApiName' => 'add-fine-fee',
            'status' => true,
            'message' => 'Successfully',
        ], 200);
        // }

    }
}
