<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\PermissionCheckTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadsValidatedRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralsController extends Controller
{
    use PermissionCheckTrait;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;

        // $routeName = Route::currentRouteName();
        //  $user = auth('api')->user()->position_id;
        //  $roleId = $user;
        //  $result = $this->checkPermission($roleId, '3', $routeName);
        //  if ($result == false)
        //  {
        //     $response = [
        //          'status' => false,
        //          'message' => 'this module not access permission.',
        //      ];
        //      print_r(json_encode($response));die();
        //  }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $lead = $this->lead->with('recruiter', 'state');

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $lead->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%');

            });
            // ->orWhereHas('additionalEmails', function ($query) use ($request)  {
            //     $query->where('email', 'like', '%' . $request->input('filter') . '%');
            // });
        }

        // $data = $lead->orderBy('id',$orderBy)->paginate(config('app.paginate', 15));

        // start lead data get type according by nikhil

        $data = $lead->where('type', 'referral')->orderBy('id', $orderBy)->paginate(config('app.paginate', 15));

        // end lead data get type according by nikhil

        // return $data;
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'Referrals_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'Referrals_list',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);

        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadsValidatedRequest $request): JsonResponse
    {
        // $recru = User::where('id',$request['recruiter_id'])->first();
        $user = auth('api')->user();
        // if(isset($recru) && $recru!='')
        // {
        $data = Lead::create(
            [
                'first_name' => $request['first_name'],
                'last_name' => $request['last_name'],
                'email' => $request['email'],
                'mobile_no' => $request['mobile_no'],
                'state_id' => $request['state_id'],
                'comments' => $request['comments'],
                'status' => $request['status'],
                'action_status' => $request['action_status'],
                'recruiter_id' => $user->id,
                'type' => 'Referral',
            ]
        );

        return response()->json([
            'ApiName' => 'add-referrals',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $data,
        ], 200);
        // }
        // else{
        //     return response()->json([
        //         'ApiName' => 'add-leads',
        //         'status' => false,
        //         'message' => 'Invalid Recruiter Id.',
        //     ], 200);
        // }

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
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        //
    }
}
