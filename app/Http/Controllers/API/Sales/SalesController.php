<?php

namespace App\Http\Controllers\API\Sales;

use App\Core\Traits\PermissionCheckTrait;
use App\Exports\EmployeesExport;
use App\Exports\ExportReportMySalesStandard;
use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\ImportExpord;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SalesController extends Controller
{
    use PermissionCheckTrait;

    public function __construct(Request $request)
    {

        // $routeName = Route::currentRouteName();
        // $user = auth('api')->user()->position_id;
        // $roleId = $user;
        // $result = $this->checkPermission($roleId, '2', $routeName);

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
        $position = auth()->user()->position_id;
        $uid = auth()->user()->id;
        $clawbackPid = DB::table('clawback_settlements')->where('user_id', auth()->user()->id)->distinct()->pluck('pid')->toArray();
        $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid')->toArray();
        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'this_month') {

            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'this_quarter') {

            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
            $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');

        } elseif ($filterDataDateWise == 'last_quarter') {

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');

        } elseif ($filterDataDateWise == 'this_year') {
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');

        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');

        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        }
        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('job_status', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
            });
        }
        if ($request->has('closed') && $request->input('closed') == '1') {
            $result->Where(function ($query) {
                return $query->where('install_complete_date', '!=', null);
            });
        }

        if ($request->has('m1') && $request->input('m1') == '1') {
            $result->Where(function ($query) {
                return $query->where('m1_date', '!=', null);
            });
        }

        if ($request->has('m2') && $request->input('m2') == '1') {
            $result->Where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }
        if ($request->has('sort') && $request->input('sort') != '') {
            $data = $result->orderBy('id', 'asc')->get();
        } else {
            $data = $result->orderBy('id', 'asc')->paginate(config('app.paginate', 15));
        }

        $data->transform(function ($data) use ($position) {
            $userM1 = 0;
            $userM2 = 0;

            if ($data->salesMasterProcess->closer1_id != '') {
                $salesUserCloserId = $data->salesMasterProcess->closer1_id;
            } else {
                $salesUserCloserId = $data->salesMasterProcess->closer2_id;
            }
            if ($data->salesMasterProcess->setter1_id != '') {
                $salesUserSetterId = $data->salesMasterProcess->setter1_id;
            } else {
                $salesUserSetterId = $data->salesMasterProcess->setter2_id;
            }

            if ($position == 2) {
                $closer1M1 = SaleMasterProcess::where('closer1_id', $salesUserCloserId)->where('pid', $data->pid)->first();
                $closer2M1 = SaleMasterProcess::where('closer2_id', $salesUserCloserId)->where('pid', $data->pid)->first();
                if ($closer1M1) {
                    $userM1 = $closer1M1->closer1_m1;
                    $userM2 = $closer1M1->closer1_m2;
                } elseif ($closer2M1) {
                    $userM1 = $closer2M1->closer2_m1;
                    $userM2 = $closer2M1->closer2_m2;
                }
            }
            if ($position == 3) {

                $setter1M1 = SaleMasterProcess::where('setter1_id', $salesUserSetterId)->where('pid', $data->pid)->first();
                $setter2M1 = SaleMasterProcess::where('setter2_id', $salesUserSetterId)->where('pid', $data->pid)->first();
                if ($setter1M1) {
                    $userM1 = $setter1M1->setter1_m1;
                    $userM2 = $setter1M1->setter1_m2;
                } elseif ($setter2M1) {
                    $userM1 = $setter2M1->setter2_m1;
                    $userM2 = $setter2M1->setter2_m2;
                }
            }

            $approveDate = $data->customer_signoff;
            $m1_date = $data->m1_date;
            $m2_date = $data->m2_date;
            $closer1 = isset($data->salesMasterProcess->closer1_id) ? $data->salesMasterProcess->closer1_id : null;
            $setter1 = isset($data->salesMasterProcess->setter1_id) ? $data->salesMasterProcess->setter1_id : null;

            $closer1_m1 = isset($data->salesMasterProcess->closer1_m1) ? $data->salesMasterProcess->closer1_m1 : 0;
            $setter1_m1 = isset($data->salesMasterProcess->setter1_m1) ? $data->salesMasterProcess->setter1_m1 : 0;
            $closer1_m2 = isset($data->salesMasterProcess->closer1_m2) ? $data->salesMasterProcess->closer1_m2 : 0;
            $setter1_m2 = isset($data->salesMasterProcess->setter1_m2) ? $data->salesMasterProcess->setter1_m2 : 0;

            $closer2_m1 = isset($data->salesMasterProcess->closer2_m1) ? $data->salesMasterProcess->closer2_m1 : 0;
            $setter2_m1 = isset($data->salesMasterProcess->setter2_m1) ? $data->salesMasterProcess->setter2_m1 : 0;
            $closer2_m2 = isset($data->salesMasterProcess->closer2_m2) ? $data->salesMasterProcess->closer2_m2 : 0;
            $setter2_m2 = isset($data->salesMasterProcess->setter2_m2) ? $data->salesMasterProcess->setter2_m2 : 0;

            $pid_status = isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null;

            $total_m2 = (((int) $closer1_m2) + ((int) $setter1_m2) + ((int) $closer2_m2) + ((int) $setter2_m2));
            // echo $total_m2;die;
            $total_m1 = ((int) $closer1_m1 + (int) $setter1_m1 + (int) $closer2_m1 + (int) $setter2_m1);
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

            $total_amount = $data->total_in_period;
            $amount = isset($data->userDetail->upfront_pay_amount) ? $data->userDetail->upfront_pay_amount : 0;
            $commission = isset($data->userDetail->commission) ? $data->userDetail->commission : 0;
            // $totalkwm1 = null;
            // $totalkwm2 = null;
            // if (!empty($data->kw)) {
            // 	return $totalkwm1 = '$'.($data->kw * $amount);
            //     $totalkwm2 = '$'.($total_amount - ($total_amount * $commission / 100) - ($data->kw * $amount));
            // }
            $location_data = Locations::with('State')->where('general_code', '=', $data->customer_state)->first();
            if ($location_data) {
                $state_code = $location_data->state->state_code;
            } else {
                $state_code = null;
            }

            return [
                'id' => $data->id,
                'pid' => $data->pid,
                'installer' => $data->install_partner,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'state_id' => $state_code,
                'state' => isset($data->customer_state) ? $data->customer_state : null,
                'closer_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                'closer' => isset($data->salesMasterProcess->closer1Detail->first_name) ? $data->salesMasterProcess->closer1Detail->first_name : null,
                'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                'setter' => isset($data->salesMasterProcess->setter1Detail->first_name) ? $data->salesMasterProcess->setter1Detail->first_name : null,
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'kw' => isset($data->kw) ? $data->kw : null,
                'status' => isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null,
                'job_status' => isset($data->salesMasterProcess->job_status) ? $data->salesMasterProcess->job_status : null,
                'date_cancelled' => isset($data->date_cancelled) ? dateToYMD($data->date_cancelled) : null,

                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                // 'm1_amount' => ($closer1_m1 + $setter1_m1 + $closer2_m1 + $setter2_m1),
                // 'm1_amount' => $total_m1,
                'm1_amount' => $userM1,
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                // 'm2_amount' => ($closer1_m2 + $setter1_m2 + $closer2_m2 + $setter2_m2),
                // 'm2_amount' => $total_m2,
                'm2_amount' => $userM2,

                'adders' => isset($data->adders) ? $data->adders : '',
                'progress_bar' => isset($progress_bar) ? $progress_bar : 0,
                'dealer_fee' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : '',
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];
        });
        $exportdata = $data;

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $file_name = 'manager_mysales_export_'.date('Y_m_d_H_i_s').'.csv';

            return Excel::download(new ExportReportMySalesStandard($exportdata), $file_name);
        }

        if (count($data) > 0) {

            if ($request->has('sort') && $request->input('sort') == 'net_epc') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'net_epc'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'net_epc'), SORT_ASC, $data);
                }

            }
            if ($request->has('sort') && $request->input('sort') == 'kw') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'kw'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'kw'), SORT_ASC, $data);
                }

            }
            if ($request->has('sort') && $request->input('sort') == 'm1') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'm1_amount'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'm1_amount'), SORT_ASC, $data);
                }

            }
            if ($request->has('sort') && $request->input('sort') == 'm2') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'm2_amount'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'm2_amount'), SORT_ASC, $data);
                }

            }
            if ($request->has('sort') && $request->input('sort') != '') {
                $data = $this->paginates($data, 10);
            }

            return response()->json([
                'ApiName' => 'sales_customer_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales_customer_list',
                'status' => false,
                'message' => 'data not found',
                'data' => $data,
            ], 200);

        }
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

    public function customerSaleTracking($id): JsonResponse
    {
        $result = SalesMaster::with('salesMasterProcess', 'userDetail')->where('id', $id)->first();

        $data = [
            'account_acquired' => isset($result->scheduled_install) ? $result->scheduled_install : null,
            'account_approved' => isset($result->customer_signoff) ? $result->customer_signoff : null,
            'import_successful' => isset($result->created_at) ? $result->created_at : null,
            'setter_paid' => isset($result->salesMasterProcess->setter1_m2_paid_status) ? $result->salesMasterProcess->setter1_m2_paid_status : null,
            'setter_first_name' => isset($result->salesMasterProcess->setter1Detail->first_name) ? $result->salesMasterProcess->setter1Detail->first_name : null,
            'setter_last_name' => isset($result->salesMasterProcess->setter1Detail->last_name) ? $result->salesMasterProcess->setter1Detail->last_name : null,
            'setter_image' => isset($result->salesMasterProcess->setter1Detail->image) ? $result->salesMasterProcess->setter1Detail->image : null,
            'm1_approved' => isset($result->m1_date) ? $result->m1_date : null,
            'design_approved' => null,
            'installation_partner_confirmation' => isset($result->customer_signoff) ? $result->customer_signoff : null,
            'adom_slept_through_the_whole_procedure' => null,
            'install_completed' => isset($result->m2_date) ? $result->m2_date : null,
            'm2_approved' => isset($result->m2_date) ? $result->m2_date : null,
            'closer_paid' => isset($result->salesMasterProcess->closer1_m2_paid_status) ? $result->salesMasterProcess->closer1_m2_paid_status : null,
            'closer_first_name' => isset($result->salesMasterProcess->closer1Detail->first_name) ? $result->salesMasterProcess->closer1Detail->first_name : null,
            'closer_last_name' => isset($result->salesMasterProcess->closer1Detail->last_name) ? $result->salesMasterProcess->closer1Detail->last_name : null,
            'closer_image' => isset($result->salesMasterProcess->closer1Detail->image) ? $result->salesMasterProcess->closer1Detail->image : null,
            'paid_status' => isset($result->salesMasterProcess->pid_status) ? $result->salesMasterProcess->pid_status : null,
        ];

        return response()->json([
            'ApiName' => 'customer Progress Date',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function exportSalesData(Request $request)
    {
        $file_name = 'employees_'.date('Y_m_d_H_i_s').'.csv';
        if (isset($request->start_date) && $request->start_date != '' && isset($request->end_date) && $request->end_date != '') {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            return Excel::download(new EmployeesExport($startDate, $endDate), $file_name);
        } else {
            return Excel::download(new EmployeesExport, $file_name);
        }

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

    public function mySalesGraph09(Request $request)
    {
        $data = [];
        $date = date('Y-m-d');
        $position = auth()->user()->position_id;
        $kwType = isset($request->kw_type) ? $request->kw_type : 'sold';

        if ($kwType == '' || $kwType == 'sold') {
            $dateColumn = 'customer_signoff';
        } else {
            // $dateColumn =  'install_complete_date';
            $dateColumn = 'm2_date';
        }

        if ($position == 2 || $position == 3) {
            $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid')->toArray();
            if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') == 'all') {
                $filterDataDateWise = $request->input('filter');
                if ($filterDataDateWise == 'this_week') {
                    $currentDate = \Carbon\Carbon::now();
                    $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                    $endDate = date('Y-m-d', strtotime(now()));
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();
                    for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {

                        $now = Carbon::now();
                        $newDateTime = Carbon::now()->subDays($i);
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'last_week') {
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                    $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                    $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();

                    for ($i = 0; $i < 7; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                        $weekDate = date('Y-m-d', strtotime($startOfLastWeek));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_month') {
                    $endOfLastWeek = Carbon::now();
                    $day = date('d', strtotime($endOfLastWeek));
                    $startDate = date('Y-m-d', strtotime(now()->subDays($day - 1)));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();
                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));
                    for ($i = 0; $i <= $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_month') {
                    $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();

                    for ($i = 0; $i < $month; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_quarter') {
                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(02)->month()->daysInMonth;
                    $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                    $weeks = (int) (($currentMonthDay % 365) / 7);
                    $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                    $eom = Carbon::parse($endDate);
                    $dates = [];
                    $f = 'Y-m-d';

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();
                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i < $weeks; $i++) {
                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2 - $i)->addDays(30)));
                        $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                        $startDate = $date->copy();
                        // loop to end of the week while not crossing the last date of month
                        // return $date->dayOfWeek;
                        while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                            $date->addDay();
                        }

                        $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        if ($date->format($f) < $eom->format($f)) {
                            $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        } else {
                            $dates['w'.$i] = [$startDate->format($f), $eom->format($f)];
                        }

                        $date->addDay();
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $dates = $dates['w'.$i];
                        $total[] = [
                            'date' => $dates,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }

                } elseif ($filterDataDateWise == 'last_quarter') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                    $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                    $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                    $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));

                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(02)->month()->daysInMonth;
                    $weeks = (int) (($currentMonthDay % 365) / 7);
                    $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-10 days', strtotime($startDate))));
                    $eom = Carbon::parse($endDate);
                    $dates = [];
                    $f = 'Y-m-d';

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();
                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));
                    for ($i = 0; $i < $weeks; $i++) {

                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));
                        $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                        $startDate = $date->copy();
                        // loop to end of the week while not crossing the last date of month
                        // return $date->dayOfWeek;
                        while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                            $date->addDay();
                        }

                        $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        if ($date->format($f) < $eom->format($f)) {
                            $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        } else {
                            $dates['w'.$i] = [$startDate->format($f), $eom->format($f)];
                        }

                        $date->addDay();

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $dates = $dates['w'.$i];

                        $total[] = [
                            'date' => $dates,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_year') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays(30)));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $currentMonth = date('m');
                        if ($i < $currentMonth) {
                            $total[] = [
                                'date' => $month,
                                'm1_account' => round($accountM1->account, 5),
                                'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                            ];
                        }
                    }
                } elseif ($filterDataDateWise == 'last_year') {
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'custom') {
                    $startDate = $filterDataDateWise = $request->input('start_date');
                    $endDate = $filterDataDateWise = $request->input('end_date');

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max('kw');
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->sum('kw');
                        $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->count();
                        $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->sum('kw');
                        $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->count();
                        $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->sum('m1_amount');
                        $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->count();
                        if ($dateDays <= 15) {
                            for ($i = 0; $i < $dateDays; $i++) {
                                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $total[] = [
                                    'date' => $weekDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];
                            }
                        } else {
                            $weekCount = round($dateDays / 7);
                            $totalWeekDay = 7 * $weekCount;
                            $extraDay = $dateDays - $totalWeekDay;

                            if ($extraDay > 0) {
                                $weekCount = $weekCount + 1;
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $time = strtotime($sDate);
                                $month = date('M', $time);
                                $total[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];

                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m1_amount');

                                $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m2_amount');

                                $amount[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_amount' => $amountM1,
                                    'm2_amount' => $amountM2,
                                ];
                            }
                        }
                    }
                }
            } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') != 'all') {
                $filterDataDateWise = $request->input('filter');
                $stateCode = $request->input('location');
                if ($filterDataDateWise == 'this_week') {
                    $currentDate = \Carbon\Carbon::now();
                    $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                    $endDate = date('Y-m-d', strtotime(now()));
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {

                        $now = Carbon::now();
                        $newDateTime = Carbon::now()->subDays($i);
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'last_week') {
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                    $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                    $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 7; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                        $weekDate = date('Y-m-d', strtotime($startOfLastWeek));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_month') {
                    $endOfLastWeek = Carbon::now();
                    $day = date('d', strtotime($endOfLastWeek));
                    $startDate = date('Y-m-d', strtotime(now()->subDays($day - 1)));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));
                    for ($i = 0; $i <= $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_month') {
                    $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < $month; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_quarter') {

                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
                    $weeks = (int) (($currentMonthDay % 365) / 7);
                    $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));
                    // return $startDate."-".$endDate;
                    $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                    $eom = Carbon::parse($endDate);
                    $dates = [];
                    $f = 'Y-m-d';
                    for ($i = 0; $i < $weeks; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        // $endDate =  date('Y-m-d', strtotime(now()));
                        $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                        $startDate = $date->copy();
                        // loop to end of the week while not crossing the last date of month
                        // return $date->dayOfWeek;
                        while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                            $date->addDay();
                        }

                        $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        if ($date->format($f) < $eom->format($f)) {
                            $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        } else {
                            $dates['w'.$i] = [$startDate->format($f), $eom->format($f)];
                        }

                        $date->addDay();
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        // $month=$sDate.'/'.$eDate;
                        $dates = $dates['w'.$i];
                        $total[] = [
                            'date' => $dates,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }

                } elseif ($filterDataDateWise == 'last_quarter') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                    $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                    $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                    $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
                    $weeks = (int) (($currentMonthDay % 365) / 7);

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));
                    $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                    $eom = Carbon::parse($endDate);
                    $dates = [];
                    $f = 'Y-m-d';
                    for ($i = 0; $i < $weeks; $i++) {
                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));
                        $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                        $startDate = $date->copy();
                        // loop to end of the week while not crossing the last date of month
                        // return $date->dayOfWeek;
                        while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                            $date->addDay();
                        }

                        $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        if ($date->format($f) < $eom->format($f)) {
                            $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
                        } else {
                            $dates['w'.$i] = [$startDate->format($f), $eom->format($f)];
                        }

                        $date->addDay();

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $dates = $dates['w'.$i];

                        $total[] = [
                            'date' => $dates,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_year') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays(30)));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $currentMonth = date('m');
                        if ($i < $currentMonth) {
                            $total[] = [
                                'date' => $month,
                                'm1_account' => round($accountM1->account, 5),
                                'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                            ];

                        }
                    }
                } elseif ($filterDataDateWise == 'last_year') {
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'custom') {
                    $startDate = $filterDataDateWise = $request->input('start_date');
                    $endDate = $filterDataDateWise = $request->input('end_date');

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->avg('kw');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->select('kw', 'm2_date')->where('m2_date', null)->where('customer_state', $stateCode)->count();
                        $clawBackAccount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                        $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                        if ($dateDays <= 15) {
                            for ($i = 0; $i < $dateDays; $i++) {
                                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                                    ->first();

                                $total[] = [
                                    'date' => $weekDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];
                            }
                        } else {
                            $weekCount = round($dateDays / 7);
                            $totalWeekDay = 7 * $weekCount;
                            $extraDay = $dateDays - $totalWeekDay;

                            if ($extraDay > 0) {
                                $weekCount = $weekCount + 1;
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $time = strtotime($sDate);
                                $month = date('M', $time);
                                $total[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];

                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m1_amount');

                                $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m2_amount');

                                $amount[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_amount' => $amountM1,
                                    'm2_amount' => $amountM2,
                                ];
                            }
                        }
                    }
                }
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->max('kw');
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->avg('kw');
                $installKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->where('m2_date', '!=', null)->sum('kw');
                $installCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->where('m2_date', '!=', null)->count();
                $pendingKw = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->where('m2_date', null)->sum('kw');
                $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereIn('pid', $pid)->where('m2_date', null)->count();
                $clawBackAccount = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', '!=', null)->sum('m1_amount');
                $clawBackAccountCount = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', '!=', null)->count();

                for ($i = 0; $i < 7; $i++) {
                    $newDateTime = Carbon::now()->subDays(6 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $amountM1 = SalesMaster::whereIn('pid', $pid)->where($dateColumn, '!=', null)->where('m1_date', $weekDate)
                        ->sum('kw');
                    $amountM2 = SalesMaster::whereIn('pid', $pid)->where($dateColumn, '!=', null)->where('m2_date', $weekDate)
                        ->sum('kw');
                    $clawBack = SalesMaster::whereIn('pid', $pid)->where($dateColumn, '!=', null)->where('date_cancelled', $weekDate)
                        ->sum('kw');

                    $total[] = [
                        'date' => $weekDate,
                        'm1_amount' => round($amountM1, 5),
                        'm2_amount' => round($amountM2, 5),
                        'claw_back' => round($clawBack, 5),
                    ];
                }
            }
        } else {
            if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') == 'all') {
                $filterDataDateWise = $request->input('filter');
                $stateIds = AdditionalLocations::where('user_id', auth()->user()->id)->pluck('state_id')->toArray();
                array_push($stateIds, auth()->user()->state_id);
                $managerState = State::whereIn('id', $stateIds)->pluck('state_code')->toArray();

                if ($filterDataDateWise == 'this_week') {
                    $currentDate = \Carbon\Carbon::now();
                    $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                    $endDate = date('Y-m-d', strtotime(now()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {
                        $now = Carbon::now();
                        $newDateTime = Carbon::now()->subDays($i);
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_week') {
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                    $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                    $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < 7; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                        $weekDate = date('Y-m-d', strtotime($startOfLastWeek));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_month') {
                    $endOfLastWeek = Carbon::now();
                    $day = date('d', strtotime($endOfLastWeek));
                    $startDate = date('Y-m-d', strtotime(now()->subDays($day - 1)));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i <= $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_month') {
                    $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < $month; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_quarter') {
                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                    $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < 3; $i++) {
                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2 - $i)->addDays(30)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'last_quarter') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                    $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                    $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                    $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < 3; $i++) {

                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_year') {
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        // $eDate = date('Y-m-d', strtotime("+". $i+1 ." months", strtotime($startDate)));
                        $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate))); // change by divya

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $currentMonth = date('m');
                        if ($i < $currentMonth) {

                            $total[] = [
                                'date' => $month,
                                'm1_account' => $accountM1->account,
                                'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                            ];

                        }
                    }
                } elseif ($filterDataDateWise == 'last_year') {
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'custom') {
                    $startDate = $filterDataDateWise = $request->input('start_date');
                    $endDate = $filterDataDateWise = $request->input('end_date');

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                        $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->max('kw');
                        $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('customer_state', $managerState)->avg('kw');
                        $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->sum('kw');
                        $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('customer_state', $managerState)->count();
                        $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->sum('kw');
                        $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->whereIn('customer_state', $managerState)->count();
                        $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->sum('m1_amount');
                        $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('customer_state', $managerState)->count();

                        if ($dateDays <= 15) {
                            for ($i = 0; $i < $dateDays; $i++) {
                                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                                $amountM1 = SalesMaster::where('m1_date', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $amountM2 = SalesMaster::where('m2_date', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $clawBack = SalesMaster::where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $total[] = [
                                    'date' => $weekDate,
                                    'm1_amount' => round($amountM1, 5),
                                    'm2_amount' => round($amountM2, 5),
                                    'claw_back' => round($clawBack, 5),
                                    'total_kw' => round($clawBack + $amountM2 + $amountM1, 5),
                                ];
                            }
                        } else {
                            $weekCount = round($dateDays / 7);
                            $totalWeekDay = 7 * $weekCount;
                            $extraDay = $dateDays - $totalWeekDay;

                            if ($extraDay > 0) {
                                $weekCount = $weekCount + 1;
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $time = strtotime($sDate);
                                $month = date('M', $time);
                                $total[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m1_amount');

                                $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m2_amount');

                                $amount[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_amount' => $amountM1,
                                    'm2_amount' => $amountM2,
                                ];
                            }
                        }
                    }

                }
            } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') != 'all') {
                $filterDataDateWise = $request->input('filter');
                $stateCode = $request->input('location');

                if ($filterDataDateWise == 'this_week') {
                    $currentDate = \Carbon\Carbon::now();
                    $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                    $endDate = date('Y-m-d', strtotime(now()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {
                        $now = Carbon::now();
                        $newDateTime = Carbon::now()->subDays($i);
                        $weekDate = date('Y-m-d', strtotime($newDateTime));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_week') {
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                    $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                    $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 7; $i++) {
                        $currentDate = \Carbon\Carbon::now();
                        $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                        $weekDate = date('Y-m-d', strtotime($startOfLastWeek));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'this_month') {
                    $endOfLastWeek = Carbon::now();
                    $day = date('d', strtotime($endOfLastWeek));
                    $startDate = date('Y-m-d', strtotime(now()->subDays($day - 1)));
                    $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    for ($i = 0; $i <= $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'last_month') {
                    $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < $month; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $total[] = [
                            'date' => $weekDate,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_quarter') {
                    $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                    $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 3; $i++) {

                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3 - $i)->addDays(30)));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2 - $i)->addDays(30)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'last_quarter') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                    $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                    $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                    $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 3; $i++) {

                        $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                        $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];

                    }
                } elseif ($filterDataDateWise == 'this_year') {
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        //  $eDate = date('Y-m-t', strtotime("+". $i+1 ." months", strtotime($startDate)));
                        $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate))); // change by divya

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $currentMonth = date('m');
                        if ($i < $currentMonth) {

                            $total[] = [
                                'date' => $month,
                                'm1_account' => $accountM1->account,
                                'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                            ];

                        }
                    }
                } elseif ($filterDataDateWise == 'last_year') {
                    // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                    // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                    $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                    $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                    $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                    $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                    $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                    $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                    $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                    for ($i = 0; $i < 12; $i++) {
                        $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                        $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                            ->first();

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $total[] = [
                            'date' => $month,
                            'm1_account' => round($accountM1->account, 5),
                            'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                        ];
                    }
                } elseif ($filterDataDateWise == 'custom') {
                    $startDate = $filterDataDateWise = $request->input('start_date');
                    $endDate = $filterDataDateWise = $request->input('end_date');

                    $now = strtotime($endDate);
                    $your_date = strtotime($startDate);
                    $dateDiff = $now - $your_date;
                    $dateDays = floor($dateDiff / (60 * 60 * 24));

                    if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                        $largestSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('customer_state', $stateCode)->max('kw');
                        $avgSystemSize = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->sum('kw');
                        $installCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('customer_state', $stateCode)->count();
                        $pendingKw = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->sum('kw');
                        $pendingKwCount = SalesMaster::select('kw', 'm2_date')->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', null)->where('customer_state', $stateCode)->count();
                        $clawBackAccount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->sum('m1_amount');
                        $clawBackAccountCount = SalesMaster::where('id', auth()->user()->id)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->where('customer_state', $stateCode)->count();

                        if ($dateDays <= 15) {
                            for ($i = 0; $i < $dateDays; $i++) {
                                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                                $amountM1 = SalesMaster::where('m1_date', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $amountM2 = SalesMaster::where('m2_date', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $clawBack = SalesMaster::where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)
                                    ->sum('kw');
                                $total[] = [
                                    'date' => $weekDate,
                                    'm1_amount' => round($amountM1, 5),
                                    'm2_amount' => round($amountM2, 5),
                                    'claw_back' => round($clawBack, 5),
                                    'total_kw' => round($clawBack + $amountM2 + $amountM1, 5),
                                ];
                            }
                        } else {
                            $weekCount = round($dateDays / 7);
                            $totalWeekDay = 7 * $weekCount;
                            $extraDay = $dateDays - $totalWeekDay;

                            if ($extraDay > 0) {
                                $weekCount = $weekCount + 1;
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();

                                $time = strtotime($sDate);
                                $month = date('M', $time);
                                $total[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_account' => round($accountM1->account, 5),
                                    'm2_account' => round($accountM2->account, 5),
                                    'claw_back' => round($clawBack->account, 5),
                                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                                ];
                            }

                            for ($i = 0; $i < $weekCount; $i++) {
                                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                                $dayWeek = 7 * $i;
                                if ($i == 0) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                                } else {

                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                                }
                                if ($i == $weekCount - 1) {
                                    $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                    $eDate = $endDate;
                                }

                                $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m1_amount');

                                $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                    ->orderBy('m1_date', 'desc')
                                    ->sum('m2_amount');

                                $amount[] = [
                                    'date' => $sDate.' to '.$eDate,
                                    'm1_amount' => $amountM1,
                                    'm2_amount' => $amountM2,
                                ];
                            }
                        }
                    }

                }
            }
        }

        $data['heading_count_kw'] = [
            'largest_system_size' => round($largestSystemSize, 5),
            'avg_system_size' => round($avgSystemSize, 5),
            'install_kw' => round($installKw, 5).'('.$installCount.')',
            'pending_kw' => $pendingKw.'('.$pendingKwCount.')',
            'clawBack_account' => $clawBackAccount.'('.$clawBackAccountCount.')',
        ];

        $data['my_sales'] = $total;
        $data['kw_type'] = $kwType;

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function mySalesGraph(Request $request): JsonResponse
    {
        $data = [];
        $date = date('Y-m-d');
        $kwType = isset($request->kw_type) ? $request->kw_type : 'sold';
        $total = [];
        $mdates = getdates();
        if ($kwType == '' || $kwType == 'sold') {
            $dateColumn = 'customer_signoff';
        } else {
            $dateColumn = 'm2_date';
        }
        $clawbackPid = DB::table('clawback_settlements')->where('user_id', auth()->user()->id)->distinct()->pluck('pid')->toArray();
        $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid');
        $filterDataDateWise = $request->input('filter');
        $companyProfile = CompanyProfile::first();
        if ($filterDataDateWise == 'this_week') {
            $csdate = Carbon::now()->startOfWeek();
            $cedate = Carbon::now();

            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $clawBackAccount = SalesMaster::leftJoin('clawback_settlements', function ($join) {
                $join->on('sale_masters.pid', '=', 'clawback_settlements.pid');
            })
                ->whereIn('clawback_settlements.pid', $clawbackPid)
                ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
                ->where('date_cancelled', '!=', null)
                ->sum('clawback_settlements.clawback_amount');

            $clawBackAccountCount = SalesMaster::leftJoin('clawback_settlements', function ($join) {
                $join->on('sale_masters.pid', '=', 'clawback_settlements.pid');
            })
                ->whereIn('clawback_settlements.pid', $clawbackPid)
                ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
                ->where('date_cancelled', '!=', null)
                ->count();
            for ($date = $csdate; $date->lte($cedate); $date->addDays(1)) {
                $now = Carbon::now();

                $newDateTime = $date;
                $weekDate = date('Y-m-d', strtotime($newDateTime));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $total[] = [
                    'date' => date('m/d/Y', strtotime($newDateTime)),
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kwTotals, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            for ($i = 0; $i < 7; $i++) {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $total[] = [
                    'date' => date('m/d/Y', strtotime($startDate.' + '.$i.' days')),
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kw, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'this_month') {
            $endOfLastWeek = Carbon::now();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            $currentMonthDay = Carbon::now()->daysInMonth;
            $weeks = (int) (($currentMonthDay % 365) / 7);

            $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-0 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/y';

            for ($i = 0; $i <= $dateDays; $i++) {
                $weekDate = date('Y-m-d', strtotime(Carbon::now()->startOfMonth()->addDays($i)));

                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDates = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime($eDates.'-1 day'));

                $startDate = $date->copy();
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }

                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }

                $date->addDay();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $total[] = [
                    'date' => date('m/d/Y', strtotime(Carbon::now()->startOfMonth()->addDays($i))),
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kwTotals, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'last_month') {
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            $startDate = Carbon::now()->startOfMonth()->subMonth()->toDateString();
            $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            for ($i = 0; $i < $month; $i++) {
                $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i)));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->where('date_cancelled', null)->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m1_date', $weekDate)->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($weekDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', $weekDate);
                        })
                        ->where('m2_date', $weekDate)->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->where($dateColumn, $weekDate)
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->where('date_cancelled', $weekDate)
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->where('date_cancelled', $weekDate)->where('date_cancelled', null)->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $total[] = [
                    'date' => date('m/d/Y', strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i))),
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kwTotals, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'this_quarter') {
            $currentMonth = date('n');
            if ($currentMonth >= 1 && $currentMonth <= 3) {
                // Q1: January 1 - March 31
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear())));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth())));
            } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                // Q2: April 1 - June 30
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)->startOfMonth())));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth())));
            } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                // Q3: July 1 - September 30
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)->startOfMonth())));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth())));
            } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                // Q4: October 1 - December 31
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)->startOfMonth())));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth())));
            }
            $currentMonthDay = $startDate->diffInDays($endDate);
            $weeks = (int) (($currentMonthDay % 365) / 7);

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/Y';
            for ($i = 0; $i < $weeks; $i++) {
                $startDate = $date->copy();
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }

                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }
                $date->addDay();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->where('date_cancelled', '!=', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $time = strtotime($sDate);
                $weekDate = $dates['w'.$i];
                $total[] = [
                    'date' => $weekDate,
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($accountM1->kw + $accountM2->kw + $clawBack->kw, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'last_quarter') {
            $currentMonth = date('n');
            if ($currentMonth >= 1 && $currentMonth <= 3) {
                // Q4 of last year: October 1 - December 31
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9))));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth())));
            } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                // Q1 of current year: January 1 - March 31
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear())));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth())));
            } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                // Q2 of current year: April 1 - June 30
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3))));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth())));
            } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                // Q3 of current year: July 1 - September 30
                $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6))));
                $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth())));
            }
            $currentMonthDay = $startDate->diffInDays($endDate);
            $weeks = (int) (($currentMonthDay % 365) / 7);

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            $date = Carbon::parse($startDate = date('Y/m/d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/Y';
            for ($i = 0; $i < $weeks; $i++) {
                $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                $startDate = $date->copy();
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }
                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }
                $date->addDay();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('gross_account_value');
                    $kwTotals = $kw;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('kw');
                    $kwTotals = $kw;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $time = strtotime($sDate);
                $month = date('M', $time);
                $weekDate = $dates['w'.$i];

                $total[] = [
                    'date' => $weekDate,
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kwTotals, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDates = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime($eDates.'-1 day'));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('gross_account_value');

                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('gross_account_value');

                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('kw');

                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('kw');

                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->first();
                }

                $time = strtotime($sDate);
                $month = date('M', $time);
                $currentMonth = date('m');

                if ($i < $currentMonth) {
                    $total[] = [
                        'date' => $month,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($kwTotals, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            }
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('gross_account_value');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('gross_account_value');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                } else {
                    $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                        ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                            $query->select('date_cancelled')
                                ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                        })
                        ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                    $kw = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween($dateColumn, [$sDate, $eDate])
                        ->sum('kw');
                    $kwCancel = SalesMaster::whereIn('pid', $pid)
                        ->whereBetween('date_cancelled', [$sDate, $eDate])
                        ->sum('kw');
                    $kwTotals = $kw - $kwCancel;
                    if ($kwTotals > 0) {
                        $kwTotals = $kwTotals;
                    } else {
                        $kwTotals = 0;
                    }

                    $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                        ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $clawbackPid)
                        ->first();
                }

                $time = strtotime($sDate);
                $month = date('M', $time);
                $total[] = [
                    'date' => $month,
                    // 'm1_account' => round($accountM1->account, 5),
                    // 'm2_account' => round($accountM2->account, 5),
                    'claw_back' => round($clawBack->account, 5),
                    'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                    'total_kw' => round($kwTotals, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDateObj = Carbon::now()->subMonths(12)->startOfMonth();
            $startDate = $startDateObj->format('Y-m-d');
            $endDateObj = Carbon::now();
            $endDate = $endDateObj->format('Y-m-d');
            $currentDate = $startDateObj->copy();
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);

                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m1_date', '!=', null)->where('m1_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $kw = SalesMaster::whereIn('pid', $pid)
                                ->where($dateColumn, $weekDate)
                                ->where('date_cancelled', null)
                                ->sum('gross_account_value');
                            $kwTotals = $kw;
                            if ($kwTotals > 0) {
                                $kwTotals = $kwTotals;
                            } else {
                                $kwTotals = 0;
                            }

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawbackPid)->first();
                        } else {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m1_date', '!=', null)->where('m1_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $kw = SalesMaster::whereIn('pid', $pid)
                                ->where($dateColumn, $weekDate)
                                ->where('date_cancelled', null)
                                ->sum('kw');
                            $kwTotals = $kw;
                            if ($kwTotals > 0) {
                                $kwTotals = $kwTotals;
                            } else {
                                $kwTotals = 0;
                            }

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawbackPid)
                                ->first();
                        }
                        $weekDates = date('m/d/Y', strtotime($startDate.' + '.$i.' days'));
                        $total[] = [
                            'date' => $weekDates,
                            // 'm1_account' => round($accountM1->account, 5),
                            // 'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($kwTotals, 5),
                        ];
                        foreach ($mdates as $date) {
                            $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                        }
                    }
                } else {
                    // $weekCount = round($dateDays / 7);
                    // $totalWeekDay = 7 * $weekCount;
                    // $extraDay = $dateDays - $totalWeekDay;

                    // if ($extraDay > 0) {
                    //     $weekCount = $weekCount + 1;
                    // }

                    // for ($i = 0; $i < $weekCount; $i++) {
                    while ($currentDate <= $endDateObj) {

                        $month = $currentDate->format('F');
                        $sDate = $currentDate->copy()->startOfMonth()->format('Y-m-d');
                        $eDate = $currentDate->copy()->endOfMonth()->format('Y-m-d');
                        if ($eDate > $endDate) {
                            $eDate = $endDate;
                        }
                        $currentDate->addMonth();
                        // $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                        // $dayWeek = 7 * $i;
                        // if ($i == 0) {
                        //     $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                        //     $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                        // } else {

                        //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                        // }
                        // if ($i == $weekCount - 1) {
                        //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDate = $endDate;
                        // }

                        $totalKw = 0;
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->first();

                            $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                        } else {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->first();

                            $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                        }

                        // $time = strtotime($sDate);
                        // $month = date("M", $time);
                        // $month = date("M" , $sDate); //$currentDate->format('F');
                        // $WeekStartDate = date('m/d/y', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        // $weekEndDate = date('m/d/y', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                        $total[] = [
                            'date' => $month, // $WeekStartDate . ' to ' . $weekEndDate,
                            // 'm1_account' => round($accountM1->account, 5),
                            // 'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($totalKw, 5),
                        ];
                        foreach ($mdates as $date) {
                            $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                        }

                    }

                    // for ($i = 0; $i < $weekCount; $i++) {
                    //     // $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                    //     // $dayWeek = 7 * $i;
                    //     // if ($i == 0) {
                    //     //     $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                    //     //     $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                    //     // } else {

                    //     //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                    //     //     $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                    //     // }
                    //     // if ($i == $weekCount - 1) {
                    //     //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                    //     //     $eDate = $endDate;
                    //     // }

                    //     $amountM1 = SalesMaster::whereBetween('m1_date', [$sDate, $eDate])
                    //         ->orderBy('m1_date', 'desc')
                    //         ->sum('m1_amount');

                    //     $amountM2 = SalesMaster::whereBetween('m2_date', [$sDate, $eDate])
                    //         ->orderBy('m1_date', 'desc')
                    //         ->sum('m2_amount');
                    //     $WeekStartDate = date('m/d/y', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                    //     $weekEndDate = date('m/d/y', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                    //     $amount[] = [
                    //         'date' => $WeekStartDate . ' to ' . $weekEndDate,
                    //         'm1_amount' => $amountM1,
                    //         'm2_amount' => $amountM2
                    //     ];
                    // }
                }
            }
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);

                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m1_date', '!=', null)->where('m1_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $kw = SalesMaster::whereIn('pid', $pid)
                                ->where($dateColumn, $weekDate)
                                ->where('date_cancelled', null)
                                ->sum('gross_account_value');
                            $kwTotals = $kw;
                            if ($kwTotals > 0) {
                                $kwTotals = $kwTotals;
                            } else {
                                $kwTotals = 0;
                            }

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawbackPid)
                                ->first();
                        } else {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m1_date', '!=', null)->where('m1_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m2_date', '!=', null)->where('m2_date', $weekDate)->whereIn('pid', $pid)
                                ->first();

                            $kw = SalesMaster::whereIn('pid', $pid)
                                ->where($dateColumn, $weekDate)
                                ->where('date_cancelled', null)
                                ->sum('kw');
                            $kwTotals = $kw;
                            if ($kwTotals > 0) {
                                $kwTotals = $kwTotals;
                            } else {
                                $kwTotals = 0;
                            }

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawbackPid)
                                ->first();
                        }
                        $weekDates = date('m/d/Y', strtotime($startDate.' + '.$i.' days'));
                        $total[] = [
                            'date' => $weekDates,
                            // 'm1_account' => round($accountM1->account, 5),
                            // 'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($kwTotals, 5),
                        ];
                        foreach ($mdates as $date) {
                            $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                        }
                    }
                } else {
                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                        } else {

                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;
                        }

                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->first();

                            $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                        } else {
                            $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m1_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('m2_date', '!=', null)->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->where('date_cancelled', function ($query) use ($sDate, $eDate) {
                                    $query->select('date_cancelled')
                                        ->whereNotBetween('date_cancelled', [$sDate, $eDate]);
                                })->orWhere('date_cancelled', null)
                                ->whereBetween('m2_date', [$sDate, $eDate])->whereIn('pid', $pid)->first();

                            $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                ->where('date_cancelled', '!=', null)->whereBetween('date_cancelled', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->first();

                            $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                        }

                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $WeekStartDate = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                        $weekEndDate = date('m/d/y', strtotime($endsDate.' + '.$dayWeek.' days'));
                        $total[] = [
                            'date' => $WeekStartDate.' to '.$weekEndDate,
                            // 'm1_account' => round($accountM1->account, 5),
                            // 'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($totalKw, 5),
                        ];
                        foreach ($mdates as $date) {
                            $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                        }
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                        } else {

                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;
                        }

                        $amountM1 = SalesMaster::whereBetween('m1_date', [$sDate, $eDate])
                            ->orderBy('m1_date', 'desc')
                            ->sum('m1_amount');

                        $amountM2 = SalesMaster::whereBetween('m2_date', [$sDate, $eDate])
                            ->orderBy('m1_date', 'desc')
                            ->sum('m2_amount');
                        $WeekStartDate = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                        $weekEndDate = date('m/d/y', strtotime($endsDate.' + '.$dayWeek.' days'));
                        $amount[] = [
                            'date' => $WeekStartDate.' to '.$weekEndDate,
                            'm1_amount' => $amountM1,
                            'm2_amount' => $amountM2,
                        ];
                    }
                }
            }
        } else {
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->avg('gross_account_value');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->count();
            } else {
                $largestSystemSize = SalesMaster::whereIn('pid', $pid)->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                $avgSystemSize = SalesMaster::whereIn('pid', $pid)->avg('kw');
                $installKw = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                $installCount = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                $pendingKw = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m2_date')->count();
            }

            $clawBackPid = SalesMaster::whereIn('pid', $clawbackPid)->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
            $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
            $clawBackAccountCount = count($clawBackPid);

            for ($i = 0; $i < 7; $i++) {
                $newDateTime = Carbon::now()->subDays(6 - $i);
                $weekDate = date('Y-m-d', strtotime($newDateTime));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $amountM1 = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('m1_date', $weekDate)
                        ->whereIn('pid', $pid)->sum('gross_account_value');
                    $amountM2 = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('m2_date', $weekDate)
                        ->whereIn('pid', $pid)->sum('gross_account_value');
                    $clawBack = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', $weekDate)
                        ->whereIn('pid', $clawbackPid)->sum('gross_account_value');
                } else {
                    $amountM1 = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('m1_date', $weekDate)
                        ->whereIn('pid', $pid)->sum('kw');
                    $amountM2 = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('m2_date', $weekDate)
                        ->whereIn('pid', $pid)->sum('kw');
                    $clawBack = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', $weekDate)
                        ->whereIn('pid', $clawbackPid)->sum('kw');
                }

                $total[] = [
                    'date' => date('m/d/Y', strtotime($newDateTime)),
                    // 'm1_amount' => round($amountM1, 5),
                    // 'm2_amount' => round($amountM2, 5),
                    'claw_back' => round($clawBack, 5),
                ];
                foreach ($mdates as $date) {
                    $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                }
            }
        }

        $data['heading_count_kw'] = [
            'largest_system_size' => round($largestSystemSize, 5),
            'avg_system_size' => round($avgSystemSize, 5),
            'install_kw' => round($installKw, 5).'('.$installCount.')',
            'pending_kw' => $pendingKw.'('.$pendingKwCount.')',
            'clawBack_account' => $clawBackAccount.'('.$clawBackAccountCount.')',
        ];

        $data['my_sales'] = $total;
        $data['kw_type'] = $kwType;

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function account_graph(Request $request)
    {
        $date = date('Y-m-d');
        $position = auth()->user()->position_id;
        $clawbackPid = DB::table('clawback_settlements')->where('user_id', auth()->user()->id)->distinct()->pluck('pid')->toArray();

        $kwType = isset($request->kw_type) ? $request->kw_type : 'sold';
        $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid');

        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $date])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $date])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$date])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            $currentDate->dayOfWeek;
            for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {
                $now = Carbon::now();
                $newDateTime = Carbon::now()->subDays($i);
                $weekDate = date('m-d-Y', strtotime($newDateTime));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                // ->whereIn('pid',$pids)
                // //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                // ->first();
                // $amountM1 = $salesm1m2Amount->m1;
                // $amountM2 = $salesm1m2Amount->m2;

                $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;
                // }

                $amount[] = [
                    'date' => date('m/d/Y', strtotime($newDateTime)),
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < 7; $i++) {
                $currentDate = \Carbon\Carbon::now();
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                $weekDate = date('m-d-Y', strtotime($startOfLastWeek));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                //     $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();
                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => date('m/d/Y', strtotime($startOfLastWeek)),
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'this_month') {

            // $endOfLastWeek = Carbon::now();
            // $day =  date('d', strtotime($endOfLastWeek));
            // $startDate =  date('Y-m-d', strtotime(now()->subDays($day-1)));
            // $endDate =  date('Y-m-d', strtotime($endOfLastWeek));

            $month = \Carbon\Carbon::now()->daysInMonth;
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            for ($i = 0; $i <= $dateDays; $i++) {
                // $weekDate =  date('m-d-Y', strtotime(now()->subDays($i)));
                $weekDate = date('m/d/Y', strtotime(Carbon::now()->startOfMonth()->addDays($i)));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                //     $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pid)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();
                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'last_month') {
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < $month; $i++) {
                // $weekDate =  date('Y-m-d', strtotime(now()->subDays($i)));
                $weekDate = date('m/d/y', strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i)));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                //     $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();
                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'this_quarter') {
            // $currentMonthDay = Carbon::now()->daysInMonth+Carbon::now()->month(01)->daysInMonth+Carbon::now(03)->month()->daysInMonth;
            // $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
            $weeks = (int) (($currentMonthDay % 365) / 7);

            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->where('m1_date',null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            // return $startDate."-".$endDate;
            $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/y';
            for ($i = 0; $i < $weeks; $i++) {
                $currentDate = \Carbon\Carbon::now();
                // $endDate =  date('Y-m-d', strtotime(now()));

                $startDate = $date->copy();
                // loop to end of the week while not crossing the last date of month
                // return $date->dayOfWeek;
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }

                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }

                $date->addDay();
                // \DB::enableQueryLog();
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid');
                // dd(\DB::getQueryLog());
                // echo $i;

                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {

                //      $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();

                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $time = strtotime($sDate);
                // $month=$sDate.'/'.$eDate;
                $weekDate = $dates['w'.$i];
                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'last_quarter') {
            // $currentMonthDay = Carbon::now()->daysInMonth+Carbon::now()->month(01)->daysInMonth+Carbon::now(03)->month()->daysInMonth;
            // $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
            $weeks = (int) (($currentMonthDay % 365) / 7);

            $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
            $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
            $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
            $data = [];

            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            $date = Carbon::parse($startDate = date('Y/m/d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/y';
            for ($i = 0; $i < $weeks; $i++) {
                $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                $startDate = $date->copy();
                // loop to end of the week while not crossing the last date of month
                // return $date->dayOfWeek;
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }

                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }

                $date->addDay();
                // \DB::enableQueryLog();
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                // dd(\DB::getQueryLog());
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                //     $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();
                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                // $currentDate = \Carbon\Carbon::now();
                //     //$endDate =  date('Y-m-d', strtotime(now()));
                //     $sDate =  date('Y-m-d', strtotime(Carbon::today()->subMonths(3-$i)->addDays(30)));
                //     $eDate= date("Y-m-d", strtotime("+7 days", strtotime($sDate)));
                //     $startDate = $date->copy();
                //     //loop to end of the week while not crossing the last date of month
                //     //return $date->dayOfWeek;
                // while($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)){
                //     $date->addDay();
                // }

                //     $dates['w'.$i] = $startDate->format($f) .' to '. $date->format($f);
                //     if ($date->format($f) < $eom->format($f)) {
                //         $dates['w'.$i] = $startDate->format($f) .' to '. $date->format($f);
                //     }else{
                //         $dates['w'.$i] = $startDate->format($f) .' to '. $eom->format($f);
                //     }

                //     $date->addDay();

                $time = strtotime($sDate);
                $month = date('M', $time);
                $weekDate = $dates['w'.$i];
                $amount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'this_year') {
            // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDates = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime($eDates.'-1 day'));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {

                //    $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();

                // $salesm1Amount = UserCommission::whereBetween('updated_at',[$sDate,$eDate])->where('user_id',auth()->user()->id)->where('status',3)->where('amount_type','m1')->sum('amount');
                // $amountM1 = $salesm1Amount;
                // $salesm2Amount = UserCommission::whereBetween('updated_at',[$sDate,$eDate])->where('user_id',auth()->user()->id)->where('status',3)->where('amount_type','m2')->sum('amount');
                // $amountM2 = $salesm2Amount;

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                // }

                $time = strtotime($sDate);
                $month = date('M', $time);
                $amount[] = [
                    'date' => $month,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
                // if (count($pids)>0) {
                //    return $amount;
                // }
            }
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

            $data = [];
            $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
            $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
            $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
            // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
            $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                $pids = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;
                // if (count($pids)>0) {
                //     $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                //     ->whereIn('pid',$pids)
                //     //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                //     ->first();
                //     $amountM1 = $salesm1m2Amount->m1;
                //     $amountM2 = $salesm1m2Amount->m2;
                // }

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $time = strtotime($sDate);
                $month = date('M', $time);
                $amount[] = [
                    'date' => $month,
                    'm1_amount' => $amountM1,
                    'm2_amount' => $amountM2,
                ];
            }
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                $data = [];
                $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
                $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
                $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
                $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

                $currentDateTime = Carbon::now();
                $m1Amount = [];
                $m2Amount = [];
                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));

                        $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        // if (count($pids)>0) {
                        // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                        // ->whereIn('pid',$pids)
                        // //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                        // ->first();
                        // $amountM1 = $salesm1m2Amount->m1;
                        // $amountM2 = $salesm1m2Amount->m2;
                        // $salesm1Amount = UserCommission::whereDate('updated_at',$weekDate)->where('user_id',auth()->user()->id)->where('status',5)->where('amount_type','m1')->sum('amount');
                        $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                        $amountM1 = $salesm1Amount;

                        // $salesm2Amount = UserCommission::whereDate('updated_at',$weekDate)->where('user_id',auth()->user()->id)->where('status',5)->where('amount_type','m2')->sum('amount');
                        $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                        $amountM2 = $salesm2Amount;
                        // }
                        $weekDates = date('m-d-y', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                        $amount[] = [
                            'date' => $weekDates,
                            'm1_amount' => $amountM1,
                            'm2_amount' => $amountM2,
                        ];
                    }
                } else {

                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));

                            $sDateg = date('m/d/y', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endsDate.' - '. 0 .' days'));
                        } else {

                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));

                            $sDateg = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;

                            $sDateg = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endDate.' + '.$dayWeek.' days'));
                        }

                        $pids = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        if (count($pids) > 0) {
                            // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            // ->whereIn('pid',$pids)
                            // //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                            // ->first();
                            // $amountM1 = $salesm1m2Amount->m1;
                            // $amountM2 = $salesm1m2Amount->m2;
                            $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                            $amountM1 = $salesm1Amount;

                            $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                            $amountM2 = $salesm2Amount;
                        }

                        $amount[] = [
                            'date' => $sDateg.' to '.$eDateg,
                            'm1_amount' => $amountM1,
                            'm2_amount' => $amountM2,
                        ];
                    }
                }

            } else {
                return response()->json([

                    'status' => false,
                    'message' => 'Custom Start Date and End Date id Required.',

                ], 200);
            }
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                $data = [];
                $totalSales = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->get();
                $m2Complete = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->count();
                $m2Pending = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereIn('pid',$pid)->whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
                $cancelled = SalesMaster::whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $date])->where('date_cancelled', '!=', null)->count();
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();

                $currentDateTime = Carbon::now();
                $m1Amount = [];
                $m2Amount = [];
                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));

                        $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        // if (count($pids)>0) {
                        // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                        // ->whereIn('pid',$pids)
                        // //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                        // ->first();
                        // $amountM1 = $salesm1m2Amount->m1;
                        // $amountM2 = $salesm1m2Amount->m2;
                        // $salesm1Amount = UserCommission::whereDate('updated_at',$weekDate)->where('user_id',auth()->user()->id)->where('status',5)->where('amount_type','m1')->sum('amount');
                        $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                        $amountM1 = $salesm1Amount;

                        // $salesm2Amount = UserCommission::whereDate('updated_at',$weekDate)->where('user_id',auth()->user()->id)->where('status',5)->where('amount_type','m2')->sum('amount');
                        $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                        $amountM2 = $salesm2Amount;
                        // }
                        $weekDates = date('m-d-y', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                        $amount[] = [
                            'date' => $weekDates,
                            'm1_amount' => $amountM1,
                            'm2_amount' => $amountM2,
                        ];
                    }
                } else {

                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));

                            $sDateg = date('m/d/y', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endsDate.' - '. 0 .' days'));
                        } else {

                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));

                            $sDateg = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;

                            $sDateg = date('m/d/y', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDateg = date('m/d/y', strtotime($endDate.' + '.$dayWeek.' days'));
                        }

                        $pids = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        if (count($pids) > 0) {
                            // $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            // ->whereIn('pid',$pids)
                            // //->where('closer1_id',auth()->user()->id)->orWhere('closer2_id',auth()->user()->id)->orWhere('setter1_id',auth()->user()->id)->orWhere('setter2_id',auth()->user()->id)
                            // ->first();
                            // $amountM1 = $salesm1m2Amount->m1;
                            // $amountM2 = $salesm1m2Amount->m2;
                            $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                            $amountM1 = $salesm1Amount;

                            $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', auth()->user()->id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                            $amountM2 = $salesm2Amount;
                        }

                        $amount[] = [
                            'date' => $sDateg.' to '.$eDateg,
                            'm1_amount' => $amountM1,
                            'm2_amount' => $amountM2,
                        ];
                    }
                }

            } else {
                return response()->json([

                    'status' => false,
                    'message' => 'Custom Start Date and End Date id Required.',

                ], 200);
            }
        }
        $data['accounts'] = [
            'total_sales' => count($totalSales),
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $clawback,
        ];
        if ($m2Complete > 0 && count($totalSales) > 0) {
            $data['install_ratio'] = [
                'install' => round((($m2Complete / count($totalSales)) * 100), 5).'%',
                'uninstall' => round(($m2Pending / count($totalSales) * 100), 5).'%',
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '0%',
            ];
        }
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

    public function payStub(Request $request)
    {
        $user_id = Auth::user()->id;
        $data['company'] = CompanyProfile::select('id', 'address', 'phone_number', 'company_email')->first();

        $payroll = Payroll::where('user_id', $user_id)->first();
        $data['paystub'] = [
            'pay_period' => null,
            'amount' => isset($payroll->net_pay) ? $payroll->net_pay : null,
            'ytd' => null,
        ];

        $emp_info = User::with(['departmentDetail', 'positionDetail', 'employeeBank'])->where('id', $user_id)->first();
        $data['employee_information'] = [
            'emp_id' => isset($emp_info->id) ? $emp_info->id : null,
            'name' => isset($emp_info->first_name) ? $emp_info->first_name.' '.$emp_info->last_name : null,
            'department' => isset($emp_info->departmentDetail->name) ? $emp_info->departmentDetail->name : null,
            'position' => isset($emp_info->positionDetail->position_name) ? $emp_info->positionDetail->position_name : null,
            'bank_account' => isset($emp_info->employeeBank->acconut_number) ? $emp_info->employeeBank->acconut_number : null,
            'ssn' => null,
        ];

        $earnings = UserCommission::with('payroll')->where('user_id', $user_id)->get();
        $earnings->transform(function ($data) {
            $total_m1_count = UserCommission::where('amount_type', 'm1')->where('user_id', $data->user_id)->count();
            $total_m1_sum = UserCommission::where('amount_type', 'm1')->where('user_id', $data->user_id)->sum('amount');
            $total_m2_count = UserCommission::where('amount_type', 'm2')->where('user_id', $data->user_id)->count();
            $total_m2_sum = UserCommission::where('amount_type', 'm2')->where('user_id', $data->user_id)->sum('amount');
            $total_override_count = Payroll::where('user_id', $data->user_id)->count();
            $total_override_sum = Payroll::where('user_id', $data->user_id)->sum('override');
            $total_gross_pay = $total_m1_sum + $total_m2_sum + $total_override_sum;
            $ytd_gross_pay = null;

            return [
                'total_m1_count' => $total_m1_count,
                'total_m1_sum' => $total_m1_sum,
                'ytd_m1_ytd' => null,
                'total_m2_count' => $total_m2_count,
                'total_m2_sum' => $total_m2_sum,
                'ytd_m2_ytd' => null,
                'total_override_count' => $total_override_count,
                'total_override_sum' => $total_override_sum,
                'total_gross_pay' => $total_gross_pay,
                'ytd_gross_pay' => $ytd_gross_pay,
            ];
        });
        $data['earnings'] = $earnings;

        $deductions = ApprovalsAndRequest::with('costcenter')->where('user_id', $user_id)->get();

        $deductions->transform(function ($data) {
            if ($data->costcenter->name == 'Travel') {
                $travel_amount = $data->sum('amount');
            } else {
                $travel_amount = 0;
            }
            if ($data->costcenter->name == 'Rent') {
                $rent_amount = $data->sum('amount');
            } else {
                $rent_amount = 0;
            }
            $rent_ytd = null;
            $travel_ytd = null;
            $clawback_amount = ClawbackSettlement::with('users')->where('user_id', $data->user_id)->sum('clawback_amount');
            $clawback_ytd = null;
            $miscellaneous_amount = null;
            $miscellaneous_ytd = null;
            $total_deduction_amount = $rent_amount + $travel_amount + $clawback_amount + $miscellaneous_amount;
            $total_deduction_ytd = null;

            return [
                'rent_amount' => $rent_amount,
                'rent_ytd' => $rent_ytd,
                'travel_amount' => $travel_amount,
                'travel_ytd' => $travel_ytd,
                'clawback_amount' => $clawback_amount,
                'clawback_ytd' => $clawback_ytd,
                'miscellaneous_amount' => $miscellaneous_amount,
                'miscellaneous_ytd' => $miscellaneous_ytd,
                'total_deduction_amount' => $total_deduction_amount,
                'total_deduction_ytd' => $total_deduction_ytd,
            ];
        });
        $data['deductions'] = $deductions;

        return response()->json([
            'ApiName' => 'pay stub list',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function accountOverride($id)
    {
        if (isset($id)) {
            $subQuery = UserOverrides::select(DB::raw('MAX(id) as id'))
                ->where(['pid' => $id, 'is_displayed' => '1'])->groupBy('user_id', 'type', 'sale_user_id');

            $account_override = UserOverrides::select('user_overrides.*', DB::raw('SUM(sub.amount) as amount'))
                ->joinSub($subQuery, 'latest_records', function ($join) {
                    $join->on('user_overrides.id', '=', 'latest_records.id');
                })
                ->join('user_overrides as sub', function ($join) {
                    $join->on('sub.user_id', '=', 'user_overrides.user_id')->on('sub.pid', '=', 'user_overrides.pid')->on('sub.sale_user_id', '=', 'user_overrides.sale_user_id')->on('sub.type', '=', 'user_overrides.type');
                })->with('user')
                ->where(['user_overrides.pid' => $id, 'user_overrides.is_displayed' => '1', 'sub.is_displayed' => '1'])
                ->groupBy('user_overrides.user_id', 'user_overrides.type', 'user_overrides.sale_user_id')
                ->orderBy('user_overrides.id')->get();

            // $account_override = UserOverrides::select('*', DB::raw('SUM(amount) as amount'))->with('user')
            //     ->where(['pid' => $id, 'is_displayed' => '1'])->whereIn('id', function ($q) use ($id) {
            //         $q->selectRaw('MAX(id)')->from('user_overrides')->where(['pid' => $id, 'is_displayed' => '1'])->groupBy('user_id', 'type');
            //     })->groupBy('user_id', 'type')->orderBy('id')->get();

            $saleMasterProcess = SaleMasterProcess::where('pid', $id)->first();
            $account_override->transform(function ($data) use ($saleMasterProcess) {
                if ($data->sale_user_id == $saleMasterProcess->closer1_id || $data->sale_user_id == $saleMasterProcess->closer2_id) {
                    $positionName = 'Closer';
                } else {
                    $positionName = 'Setter';
                }

                $image = isset($data->user->image) ? $data->user->image : null;
                $first_name = isset($data->user->first_name) ? $data->user->first_name : null;
                $last_name = isset($data->user->last_name) ? $data->user->last_name : null;
                if ($image) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$image);
                } else {
                    $s3_image = null;
                }
                $clawback = ClawbackSettlement::where(['pid' => $data->pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');

                $weight = $data->overrides_type;
                if ($data->type == 'Stack') {
                    if ($data->calculated_redline_type == 'per sale' || $data->calculated_redline_type == 'per kw') {
                        $weight = $data->calculated_redline_type;
                    } else {
                        $weight = 'percent';
                    }
                }

                return [
                    'through' => $positionName,
                    'user_id' => $data->user_id,
                    'image' => $image,
                    'image_s3' => $s3_image,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'position_id' => isset($data->user->position_id) ? $data->user->position_id : null,
                    'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : null,
                    'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : null,
                    'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : null,
                    'type' => $data->type,
                    'amount' => $data->overrides_amount,
                    'weight' => $weight,
                    'total' => ($data->amount - $clawback),
                    'calculated_redline' => $data->calculated_redline,
                    'assign_cost' => null,
                ];
            });

            return response()->json([
                'ApiName' => 'account overrides',
                'status' => true,
                'message' => 'Successfully',
                'data' => ['account_override' => $account_override],
            ]);
        } else {
            return response()->json([
                'ApiName' => 'account overrides',
                'status' => false,
                'message' => 'Not found',
            ], 400);
        }
    }

    public function getPidByUser($id): JsonResponse
    {
        // $pid = DB::table('sale_master_process')->where('closer1_id',$id)->orWhere('closer2_id',$id)->orWhere('setter1_id',$id)->orWhere('setter2_id',$id)->pluck('pid');
        $pid = DB::table('sale_master_process')->pluck('pid');

        return response()->json([
            'ApiName' => 'account overrides',
            'status' => true,
            'message' => 'Successfully',
            'data' => $pid,
        ], 200);
    }

    public function getUserRedlines($pid): JsonResponse
    {
        if (isset($pid)) {
            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $closerId = $checked->salesMasterProcess->closer1_id;
            $closer2Id = $checked->salesMasterProcess->closer2_id;
            $setterId = $checked->salesMasterProcess->setter1_id;
            $setter2Id = $checked->salesMasterProcess->setter2_id;

            // $saleState = $checked->customer_state;
            $approvedDate = $checked->customer_signoff;

            if (config('app.domain_name') == 'flex') {
                $saleState = $checked->customer_state;
            } else {
                $saleState = $checked->location_code;
            }

            $generalCode = Locations::where('general_code', $saleState)->first();
            if ($generalCode) {
                $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $saleStandardRedline = $locationRedlines->redline_standard;
                } else {
                    $saleStandardRedline = $generalCode->redline_standard;
                }
            } else {
                // customer state Id..................................................
                $state = State::where('state_code', $saleState)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
                $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $saleStandardRedline = $locationRedlines->redline_standard;
                } else {
                    $saleStandardRedline = isset($location->redline_standard) ? $location->redline_standard : 0;
                }
            }

            if ($approvedDate != null) {
                $data['closer1_redline'] = '0';
                $data['closer1_redline_type'] = null;
                $data['closer1_is_redline_missing'] = 1;
                $data['closer1_commission_type'] = null;
                $data['closer2_redline'] = '0';
                $data['closer2_redline_type'] = null;
                $data['closer2_commission_type'] = null;
                $data['closer2_is_redline_missing'] = 1;
                $data['setter1_redline'] = '0';
                $data['setter1_redline_type'] = null;
                $data['setter1_commission_type'] = null;
                $data['setter1_is_redline_missing'] = 1;
                $data['setter2_redline'] = '0';
                $data['setter2_redline_type'] = null;
                $data['setter2_commission_type'] = null;
                $data['setter2_is_redline_missing'] = 1;

                if ($setterId && $setter2Id) {
                    // setter1
                    $userCommission = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $setter1 = User::where('id', $setterId)->first();
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    $comType = 'percent';
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $setter1->redline;
                                $redLineAmountType = $setter1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    } else {
                        $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $setter1->redline;
                                $redLineAmountType = $setter1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    }

                    $closerOfficeId = $setter1->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }
                    $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($closerLocation) {
                            $closerLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($userCommission && $userCommission->status == 3) {
                            $closerRedLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['setter1_redline'] = $closerRedLine;
                        $data['setter1_redline_type'] = $redLineAmountType;
                        $data['setter1_commission_type'] = $comType;
                        $data['setter1_is_redline_missing'] = 0;
                        $data['setter1_office_data'] = $closerLocation;
                    } else {
                        if ($comType == 'percent') {
                            $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                        } else {
                            $redLine = $closerRedLine;
                        }

                        if ($userCommission && $userCommission->status == 3) {
                            $redLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }

                        $data['setter1_redline'] = $redLine;
                        $data['setter1_redline_type'] = $redLineAmountType;
                        $data['setter1_commission_type'] = $comType;
                        $data['setter1_is_redline_missing'] = 0;
                        $data['setter1_office_data'] = $closerLocation;
                    }

                    $user2Commission = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $setter2 = User::where('id', $setter2Id)->first();
                    $comType = 'percent';
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $commissionType = UserCommissionHistory::where('user_id', $setter2Id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $setter2->redline;
                                $redLineAmountType = $setter2->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    } else {
                        $commissionType = UserCommissionHistory::where('user_id', $setter2Id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $setter2->redline;
                                $redLineAmountType = $setter2->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    }

                    $closerOfficeId = $setter2->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $setter2Id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }
                    $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($closerLocation) {
                            $closerLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($user2Commission && $user2Commission->status == 3) {
                            $closerRedLine = $user2Commission->redline;
                            $redLineAmountType = $user2Commission->redline_type;
                        }
                        $data['setter2_redline'] = $closerRedLine;
                        $data['setter2_redline_type'] = $redLineAmountType;
                        $data['setter2_commission_type'] = $comType;
                        $data['setter2_is_redline_missing'] = 0;
                        $data['setter2_office_data'] = $closerLocation;
                    } else {
                        if ($comType == 'percent') {
                            $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                        } else {
                            $redLine = $closerRedLine;
                        }

                        if ($user2Commission && $user2Commission->status == 3) {
                            $redLine = $user2Commission->redline;
                            $redLineAmountType = $user2Commission->redline_type;
                        }

                        $data['setter2_redline'] = $redLine;
                        $data['setter2_redline_type'] = $redLineAmountType;
                        $data['setter2_commission_type'] = $comType;
                        $data['setter2_is_redline_missing'] = 0;
                        $data['setter2_office_data'] = $closerLocation;
                    }
                } elseif ($setterId) {
                    $userCommission = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $setter = User::where('id', $setterId)->first();
                    $comType = 'percent';
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == 1) {
                        // $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->first();
                        if ($userOrganizationHistory->position_id == '3') {
                            $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $setterRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $setterRedLine = $setter->redline;
                                    $redLineAmountType = $setter->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        } else {
                            $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $setterRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $setterRedLine = $setter->redline;
                                    $redLineAmountType = $setter->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        }
                    } else {
                        if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                            $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $setterRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $setterRedLine = $setter->redline;
                                    $redLineAmountType = $setter->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        } else {
                            $commissionType = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $setterRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $setterRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $setterRedLine = $setter->redline;
                                    $redLineAmountType = $setter->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        }
                    }

                    $setterOfficeId = $setter->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $setterOfficeId = $userTransferHistory->office_id;
                    }
                    $setterLocation = Locations::with('State')->where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($setterLocation) {
                            $setterLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($userCommission && $userCommission->status == 3) {
                            $setterRedLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['setter1_redline'] = $setterRedLine;
                        $data['setter1_redline_type'] = $redLineAmountType;
                        $data['setter1_commission_type'] = $comType;
                        $data['setter1_is_redline_missing'] = 0;
                        $data['setter1_office_data'] = $setterLocation;
                    } else {
                        if ($comType == 'percent') {
                            $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                        } else {
                            $redLine = $setterRedLine;
                        }

                        if ($userCommission && $userCommission->status == 3) {
                            $redLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }

                        $data['setter1_redline'] = $redLine;
                        $data['setter1_redline_type'] = $redLineAmountType;
                        $data['setter1_commission_type'] = $comType;
                        $data['setter1_is_redline_missing'] = 0;
                        $data['setter1_office_data'] = $setterLocation;
                    }
                }

                if ($closerId && $closer2Id) {
                    $userCommission = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $closer1 = User::where('id', $closerId)->first();
                    $comType = 'percent';
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $closer1->redline;
                                $redLineAmountType = $closer1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    } else {
                        $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $closer1->redline;
                                $redLineAmountType = $closer1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    }

                    $closerOfficeId = $closer1->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }
                    $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($closerLocation) {
                            $closerLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($userCommission && $userCommission->status == 3) {
                            $closerRedLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['closer1_redline'] = $closerRedLine;
                        $data['closer1_redline_type'] = $redLineAmountType;
                        $data['closer1_commission_type'] = $comType;
                        $data['closer1_is_redline_missing'] = 0;
                        $data['closer1_office_data'] = $closerLocation;
                    } else {
                        if ($comType == 'percent') {
                            $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                        } else {
                            $redLine = $closerRedLine;
                        }

                        if ($userCommission && $userCommission->status == 3) {
                            $redLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['closer1_redline'] = $redLine;
                        $data['closer1_redline_type'] = $redLineAmountType;
                        $data['closer1_commission_type'] = $comType;
                        $data['closer1_is_redline_missing'] = 0;
                        $data['closer1_office_data'] = $closerLocation;
                    }

                    $user2Commission = UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $closer2 = User::where('id', $closer2Id)->first();
                    $comType = 'percent';
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $commissionType = UserCommissionHistory::where('user_id', $closer2Id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $closer1->redline;
                                $redLineAmountType = $closer1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    } else {
                        $commissionType = UserCommissionHistory::where('user_id', $closer2Id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionType && $commissionType->commission_type == 'per kw') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per kw';
                        } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                            $closerRedLine = $commissionType->commission;
                            $redLineAmountType = $commissionType->commission_type;
                            $comType = 'per sale';
                        } else {
                            $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                            if ($userRedLines) {
                                $closerRedLine = $userRedLines->redline;
                                $redLineAmountType = $userRedLines->redline_amount_type;
                            } else {
                                $closerRedLine = $closer1->redline;
                                $redLineAmountType = $closer1->redline_amount_type;
                            }
                            $comType = 'percent';
                        }
                    }

                    $closerOfficeId = $closer2->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $closer2Id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }
                    $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($closerLocation) {
                            $closerLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($user2Commission && $user2Commission->status == 3) {
                            $closerRedLine = $user2Commission->redline;
                            $redLineAmountType = $user2Commission->redline_type;
                        }
                        $data['closer2_redline'] = $closerRedLine;
                        $data['closer2_redline_type'] = $redLineAmountType;
                        $data['closer2_commission_type'] = $comType;
                        $data['closer2_is_redline_missing'] = 0;
                        $data['closer2_office_data'] = $closerLocation;
                    } else {
                        if ($comType == 'percent') {
                            $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                        } else {
                            $redLine = $closerRedLine;
                        }

                        if ($user2Commission && $user2Commission->status == 3) {
                            $redLine = $user2Commission->redline;
                            $redLineAmountType = $user2Commission->redline_type;
                        }
                        $data['closer2_redline'] = $redLine;
                        $data['closer2_redline_type'] = $redLineAmountType;
                        $data['closer2_commission_type'] = $comType;
                        $data['closer2_is_redline_missing'] = 0;
                        $data['closer2_office_data'] = $closerLocation;
                    }
                } elseif ($closerId) {
                    $userCommission = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    $closer = User::where('id', $closerId)->first();
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    $comType = 'percent';
                    if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                        if ($userOrganizationHistory->position_id == '3') {
                            $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $closerRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $closerRedLine = $closer->redline;
                                    $redLineAmountType = $closer->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        } else {
                            $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $closerRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $closerRedLine = $closer->redline;
                                    $redLineAmountType = $closer->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        }
                    } else {
                        if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                            $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $closerRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $closerRedLine = $closer->redline;
                                    $redLineAmountType = $closer->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        } else {
                            $commissionType = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionType && $commissionType->commission_type == 'per kw') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per kw';
                            } elseif ($commissionType && $commissionType->commission_type == 'per sale') {
                                $closerRedLine = $commissionType->commission;
                                $redLineAmountType = $commissionType->commission_type;
                                $comType = 'per sale';
                            } else {
                                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                                if ($userRedLines) {
                                    $closerRedLine = $userRedLines->redline;
                                    $redLineAmountType = $userRedLines->redline_amount_type;
                                } else {
                                    $closerRedLine = $closer->redline;
                                    $redLineAmountType = $closer->redline_amount_type;
                                }
                                $comType = 'percent';
                            }
                        }
                    }

                    $closerOfficeId = $closer->office_id;
                    $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }
                    $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        if ($closerLocation) {
                            $closerLocation->redline_standard = $locationRedLines->redline_standard;
                        }
                    }

                    if ($redLineAmountType == 'Fixed') {
                        if ($userCommission && $userCommission->status == 3) {
                            $closerRedLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['closer1_redline'] = $closerRedLine;
                        $data['closer1_redline_type'] = $redLineAmountType;
                        $data['closer1_commission_type'] = $comType;
                        $data['closer1_is_redline_missing'] = 0;
                        $data['closer1_office_data'] = $closerLocation;
                    } else {
                        if ($comType == 'percent') {
                            $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                            $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                        } else {
                            $redLine = $closerRedLine;
                        }
                        if ($userCommission && $userCommission->status == 3) {
                            $redLine = $userCommission->redline;
                            $redLineAmountType = $userCommission->redline_type;
                        }
                        $data['closer1_redline'] = $redLine;
                        $data['closer1_redline_type'] = $redLineAmountType;
                        $data['closer1_commission_type'] = $comType;
                        $data['closer1_is_redline_missing'] = 0;
                        $data['closer1_office_data'] = $closerLocation;
                    }
                    if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1 && @$comType = 'percent') {
                        $redLine1 = $data['setter1_redline'];
                        $redLine2 = $data['closer1_redline'];
                        $redLineType1 = $data['setter1_redline_type'];
                        $redLineType2 = $data['closer1_redline_type'];
                        if ($redLine1 > $redLine2) {
                            $data['closer1_redline'] = $redLine2;
                            $data['closer1_redline_type'] = $redLineType2;
                        } else {
                            if ($userCommission && $userCommission->status == 3) {
                                $redLine1 = $userCommission->redline;
                                $redLineType1 = $userCommission->redline_type;
                            }
                            $data['closer1_redline'] = $redLine1;
                            $data['closer1_redline_type'] = $redLineType1;
                        }
                    }
                }

                return response()->json([
                    'ApiName' => 'get_user_redlines',
                    'status' => true,
                    'message' => 'Successfully',
                    'data' => $data,
                ]);
            }
        }

        return response()->json([
            'ApiName' => 'get_user_redlines',
            'status' => false,
            'message' => 'Not found',
        ], 400);
    }

    public function getUserWiseRedlines(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'customer_state' => 'required',
            'customer_signoff' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $closerId = $userId;
        $positionId = $request->position_id;
        $approvedDate = $request->customer_signoff;

        if (config('app.domain_name') == 'flex') {
            $saleState = $request->customer_state;
        } else {
            $saleState = isset($request->location_code) ? $request->location_code : $request->customer_state;
        }

        $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        $commissionType = null;
        if ($organizationHistory && $organizationHistory->self_gen_accounts == 1 && ($positionId == 2 && $organizationHistory['position_id'] == 3) || ($positionId == 3 && $organizationHistory['position_id'] == 2)) {
            $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $commissionType = $commissionHistory->commission_type;
            }
        } else {
            $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $commissionType = $commissionHistory->commission_type;
            }
        }

        if ($commissionType == 'per kw' || $commissionType == 'per sale') {
            return response()->json([
                'ApiName' => 'get_user_redlines',
                'status' => true,
                'message' => 'Successfully',
                'data' => [
                    'redline' => '0',
                    'redline_type' => null,
                ],
            ]);
        }

        $closerRedLine = 0;
        $redLineAmountType = null;
        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
            if (isset($positionId) && ($positionId == 2 && $userOrganizationHistory['position_id'] == 3) || ($positionId == 3 && $userOrganizationHistory['position_id'] == 2)) {
                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                }
            } else {
                $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                }
            }
        } else {
            $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
            if ($userRedLines) {
                $closerRedLine = $userRedLines->redline;
                $redLineAmountType = $userRedLines->redline_amount_type;
            }
        }

        $data = [
            'redline' => '0',
            'redline_type' => null,
        ];
        $closer = User::where('id', $closerId)->first();
        $closerOfficeId = $closer->office_id;
        if ($redLineAmountType == 'Fixed') {
            $data['redline'] = $closerRedLine;
            $data['redline_type'] = $redLineAmountType;
        } else {
            $generalCode = Locations::where('general_code', $saleState)->first();
            if ($generalCode) {
                $locationRedLines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedLines) {
                    $saleStandardRedLine = $locationRedLines->redline_standard;
                } else {
                    $saleStandardRedLine = $generalCode->redline_standard;
                }
            } else {
                $state = State::where('state_code', $saleState)->first();
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
                $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedLines) {
                    $saleStandardRedLine = $locationRedLines->redline_standard;
                } else {
                    $saleStandardRedLine = isset($location->redline_standard) ? $location->redline_standard : 0;
                }
            }

            $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $closerOfficeId = $userTransferHistory->office_id;
            }

            $closerLocation = Locations::with('State')->where('id', $closerOfficeId)->first();
            $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
            $locationRedLines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedLines) {
                $closerStateRedline = $locationRedLines->redline_standard;
                if ($closerLocation) {
                    $closerLocation->redline_standard = $locationRedLines->redline_standard;
                }
            } else {
                $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
            }
            $is_redline_missing = isset($redLineAmountType) ? 0 : 1;
            $redline = $saleStandardRedLine + ($closerRedLine - $closerStateRedline);
            $data['redline'] = $redline;
            $data['redline_type'] = $redLineAmountType;
            $data['is_redline_missing'] = $is_redline_missing;
            $data['office'] = $closerLocation;
        }

        return response()->json([
            'ApiName' => 'get_user_redlines',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ]);
    }
}
