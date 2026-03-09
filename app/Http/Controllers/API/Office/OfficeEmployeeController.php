<?php

namespace App\Http\Controllers\API\Office;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeEmployeeController extends Controller
{
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        $data['total_setter'] = $setter = $this->user->where('type', 'setter')->count();
        $data['active_closer'] = $closer = $this->user->where('type', 'closer')->count();
        $data['manager'] = $manager = $this->user->where('type', 'manager')->count();
        $user = $this->user->newQuery();
        $user->select('id', 'first_name', 'image', 'last_name', 'email', 'position_id', 'mobile_no', 'location', 'created_at')->with('lastHiredLeads');

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                return $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%');
            })
                ->orWhereHas('additionalEmails', function ($query) use ($request) {
                    $query->where('email', 'like', '%'.$request->input('filter').'%');
                });

            // return $query;
        }

        $data['office_employee_list'] = $user->orderBy('id', $orderBy)->paginate(config('app.paginate', 15));

        return response()->json([
            'ApiName' => 'office_employee_api',
            'status' => true,
            'message' => 'successfully',
            'data' => $data,
        ], 200);
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
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
