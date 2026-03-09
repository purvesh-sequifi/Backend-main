<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Positions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    public function dropdown(): JsonResponse
    {
        $data = Department::orderBy('id', 'ASC')->get();

        return response()->json([
            'ApiName' => 'drop-down-department',
            'status' => true,
            'message' => 'get Department data .',
            'data' => $data,
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $excludePosition = [1];
        if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
            $excludePosition = [1, 2, 3];
        }

        $departments = Department::where('parent_id', null)->with('subdepartmant');
        if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $departments->Where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%'.$search.'%');
            });
            $departments->orWhereHas('subdepartmant', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            });
        }

        $perPage = 10;
        if (isset($request->perpage) && $request->perpage != '') {
            $perPage = $request->perpage;
        }

        // $departments = $departments->with('subdepartmant')->paginate(env('PAGINATE'));
        $departments = $departments->with('subdepartmant')->get();

        $responseData = [];
        foreach ($departments as $department) {
            $PositionId = Positions::where('department_id', $department->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('id')->toArray();
            $userCount = User::whereIn('sub_position_id', $PositionId)->count();
            $Positions = Positions::where('department_id', $department->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->count();
            $Positionname = Positions::where('department_id', $department->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('position_name')->toArray();
            $subdepartmant = Department::where('parent_id', $department->id)->get();

            $subdepartmants = [];
            if (count($subdepartmant) > 0) {
                foreach ($subdepartmant as $value) {
                    $dPositionId = Positions::where('department_id', $value->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('id')->toArray();
                    $duserCount = User::whereIn('sub_position_id', $dPositionId)->count();
                    $dpositions = Positions::where('department_id', $value->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->count();
                    $positionNames = Positions::where('department_id', $value->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('position_name');
                    $subdepartmant1 = Department::where('parent_id', $value->id)->get();
                    $subdepartmants1 = [];
                    if (count($subdepartmant1) > 0) {
                        foreach ($subdepartmant1 as $key1 => $value1) {
                            $dPositionId1 = Positions::where('department_id', $value1->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('id')->toArray();
                            $duserCount1 = User::whereIn('sub_position_id', $dPositionId1)->count();
                            $dpositions1 = Positions::where('department_id', $value1->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->count();
                            $positionNames1 = Positions::where('department_id', $value1->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('position_name');
                            $subdepartmant2 = Department::where('parent_id', $value1->id)->get();
                            $subdepartmants2 = [];
                            if (count($subdepartmant2) > 0) {
                                foreach ($subdepartmant2 as $key2 => $value2) {
                                    $dPositionId2 = Positions::where('department_id', $value2->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('id')->toArray();
                                    $duserCount2 = User::whereIn('sub_position_id', $dPositionId2)->count();
                                    $dpositions2 = Positions::where('department_id', $value2->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->count();
                                    $positionNames2 = Positions::where('department_id', $value1->id)->whereNotIn('id', $excludePosition)->where('position_name', '!=', 'Super Admin')->pluck('position_name');
                                    $subdepartmants2[] = [
                                        'id' => $value2->id,
                                        'name' => $value2->name,
                                        'parent_id' => $value2->parent_id,
                                        'position_count' => $dpositions2,
                                        'positions_name' => $positionNames2,
                                        'people_count' => $duserCount2,
                                    ];
                                }
                            }

                            $subdepartmants1[] = [
                                'id' => $value1->id,
                                'name' => $value1->name,
                                'parent_id' => $value1->parent_id,
                                'position_count' => $dpositions1,
                                'people_count' => $duserCount1,
                                'positions_name' => $positionNames1,
                                'subdepartments' => @$subdepartmants2,
                            ];
                        }
                    }

                    $subdepartmants[] = [
                        'id' => $value->id,
                        'name' => $value->name,
                        'parent_id' => $value->parent_id,
                        'position_count' => $dpositions,
                        'people_count' => $duserCount,
                        'positions_name' => $positionNames,
                        'subdepartments' => $subdepartmants1,
                    ];
                }
            }

            $responseData[] = [
                'id' => $department->id,
                'name' => $department->name,
                'parent_id' => $department->parent_id,
                'position_count' => $Positions,
                'positions_name' => $Positionname,
                'people_count' => $userCount,
                'subdepartments' => $subdepartmants,
            ];
        }

        $responseData = $this->paginate($responseData, $perPage);

        return response()->json([
            'ApiName' => 'list-department',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $responseData,
        ]);
    }

    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function store(Request $request): JsonResponse
    {
        //    dd($request);
        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    // 'parent_id' => 'required',
                    // 'city_id' => 'required',
                    'parent_id' => 'nullable|exists:departments,id',
                ],
                [
                    'name.required' => 'The department name is required.',
                    'parent_id.exists' => 'The selected parent department does not exist.',
                ]
            );
            if (! $Validator->fails()) {
                if (is_null($request->parent_id)) {
                    $existingParent = Department::where('name', $request->name)->whereNull('parent_id')->first();
                    if ($existingParent) {
                        return response()->json([
                            'ApiName' => 'add-department',
                            'status' => false,
                            'message' => 'Department with this name already exists.',
                        ], 400);
                    }
                } else {
                    $existingSub = Department::where('name', $request->name)->where('parent_id', $request->parent_id)->first();
                    if ($existingSub) {
                        return response()->json([
                            'ApiName' => 'add-department',
                            'status' => false,
                            'message' => 'A sub-department with this name already exists under the selected parent department.',
                        ], 400);
                    }

                    $existingParentWithSameName = Department::where('name', $request->name)->whereNull('parent_id')->first();
                    if ($existingParentWithSameName) {
                        return response()->json([
                            'ApiName' => 'add-department',
                            'status' => false,
                            'message' => 'A sub-department cannot have the same name as an existing parent department.',
                        ], 400);
                    }
                }
            } else {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            // if ($Validator->fails()) {
            //     return response()->json(['error' => $Validator->errors()], 200);
            // }
            $data = Department::create(
                [
                    'name' => $request['name'],
                    'parent_id' => $request['parent_id'],
                ]
            );

            return response()->json([
                'ApiName' => 'add-department',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Add-department',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        if ($request->id) {
            $id = Department::find($request->id)->delete();

            return response()->json([
                'ApiName' => 'delete-department',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'delete-department',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function update(Request $request)
    {
        // Ensure the request data is not empty
        if ($request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    // 'parent_id' => 'required',
                    'id' => 'required',
                ]
            );

            // If validation fails, return the error response
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 422);
            }

            if (is_null($request->parent_id)) {
                $existingParent = Department::where('name', $request->name)->where('id', '!=', $request->id)->whereNull('parent_id')->first();
                if ($existingParent) {
                    return response()->json([
                        'ApiName' => 'add-department',
                        'status' => false,
                        'message' => 'Department with this name already exists.',
                    ], 400);
                }
            } else {
                $existingSub = Department::where('name', $request->name)->where('id', '!=', $request->id)->where('parent_id', $request->parent_id)->first();
                if ($existingSub) {
                    return response()->json([
                        'ApiName' => 'add-department',
                        'status' => false,
                        'message' => 'A sub-department with this name already exists under the selected parent department.',
                    ], 400);
                }

                $existingParentWithSameName = Department::where('name', $request->name)->where('id', '!=', $request->id)->whereNull('parent_id')->first();
                if ($existingParentWithSameName) {
                    return response()->json([
                        'ApiName' => 'add-department',
                        'status' => false,
                        'message' => 'A sub-department cannot have the same name as an existing parent department.',
                    ], 400);
                }
            }

            // Attempt to find the department by ID
            $department = Department::find($request['id']);

            // If department is not found, return a not found response
            if (! $department) {
                return response()->json([
                    'ApiName' => 'update-department',
                    'status' => false,
                    'message' => 'Department not found.',
                ], 404);
            }

            // Update the department fields and save
            $department->name = $request['name'];
            $department->save();

            // Return success response
            return response()->json([
                'ApiName' => 'update-department',
                'status' => true,
                'message' => 'Update successful.',
            ], 200);
        } else {
            // Return error response for empty request
            return response()->json([
                'ApiName' => 'update-department',
                'status' => false,
                'message' => 'Request data is empty.',
                'data' => null,
            ], 400);
        }
    }

    public function departmentPeople(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $Validator = Validator::make($request->all(), [
            'department_id' => 'required|exists:departments,id',
        ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $departmentPeoples = User::with('positionDetail.departmentDetail', 'office')
            ->whereHas('positionDetail.departmentDetail', function ($q) use ($request) {
                $q->where('id', $request->department_id);
            })->when($request->has('search') && ! empty($request->input('search')), function ($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->search.'%')
                    ->orWhere('last_name', 'like', '%'.$request->search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$request->search.'%']);
            })->when($request->has('position_id') && ! empty($request->input('position_id')), function ($q) use ($request) {
                $q->whereHas('positionDetail', function ($q) use ($request) {
                    $q->where('id', $request->position_id);
                });
            })->when($request->has('office_id') && ! empty($request->input('office_id')), function ($q) use ($request) {
                $q->where('office_id', $request->office_id);
            })->paginate($perpage);

        $departmentPeoples->getCollection()->transform(function ($departmentPeople) {
            $departmentPeople = $departmentPeople->toArray();

            if (isset($departmentPeople['image']) && $departmentPeople['image'] != null) {
                $departmentPeople['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$departmentPeople['image']);
            } else {
                $departmentPeople['image_s3'] = null;
            }

            $departmentPeople['position_name'] = @$departmentPeople['position_detail']['position_name'];
            $departmentPeople['department_name'] = @$departmentPeople['position_detail']['department_detail']['name'];

            unset($departmentPeople['position_detail']);

            $departmentPeople['office_name'] = @$departmentPeople['office']['office_name'];

            unset($departmentPeople['position_detail']);
            unset($departmentPeople['office']);

            return $departmentPeople;
        });

        return response()->json([
            'ApiName' => 'departmentPeople',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $departmentPeoples,
        ], 200);
    }
}
