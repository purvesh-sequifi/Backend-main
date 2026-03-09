<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\PositionValidatedRequest;
use App\Models\Positions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {

        $allData = Positions::select('positions.id', 'positions.position_name', 'positions.department_id', 'positions.parent_position', 'positions.compensation_plan_id', 'departments.name as department_name', 'compensation_plans.compensation_plan_name')
            ->leftJoin('departments', 'departments.id', 'positions.department_id')
            ->leftJoin('compensation_plans', 'compensation_plans.id', 'positions.compensation_plan_id')
            ->where('position_name', '!=', 'Super Admin')
            ->get();

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $allData,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PositionValidatedRequest $request): JsonResponse
    {

        if (! null == $request->all()) {

            $data = Positions::create(
                [
                    'position_name' => $request['position_name'],
                    'department_id' => $request['department_id'],
                    'parent_position' => $request['parent_position'],
                    'compensation_plan_id' => $request['compensation_plan_id'],
                ]
            );

            return response()->json([
                'ApiName' => 'add-position',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Add-position',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        //
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
     */
    public function update(Request $request, int $id): JsonResponse
    {

        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'position_name' => 'required',
                    'department_id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 200);
            }
            // dd($request);

            $position = Positions::find($id);
            $position->position_name = $request['position_name'];
            $position->department_id = $request['department_id'];
            $position->parent_position = $request['parent_position'];
            $position->compensation_plan_id = $request['compensation_plan_id'];
            $position->save();

            return response()->json([
                'ApiName' => 'update-position',
                'status' => true,
                'message' => 'update Successfully.',
                'data' => $position,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'update-position',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {

        // return 0;
        if (! null == $id) {
            $id = Positions::find($id);
            $id->delete();

            return response()->json([
                'ApiName' => 'delete-position',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'delete-position',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }
}
