<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\PermissionCheckTrait;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\OnboardingEmployees;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class HiringProgressController extends Controller
{
    use PermissionCheckTrait;

    public function __construct(OnboardingEmployees $user, Lead $lead)
    {
        $this->user = $user;
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
    public function indexOld(Request $request)
    {
        $data6 = $totalLead = $this->lead->count();
        $data7 = $activeLead = $this->lead->where('status', 'FollowUp')->count();

        if (! isset($request->from_date) || ! isset($request->to_date)) {

            $recruiter_ids = Lead::pluck('recruiter_id')->toArray();
            $data = User::whereIn('id', $recruiter_ids)->paginate(10);
            $stateId = $request->state_id;
            // $last_hired_data = OnboardingEmployees::where('manager_id', $data->id)->orderBy('id', 'desc')->first();
            $data->transform(function ($data) {

                $last_hired_data = Lead::where('recruiter_id', $data->id)->orderBy('id', 'desc')->first();

                $active = Lead::where('recruiter_id', $data->id)->where('status', 'FollowUp')->count();
                $hired = Lead::where('recruiter_id', $data->id)->where('status', 'hired')->count();
                $notInterested = Lead::where('recruiter_id', $data->id)->where('status', 'Not Interested')->count();

                // $lastDate = isset($last_hired_data)?$last_hired_data->created_at->format('m-d-Y'):null;
                return [
                    'id' => $data->id,
                    'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : 'NA',
                    'image' => $data->image,
                    'hired' => $hired,
                    'not_interested' => $notInterested,
                    'active_lead' => $active,
                    // 'last_hired' => isset($last_hired_data->created_at)?$last_hired_data->created_at->format('m/d/Y'):NULL,
                    'conversion_rate' => 50 .'%',
                ];
            });

        } else {
            // echo "das";die;
            $fromDate = $request->from_date;
            $endDate = $request->to_date;
            $recruiter_ids = Lead::pluck('recruiter_id')->toArray();
            $data = User::whereIn('id', $recruiter_ids)->paginate(10);
            $data->transform(function ($data) use ($fromDate, $endDate) {
                $last_hired_data = Lead::where('recruiter_id', $data->id)->whereBetween('created_at', [$fromDate, $endDate])->orderBy('id', 'desc')->first();
                $active = Lead::where('recruiter_id', $data->id)->whereBetween('created_at', [$fromDate, $endDate])->where('status', 'FollowUp')->count();
                $hired = Lead::where('recruiter_id', $data->id)->whereBetween('created_at', [$fromDate, $endDate])->where('status', 'hired')->count();
                $notInterested = Lead::where('recruiter_id', $data->id)->whereBetween('created_at', [$fromDate, $endDate])->where('status', 'Not Interested')->count();
                $lastDate = isset($last_hired_data) ? $last_hired_data->created_at->format('m-d-Y') : null;

                return [
                    'id' => $data->id,
                    'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : 'NA',
                    'image' => $data->image,
                    'hired' => $hired,
                    'not_interested' => $notInterested,
                    'active_lead' => $active,
                    'last_hired' => $lastDate,
                    'conversion_rate' => '0%',
                ];

            });
        }

        return response()->json([
            'ApiName' => 'hiring_progress_api',
            'status' => true,
            'message' => 'successfully',
            'total_lead' => $totalLead,
            'active_lead' => $activeLead,
            'Hired' => 0, // $hired,
            'data' => $data,
        ], 200);
    }

    public function index(Request $request)
    {
        $perPage = $request->perpage ?? 10;
        $totalHired = OnboardingEmployees::when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
            $q->where('office_id', \request()->input('office_id'));
        })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
            $fromDate = \request()->input('from_date').' 00:00:00';
            $endDate = \request()->input('to_date').' 23:59:59';
            $q->whereBetween('created_at', [$fromDate, $endDate]);
        })->where('status_id', 7)->count();

        $activeLeads = Lead::whereNotIn('status', ['Hired', 'Rejected'])->when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
            $q->where('office_id', \request()->input('office_id'));
        })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
            $fromDate = \request()->input('from_date').' 00:00:00';
            $endDate = \request()->input('to_date').' 23:59:59';
            $q->whereBetween('created_at', [$fromDate, $endDate]);
        })->count();

        $totalLeads = Lead::when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
            $q->where('office_id', \request()->input('office_id'));
        })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
            $fromDate = \request()->input('from_date').' 00:00:00';
            $endDate = \request()->input('to_date').' 23:59:59';
            $q->whereBetween('created_at', [$fromDate, $endDate]);
        })->count();

        $recruiterLead = Lead::whereNotNull('recruiter_id')->groupBy('recruiter_id')->pluck('recruiter_id')->toArray();
        $recruiterOnboarding = OnboardingEmployees::whereNotNull('recruiter_id')->groupBy('recruiter_id')->pluck('recruiter_id')->toArray();
        $recruiterOnboarding2 = OnboardingEmployees::whereNotNull('hired_by_uid')->groupBy('hired_by_uid')->pluck('hired_by_uid')->toArray();
        $recruiter_ids = array_merge($recruiterLead, $recruiterOnboarding, $recruiterOnboarding2);
        if ($request->has('sort') && $request->input('sort') != '') {
            $data = User::whereIn('id', $recruiter_ids)->where('dismiss', '0')->get();
        } else {
            $data = User::whereIn('id', $recruiter_ids)->where('dismiss', '0')->paginate($perPage);
        }

        $data->transform(function ($data) use ($request) {
            $activeLeads = Lead::whereNotIn('status', ['Hired', 'Rejected'])->where('recruiter_id', $data->id)->when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
                $q->where('office_id', \request()->input('office_id'));
            })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
                $fromDate = \request()->input('from_date').' 00:00:00';
                $endDate = \request()->input('to_date').' 23:59:59';
                $q->whereBetween('created_at', [$fromDate, $endDate]);
            })->count();

            $rejectedLeads = Lead::where('status', 'Rejected')->where('recruiter_id', $data->id)->when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
                $q->where('office_id', \request()->input('office_id'));
            })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
                $fromDate = \request()->input('from_date').' 00:00:00';
                $endDate = \request()->input('to_date').' 23:59:59';
                $q->whereBetween('created_at', [$fromDate, $endDate]);
            })->count();

            $hiredRecruiter = OnboardingEmployees::when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
                $q->where('office_id', \request()->input('office_id'));
            })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
                $fromDate = \request()->input('from_date').' 00:00:00';
                $endDate = \request()->input('to_date').' 23:59:59';
                $q->whereBetween('created_at', [$fromDate, $endDate]);
            })->where('status_id', 7)->whereNotNull('recruiter_id')->where('recruiter_id', $data->id)->count();

            $hiredBy = OnboardingEmployees::when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
                $q->where('office_id', \request()->input('office_id'));
            })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
                $fromDate = \request()->input('from_date').' 00:00:00';
                $endDate = \request()->input('to_date').' 23:59:59';
                $q->whereBetween('created_at', [$fromDate, $endDate]);
            })->where('status_id', 7)->whereNull('recruiter_id')->where('hired_by_uid', $data->id)->count();

            $hired = $hiredRecruiter + $hiredBy;

            $last_hired_data = [];
            $conversionRate = 0;

            if ($hired > 0) {
                $totalRate = $hired + $rejectedLeads + $activeLeads;
                if ($totalRate > 0) {
                    $conversionRate = ($hired / $totalRate) * 100;
                }

                $last_hired_data = OnboardingEmployees::where(function ($q) use ($data) {
                    $q->where('hired_by_uid', $data->id)
                        ->orWhere('recruiter_id', $data->id);
                })->orderBy('id', 'desc')->first();
            }

            if (isset($data->image) && $data->image != null) {
                $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$data->image);
            } else {
                $image_s3 = null;
            }

            return [
                'id' => $data->id,
                'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : 'NA',
                'position_id' => isset($data->position_id) ? $data->position_id : null,
                'sub_position_id' => isset($data->sub_position_id) ? $data->sub_position_id : null,
                'is_super_admin' => isset($data->is_super_admin) ? $data->is_super_admin : null,
                'is_manager' => isset($data->is_manager) ? $data->is_manager : null,
                'image' => $data->image,
                'image_s3' => $image_s3,
                'hired' => $hired,
                'rejected' => $rejectedLeads,
                'active_lead' => $activeLeads,
                'last_hired' => isset($last_hired_data->created_at) ? $last_hired_data->created_at->format('m/d/Y') : null,
                'conversion_rate' => round($conversionRate),
            ];
        });

        if ($request->has('sort') && $request->input('sort') == 'active_lead') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'active_lead'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'active_lead'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }
        if ($request->has('sort') && $request->input('sort') == 'hired') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'hired'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'hired'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }
        if ($request->has('sort') && $request->input('sort') == 'conversion_rate') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'conversion_rate'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'conversion_rate'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }

        return response()->json([
            'ApiName' => 'hiring_progress_api',
            'status' => true,
            'message' => 'successfully',
            'total_lead' => $totalLeads,
            'active_lead' => $activeLeads,
            'Hired' => $totalHired,
            'data' => $data,
        ]);
    }

    public function paginates($items, $perPage = 10, $page = null)
    {
        $total = count($items);

        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);

        $start = ($paginator->currentPage() - 1) * $perPage;

        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function graphForLead(Request $request): JsonResponse
    {
        $totalHired = OnboardingEmployees::selectRaw('COUNT(id) as count, MONTH(created_at) as month')->when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
            $q->where('office_id', \request()->input('office_id'));
        })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
            $fromDate = \request()->input('from_date').' 00:00:00';
            $endDate = \request()->input('to_date').' 23:59:59';
            $q->whereBetween('created_at', [$fromDate, $endDate]);
        })->where('status_id', 7)->whereYear('created_at', '=', date('Y'))->groupBy(DB::raw('MONTH(created_at)'))->get();

        $activeLeads = Lead::selectRaw('COUNT(id) as count, MONTH(created_at) as month')->whereNotIn('status', ['Hired', 'Rejected'])->when($request->has('office_id') && $request->input('office_id') != 'all', function ($q) {
            $q->where('office_id', \request()->input('office_id'));
        })->when($request->has('from_date') && $request->input('from_date') != '' && $request->has('to_date') && $request->input('to_date') != '', function ($q) {
            $fromDate = \request()->input('from_date').' 00:00:00';
            $endDate = \request()->input('to_date').' 23:59:59';
            $q->whereBetween('created_at', [$fromDate, $endDate]);
        })->whereYear('created_at', '=', date('Y'))->groupBy(DB::raw('MONTH(created_at)'))->get();

        $leadCount = [];
        $hiredCount = [];
        foreach ($activeLeads as $value) {
            $leadCount[(int) $value->month] = $value->count ?? 0;
        }
        foreach ($totalHired as $value) {
            $hiredCount[(int) $value->month] = $value->count ?? 0;
        }

        $countArr = [];
        $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = 1; $i <= 12; $i++) {
            if (! empty($leadCount[$i])) {
                $countArr[$month[$i - 1]]['lead_count'] = $leadCount[$i];
            } else {
                $countArr[$month[$i - 1]]['lead_count'] = 0;
            }

            if (! empty($hiredCount[$i]) || ! empty($hiredLeadCount[$i])) {
                $countArr[$month[$i - 1]]['hired_count'] = (($hiredCount[$i] ?? 0) + ($hiredLeadCount[$i] ?? 0));
            } else {
                $countArr[$month[$i - 1]]['hired_count'] = 0;
            }
        }

        $data[] = $countArr;

        return response()->json([
            'ApiName' => 'hiring_progress_api',
            'status' => true,
            'message' => 'successfully',
            'data' => $data,
        ]);
    }

    public function recentHired(Request $request): JsonResponse
    {
        if (isset($request->office_id) && $request->office_id == 'all') {
            $data = User::select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'department_id', 'position_id', 'sub_position_id', 'manager_id', 'state_id', 'office_id', 'onboardProcess', 'dismiss')
                ->with('departmentDetail', 'positionDetail', 'managerDetail', 'subpositionDetail')
//            ->where('onboardProcess',1)
                ->where('is_super_admin', '!=', '1')
                ->where('dismiss', '0')
                ->orderBy('id', 'desc')
                ->take(10)
                ->get();

        } else {
            $state = State::where('state_code', $request->location)->first();
            $data = User::select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'department_id', 'position_id', 'sub_position_id', 'manager_id', 'state_id', 'onboardProcess', 'office_id', 'dismiss')
                ->with('departmentDetail', 'positionDetail', 'managerDetail', 'subpositionDetail')
                ->where('office_id', $request->office_id)
//        ->where('onboardProcess',1)
                ->where('is_super_admin', '!=', '1')
                ->where('dismiss', '0')
                ->orderBy('id', 'desc')
                ->take(10)
                ->get();
        }

        $responseData = [];
        foreach ($data as $recent) {
            $responseData[] = [
                'id' => $recent->id,
                'first_name' => $recent->first_name,
                'last_name' => $recent->last_name,
                'email' => $recent->email,
                'mobile_no' => $recent->mobile_no,
                'department_id' => $recent->department_id,
                'department_name' => isset($recent->departmentDetail->name) ? $recent->departmentDetail->name : null,
                'manager_id' => $recent->manager_id,
                'manager_name' => isset($recent->managerDetail->name) ? $recent->managerDetail->name : null,
                'position_id' => $recent->position_id,
                'sub_position_id' => isset($recent->sub_position_id) ? $recent->sub_position_id : null,
                'sub_position_name' => isset($recent->subpositionDetail->position_name) ? $recent->subpositionDetail->position_name : null,
                'state_id' => $recent->state_id,
                'office_id' => $recent->position_id,
                'onboardProcess' => $recent->onboardProcess,
                'position_name' => isset($recent->positionDetail->position_name) ? $recent->positionDetail->position_name : null,
            ];
        }

        return response()->json([
            'ApiName' => 'recent_hired_employee',
            'status' => true,
            'message' => 'successfully',
            'data' => $responseData,
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
    public function filter(Request $request)
    {

        $user = $user->newQuery();
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%');

            })->orWhereHas('additionalEmails', function ($query) use ($request) {
                $query->where('email', 'like', '%'.$request->input('filter').'%');
            });
        }
        $data = $user::whereNotIn('position_id', ['2', '3'])->get();
        // $data = User::whereNotIn('position_id',['2','3'])->get();
        $data->transform(function ($data) {
            $last_hired_data = OnboardingEmployees::where('manager_id', $data->id)->orderBy('id', 'desc')->first();
            if ($last_hired_data) {
                return [
                    // dd($data->Override),
                    'id' => $data->id,
                    'name' => isset($data->first_name, $data->last_name) ? $data->first_name.' '.$data->last_name : 'NA',
                    'image' => $data->image,
                    'hired' => $data->data2 = OnboardingEmployees::where('manager_id', $data->id)->where('status_id', 7)->count(),
                    'not_interested' => $data->data3 = OnboardingEmployees::where('manager_id', $data->id)->where('status_id', 10)->count(),
                    'active_lead' => $total = $data->data2 + $data->data3,
                    'last_hired' => $last_hired_data->created_at->format('m/d/Y'),
                    'conversion_rate' => $total / $data->data2 * 100 .'%',
                ];
            }
        });

        return response()->json([
            'ApiName' => 'onboarding_employee_recruiter_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
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
