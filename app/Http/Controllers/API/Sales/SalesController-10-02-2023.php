<?php

namespace App\Http\Controllers\API\Sales;

use App\Core\Traits\PermissionCheckTrait;
use App\Exports\SalesDataExport;
use App\Http\Controllers\Controller;
use App\Models\ImportExpord;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

class SalesController extends Controller
{
    use PermissionCheckTrait;

    public function __construct(ImportExpord $ImportExpord, Request $request)
    {
        // $this->ImportExpord = $ImportExpord;

        // $routeName = Route::currentRouteName();
        // $user = auth('api')->user()->position_id;
        // $roleId = $user;
        //  $result = $this->checkPermission($roleId, '2', $routeName);
        // if ($result == false)
        // {
        //    $response = [
        //         'status' => false,
        //         'message' => 'this module not access permission.',
        //     ];
        //     print_r(json_encode($response));die();
        // }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = [];
        // if ($request->has('rep_email') && !empty($request->input('rep_email')))
        // {
        $result = SalesMaster::with('salesMasterProcess', 'userDetail')->orderBy('id', 'desc');
        // }
        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
            });
        }

        if ($request->has('closed') && ! empty($request->input('closed'))) {
            $result->where(function ($query) {
                return $query->where('install_complete_date', '!=', null);
            });
        }

        if ($request->has('m1') && ! empty($request->input('m1'))) {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null);
            });
        }

        if ($request->has('m2') && ! empty($request->input('m2'))) {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }

        $legacydata = $result->orderBy('id', $orderBy)->paginate(config('app.paginate', 15));

        $data = [];
        if (count($legacydata) > 0) {
            foreach ($legacydata as $key => $value) {
                // return $value->salesMasterProcess;
                $approveDate = $value->customer_signoff;
                $m1_date = $value->m1_date;
                $m2_date = $value->m2_date;
                $closer1 = isset($value->salesMasterProcess->closer1_id) ? $value->salesMasterProcess->closer1_id : null;
                $setter1 = isset($value->salesMasterProcess->setter1_id) ? $value->salesMasterProcess->setter1_id : null;
                $closer1_m1 = isset($value->salesMasterProcess->closer1_m1) ? $value->salesMasterProcess->closer1_m1 : null;
                $setter1_m1 = isset($value->salesMasterProcess->setter1_m1) ? $value->salesMasterProcess->setter1_m1 : null;
                $closer1_m2 = isset($value->salesMasterProcess->closer1_m2) ? $value->salesMasterProcess->closer1_m2 : null;
                $setter1_m2 = isset($value->salesMasterProcess->setter1_m2) ? $value->salesMasterProcess->setter1_m2 : null;
                $pid_status = isset($value->salesMasterProcess->pid_status) ? $value->salesMasterProcess->pid_status : null;
                $progress_bar = '25%';

                if ($approveDate != null) {
                    $progress_bar = '33%';
                }
                if ($closer1 != null) {
                    $progress_bar = '42%';
                }
                if ($setter1 != null) {
                    $progress_bar = '50%';
                }
                if ($m1_date != null) {
                    $progress_bar = '60%';
                }
                if ($closer1_m1 != null || $setter1_m1 != null) {
                    $progress_bar = '65%';
                }
                if ($m2_date != null) {
                    $progress_bar = '70%';
                }
                if ($closer1_m2 != null || $setter1_m2 != null) {
                    $progress_bar = '85%';
                }
                if ($pid_status != null) {
                    $progress_bar = '100%';
                }

                $total_amount = $value->total_in_period;
                $amount = isset($value->userDetail->upfront_pay_amount) ? $value->userDetail->upfront_pay_amount : 0;
                $commission = isset($value->userDetail->commission) ? $value->userDetail->commission : 0;
                // $totalkwm1 = null;
                // $totalkwm2 = null;
                // if (!empty($value->kw)) {
                // 	return $totalkwm1 = '$'.($value->kw * $amount);
                //     $totalkwm2 = '$'.($total_amount - ($total_amount * $commission / 100) - ($value->kw * $amount));
                // }
                $data[] = [
                    'id' => $value->id,
                    'pid' => $value->pid,
                    'customer_name' => isset($value->customer_name) ? $value->customer_name : null,
                    'state' => isset($value->customer_state) ? $value->customer_state : null,
                    'setter' => isset($value->sales_rep_name) ? $value->sales_rep_name : null,
                    'net_epc' => isset($value->net_epc) ? $value->net_epc : null,
                    'kw' => isset($value->kw) ? $value->kw : null,
                    'date_cancelled' => isset($value->date_cancelled) ? dateToYMD($value->date_cancelled) : null,
                    'm1_date' => isset($value->m1_date) ? dateToYMD($value->m1_date) : null,
                    'm1_amount' => isset($value->m1_amount) ? $value->m1_amount : '',
                    'm2_date' => isset($value->m2_date) ? dateToYMD($value->m2_date) : null,
                    'm2_amount' => isset($value->m2_amount) ? $value->m2_amount : '',
                    'progress_bar' => isset($progress_bar) ? $progress_bar : 0,
                    'created_at' => $value->created_at,
                    'updated_at' => $value->updated_at,
                ];
            }

            return response()->json([
                'ApiName' => 'salel_customer_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'salel_customer_list',
                'status' => false,
                'message' => 'data not found',
                'data' => $data,
            ], 200);

        }
    }

    public function customerProgressDate($id): JsonResponse
    {
        $result = SalesMaster::with('salesMasterProcess', 'userDetail')->where('id', $id)->first();

        $data = [
            'account_acquired' => isset($result->scheduled_install) ? $result->scheduled_install : null,
            'account_approved' => isset($result->customer_signoff) ? $result->customer_signoff : null,
            'import_successful' => isset($result->created_at) ? $result->created_at : null,
            'setter_paid' => isset($result->salesMasterProcess->setter1_m2_paid_status) ? $result->salesMasterProcess->setter1_m2_paid_status : null,
            'setter_first_name' => isset($result->salesMasterProcess->setterDetail->first_name) ? $result->salesMasterProcess->setterDetail->first_name : null,
            'setter_last_name' => isset($result->salesMasterProcess->setterDetail->last_name) ? $result->salesMasterProcess->setterDetail->last_name : null,
            'setter_image' => isset($result->salesMasterProcess->userDetail->image) ? $result->salesMasterProcess->userDetail->image : null,
            'm1_approved' => isset($result->m1_date) ? $result->m1_date : null,
            'design_approved' => null,
            'installation_partner_confirmation' => null,
            'adom_slept_through_the_whole_procedure' => null,
            'install_completed' => isset($result->install_complete_date) ? $result->install_complete_date : null,
            'm2_approved' => isset($result->m2_date) ? $result->m2_date : null,
            'closer_paid' => isset($result->salesMasterProcess->closer1_m2_paid_status) ? $result->salesMasterProcess->closer1_m2_paid_status : null,
            'closer_first_name' => isset($result->closerDetail->first_name) ? $result->closerDetail->first_name : null,
            'closer_last_name' => isset($result->closerDetail->last_name) ? $result->closerDetail->last_name : null,
            'closer_image' => isset($result->closerDetail->image) ? $result->closerDetail->image : null,
            'paid_status' => isset($result->salesMasterProcess->pid_status) ? $result->salesMasterProcess->pid_status : null,
        ];

        return response()->json([
            'ApiName' => 'customer Progress Date',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function filter(Request $request)
    {
        // $result = $this->ImportExpord->newQuery();
        $result = ImportExpord::with('userDetail');

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('rep_email') && ! empty($request->input('rep_email'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('sales_rep_email', $request->input('rep_email'));
            });
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('state', 'LIKE', '%'.$request->input('search').'%')
                    // ->orWhere('sales_rep_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
            });
        }

        if ($request->has('closed') && ! empty($request->input('closed'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('closed').'%');
            });
        }

        if ($request->has('m1') && ! empty($request->input('m1'))) {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null);
            });
        }

        if ($request->has('m2') && ! empty($request->input('m2'))) {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }

        $data = $result->orderBy('id', $orderBy)->get();

        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'pid' => $data->pid,
                'customer_name' => $data->customer_name,
                'state' => $data->state,
                'setter' => $data->sales_rep_name,
                'net_epc' => $data->net_epc,
                'kw' => $data->kw,
                'cancel_date' => isset($data->cancel_date) ? dateToYMD($data->cancel_date) : null,
                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                'm1_amount' => ($data->kw != null) ? '$'.($data->kw * $data->userDetail->upfront_pay_amount) : null,
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                'm2_amount' => ($data->kw != null) ? '$'.($data->total_in_period - ($data->total_in_period * $data->userDetail->commission / 100) - ($data->kw * $data->userDetail->upfront_pay_amount)) : null,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];
        });

        return response()->json([
            'ApiName' => 'filter_customer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function sales_graph_old(Request $request): JsonResponse
    {
        $data = [];
        $date = date('Y-m-d');

        $graphdata = SalesMaster::where('m1_date', '!=', null)
            ->where('m1_date', '<=', $date)
            ->groupBy('m1_date')
            ->orderBy('m1_date', 'desc')
            ->skip(0)->take(6)
            ->get();

        $graphsecond = [];
        foreach ($graphdata as $key => $graph) {
            $month = date('j M', strtotime($graph->m1_date));

            $m1 = ImportExpord::where('m1_date', $graph->m1_date)->sum('kw');
            $m2 = ImportExpord::where('m1_date', $graph->m1_date)->where('install_complete_date', '!=', null)->sum('kw');
            $clawback = ImportExpord::where('m1_date', $graph->m1_date)->where('return_sales_date', '!=', null)->sum('kw');

            $graphsecond[$month] = [
                'm1' => $m1,
                'm2' => $m2,
                'clawback' => $clawback,
            ];
        }

        $data = $graphsecond;

        return response()->json([
            'ApiName' => 'sales_graph_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function mySalesGraph(Request $request): JsonResponse
    {

        $data = [];
        $date = date('Y-m-d');
        $position_id = auth()->user()->position_id;

        if ($position_id == 2 || $position_id == 3) {

            if ($request->has('custom_value') && ! empty($request->input('custom_value'))) {
                $newDateTime = Carbon::now()->subDays(6);
                $weekDate = date('Y-m-d', strtotime($newDateTime));
                $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->max('kw');
                $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->where('id', auth()->user()->id)->avg('kw');
                $installKw = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', '!=', null)->sum('kw');
                $installCount = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', '!=', null)->count();
                $pendingKw = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->sum('kw');
                $pendingKwCount = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->count();
                $clawBackAccount = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->sum('m1_amount');
                $clawBackAccountCount = SalesMaster::whereBetween('customer_signoff', [$weekDate, $date])->where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->count();
                for ($i = 0; $i < 7; $i++) {
                    $newDateTime = Carbon::now()->subDays(6 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $amountM1 = SalesMaster::where('id', auth()->user()->id)->where('m1_date', $weekDate)
                        ->sum('kw');
                    $amountM2 = SalesMaster::where('id', auth()->user()->id)->where('m2_date', $weekDate)
                        ->sum('kw');
                    $clawBack = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', $weekDate)
                        ->sum('kw');

                    $total[] = [
                        'date' => $weekDate,
                        'm1_amount' => round($amountM1, 3),
                        'm2_amount' => round($amountM2, 3),
                        'claw_back' => round($clawBack, 3),
                    ];
                }
            } else {
                $largestSystemSize = SalesMaster::where('id', auth()->user()->id)->max('kw');
                $avgSystemSize = SalesMaster::where('id', auth()->user()->id)->avg('kw');
                $installKw = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', '!=', null)->sum('kw');
                $installCount = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', '!=', null)->count();
                $pendingKw = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->sum('kw');
                $pendingKwCount = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->count();
                $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->sum('m1_amount');
                $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->count();

                for ($i = 0; $i < 7; $i++) {
                    $newDateTime = Carbon::now()->subDays(6 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $amountM1 = SalesMaster::where('id', auth()->user()->id)->where('m1_date', $weekDate)
                        ->sum('kw');
                    $amountM2 = SalesMaster::where('id', auth()->user()->id)->where('m2_date', $weekDate)
                        ->sum('kw');
                    $clawBack = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', $weekDate)
                        ->sum('kw');

                    $total[] = [
                        'date' => $weekDate,
                        'm1_amount' => round($amountM1, 3),
                        'm2_amount' => round($amountM2, 3),
                        'claw_back' => round($clawBack, 3),
                    ];
                }
            }

        } else {

            if ($request->has('custom_value') && ! empty($request->input('custom_value'))) {
                $largestSystemSize = SalesMaster::max('kw');
                $avgSystemSize = SalesMaster::avg('kw');
                $installKw = SalesMaster::select('kw', 'install_complete_date')->where('install_complete_date', '!=', null)->sum('kw');
                $installCount = SalesMaster::select('kw', 'install_complete_date')->where('install_complete_date', '!=', null)->count();
                $pendingKw = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->sum('kw');
                $pendingKwCount = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->count();
                $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->sum('m1_amount');
                $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->count();

                $filterDataDateWise = $request->input('custom_value');
                if ($filterDataDateWise == 'this_week') {
                    for ($i = 0; $i <= 7; $i++) {
                        $now = Carbon::now();
                        $newDateTime = Carbon::now()->subDays(7 - $i);
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $amountM1 = SalesMaster::where('m1_date', $weekDate)
                            ->sum('kw');
                        $amountM2 = SalesMaster::where('m2_date', $weekDate)
                            ->sum('kw');
                        $clawBack = SalesMaster::where('date_cancelled', $weekDate)
                            ->sum('kw');
                        $total[] = [
                            'date' => $weekDate,
                            'm1_amount' => round($amountM1, 3),
                            'm2_amount' => round($amountM2, 3),
                            'claw_back' => round($clawBack, 3),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_week') {
                    for ($i = 0; $i <= 7; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        $agoDate = $currentDate->subDays($currentDate->dayOfWeek)->subWeek()->addDays($i);
                        $newDateTime = date('Y-m-d', strtotime($agoDate));
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $amountM1 = SalesMaster::where('m1_date', $weekDate)
                            ->sum('kw');
                        $amountM2 = SalesMaster::where('m2_date', $weekDate)
                            ->sum('kw');
                        $clawBack = SalesMaster::where('date_cancelled', $weekDate)
                            ->sum('kw');
                        $total[] = [
                            'date' => $weekDate,
                            'm1_amount' => round($amountM1, 3),
                            'm2_amount' => round($amountM2, 3),
                            'claw_back' => round($clawBack, 3),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_month') {
                    for ($i = 0; $i < 4; $i++) {
                        $startDate = date('Y-m-d', strtotime(now()->subWeeks(4 - $i)));
                        $endDate = date('Y-m-d', strtotime(now()->subWeeks(3 - $i)));
                        $amountM1 = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])
                            ->sum('kw');
                        $amountM2 = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])
                            ->sum('kw');
                        $clawBack = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])
                            ->sum('kw');
                        $total[] = [
                            'week_start_date' => $startDate,
                            'week_end_date' => $endDate,
                            'm1_amount' => round($amountM1, 3),
                            'm2_amount' => round($amountM2, 3),
                            'claw_back' => round($clawBack, 3),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_month') {
                    for ($i = 0; $i < 4; $i++) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->subWeeks(4 - $i)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->subWeeks(3 - $i)));
                        $amountM1 = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])
                            ->sum('kw');
                        $amountM2 = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])
                            ->sum('kw');
                        $clawBack = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])
                            ->sum('kw');
                        $total[] = [
                            'week_start_date' => $startDate,
                            'week_end_date' => $endDate,
                            'm1_amount' => round($amountM1, 3),
                            'm2_amount' => round($amountM2, 3),
                            'claw_back' => round($clawBack, 3),
                        ];
                    }
                } elseif ($filterDataDateWise == 'past_three_month') {
                    for ($i = 0; $i < 90; $i++) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0 + $i)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(15 + $i)));
                        $amountM1 = SalesMaster::whereBetween('m1_date', [$startDate, $endDate])
                            ->sum('kw');
                        $amountM2 = SalesMaster::whereBetween('m2_date', [$startDate, $endDate])
                            ->sum('kw');
                        $clawBack = SalesMaster::whereBetween('date_cancelled', [$startDate, $endDate])
                            ->sum('kw');
                        $total[] = [
                            'week_start_date' => $startDate,
                            'week_end_date' => $endDate,
                            'm1_amount' => round($amountM1, 3),
                            'm2_amount' => round($amountM2, 3),
                            'claw_back' => round($clawBack, 3),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_year') {
                    $newDateTime = Carbon::now()->subDays(7 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));
                }

            } else {
                $largestSystemSize = SalesMaster::max('kw');
                $avgSystemSize = SalesMaster::avg('kw');
                $installKw = SalesMaster::select('kw', 'install_complete_date')->where('install_complete_date', '!=', null)->sum('kw');
                $installCount = SalesMaster::select('kw', 'install_complete_date')->where('install_complete_date', '!=', null)->count();
                $pendingKw = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->sum('kw');
                $pendingKwCount = SalesMaster::select('kw', 'install_complete_date')->where('id', auth()->user()->id)->where('install_complete_date', null)->count();
                $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->sum('m1_amount');
                $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->where('date_cancelled', '!=', null)->count();

                for ($i = 0; $i < 7; $i++) {
                    $newDateTime = Carbon::now()->subDays(7 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $amountM1 = SalesMaster::where('m1_date', $weekDate)
                        ->sum('kw');

                    $amountM2 = SalesMaster::where('m2_date', $weekDate)
                        ->sum('kw');

                    $clawBack = SalesMaster::where('date_cancelled', $weekDate)
                        ->sum('kw');

                    $total[] = [
                        'date' => $weekDate,
                        'm1_amount' => round($amountM1, 3),
                        'm2_amount' => round($amountM2, 3),
                        'claw_back' => round($clawBack, 3),
                    ];
                }

            }

        }

        $data['heading_count_kw'] = [
            'largest_system_size' => round($largestSystemSize, 3),
            'avg_system_size' => round($avgSystemSize, 3),
            'install_kw' => $installKw.'('.$installCount.')',
            'pending_kw' => $pendingKw.'('.$pendingKwCount.')',
            'clawBack_account' => $clawBackAccount.'('.$clawBackAccountCount.')',
        ];
        $data['my_sales'] = $total;

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function account_graph(Request $request): JsonResponse
    {
        $date = date('Y-m-d');
        $position = auth()->user()->position_id;
        if ($position == 2 || $position == 3) {
            $data = [];
            $totalSales = SalesMaster::where('sales_rep_email', auth()->user()->email)->get();
            $m2Complete = SalesMaster::where('sales_rep_email', auth()->user()->email)->where('m2_date', '!=', null)->count();
            $m2Pending = SalesMaster::where('sales_rep_email', auth()->user()->email)->where('m2_date', null)->count();
            $cancelled = SalesMaster::where('sales_rep_email', auth()->user()->email)->where('date_cancelled', '!=', null)->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i <= 5; $i++) {
                $newDateTime = Carbon::now()->subDays(6 - $i);
                $weekDate = date('Y-m-d', strtotime($newDateTime));

                $amountM1 = SalesMaster::where('sales_rep_email', auth()->user()->email)->where('customer_signoff', $weekDate)
                    ->orderBy('m1_date', 'desc')
                    ->skip(0)->take(6)
                    ->sum('m1_amount');

                $amountM2 = SalesMaster::where('sales_rep_email', auth()->user()->email)->where('customer_signoff', $weekDate)
                    ->orderBy('m1_date', 'desc')
                    ->skip(0)->take(6)
                    ->sum('m2_amount');
                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }

        } else {
            $data = [];
            $totalSales = SalesMaster::get();
            $m2Complete = SalesMaster::where('install_complete_date', '!=', null)->count();
            $m2Pending = SalesMaster::where('m2_date', '=', null)->count();
            $cancelled = SalesMaster::where('date_cancelled', '!=', null)->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i <= 6; $i++) {
                $newDateTime = Carbon::now()->subDays(6 - $i);
                $weekDate = date('Y-m-d', strtotime($newDateTime));

                $amountM1 = SalesMaster::where('customer_signoff', $weekDate)
                    ->orderBy('m1_date', 'desc')
                    ->skip(0)->take(6)
                    ->sum('m1_amount');

                $amountM2 = SalesMaster::where('customer_signoff', $weekDate)
                    ->orderBy('m1_date', 'desc')
                    ->skip(0)->take(6)
                    ->sum('m2_amount');
                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        }

        $data['accounts'] = [
            'total_sales' => count($totalSales),
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $cancelled,
        ];

        $data['install_ratio'] = [
            'install' => round(($m2Complete / count($totalSales) * 100), 3).'%',
            'uninstall' => round(100 - ($m2Complete / count($totalSales) * 100), 3).'%',
        ];

        $data['graph_m1_m2_amount'] = [
            'graph_amount' => $amount,
        ];

        return response()->json([
            'ApiName' => 'filter_customer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function system_size(Request $request): JsonResponse
    {
        $data = [];

        $largeSize = ImportExpord::select('kw')->where('sales_rep_email', $request->input('rep_email'))->max('kw');
        $avgSize = ImportExpord::select('kw')->where('sales_rep_email', $request->input('rep_email'))->avg('kw');

        $kw_installed = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('install_complete_date', '!=', null)->sum('kw');
        $nstalled_count = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('install_complete_date', '!=', null)->count();
        $kw_pending = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('install_complete_date', '=', null)->sum('kw');
        $pending_count = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('install_complete_date', '=', null)->count();
        $clawback_kw = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('return_sales_date', '!=', null)->sum('kw');
        $clawback_count = ImportExpord::where('sales_rep_email', $request->input('rep_email'))->where('return_sales_date', '!=', null)->count();
        $upfront_amount = User::select('id', 'upfront_pay_amount')->where('email', $request->input('rep_email'))->first();
        if (empty($upfront_amount)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $request->input('rep_email'))->value('user_id');
            if (! empty($additional_user_id)) {
                $upfront_amount = User::select('id', 'upfront_pay_amount')->where('id', $additional_user_id)->first();
            }
        }
        $totalClawback = '$'.($clawback_kw * $upfront_amount->upfront_pay_amount);

        $data['large_system_size'] = $largeSize;
        $data['average_system_size'] = $avgSize;
        $data['kw_installed'] = [
            'kw' => $kw_installed,
            'count' => $nstalled_count,
        ];

        $data['kw_pending'] = [
            'kw' => $kw_pending,
            'count' => $pending_count,
        ];

        $data['clawback'] = [
            'amount' => $totalClawback,
            'count' => $clawback_count,
        ];

        // $data['install_ratio'] = ($m2Complete / count($totalSales) * 100 );

        return response()->json([
            'ApiName' => 'filter_customer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function exportSales(Request $request)
    {

        $from_date = $request->from_date;
        $to_date = $request->to_date;
        print_r($from_date);
        exit;
        $export = new SalesDataExport($from_date, $to_date);
        Excel::download($export, 'salesData.xlsx');

        return 'Ram';

        // return Excel::download(new SalesDataExport, 'salesData.xlsx');
        return response()->json([
            'ApiName' => 'export_excel_api',
            'status' => true,
            'message' => 'Export Sheet Successfully',
            // 'data'    => $data,
        ], 200);
    }

    public function filter11(Request $request)
    {
        // $data = ImportExpord::get();
        $result = $this->ImportExpord->newQuery();
        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('state', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('sales_rep_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
            });
        }

        if ($request->has('closed') && ! empty($request->input('closed'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('closed').'%');
            });
        }

        if ($request->has('m1') && ! empty($request->input('m1'))) {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null);
            });
        }

        if ($request->has('m2') && ! empty($request->input('m2'))) {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }

        /*if ($request->has('closed') && !empty($request->input('closed')))
        {
            $result->where(function($query) use ($request,$orderBy) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%');
                });
        }

        if ($request->has('state_id') && !empty($request->input('state_id')))
        {

            $result->where(function($query) use ($request,$orderBy) {
                return $query->where('state_id', $request->input('state_id'));
                });
        }*/

        $data = $result->orderBy('id', $orderBy)->get();

        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'first_name' => $data->first_name,
                'middle_name' => $data->middle_name,
                'last_name' => $data->last_name,
                'sex' => $data->sex,
                'dob' => isset($data->dob) ? dateToYMD($data->dob) : null,
                'image' => $data->image,
                'zip_code' => $data->zip_code,
                'work_email' => $data->work_email,
                'home_address' => $data->home_address,
                'emergency_contact_name' => $data->emergency_contact_name,
                'emergency_phone' => $data->emergency_phone,
                'emergency_contact_relationship' => $data->emergency_contact_relationship,
                'emergrncy_contact_address' => $data->emergrncy_contact_address,
                'emergrncy_contact_city' => $data->emergrncy_contact_city,
                'mobile_no' => $data->mobile_no,
                'state_id' => $data->state_id,
                'city_id' => $data->city_id,
                'location' => $data->location,
                'department_id' => $data->department_id,
                'department_name' => $data->departmentDetail->name,
                'employee_position_id' => $data->employee_position_id,
                'manager_id' => $data->manager_id,
                'manager_name' => isset($data->managerDetail->id) ? $data->managerDetail->name : null,
                'team_id' => $data->team_id,
                'status_id' => $data->status_id,
                'status_name' => isset($data->statusDetail->status) ? $data->statusDetail->status : null,
                'recruiter_id' => $data->recruiter_id,
                'recruiter_name' => isset($data->recruiter->first_name) ? $data->recruiter->first_name : null,
                // 'additional_recruiter' => $data->additional_recruiter,
                'position_id' => $data->position_id,
                'position_name' => $data->positionDetail->position_name,
                'redline' => $data->redline,
                'redline_amount' => $data->redline_amount,
                'redline_type' => $data->redline_type,
                'upfront_pay_amount' => $data->upfront_pay_amount,
                'per_sale' => $data->per_sale,
                'direct_overrides_amount' => $data->direct_overrides_amount,
                'direct_per_kw' => $data->direct_per_kw,
                'direct_per_kw' => $data->direct_per_kw,
                'indirect_overrides_amount' => $data->indirect_overrides_amount,
                'indirect_per_kw' => $data->indirect_per_kw,
                'office_overrides_amount' => $data->office_overrides_amount,
                'office_per_kw' => $data->office_per_kw,
                'probation_period' => $data->probation_period,
                'hiring_bonus_amount' => $data->hiring_bonus_amount,
                'date_to_be_paid' => isset($data->date_to_be_paid) ? dateToYMD($data->date_to_be_paid) : null,
                'period_of_agreement_start_date' => isset($data->period_of_agreement_start_date) ? dateToYMD($data->period_of_agreement_start_date) : null,
                'end_date' => isset($data->end_date) ? dateToYMD($data->end_date) : null,
                'offer_expiry_date' => isset($data->offer_expiry_date) ? $data->offer_expiry_date : null,
                'type' => $data->type,
                // 'additional_recruter_id'=> $data['additionalDetail'],
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];
        });

        return response()->json([
            'ApiName' => 'onboarding_employee_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }
}
