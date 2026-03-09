<?php

namespace App\Http\Controllers\API\Hiring\Filter;

use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingFilterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {}

    public function recruiterFilter(Request $request, User $user): JsonResponse
    {
        $authUser = auth()->user();
        if (empty($request->state_id)) {
            $user = $user->newQuery();
            $filter = $request->input('filter');
            if (! empty($filter)) {
                if ($authUser->is_super_admin) {
                    $user->where(function ($query) use ($filter) {
                        $query->where('first_name', 'like', "%$filter%")
                            ->orWhere('last_name', 'like', "%$filter%")
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%$filter%"])
                            ->orWhere('email', 'like', "%$filter%")
                            ->orWhere('mobile_no', 'like', "%$filter%");
                    })->orWhereHas('positionDetail', function ($query) use ($filter) {
                        $query->where('position_name', 'like', "%$filter%");
                    })->orWhereHas('additionalEmails', function ($query) use ($filter) {
                        $query->where('email', 'like', "%$filter%");
                    })->with('positionpayfrequencies.frequencyType');
                } else {
                    $officeIds = array_merge(
                        AdditionalLocations::where('user_id', $authUser->id)->pluck('office_id')->toArray(),
                        [$authUser->office_id]
                    );

                    $user->where(function ($query) use ($filter, $authUser, $officeIds) {
                        $query->where(function ($sub) use ($filter, $authUser, $officeIds) {
                            $sub->where('first_name', 'like', "%$filter%")
                                ->orWhere('last_name', 'like', "%$filter%")
                                ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%$filter%"])
                                ->orWhere('email', 'like', "%$filter%")
                                ->orWhere('mobile_no', 'like', "%$filter%")
                                ->where('manager_id', $authUser->id)
                                ->whereNotNull('manager_id')
                                ->whereIn('office_id', $officeIds);
                        });
                    })->orWhereHas('positionDetail', function ($query) use ($filter) {
                        $query->where('position_name', 'like', "%$filter%");
                    })->orWhereHas('additionalEmails', function ($query) use ($filter) {
                        $query->where('email', 'like', "%$filter%");
                    })->with('positionpayfrequencies.frequencyType');
                }
            }
            $user->with('departmentDetail', 'subpositionDetail', 'positionDetail', 'managerDetail')->where('office_id', $authUser->office_id);
            $data = $user->get();

            return response()->json([
                'ApiName' => 'onboarding_employee_recruiter_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ]);
        }

        $result = User::where('state_id', 'like', "%{$request->state_id}%")
            ->with('departmentDetail', 'positionDetail', 'managerDetail', 'subpositionDetail')
            ->get();

        return response()->json([
            'ApiName' => 'onboarding_employee_recruiter_list',
            'status' => count($result) > 0,
            'message' => count($result) > 0 ? 'search Successfully.' : 'data not found',
            'data' => count($result) > 0 ? $result : null,
        ], count($result) > 0 ? 200 : 400);
    }
}
