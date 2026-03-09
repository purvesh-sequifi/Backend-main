<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CostCenterValidatedRequest;
use App\Models\CostCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    private $costCenter;

    public function __construct(CostCenter $costCenter)
    {
        $this->costCenter = $costCenter;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->perpage ?? 10;
        $costCenters = CostCenter::with('chields')->where('parent_id', null)
            ->when($request->has('search') && ! empty($request->input('search')), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('code', 'LIKE', '%'.$request->input('search').'%');
                });
            })->when($request->filter == 'active', function ($q) {
                $q->where('status', 1);
            })->when($request->filter == 'inactive', function ($q) {
                $q->where('status', 0);
            })->orderBy('id', 'Asc')->paginate($perPage);

        return response()->json(['status' => true, 'message' => 'Successfully.', 'costCenters' => $costCenters]);
    }

    public function store(CostCenterValidatedRequest $request): JsonResponse
    {
        if ($request->parent_id) {
            if (CostCenter::where(['id' => $request->parent_id, 'status' => '1'])->first()) {
                $this->costCenter = CostCenter::create([
                    'parent_id' => $request->parent_id,
                    'name' => $request->name,
                    'code' => $request->code,
                    'description' => $request->description,
                    'status' => 1,
                ]);
                $this->costCenter->save();
            } else {
                return response()->json(['status' => false, 'message' => 'Parent cost head is inactive or does not exists. Please select another.'], 400);
            }
        } else {
            $this->costCenter = CostCenter::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'status' => 1,
            ]);
            $this->costCenter->save();
        }

        return response()->json(['status' => true, 'message' => 'Add Successfully.', 'cost_center_id' => $this->costCenter->id]);
    }

    public function show($id): JsonResponse
    {
        if (! $costCenter = CostCenter::where(['id' => $id])->first()) {
            return response()->json(['status' => false, 'message' => 'Sorry the Cost Center you are looking for is not found.'], 400);
        }

        return response()->json(['status' => true, 'message' => 'Show Successfully.', 'costCenter' => $costCenter]);
    }

    public function update(CostCenterValidatedRequest $request, $id): JsonResponse
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('cost_centers')->ignore($id),
            ],
            'code' => [
                'required',
                Rule::unique('cost_centers')->ignore($id),
            ],
        ]);

        $this->costCenter = CostCenter::find($id);
        if (! $this->costCenter) {
            return response()->json(['status' => false, 'message' => 'Sorry the Cost Center you are looking for is not found.'], 400);
        }

        if ($request->parent_id) {
            $parent = CostCenter::where(['id' => $request->parent_id, 'status' => '1'])->first();
            if ($parent || $this->costCenter->parent_id == $request->parent_id) {
                $this->costCenter->parent_id = $request->parent_id;
                $this->costCenter->name = $request->name;
                $this->costCenter->code = $request->code;
                $this->costCenter->description = $request->description;
                $this->costCenter->update();
            } else {
                return response()->json(['status' => false, 'message' => 'Parent cost head is inactive. Please select another.'], 400);
            }
        } else {
            $this->costCenter->name = $request->name;
            $this->costCenter->code = $request->code;
            $this->costCenter->description = $request->description;
            $this->costCenter->update();
        }

        return response()->json(['status' => true, 'message' => 'Updated Successfully.', 'cost_center_id' => $this->costCenter->id]);
    }

    public function disableCostCenter(Request $request): JsonResponse
    {
        $costCenter = CostCenter::find($request->id);
        if (! $costCenter) {
            return response()->json(['status' => false, 'message' => 'Sorry the Cost Center you are looking for is not found.'], 400);
        }

        if ($costCenter->parent_id) {
            if (! CostCenter::where(['id' => $costCenter->parent_id, 'status' => '1'])->first()) {
                return response()->json(['status' => false, 'message' => 'Sorry the parent is inactive for this child.'], 400);
            }

            $parent = CostCenter::where('id', $costCenter->id)->first();
            if ($parent) {
                $costCenter->status = $request->status;
                $costCenter->update();
            } else {
                return response()->json(['status' => false, 'message' => 'Sorry the parent is inactive for this child.'], 400);
            }
        } else {
            if ($request->status == 0) {
                $parent_id = $request->id;

                $child = CostCenter::where('id', $parent_id)->first();
                $child->status = $request->status;
                $child->update();

                CostCenter::where('parent_id', $parent_id)->update(['status' => 0]);
            } else {
                $parent = CostCenter::find($request->id);
                $parent->status = $request->status;
                $parent->update();
            }
        }

        return response()->json(['status' => true, 'message' => 'Disable CostCenter Successfully.', 'cost_center_id' => $this->costCenter->id]);
    }
}
