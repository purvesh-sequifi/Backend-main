<?php

namespace App\Http\Controllers\API\Reports;

use Log;
use Illuminate\Support\Facades\Auth;
use DateTime;
use App\MHistory;

use App\Models\Crms;
use App\Models\User;
use App\Models\State;
use App\Models\Payroll;
use App\Models\Locations;
use App\Models\CostCenter;
use App\Jobs\SaleMasterJob;
use App\Models\SalesMaster;
use Illuminate\Support\Arr;
use App\Imports\ImportSales;
use App\Models\UserRedlines;
use Illuminate\Http\Request;
use App\Models\UserOverrides;
use App\Models\CompanyProfile;
use App\Models\PayrollHistory;
use App\Models\UserCommission;
use Illuminate\Support\Carbon;
use App\Models\LegacyApiRowData;
use App\Models\LegacyApiNullData;
use App\Models\LegacyWeeklySheet;
use App\Models\PayrollAdjustment;
use App\Models\SaleMasterProcess;
use App\Exports\ReportCostsExport;
use App\Exports\ReportSalesExport;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exports\ExportSampleReport;
use App\Models\ApprovalsAndRequest;
USE App\Models\ExcelImportHistory;
//addsiraj
use App\Models\Crmsaleinfo;
use App\Models\AdditionalLocations;

//use Validator;
use App\Http\Controllers\Controller;
use App\Models\UsersAdditionalEmail;
use Illuminate\Pagination\Paginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\UserCommissionHistory;
use App\Traits\EmailNotificationTrait;
use App\Models\PayrollAdjustmentDetail;
use Illuminate\Support\Facades\Storage;
use App\Core\Traits\SubroutineListTrait;
use Illuminate\Support\Facades\Validator;
use App\Models\ReconciliationsAdjustement;
use App\Exports\ReportReconciliationExport;
use App\Imports\PestSalesImport;
use App\Models\CustomerPayment;
use App\Models\LegacyApiRawDataHistory;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\OneTimePayments; // use model
use App\Models\GetPayrollData; // use model
use App\Models\LocationRedlineHistory;
use App\Models\Positions; // use model
use App\Models\Products;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationAdjustmentDetails;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;

class ReportsAdminController extends Controller
{
    use SubroutineListTrait;
    use EmailNotificationTrait;
    private $getPayrollData; //

    public $setter_closer_arr = ['setter1','closer1','setter2','closer2'];
    public function __construct(Request $request,GetPayrollData $getPayrollData) // pass getPayrollData in construct
    {
        //$user = auth('api')->user();
        $this->getPayrollData = $getPayrollData; //
    }

    public function company_report(Request $request)
    {
    	$result = array();
    	$office_id = $request->office_id;
    	$location = $request->location;
    	$filter   = $request->filter;
        //$pid = DB::table('sale_master_process')->orWhere('mark_account_status_id', 1)->pluck('pid')->toArray();
        $result = SalesMaster::select('install_partner', 'customer_signoff')->selectRaw('sum(gross_account_value) As gross_total');
        $result1 = SalesMaster::orderBy('id','asc');
        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');

       if ($request->has('order_by') && !empty($request->input('order_by'))){
        $orderBy = $request->input('order_by');
        }else{
            $orderBy = 'desc';
        }

        // office_id code
        if ($office_id!='all')
        {
            //$office_id = '1';
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');

            $result->where(function($query) use ($request,$orderBy, $salesPid) {
                return $query->whereIn('pid', $salesPid);
                });

            $result1->where(function($query) use ($request,$orderBy, $salesPid) {
                return $query->whereIn('pid', $salesPid);
                });

            $resultcosts->where(function($query) use ($request,$userId) {
                return $query->whereIn('user_id', $userId);
                });
        }
        // end office_id code

        if ($location!='all' && 1==2)
        {
            $state = State::where('state_code', $location)->first();

            $result->where(function($query) use ($request,$orderBy) {
                return $query->where('customer_state','=', $request->location);
                });

            $result1->where(function($query) use ($request,$orderBy) {
                return $query->where('customer_state','=', $request->location);
                });

            $resultcosts->where(function($query) use ($request,$state) {
                return $query->where('state_id','=', $state->id);
                });
        }

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            if($request->filter=='this_year'){
                $result->where(function($query) use ($request,$orderBy) {
                    return $query->whereYear('customer_signoff', date('Y'));
                    });
                $result1->where(function($query) use ($request,$orderBy) {
                    return $query->whereYear('customer_signoff', date('Y'));
                    });
                $resultcosts->where(function($query) use ($request,$orderBy) {
                    return $query->whereYear('cost_date', date('Y'));
                    });
            }

            if($request->filter=='last_year'){
                $lastYear = date('Y', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                //return $lastYear;
                $result->where(function($query) use ($request,$lastYear) {
                    return $query->whereYear('customer_signoff', $lastYear);
                    });
                $result1->where(function($query) use ($request,$lastYear) {
                    return $query->whereYear('customer_signoff', $lastYear);
                    });
                $resultcosts->where(function($query) use ($request,$lastYear) {
                    return $query->whereYear('cost_date', $lastYear);
                    });
            }

            if($request->filter=='this_month'){
                $result->where(function($query) use ($request,$orderBy) {
                    return $query->whereMonth('customer_signoff', date('m'))->whereYear('customer_signoff', date('Y'));
                    });
                $result1->where(function($query) use ($request,$orderBy) {
                    return $query->whereMonth('customer_signoff', date('m'))->whereYear('customer_signoff', date('Y'));
                    });
                $resultcosts->where(function($query) use ($request,$orderBy) {
                    return $query->whereMonth('cost_date', date('m'))->whereYear('cost_date', date('Y'));
                    });
            }

            if($request->filter=='this_week'){

                $startOfWeek = Carbon::now()->startOfWeek();
                $endOfWeek   = Carbon::now()->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfWeek));
                $endDate   =  date('Y-m-d', strtotime($endOfWeek));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    });
                $result1->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    });
                $resultcosts->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('cost_date', [$startDate, $endDate]);
                    });
            }

            if($request->filter=='this_quarter'){
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $result1->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $resultcosts->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('cost_date', [$startDate,$endDate]);
                    });
            }

            if($request->filter=='last_quarter'){
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $result1->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $resultcosts->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('cost_date', [$startDate,$endDate]);
                    });
            }

            if ($request->filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $result->where(function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                });
                $result1->where(function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                });
                $resultcosts->where(function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('cost_date', [$startDate, $endDate]);
                });
            }

            if($request->filter=='custom'){

                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $result1->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                $resultcosts->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('cost_date', [$startDate,$endDate]);
                    });
            }

        }

        $legacydata = $result->orderBy('id',$orderBy)->groupBy('install_partner')->get();
        $costdata = $resultcosts->get();
        // return $costdata;
    	$data = array();
    	if (count($legacydata) > 0) {
            $total_revenue = 0;
            $aa = array();
    		foreach ($legacydata as $key => $value) {
                $total_revenue = ($total_revenue + $value->gross_total);
                if($value->gross_total>00)
                {
                $aa[$key] = array(
                    'install_partner' => $value->install_partner,
                    'gross_total' => round($value->gross_total,2),
                );
                }
	    	}

            $data['revenue_summary']['data'] = $aa;
            $data['revenue_summary']['total'] = round($total_revenue,2);
            $data['revenue_summary']['revenue_percentage'] = 0;

            $data['cost_summary'] = [];
            $costs =[];
            if(count($costdata) > 0){
                foreach ($costdata as $key => $val) {
                    if ($val->cost_tracking_id!=null) {
                        $costs[$val->cost_tracking_id] = array(
                            'id' => $val->cost_tracking_id,
                            'name' => $val->costcenter->name,
                          );
                    }

                    $payrolls[$val->adjustment_type_id] = array(
                        'id' => $val->adjustment_type_id,
                        'name' => $val->adjustment->name,
                    );
                }

                $totalcostamount = 0;
                $bb = [];
                if(count($costs) > 0){
                    foreach ($costs as $key => $cost) {
                        //return $cost['id'];
                        $costAmount = ApprovalsAndRequest::where('status','Approved')->where('cost_tracking_id',$cost['id'])->sum('amount');
                        $bb[] = array(
                        'cost_data' => $cost['name'],
                        'cost_amount' => $costAmount,
                        );

                        $totalcostamount = ($totalcostamount + $costAmount);
                    }
                }
                $totalpayrollamount = 0;
                $cc = [];
                if(count($payrolls) > 0){
                    foreach ($payrolls as $key => $payroll) {
                        $payrollAmount = ApprovalsAndRequest::where('status','Approved')->where('adjustment_type_id',$payroll['id'])->sum('amount');
                        $cc[] = array(
                        'payroll_data' => $payroll['name'],
                        'payroll_amount' => $payrollAmount,
                        );

                        $totalpayrollamount = ($totalpayrollamount + $payrollAmount);
                    }
                }

                $data['cost_summary']['costs']['data'] = $bb;
                $data['cost_summary']['costs']['total'] = $totalcostamount;
                $data['cost_summary']['payroll']['data'] = $cc;
                $data['cost_summary']['payroll']['total'] = $totalpayrollamount;
                $data['cost_summary']['total_costs'] = ($totalcostamount + $totalpayrollamount);
                $data['cost_summary']['cost_percentage'] = 0;

            }


            $pid = $result1->pluck('pid')->toArray();
            $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();

            $total_clowback = 0;
            if($clowbacks){
            foreach ($clowbacks as $key => $clowback) {
                $closer1_m1 = $clowback->closer1_m1;
                $closer2_m1 = $clowback->closer2_m1;
                $setter1_m1 = $clowback->setter1_m1;
                $setter2_m1 = $clowback->setter2_m1;
                $closer1_m2 = $clowback->closer1_m2;
                $closer2_m2 = $clowback->closer2_m2;
                $setter1_m2 = $clowback->setter1_m2;
                $setter2_m2 = $clowback->setter2_m2;
                $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
            }
            }
            $costs = isset($totalcostamount) ? $totalcostamount : '0';
            $payroll = isset($totalpayrollamount) ? $totalpayrollamount : '0';
            $clowbackTotal = $total_clowback;

            $data['profitability_summary']['data'] = array(
                    'revenue' => $total_revenue,
                    'costs'   => -($costs),
                    'payroll' => -($payroll),
                    'clowback' => $clowbackTotal,
                );
            $data['profitability_summary']['total'] = (($total_revenue + $clowbackTotal) - ($costs + $payroll));
            $data['profitability_summary']['profitability_percentage'] = 0;


	    	return response()->json([
                'ApiName' => 'revenue_summary_data',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'revenue_summary_data',
                'status' => false,
                'message' => 'data not found',
                'data' => $data,
            ], 200);

    	}



    }

    public function company_graph(Request $request)
    {
        $result = array();
        $data = array();
        $office_id = $request->office_id;
    	$location = $request->location;
    	$filter   = $request->filter;

        $result1 = SalesMaster::orderBy('id','asc');
        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            if($request->filter=='this_year'){
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->selectRaw('DATE_FORMAT(customer_signoff, "%Y-%m") AS new_date, sum(gross_account_value) As gross_total');
                $result->where(function($query) use ($request) {
                    return $query->whereYear('customer_signoff', date('Y'));
                    });
                // $result1->where(function($query) use ($request) {
                //     return $query->whereYear('customer_signoff', date('Y'));
                //     });
            }

            else if($request->filter=='last_year'){
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->selectRaw('DATE_FORMAT(customer_signoff, "%Y-%m") AS new_date, sum(gross_account_value) As gross_total');
                $lastYear = date('Y', strtotime(Carbon::now()->subYears(1)->startOfYear()));

                $result->where(function($query) use ($request,$lastYear) {
                    return $query->whereYear('customer_signoff', $lastYear);
                    });
                // $result1->where(function($query) use ($request,$lastYear) {
                //     return $query->whereYear('customer_signoff', $lastYear);
                //     });
            }

            else if($request->filter=='this_month'){
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value');
                $result->where(function($query) use ($request) {
                    return $query->whereMonth('customer_signoff', date('m'))->whereYear('customer_signoff', date('Y'));
                    });
                // $result1->where(function($query) use ($request) {
                //     return $query->whereMonth('customer_signoff', date('m'))->whereYear('customer_signoff', date('Y'));
                //     });
            }

            else if($request->filter=='this_week'){
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value');
                $startOfWeek = Carbon::now()->startOfWeek();
                $endOfWeek   = Carbon::now()->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfWeek));
                $endDate   =  date('Y-m-d', strtotime($endOfWeek));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    });
                // $result1->where(function($query) use ($startDate,$endDate) {
                //     return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                //     });
            }

            else if($request->filter=='this_quarter'){
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->selectRaw('DATE_FORMAT(customer_signoff, "%Y-%m") AS new_date, sum(gross_account_value) As gross_total');
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                //$endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfMonth()));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                // $result1->where(function($query) use ($startDate,$endDate) {
                //     return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                //     });
            }

            else if($request->filter=='last_quarter'){
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->selectRaw('DATE_FORMAT(customer_signoff, "%Y-%m") AS new_date, sum(gross_account_value) As gross_total');
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

                $result->where(function($query) use ($startDate,$endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                    });
                // $result1->where(function($query) use ($startDate,$endDate) {
                //     return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                //     });
            } 
            
            else if ($request->filter == 'last_12_months') {
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->selectRaw('DATE_FORMAT(customer_signoff, "%Y-%m") AS new_date, sum(gross_account_value) As gross_total');
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $result->where(function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                });
            }

            else if($request->filter=='custom'){
                $result = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value');

                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));
                // $result->where(function($query) use ($startDate,$endDate) {
                //     return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                //     });
                // $result1->where(function($query) use ($startDate,$endDate) {
                //     return $query->whereBetween('customer_signoff', [$startDate,$endDate]);
                //     });
            }

        }

        if ($office_id!='all')
        {
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');

            $result->where(function($query) use ($request,$salesPid) {
                return $query->whereIn('pid', $salesPid);
                });

            $result1->where(function($query) use ($request,$salesPid) {
                return $query->whereIn('pid', $salesPid);
                });

            $resultcosts->where(function($query) use ($request,$userId) {
                return $query->whereIn('user_id', $userId);
                });
        }

        if ($location!='all' && 1==2)
        {
            $state = State::where('state_code', $location)->first();

            $result->where(function($query) use ($request) {
                return $query->where('customer_state','=', $request->location);
                });
            $result1->where(function($query) use ($request) {
                return $query->where('customer_state','=', $request->location);
                });
            $resultcosts->where(function($query) use ($request,$state) {
                return $query->where('state_id','=', $state->id);
                });
        }

        $graph = array();
        $graphdata = array();

        if($request->filter=='this_week'|| $request->filter=='this_month'){
            $currentDate = \Carbon\Carbon::now();
            // return $currentDate->dayOfWeek
            $year = date('Y');
            $month = date('m');
            if ($request->filter=='this_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfMonth()));
                $d=cal_days_in_month(CAL_GREGORIAN,$month,$year);
            }else{
                $d=7;
            }

            for($i=0; $i<$d; $i++)
            {
                $weekDate = date('Y-m-d', strtotime($startDate . ' +'.$i.' day'));
                if ($office_id!='all')
                {
                    $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->where('customer_signoff',$weekDate)->whereIn('pid', $salesPid)->orderBy('customer_signoff', 'asc')->get();
                    $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved')->whereIn('user_id', $userId);
                    $pid = SalesMaster::where('customer_signoff', '=', $weekDate)->whereIn('pid', $salesPid)->pluck('pid')->toArray();
                }else{
                    $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->where('customer_signoff',$weekDate)->orderBy('customer_signoff', 'asc')->get();
                    $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');
                    $pid = SalesMaster::where('customer_signoff', '=', $weekDate)->pluck('pid')->toArray();
                }
                $gross_total = 0;
                if(count($legacydata) > 0){
                    foreach ($legacydata as $key => $value) {
                        $gross_total = ($gross_total + $value->gross_account_value);
                    }
                }

                $costdata = $resultcosts->where('cost_date', '=', $weekDate)->get();
                $cost_total = 0;
                if(count($costdata) > 0){
                    $costTotal = 0;
                    foreach ($costdata as $key => $cost) {
                        $costTotal = ($costTotal + $cost->amount);
                    }
                    $payrollTotal = 0;
                    foreach ($costdata as $key => $payroll) {
                        $payrollTotal = ($payrollTotal + $payroll->amount);
                    }

                    $cost_total = ($costTotal + $payrollTotal);

                }

                $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();

                $profitability = 0;
                $total_clowback = 0;
                if($clowbacks){
                foreach ($clowbacks as $key => $clowback) {
                    $closer1_m1 = $clowback->closer1_m1;
                    $closer2_m1 = $clowback->closer2_m1;
                    $setter1_m1 = $clowback->setter1_m1;
                    $setter2_m1 = $clowback->setter2_m1;
                    $closer1_m2 = $clowback->closer1_m2;
                    $closer2_m2 = $clowback->closer2_m2;
                    $setter1_m2 = $clowback->setter1_m2;
                    $setter2_m2 = $clowback->setter2_m2;
                    $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
                }
                $clowbackTotal = $total_clowback;

                $profitability = (($gross_total + $clowbackTotal) - $cost_total);
                }
                $dateWeek = date('m-d-y', strtotime($weekDate));
                $graphdata[$dateWeek] = [
                    'date'=> $dateWeek,
                    'revenue' => $gross_total,
                    'costs' => $cost_total,
                    'profitability' => $profitability,
                ];
            }

        }
        else if($request->filter=='custom' || $request->filter == 'last_12_months'){
            $now        = strtotime($endDate);
            $your_date  = strtotime($startDate);
            $dateDiff   = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24))+1;
            if ($dateDays<=15) {
                for($i=0; $i<$dateDays; $i++)
                {
                    $weekDate = date('Y-m-d', strtotime($startDate . ' +'.$i.' day'));
                    if ($office_id!='all')
                    {
                        $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->where('customer_signoff',$weekDate)->whereIn('pid', $salesPid)->orderBy('customer_signoff', 'asc')->get();
                        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved')->where('user_id', $userId);
                        $pid = SalesMaster::where('customer_signoff', '=', $weekDate)->whereIn('pid', $salesPid)->pluck('pid')->toArray();
                    }else{
                        $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->where('customer_signoff',$weekDate)->orderBy('customer_signoff', 'asc')->get();
                        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');
                        $pid = SalesMaster::where('customer_signoff', '=', $weekDate)->pluck('pid')->toArray();
                    }

                    $gross_total = 0;
                    if(count($legacydata) > 0){
                        foreach ($legacydata as $key => $value) {
                            $gross_total = ($gross_total + $value->gross_account_value);
                        }
                    }

                    $costdata = $resultcosts->where('cost_date', '=', $weekDate)->get();
                    $cost_total = 0;
                    if(count($costdata) > 0){
                        $costTotal = 0;
                        foreach ($costdata as $key => $cost) {
                            $costTotal = ($costTotal + $cost->amount);
                        }
                        $payrollTotal = 0;
                        foreach ($costdata as $key => $payroll) {
                            $payrollTotal = ($payrollTotal + $payroll->amount);
                        }

                        $cost_total = ($costTotal + $payrollTotal);

                    }

                    $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();

                    $profitability = 0;
                    $total_clowback = 0;
                    if($clowbacks){
                    foreach ($clowbacks as $key => $clowback) {
                        $closer1_m1 = $clowback->closer1_m1;
                        $closer2_m1 = $clowback->closer2_m1;
                        $setter1_m1 = $clowback->setter1_m1;
                        $setter2_m1 = $clowback->setter2_m1;
                        $closer1_m2 = $clowback->closer1_m2;
                        $closer2_m2 = $clowback->closer2_m2;
                        $setter1_m2 = $clowback->setter1_m2;
                        $setter2_m2 = $clowback->setter2_m2;
                        $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
                    }
                    $clowbackTotal = $total_clowback;

                    $profitability = (($gross_total + $clowbackTotal) - $cost_total);
                    }

                    $dateWeek = date('m-d-y', strtotime($weekDate));
                    $graphdata[$dateWeek] = [
                        'date'=> $dateWeek,
                        'revenue' => $gross_total,
                        'costs' => $cost_total,
                        'profitability' => $profitability,
                    ];
                }
            }else{
                $weekCount = round($dateDays/7);
                $totalWeekDay = 7*$weekCount;
                $extraDay = $dateDays - $totalWeekDay;
                if($extraDay>0)
                {
                    $weekCount =$weekCount+1;
                }

                for($i=0; $i<$weekCount; $i++)
                {
                    $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));
                    $dayWeek = 7*$i;
                    if($i==0)
                    {
                        $sDate = date('Y-m-d', strtotime($startDate .' - ' . $dayWeek . ' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate. ' - '. 0 . ' days'));
                    }else{

                        $sDate = date('Y-m-d', strtotime($startDate .' + ' . $dayWeek . ' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate. ' + '. $dayWeek . ' days'));
                    }
                    if($i==$weekCount-1)
                    {
                        $sDate = date('Y-m-d', strtotime($startDate .' + ' . $dayWeek . ' days'));
                        $eDate = $endDate;
                    }

                    // $aWeek = $sDate.'-to-'.$eDate;
                    $aWeek = date('m-d-y', strtotime($sDate)).' to '.date('m-d-y', strtotime($eDate));
                    if ($office_id!='all')
                    {
                        $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate,$eDate])->orderBy('customer_signoff', 'asc')->get();
                        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved')->whereIn('user_id', $userId);
                        $pid = SalesMaster::whereBetween('customer_signoff', [$sDate,$eDate])->whereIn('pid', $salesPid)->pluck('pid')->toArray();
                    }else{
                        $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->whereBetween('customer_signoff', [$sDate,$eDate])->orderBy('customer_signoff', 'asc')->get();
                        $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');
                        $pid = SalesMaster::whereBetween('customer_signoff', [$sDate,$eDate])->pluck('pid')->toArray();
                    }
                    $gross_total = 0;
                    if(count($legacydata) > 0){
                        foreach ($legacydata as $key => $value) {
                            $gross_total = ($gross_total + $value->gross_account_value);
                        }
                    }

                    $costdata = $resultcosts->whereBetween('cost_date', [$sDate,$eDate])->get();
                    $cost_total = 0;
                    if(count($costdata) > 0){
                        $costTotal = 0;
                        foreach ($costdata as $key => $cost) {
                            $costTotal = ($costTotal + $cost->amount);
                        }
                        $payrollTotal = 0;
                        foreach ($costdata as $key => $payroll) {
                            $payrollTotal = ($payrollTotal + $payroll->amount);
                        }

                        $cost_total = ($costTotal + $payrollTotal);

                    }

                    $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();

                    $profitability = 0;
                    $total_clowback = 0;
                    if(count($clowbacks) > 0){
                    foreach ($clowbacks as $key => $clowback) {
                        $closer1_m1 = $clowback->closer1_m1;
                        $closer2_m1 = $clowback->closer2_m1;
                        $setter1_m1 = $clowback->setter1_m1;
                        $setter2_m1 = $clowback->setter2_m1;
                        $closer1_m2 = $clowback->closer1_m2;
                        $closer2_m2 = $clowback->closer2_m2;
                        $setter1_m2 = $clowback->setter1_m2;
                        $setter2_m2 = $clowback->setter2_m2;
                        $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
                    }
                    $clowbackTotal = $total_clowback;

                    $profitability = (($gross_total + $clowbackTotal) - $cost_total);
                    }

                    $graphdata[$aWeek] = [
                        'date'=> $aWeek,
                        'revenue' => $gross_total,
                        'costs' => $cost_total,
                        'profitability' => $profitability,
                    ];
                }

            }

        }
        else if($request->filter=='this_quarter' || $request->filter=='last_quarter'){
            $now        = strtotime($endDate);
            $your_date  = strtotime($startDate);
            $dateDiff   = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24))+1;

            $weekCount = round($dateDays/7);
            $totalWeekDay = 7*$weekCount;
            $extraDay = $dateDays - $totalWeekDay;
            if($extraDay>0)
            {
                $weekCount =$weekCount+1;
            }

            for($i=0; $i<$weekCount; $i++)
            {
                $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));
                $dayWeek = 7*$i;
                if($i==0)
                {
                    $sDate = date('Y-m-d', strtotime($startDate .' - ' . $dayWeek . ' days'));
                    $eDate = date('Y-m-d', strtotime($endsDate. ' - '. 0 . ' days'));
                }else{

                    $sDate = date('Y-m-d', strtotime($startDate .' + ' . $dayWeek . ' days'));
                    $eDate = date('Y-m-d', strtotime($endsDate. ' + '. $dayWeek . ' days'));
                }
                if($i==$weekCount-1)
                {
                    $sDate = date('Y-m-d', strtotime($startDate .' + ' . $dayWeek . ' days'));
                    $eDate = $endDate;
                }

                // $aWeek = $sDate.'-to-'.$eDate;
                $aWeek = date('m-d-y', strtotime($sDate)).' to '.date('m-d-y', strtotime($eDate));
                if ($office_id!='all')
                {
                    $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate,$eDate])->orderBy('customer_signoff', 'asc')->get();
                    $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved')->whereIn('user_id', $userId);
                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate,$eDate])->whereIn('pid', $salesPid)->pluck('pid')->toArray();
                }else{
                    $legacydata = SalesMaster::select('install_partner', 'customer_signoff', 'gross_account_value')->whereBetween('customer_signoff', [$sDate,$eDate])->orderBy('customer_signoff', 'asc')->get();
                    $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');
                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate,$eDate])->pluck('pid')->toArray();
                }
                $gross_total = 0;
                if(count($legacydata) > 0){
                    foreach ($legacydata as $key => $value) {
                        $gross_total = ($gross_total + $value->gross_account_value);
                    }
                }

                $costdata = $resultcosts->whereBetween('cost_date', [$sDate,$eDate])->get();
                $cost_total = 0;
                if(count($costdata) > 0){
                    $costTotal = 0;
                    foreach ($costdata as $key => $cost) {
                        $costTotal = ($costTotal + $cost->amount);
                    }
                    $payrollTotal = 0;
                    foreach ($costdata as $key => $payroll) {
                        $payrollTotal = ($payrollTotal + $payroll->amount);
                    }

                    $cost_total = ($costTotal + $payrollTotal);

                }

                $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();

                $profitability = 0;
                $total_clowback = 0;
                if(count($clowbacks) > 0){
                foreach ($clowbacks as $key => $clowback) {
                    $closer1_m1 = $clowback->closer1_m1;
                    $closer2_m1 = $clowback->closer2_m1;
                    $setter1_m1 = $clowback->setter1_m1;
                    $setter2_m1 = $clowback->setter2_m1;
                    $closer1_m2 = $clowback->closer1_m2;
                    $closer2_m2 = $clowback->closer2_m2;
                    $setter1_m2 = $clowback->setter1_m2;
                    $setter2_m2 = $clowback->setter2_m2;
                    $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
                }
                $clowbackTotal = $total_clowback;

                $profitability = (($gross_total + $clowbackTotal) - $cost_total);
                }

                $graphdata[$aWeek] = [
                    'date'=> $aWeek,
                    'revenue' => $gross_total,
                    'costs' => $cost_total,
                    'profitability' => $profitability,
                ];
            }

        }
        else{
            $legacydata = $result->groupBy('new_date')->orderBy('customer_signoff', 'asc')->get();
            foreach ($legacydata as $key => $value) {

                $monthName = date('M', strtotime($value->customer_signoff));
                $year = date('Y', strtotime($value->customer_signoff));
                $month = date('m', strtotime($value->customer_signoff));
                $resultcosts = ApprovalsAndRequest::with('adjustment', 'costcenter')->where('status','Approved');
                $costdata = $resultcosts->whereYear('cost_date', '=', $year)->whereMonth('cost_date', '=', $month)->get();
                $cost_total = 0;
                if(count($costdata) > 0){
                    $costTotal = 0;
                    foreach ($costdata as $key => $cost) {
                        $costTotal = ($costTotal + $cost->amount);
                        $bb[$key] = array(
                        //'cost_data' => $cost->costcenter->name,
                        'cost_data' => isset($cost->costcenter->name)?$cost->costcenter->name:null,
                        'cost_amount' => $cost->amount,
                    );
                    }
                    $payrollTotal = 0;
                    foreach ($costdata as $key => $payroll) {
                        $payrollTotal = ($payrollTotal + $payroll->amount);
                        $cc[$key] = array(
                        'payroll_data' => $payroll->adjustment->name,
                        'payroll_amount' => $payroll->amount,
                    );
                    }

                    $cost_total = ($costTotal + $payrollTotal);

                }

                $pid = $result1->whereYear('customer_signoff', '=', $year)->whereMonth('customer_signoff', '=', $month)->pluck('pid')->toArray();
                $clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->where('mark_account_status_id', 1)->whereIn('pid',$pid)->get();
                //$clowbacks = SaleMasterProcess::select('pid','closer1_m1','closer2_m1','setter1_m1','setter2_m1','closer1_m2','closer2_m2','setter1_m2','setter2_m2')->whereIn('pid',$pid)->get();
                $profitability = 0;
                $total_clowback = 0;
                if($clowbacks){
                foreach ($clowbacks as $key => $clowback) {
                    $closer1_m1 = $clowback->closer1_m1;
                    $closer2_m1 = $clowback->closer2_m1;
                    $setter1_m1 = $clowback->setter1_m1;
                    $setter2_m1 = $clowback->setter2_m1;
                    $closer1_m2 = $clowback->closer1_m2;
                    $closer2_m2 = $clowback->closer2_m2;
                    $setter1_m2 = $clowback->setter1_m2;
                    $setter2_m2 = $clowback->setter2_m2;
                    $total_clowback = $total_clowback + ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1 + $closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
                }
                $clowbackTotal = $total_clowback;

                $profitability = (($value->gross_total + $clowbackTotal) - $cost_total);
                }

                    $graph[$monthName] = array(
                        'revenue' => $value->gross_total,
                        'costs' => $cost_total,
                        'profitability' => $profitability,
                        );

            }
            
            for($i=0; $i<12; $i++)
            {
                $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
               
                //$eDate = date('Y-m-d', strtotime("+". $i+1 ." months", strtotime($startDate)));
                if($sDate <= $endDate){
                    $time=strtotime($sDate);
                    $month=date("M",$time);
                    $months[] = $month;
                }
            }
            // $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            foreach ($months as $keymonth => $vmonth) {

                if (isset($graph[$vmonth])) {
                    $graphdata[$vmonth] = $graph[$vmonth];
                }else{
                    $graphdata[$vmonth] = 0;
                }
            }
        }


        if (count($graphdata) > 0) {
            $data1[] = $graphdata;
        }else{
            $data1 = $data;
        }

        return response()->json([
            'ApiName' => 'company_graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data1,
        ], 200);

    }

    public function sales_report(Request $request)
    {
    	$result = array();
        $location = $request->location;
    	$filter   = $request->filter;
        if(!empty($request->perpage)){
            $perpage = $request->perpage;
        }else{
            $perpage = 10;
        }
        if ($request->has('filter') && !empty($request->input('filter')))
        {
            $filterDataDateWise = $request->input('filter');
            if($filterDataDateWise=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);
            }
            else if($filterDataDateWise=='last_week')
            {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
                $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);
            }
            else if($filterDataDateWise=='this_month')
            {
                $month = \Carbon\Carbon::now()->daysInMonth;
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $currentMonthDay = Carbon::now()->daysInMonth+Carbon::now()->month(01)->daysInMonth+Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);

            }
            else if($filterDataDateWise=='last_quarter')
            {
                $currentMonthDay = Carbon::now()->daysInMonth+Carbon::now()->month(01)->daysInMonth+Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);

            }
            else if($filterDataDateWise=='this_year')
            {
                // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);

            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);

            }
            else if($filterDataDateWise=='last_12_months')
            {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);
            }
            else if($filterDataDateWise=='custom')
            {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));
                $result = SalesMaster::with('salesMasterProcess','userDetail')->whereBetween('customer_signoff',[$startDate,$endDate]);
            }

        }else{
            $result = SalesMaster::with('salesMasterProcess','userDetail');
        }

        if ($request->has('order_by') && !empty($request->input('order_by'))){
            $orderBy = $request->input('order_by');
        }else{
            $orderBy = 'desc';
        }

        if ($request->has('office_id') && !empty($request->input('office_id')))
        {
            $office_id = $request->office_id;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');

                $result->where(function($query) use ($request,$orderBy,$salesPid) {
                    return $query->whereIn('pid', $salesPid);
                    });
            }
        }

        if ($request->has('location') && !empty($request->input('location')) && 1==2)
        {
            if ($location!='all')
            {
                $result->where(function($query) use ($request,$orderBy) {
                    return $query->where('customer_state','=', $request->location);
                    });
            }
        }

      if ($request->has('search') && !empty($request->input('search')))
      {
          $result->where(function($query) use ($request,$orderBy) {
              return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('customer_city', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('sales_rep_name', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('job_status', 'LIKE', '%'.$request->input('search').'%')
                  ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
              });
      }

      if ($request->has('closed') && !empty($request->input('closed')))
      {
          $result->where(function($query) use ($request,$orderBy) {
              return $query->where('date_cancelled','!=', Null);
              });
      }

      if ($request->has('m1') && !empty($request->input('m1')))
      {
          $result->where(function($query) use ($request,$orderBy) {
              return $query->where('m1_date', '!=', null);
              });
      }

      if ($request->has('m2') && !empty($request->input('m2')))
      {
          $result->where(function($query) use ($request,$orderBy) {
              return $query->where('m2_date', '!=', null);
              });
      }
      if($request->has('sort') &&  $request->input('sort') !='')
      {
        $data = $result->orderBy('id',$orderBy)->get();
      }else{
        $data = $result->orderBy('id',$orderBy)->paginate($perpage);
      }



        // return $data;die;
    	if (count($data) > 0) {

            $data->transform(function ($data) {
                $clawbackss = ClawbackSettlement::where(['pid'=> $data->pid, 'type'=> 'commission', 'status'=> 3, 'is_displayed' => '1'])->first();
                if ($clawbackss) {
                    $clawbackAmount = $clawbackss->clawback_amount;
                }else {
                    $clawbackAmount = 0;
                }

                $commissionData = UserCommission::where(['pid'=> $data->pid, 'status'=> 3])->first();

                // dd($clawbackAmount);
                $approveDate = $data->customer_signoff;
                $m1_date = $data->m1_date;
                $m2_date = $data->m2_date;

                if (!in_array($data->salesMasterProcess->mark_account_status_id, [1,6]) && $commissionData) {
                    $mark_account_status_name = ($commissionData)? 'Paid':null;
                }else {
                    $mark_account_status_name = isset($data->salesMasterProcess->status->account_status)?$data->salesMasterProcess->status->account_status:null;
                }
                

                $closer1_detail = isset($data->salesMasterProcess->closer1_id)?$data->salesMasterProcess->closer1Detail:null;
                $closer2_detail = isset($data->salesMasterProcess->closer2_id)?$data->salesMasterProcess->closer2Detail:null;
                $setter1_detail = isset($data->salesMasterProcess->setter1_id)?$data->salesMasterProcess->setter1Detail:null;
                $setter2_detail = isset($data->salesMasterProcess->setter2_id)?$data->salesMasterProcess->setter2Detail:null;

                $setter1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                    ->where(['user_id' => $data->salesMasterProcess->setter1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                $setter1_m1 = 0;
                $setter1_m2 = 0;
                foreach ($setter1Commissions as $setter1Commission) {
                    if($setter1Commission->amount_type == 'm1') {
                        $setter1_m1 = $setter1Commission->commission;
                    } else if ($setter1Commission->amount_type == 'm2') {
                        $setter1_m2 = $setter1Commission->commission;
                    }
                }

                $setter2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                    ->where(['user_id' => $data->salesMasterProcess->setter2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                $setter2_m1 = 0;
                $setter2_m2 = 0;
                foreach ($setter2Commissions as $setter2Commission) {
                    if($setter2Commission->amount_type == 'm1') {
                        $setter2_m1 = $setter2Commission->commission;
                    } else if ($setter2Commission->amount_type == 'm2') {
                        $setter2_m2 = $setter2Commission->commission;
                    }
                }

                $closer1_m1 = 0;
                $closer1_m2 = 0;
                if($data->salesMasterProcess->setter1_id != $data->salesMasterProcess->closer1_id){
                    $closer1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                        ->where(['user_id' => $data->salesMasterProcess->closer1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                    foreach ($closer1Commissions as $closer1Commission) {
                        if($closer1Commission->amount_type == 'm1') {
                            $closer1_m1 = $closer1Commission->commission;
                        } else if ($closer1Commission->amount_type == 'm2') {
                            $closer1_m2 = $closer1Commission->commission;
                        }
                    }
                }

                $closer2_m1 = 0;
                $closer2_m2 = 0;
                if($data->salesMasterProcess->setter2_id != $data->salesMasterProcess->closer2_id) {
                    $closer2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                        ->where(['user_id' => $data->salesMasterProcess->closer2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                    foreach ($closer2Commissions as $closer2Commission) {
                        if ($closer2Commission->amount_type == 'm1') {
                            $closer2_m1 = $closer2Commission->commission;
                        } else if ($closer2Commission->amount_type == 'm2') {
                            $closer2_m2 = $closer2Commission->commission;
                        }
                    }
                }
//                $closer1_m1 = isset($data->salesMasterProcess->closer1_m1)?$data->salesMasterProcess->closer1_m1:null;
//                $closer1_m2 = isset($data->salesMasterProcess->closer1_m2)?$data->salesMasterProcess->closer1_m2:null;
//                $closer2_m1 = isset($data->salesMasterProcess->closer2_m1)?$data->salesMasterProcess->closer2_m1:null;
//                $closer2_m2 = isset($data->salesMasterProcess->closer2_m2)?$data->salesMasterProcess->closer2_m2:null;
//
//                $setter1_m1 = isset($data->salesMasterProcess->setter1_m1)?$data->salesMasterProcess->setter1_m1:null;
//                $setter1_m2 = isset($data->salesMasterProcess->setter1_m2)?$data->salesMasterProcess->setter1_m2:null;
//                $setter2_m1 = isset($data->salesMasterProcess->setter2_m1)?$data->salesMasterProcess->setter2_m1:null;
//                $setter2_m2 = isset($data->salesMasterProcess->setter2_m2)?$data->salesMasterProcess->setter2_m2:null;

                $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
//                dd($total_m1,$clawbackAmount);
                $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);

                $pid_status = isset($data->salesMasterProcess->pid_status)?$data->salesMasterProcess->pid_status:null;

                $total_amount = $data->total_in_period;
	    		$amount = isset($data->userDetail->upfront_pay_amount)?$data->userDetail->upfront_pay_amount:0;
                $commission = isset($data->userDetail->commission)?$data->userDetail->commission:0;
                $total_commission = ($data->salesMasterProcess->closer1_commission + $data->salesMasterProcess->closer2_commission + $data->salesMasterProcess->setter1_commission + $data->salesMasterProcess->setter2_commission);

                $legacyApiNullData = LegacyApiNullData::where('pid',$data->pid)->whereNotNull('data_source_type')->orderBy('id','desc')->first();
                if($legacyApiNullData){
                    // if($legacyApiNullData->status =='Resolved' || empty($legacyApiNullData->status) && ($legacyApiNullData->sales_alert == NULL && $legacyApiNullData->missingrep_alert == NULL && $legacyApiNullData->closedpayroll_alert == NULL && $legacyApiNullData->repredline_alert == NULL && $legacyApiNullData->people_alert == NULL)){
                    if(($legacyApiNullData->status =='Resolved' || $legacyApiNullData->status == null || empty($legacyApiNullData->status)) && ($legacyApiNullData->sales_alert == NULL && $legacyApiNullData->missingrep_alert == NULL && $legacyApiNullData->closedpayroll_alert == NULL && $legacyApiNullData->repredline_alert == NULL)){
                        $alertcentre_status = 0;
                    }else{
                        $alertcentre_status = 1;
                    }

                }else{
                    $alertcentre_status = 0;
                }

                $locationData = Locations::with('State')->where('general_code','=', $data->customer_state)->first();
                if($locationData){
                    $state_code = $locationData->state->state_code;
                }else{
                    $state_code = null;
                }
//                dd($total_m1,$clawbackAmount);
                return [
                    'id' => $data->id,
	                'pid' => $data->pid ,
                    'job_status' => $data->job_status,
                    'alertcentre_status'=>$alertcentre_status,
	                'customer_name' => isset($data->customer_name)?$data->customer_name:null,
                    'state_id' => $state_code,
	                'state' => isset($data->customer_state)?$data->customer_state:null,
                    'city' => isset($data->customer_city)?$data->customer_city:null,
                    'sales_rep_name' => isset($data->sales_rep_name)?$data->sales_rep_name:null,
                    'mark_account_status_id' => isset($data->salesMasterProcess->mark_account_status_id)?$data->salesMasterProcess->mark_account_status_id:null,
                    'mark_account_status_name' => $mark_account_status_name,

                    'closer1_detail' => $closer1_detail,
                    'closer2_detail' => $closer2_detail,
                    'setter1_detail' => $setter1_detail,
                    'setter2_detail' => $setter2_detail,

                    'closer1_m1' => $closer1_m1,
                    'closer1_m2' => $closer1_m2,
                    'closer2_m1' => $closer2_m1,
                    'closer2_m2' => $closer2_m2,
                    'setter1_m1' => $setter1_m1,
                    'setter1_m2' => $setter1_m2,
                    'setter2_m1' => $setter2_m1,
                    'setter2_m2' => $setter2_m2,

	                'epc' => isset($data->epc)?$data->epc:null,
                    'net_epc' => isset($data->net_epc)?$data->net_epc:null,
                    'adders' => isset($data->adders)?$data->adders:null,
	                'kw' => isset($data->kw)?$data->kw:null,
	                'date_cancelled' => isset($data->date_cancelled)?dateToYMD($data->date_cancelled):null,
	                'total_m1' => ($total_m1),
	                'total_m2' => $total_m2,
	                'm1_date' =>  isset($data->m1_date)?dateToYMD($data->m1_date):null,
	                'm2_date' => isset($data->m2_date)? dateToYMD($data->m2_date):null,
                    'total_commission' => ($total_commission),
	                'created_at' => $data->created_at,
	                'updated_at' => $data->updated_at,
                    'data_source_type'=>$data->data_source_type
                ];
            });

            if($request->has('sort') &&  $request->input('sort') =='kw')
            {
                $val = $request->input('sort_val');
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'kw'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'kw'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,10);
            }
            if($request->has('sort') &&  $request->input('sort') =='epc')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'epc'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'epc'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,10);
            }
            if($request->has('sort') &&  $request->input('sort') =='net_epc')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'net_epc'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'net_epc'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,$perpage);
            }
            if($request->has('sort') &&  $request->input('sort') =='adders')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'adders'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'adders'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,10);
            }
            if($request->has('sort') &&  $request->input('sort') =='state')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'state'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'state'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,$perpage);
            }
            if($request->has('sort') &&  $request->input('sort') =='m1')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'total_m1'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'total_m1'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,$perpage);
            }
            if($request->has('sort') &&  $request->input('sort') =='m2')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'total_m2'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'total_m2'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,$perpage);
            }
            if($request->has('sort') &&  $request->input('sort') =='total_commission')
            {
                $data = json_decode($data);
                if($request->input('sort_val')=='desc')
                {
                    array_multisort(array_column($data, 'total_commission'),SORT_DESC, $data);
                } else{
                    array_multisort(array_column($data, 'total_commission'),SORT_ASC, $data);
                }
                $data = $this->paginates($data,$perpage);
            }
	    	return response()->json([
                'ApiName' => 'sales_report_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'sales_report_list',
                'status' => false,
                'message' => 'data not found',
                //'data' => $data,
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

    public function global_search_old(Request $request)
    {
        //$datas = array();
        $filter   = $request->filter;

        $result = SalesMaster::with('salesMasterProcess','userDetail');

        if ($request->has('search') && !empty($request->input('search')))
            {
                $result->where(function($query) use ($request) {
                    return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('customer_city', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('sales_rep_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
                    });
            }
            $data = $result->orderBy('id','desc')->get();



            $results = LegacyApiNullData::with('salesMasterProcess','userDetail');

            if ($request->has('search') && !empty($request->input('search')))
                {
                    $results->where(function($query) use ($request) {
                        return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('customer_city', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('sales_rep_name', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                            ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
                        });
                }
            $datas = $results->whereNotNull('data_source_type')->orderBy('id','desc')->get();
            $mergedArray = Arr::collapse([$datas, $data]);
             //config('app.paginate', 15);

            $data = $this->paginate($mergedArray);
            // return $data;die;
    	if (count($data) > 0) {

            $data->transform(function ($data) {
                $value = [];
                    $keys = [];
                if (empty($data->pid)) {
                    $value[] = 'pid';
                    $keys[] = 'pid';
                }
                if (empty($data->install_partner)) {
                    $value[] = 'Install Partner';
                    $keys[] = 'install_partner';
                }
                if (empty($data->customer_signoff)) {
                    $value[] = 'Customer Signoff';
                    $keys[] = 'customer_signoff';
                }
                if (empty($data->gross_account_value)) {
                    $value[] = 'Gross Account Value';
                    $keys[] = 'gross_account_value';
                }
                if (empty($data->epc)) {
                    $value[] = 'Epc';
                    $keys[] = 'epc';
                }
                if (empty($data->net_epc)) {
                    $value[] = 'Net Epc';
                    $keys[] = 'net_epc';
                }
                if (empty($data->dealer_fee_percentage)) {
                    $value[] = 'Dealer Fee Percentage';
                    $keys[] = 'dealer_fee_percentage';
                }
                if($data->customer_name==null){
                    $value[] = 'Customer Name';
                    $keys[] = 'customer_name';
                }
                if($data->customer_state==null){
                    $value[] = 'Customer state';
                    $keys[] = 'customer_state';
                }
                if($data->sales_rep_name == null){
                    $value[] = 'Rep Name';
                    $keys[] = 'sales_rep_name';
                }
                if($data->kw == null){
                    $value[] = 'Kw';
                    $keys[] = 'kw';
                }

                $update = implode(',',$value);


                $approveDate = $data->customer_signoff;
                $m1_date = $data->m1_date;
                $m2_date = $data->m2_date;

                $closer1_detail = isset($data->salesMasterProcess->closer1_id)?$data->salesMasterProcess->closer1Detail:null;
                $closer2_detail = isset($data->salesMasterProcess->closer2_id)?$data->salesMasterProcess->closer2Detail:null;
                $setter1_detail = isset($data->salesMasterProcess->setter1_id)?$data->salesMasterProcess->setter1Detail:null;
                $setter2_detail = isset($data->salesMasterProcess->setter2_id)?$data->salesMasterProcess->setter2Detail:null;

                $closer1_m1 = isset($data->salesMasterProcess->closer1_m1)?$data->salesMasterProcess->closer1_m1:null;
                $closer1_m2 = isset($data->salesMasterProcess->closer1_m2)?$data->salesMasterProcess->closer1_m2:null;
                $closer2_m1 = isset($data->salesMasterProcess->closer2_m1)?$data->salesMasterProcess->closer2_m1:null;
                $closer2_m2 = isset($data->salesMasterProcess->closer2_m2)?$data->salesMasterProcess->closer2_m2:null;

                $setter1_m1 = isset($data->salesMasterProcess->setter1_m1)?$data->salesMasterProcess->setter1_m1:null;
                $setter1_m2 = isset($data->salesMasterProcess->setter1_m2)?$data->salesMasterProcess->setter1_m2:null;
                $setter2_m1 = isset($data->salesMasterProcess->setter2_m1)?$data->salesMasterProcess->setter2_m1:null;
                $setter2_m2 = isset($data->salesMasterProcess->setter2_m2)?$data->salesMasterProcess->setter2_m2:null;

                $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
                $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);

                $pid_status = isset($data->salesMasterProcess->pid_status)?$data->salesMasterProcess->pid_status:null;

                $total_amount = $data->total_in_period;
	    		$amount = isset($data->userDetail->upfront_pay_amount)?$data->userDetail->upfront_pay_amount:0;
                $commission = isset($data->userDetail->commission)?$data->userDetail->commission:0;
                $closer1Comm  = isset($data->salesMasterProcess->closer1_commission)?$data->salesMasterProcess->closer1_commission:0;
                $closer2Comm = isset($data->salesMasterProcess->closer2_commission)?$data->salesMasterProcess->closer2_commission:0;
                $setter1Comm = isset($data->salesMasterProcess->setter1_commission)?$data->salesMasterProcess->setter1_commission:0;
                $setter2Comm = isset($data->salesMasterProcess->setter2_commission)?$data->salesMasterProcess->setter2_commission:0;
                $total_commission = ($closer1Comm + $closer2Comm + $setter1Comm + $setter2Comm);

                return [
                    'id' => $data->id,
	                'pid' => $data->pid ,
	                'customer_name' => isset($data->customer_name)?$data->customer_name:null,
	                'state' => isset($data->customer_state)?$data->customer_state:null,
                    'city' => isset($data->customer_city)?$data->customer_city:null,
                    'sales_rep_name' => isset($data->sales_rep_name)?$data->sales_rep_name:null,
                    'mark_account_status_id' => isset($data->salesMasterProcess->mark_account_status_id)?$data->salesMasterProcess->mark_account_status_id:null,
                    'mark_account_status_name' => isset($data->salesMasterProcess->status->account_status)?$data->salesMasterProcess->status->account_status:null,

                    'closer1_detail' => $closer1_detail,
                    'closer2_detail' => $closer2_detail,
                    'setter1_detail' => $setter1_detail,
                    'setter2_detail' => $setter2_detail,

                    'closer1_m1' => $closer1_m1,
                    'closer1_m2' => $closer1_m2,
                    'closer2_m1' => $closer2_m1,
                    'closer2_m2' => $closer2_m2,
                    'setter1_m1' => $setter1_m1,
                    'setter1_m2' => $setter1_m2,
                    'setter2_m1' => $setter2_m1,
                    'setter2_m2' => $setter2_m2,
                    'alert_summary' => 'Update '.$update,
                    'keys' => $keys,
                    'type_val' => isset($data->sales_type)?$data->sales_type:null,
	                'epc' => isset($data->epc)?$data->epc:null,
                    'net_epc' => isset($data->net_epc)?$data->net_epc:null,
                    'adders' => isset($data->adders)?$data->adders:null,
	                'kw' => isset($data->kw)?$data->kw:null,
	                'date_cancelled' => isset($data->date_cancelled)?dateToYMD($data->date_cancelled):null,
	                'total_m1' => $total_m1,
	                'total_m2' => $total_m2,
	                'm1_date' =>  isset($data->m1_date)?dateToYMD($data->m1_date):null,
	                'm2_date' => isset($data->m2_date)? dateToYMD($data->m2_date):null,
                    'total_commission' => $total_commission,
	                'created_at' => $data->created_at,
	                'updated_at' => $data->updated_at,
                    'data_source_type'=>$data->data_source_type
                ];
            });

	    	return response()->json([
                'ApiName' => 'sales_report_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'sales_report_list',
                'status' => false,
                'message' => 'data not found',
                //'data' => $data,
            ], 200);

    	}
    }

    // public function global_search(Request $request)
    // {
    //     $finalData = [];
    //     $finalData1 = [];
    //     $finalData2 = [];
    //     $finalData3 = [];
    //     $finalData4 = [];
    //     $finalData5 = [];
    //     $finalData6 = [];
    //     $per_page = !empty($request['perpage']) ? $request['perpage'] : 10;
    //     $result = array();
    //     $filter = isset($request->filter)?$request->filter:'all';
    //     $quick_filter = isset($request->quick_filter)?$request->quick_filter:'';
    //     $search = isset($request->search)?$request->search:'';
    //     $startDate = $request->start_date;
    //     $endDate = $request->end_date;

        // $people_keys = ['entity_type', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'redline', 'self_gen_redline'];
        // if ($request->filter_type == 'people') {
        //     $people = User::where(function ($query) use ($search) {
        //         $query->where('users.first_name', 'like', '%' . $search . '%')
        //             ->orWhere('users.last_name', 'like', '%' . $search . '%')
        //             ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%' . $search . '%']);
        //     })->select(
        //         'users.id',
        //         'users.self_gen_type',
        //         'users.first_name',
        //         'users.last_name',
        //         'users.entity_type',
        //         'users.name_of_bank',
        //         'users.routing_no',
        //         'users.account_no',
        //         'users.self_gen_redline',
        //         'users.type_of_account',
        //         'users.onboardProcess',
        //         'users.redline',
        //         'users.commission_type',
        //         'users.self_gen_accounts'
        //     );

    //     $sales_keys = ['pid','customer_signoff','epc','net_epc','customer_name','customer_state','kw'];
    //     $missingRep_keys = ['sales_rep_email','setter_id'];
    //     $people_keys = ['entity_type','name_of_bank','routing_no','account_no','type_of_account','work_email','onboardProcess','redline','self_gen_redline'];

    //     $companyProfile = CompanyProfile::first();

    //     // Check if company type is PEST and domain is in PEST_TYPE_COMPANY_DOMAIN_CHECK
    //     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //         // Conditionally remove fields from sales_keys
    //         $sales_keys = ['pid', 'customer_signoff', 'customer_name', 'customer_state', 'location_code', 'gross_account_value'];
    //     }
    //     if('sales' == 'sales'){  //->where('sales_alert',1)

    //         $sales = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type');//->get();


    //         if(!empty($search)){
    //             $sales->where(function($query) use($search){
    //                 $query->where('pid', 'like', '%' . $search . '%')
    //                     ->orWhere('customer_name', 'like', '%' . $search . '%');
    //             });
    //         }
    //         $sales = $sales->where(function($query) use ($sales_keys){
    //             foreach($sales_keys as $key){
    //                 $query->orWhereNull($key)->orWhere($key,'0')->orWhere($key,'');
    //             }
    //         });

    //         //QUICK FILTERS
    //         if(!empty($quick_filter)){
    //             $sales = $sales->whereNull($quick_filter);
    //         }
    //         if(isset($startDate) && $startDate!="" && isset($endDate) && $endDate!="")
    //         {
    //             $sales = $sales->where('m1_date','>=',$startDate)->where('m1_date','<=',$endDate)->orWhere('m2_date','>=',$startDate)->where('m2_date','<=',$endDate)->get();
    //         }else{
    //             $sales = $sales->get();
    //         }
            

    //         if(isset($sales))
    //         {
    //             $sales->transform(function ($salesCountVal) {
    //                 $value = [];
    //                 $keys = [];
    //                 if (empty($salesCountVal->pid)) {
    //                     $value[] = 'pid';
    //                     $keys[] = 'pid';
    //                 }
              
    //                 if (empty($salesCountVal->customer_signoff)) {
    //                     $value[] = 'Customer Signoff';
    //                     $keys[] = 'customer_signoff';
    //                 }
                   
    //                 if (empty($salesCountVal->epc)) {
    //                     $value[] = 'Epc';
    //                     $keys[] = 'epc';
    //                 }
    //                 if (empty($salesCountVal->net_epc)) {
    //                     $value[] = 'Net Epc';
    //                     $keys[] = 'net_epc';
    //                 }
                 
    //                 if(empty($salesCountVal->customer_name)){
    //                     $value[] = 'Customer Name';
    //                     $keys[] = 'customer_name';
    //                 }
    //                 if(empty($salesCountVal->customer_state)){
    //                     $value[] = 'Customer state';
    //                     $keys[] = 'customer_state';
    //                 }
    //                 if(empty($salesCountVal->sales_rep_name)){
    //                     $value[] = 'Rep Name';
    //                     $keys[] = 'sales_rep_name';
    //                 }
    //                 if(empty($salesCountVal->sales_rep_name)){
    //                     $value[] = 'Rep Name';
    //                     $keys[] = 'sales_rep_name';
    //                 }
                  
    //                 if(empty($salesCountVal->kw)){
    //                     $value[] = 'Kw';
    //                     $keys[] = 'kw';
    //                 }

    //                 $update = implode(',',$value);
    //                 return [
    //                     'type_val' => 'Sales',
    //                     'id' => $salesCountVal->id,
    //                     'pid' => $salesCountVal->pid,
    //                     'alert_summary' => 'Update '.$update,
    //                     'keys' => $keys,
    //                     'updated' => $salesCountVal->updated_at,
    //                     'customer_name' => $salesCountVal->customer_name,
    //                 ];
    //             });
    //         }
    //         $finalData1 = $sales;
    //     }
    //     if('missingRep' == 'missingRep'){ //->where('missingrep_alert',1)
    //         $missing = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type');//->get();
    //         if(!empty($search)){
    //             $missing->where(function($query) use($search){
    //                 $query->where('pid', 'like', '%' . $search . '%')
    //                     ->orWhere('customer_name', 'like', '%' . $search . '%');
    //             });
    //         }

    //         //QUICK FILTERS
    //         if(!empty($quick_filter)){
    //             $missing = $missing->whereNull($quick_filter);
    //         }

    //         if($startDate!="" && $endDate!="")
    //         {
    //             $missing = $missing->where('customer_signoff','>=',$startDate)->where('customer_signoff','<=',$endDate)->get();
    //         }else{
    //             $missing = $missing->get();
    //         }
            
    //         $data = [];
    //         foreach($missing as $missingCountVal) {
    //             $value = [];
    //             $keys = [];
    //             if(empty($missingCountVal->sales_rep_email))
    //             {
    //                 $value[] = 'Sales Rep Email';
    //                 $keys[] = 'sales_rep_email';
    //             }
    //             if(empty($missingCountVal->setter_id))
    //             {
    //                 $value[] = 'Setter';
    //                 $keys[] = 'setter_id';
    //             }
    //             $missingCountVal->sales_rep_email;
    //             if($missingCountVal->sales_rep_email != null || $missingCountVal->sales_rep_email != '')
    //             {
    //                 $user = User::where('email',$missingCountVal->sales_rep_email)->first();
    //                 if (empty($user)) {
    //                     $additional_user_id = UsersAdditionalEmail::where('email',$missingCountVal->sales_rep_email)->value('user_id');
    //                     if(!empty($additional_user_id)){
    //                         $user = User::where('id', $additional_user_id)->first();
    //                     }
    //                 }
    //                 if(empty($user))
    //                 {
    //                     $value[] = 'Closer '.$missingCountVal->sales_rep_email.' not in users';
    //                     $keys[] = 'sales_rep_email';
    //                 }
    //             }
    //             if(!empty($value)){
    //                 $update = implode(',',$value);
    //                 $data[] = [
    //                     'type_val' => 'Missing Rep',
    //                     'id' => $missingCountVal->id,
    //                     'pid' => $missingCountVal->pid,
    //                     // 'heading' => $missingCountVal->pid.'-'.$missingCountVal->sales_rep_name.' - Data Missing',
    //                     // 'sales_rep_name' => $missingCountVal->sales_rep_name,
    //                     'alert_summary' => 'Update '.$update,
    //                     'keys' => $keys,
    //                     // 'type' => isset($missingCountVal->type)?$missingCountVal->type:'Missing Info',
    //                     // 'severity' => 'High',
    //                     // 'status' => ($missingCountVal->onboardProcess==1)?'Resolve':'Pending',
    //                     'updated' => $missingCountVal->updated_at,
    //                     'customer_name' => $missingCountVal->customer_name,
    //                 ];
    //             }
    //         }
    //          $finalData2 = $data;
    //     }
    //     if('closedPayroll' == 'closedPayroll'){ //->where('closedpayroll_alert',1)

    //         $closedPayroll = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type');//->get();
    //         if(!empty($search)){
    //             $closedPayroll->where(function($query) use($search){
    //                 $query->where('pid', 'like', '%' . $search . '%')
    //                     ->orWhere('customer_name', 'like', '%' . $search . '%');
    //             });
    //         }

    //         //QUICK FILTERS
    //         if(!empty($quick_filter)){
    //             $closedPayroll = $closedPayroll->where('closedpayroll_type',$quick_filter);
    //         }

    //         if($startDate!="" && $endDate!="")
    //         {
    //             $closedPayroll = $closedPayroll->where('customer_signoff','>=',$startDate)->where('customer_signoff','<=',$endDate)->where('type','Payroll')->where('status','!=','Resolved')->orderBy('id','DESC')->get();//->where('status','!=','Resolved')
    //         }else{
    //             $closedPayroll = $closedPayroll->where('type','Payroll')->where('status','!=','Resolved')->orderBy('id','DESC')->get();//->where('status','!=','Resolved')
    //         }

    //         $closedPayroll->transform(function ($closedPayrollCountVal) {
    //             $value = [];
    //             $keys = [];
    //             if($closedPayrollCountVal->type == 'Payroll')
    //             {
    //                 $value[] = $closedPayrollCountVal->closedpayroll_type;
    //                 $keys[] = $closedPayrollCountVal->closedpayroll_type;
    //             }
    //             $update = implode(',',$value);
    //                 return [
    //                     'type_val' => 'Closed Payroll',
    //                     'id' => $closedPayrollCountVal->id,
    //                     'pid' => $closedPayrollCountVal->pid,
    //                     'alert_summary' => 'Update '.$update,
    //                     'keys' => $keys,
    //                     'updated' => $closedPayrollCountVal->updated_at,
    //                     'customer_name' => $closedPayrollCountVal->customer_name,
    //                 ];
    //         });
    //         $finalData3 = $closedPayroll;
    //     }
    //     if('locationRedline' == 'locationRedline'){ //->where('locationredline_alert',1)

    //         $locationRedline = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')->where('locationredline_alert','!=','');//->get();
    //         if(!empty($search)){
    //             $locationRedline->where(function($query) use($search){
    //                 $query->where('pid', 'like', '%' . $search . '%')
    //                     ->orWhere('customer_name', 'like', '%' . $search . '%');
    //             });
    //         }
    //         if($startDate!="" && $endDate!="")
    //         {
    //             $locationRedline = $locationRedline->where('customer_signoff','>=',$startDate)->where('customer_signoff','<=',$endDate)->get();
    //         }else{
    //             $locationRedline = $locationRedline->get();
    //         }

    //         $data = [];
    //         foreach($locationRedline as $locationValue){
    //             if($locationValue->locationredline_alert =='Location'){
    //                 $alert_summery = 'Add location  for sale approval - '. date('m/d/Y',strtotime($locationValue->locationredline_alert));
    //             }
    //             else{
    //                 $alert_summery = 'update location redliine for sale approval - '. date('m/d/Y',strtotime($locationValue->locationredline_alert));
    //             }

    //             $state = State::where('state_code',$locationValue->customer_state)->first();
    //             if(config("app.domain_name") == 'flex') {
    //                 $location_data = Locations::where('general_code',$locationValue->customer_state)->first();
    //             }
    //             else{
    //                 $location_data = Locations::where('general_code',$locationValue->location_code)->first();
    //             }
    //             $data[] =  [
    //                 'type_val' => 'location Redline',
    //                 'id' => $locationValue->id,
    //                 'pid' => $locationValue->pid,
    //                 'alert_summary' => $alert_summery,
    //                 'keys' => [$locationValue->locationredline_alert],
    //                 'updated' => $locationValue->updated_at,
    //                 'customer_name' => $locationValue->customer_name,
    //                 'location_data' => $location_data,
    //                 'state_name' => isset($state->name)?$state->name:null,
    //                 'state_data' => $state
    //             ];
    //         }
            
    //         $finalData4 = $data;
    //     }
    //     if('repRedline' == 'repRedline'){ //->where('repredline_alert',1)
    //         $data = [];
    //         // $repRedline = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')
    //         $user_email_data = DB::Select("select * from ( SELECT uae.user_id,u.self_gen_accounts,u.self_gen_type,u.first_name,u.middle_name,u.last_name,uae.email,u.state_id,u.city_id,u.location,u.position_id,u.sub_position_id,u.is_super_admin,u.is_manager,u.entity_type,u.name_of_bank,u.routing_no,u.account_no,u.type_of_account,u.onboardProcess,u.redline,u.self_gen_redline
    //         FROM `users_additional_emails` uae join users u on u.id = uae.user_id
    //         union
    //         select id,self_gen_accounts,self_gen_type,first_name,middle_name,last_name,email,state_id,city_id,location,position_id,sub_position_id,is_super_admin,is_manager,entity_type,name_of_bank,routing_no,account_no,type_of_account,onboardProcess,redline,self_gen_redline
    //         from users
    //         ) as tbl");
    //         $arr = [];
    //         foreach($user_email_data as $key => $ued){
    //             $arr[$key]['user_id'] = $ued->user_id;
    //             $arr[$key]['email'] = $ued->email;
    //             $arr[$key]['self_gen_accounts'] = $ued->self_gen_accounts;
    //             $arr[$key]['self_gen_type'] = $ued->self_gen_type;
    //             $arr[$key]['first_name'] = $ued->first_name;
    //             $arr[$key]['middle_name'] = $ued->middle_name;
    //             $arr[$key]['last_name'] = $ued->last_name;
    //             $arr[$key]['state_id'] = $ued->state_id;
    //             $arr[$key]['city_id'] = $ued->city_id;
    //             $arr[$key]['location'] = $ued->location;
    //             $arr[$key]['position_id'] = $ued->position_id;
    //             $arr[$key]['sub_position_id'] = $ued->sub_position_id;
    //             $arr[$key]['is_super_admin'] = $ued->is_super_admin;
    //             $arr[$key]['is_manager'] = $ued->is_manager;
    //             $arr[$key]['entity_type'] = $ued->entity_type;
    //             $arr[$key]['name_of_bank'] = $ued->name_of_bank;
    //             $arr[$key]['routing_no'] = $ued->routing_no;
    //             $arr[$key]['account_no'] = $ued->account_no;
    //             $arr[$key]['type_of_account'] = $ued->type_of_account;
    //             // $arr[$key]['work_email'] = $ued->work_email;
    //             $arr[$key]['onboardProcess'] = $ued->onboardProcess;
    //             $arr[$key]['redline'] = $ued->redline;
    //             $arr[$key]['self_gen_redline'] = $ued->self_gen_redline;
    //         }
    //         $user_email_data = $arr;

    //         $user_id_array = array_column($user_email_data, 'user_id');
    //         $user_email_array = array_column($user_email_data, 'email');
    //         $repRedline = LegacyApiNullData::whereNotNull('repredline_alert')->whereNotNull('data_source_type')->whereNotNull('setter_id')
    //         ->where(function($query) use($search){
    //             $query->where('pid', 'like', '%' . $search . '%')
    //                   ->orWhere('customer_name', 'like', '%' . $search . '%');
    //         });
    //         if($startDate!="" && $endDate!="")
    //         {
    //             $repRedline = $repRedline->where('customer_signoff','>=',$startDate)->where('customer_signoff','<=',$endDate)->get();
    //         }else{
    //             $repRedline = $repRedline->get();
    //         }
    //         $repredline_alert_value_array = [];
    //         $repredline_alert_key_array = [];
    //         foreach($repRedline as $key =>  $repRedlineCountVal) {
    //             $value = [];
    //             $keys = [];
    //             $position = '';
    //             $position_name = ['2'=>'Closer','3'=>'Setter'];

    //             $closer_id_index = array_search($repRedlineCountVal['sales_rep_email'],$user_email_array);
    //             if($closer_id_index != '' && $closer_id_index != null && isset($user_email_data[$closer_id_index])){
    //                 $closer_data = $user_email_data[$closer_id_index];
    //                 $closer_data['sales_data'] = $repRedlineCountVal;
    //                 $closer_obj = json_decode(json_encode($closer_data));

    //                 $repredline_alert_value_array['repredline_closer_redline_saleapproval'] = 'closer '.$closer_obj->first_name.' '.$closer_obj->last_name.' Redline is missing for sale approval '.date('m/d/Y',strtotime($closer_obj->sales_data->customer_signoff));
    //                 $repredline_alert_value_array['repredline_closer_selfgenredline_saleapproval'] = 'Closer '.$closer_obj->first_name.' '.$closer_obj->last_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y',strtotime($closer_obj->sales_data->customer_signoff));

    //                 $repredline_alert_key_array['repredline_closer_redline_saleapproval'] = 'closer_rep_redline';
    //                 $repredline_alert_key_array['repredline_closer_selfgenredline_saleapproval'] = 'closer_self_gen_redline';
    //             }
    //             $setter_id_index = array_search($repRedlineCountVal['setter_id'],$user_id_array);
    //             if($setter_id_index != '' && $setter_id_index != null && isset($user_email_data[$setter_id_index])){
    //                 $setter_data = $user_email_data[$setter_id_index]; 
    //                 $setter_data['sales_data'] = $repRedlineCountVal;
    //                 $setter_obj = json_decode(json_encode($setter_data));

    //                 $repredline_alert_value_array['repredline_setter_redline_saleapproval'] = 'Setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' Redline is missing for sale approval '.date('m/d/Y',strtotime($setter_obj->sales_data->customer_signoff));
    //                 $repredline_alert_value_array['repredline_setter_selfgenredline_saleapproval'] = 'Setter '.$setter_obj->first_name.' '.$setter_obj->last_name.' Self Gen Redline is missing for sale approval '.date('m/d/Y',strtotime($setter_obj->sales_data->customer_signoff));

    //                 $repredline_alert_key_array['repredline_setter_redline_saleapproval'] = 'setter_rep_redline';
    //                 $repredline_alert_key_array['repredline_setter_selfgenredline_saleapproval'] = 'setter_rep_redline';
    //             }

    //             $repredline_alerts = explode(',',$repRedlineCountVal['repredline_alert']);
               
    //             foreach($repredline_alerts as $row_alert){
    //                 if(isset($repredline_alert_value_array[$row_alert])){
    //                     $value[] = str_replace("_"," ",$repredline_alert_value_array[$row_alert]);
    //                     // $key[] = $repredline_alert_key_array[$row_alert];
    //                 }
    //             }
    //             if(!empty($value)){
    //             // $update = implode(',',$repredline_alert_value_array);
    //                $data[] =  [
    //                     'type_val' => 'Rep Redline',
    //                     'id' => $repRedlineCountVal->id,
    //                     'pid' => $repRedlineCountVal->pid,
    //                     'alert_summary' => "Update ".join(', ',$value),
    //                     'keys' => $keys,
    //                     'updated' => $repRedlineCountVal->updated_at,
    //                     'customer_name' => $repRedlineCountVal->customer_name,
    //                     'position_name' => !empty($position)?$position_name[$position]:null,
    //                     'rep_name' => $repRedlineCountVal->first_name .' '.$repRedlineCountVal->last_name
    //                 ];
    //             }
    //         };
    //         $finalData5 = $data;
    //     }
    //     if('people' == 'people'){   //->where('people_alert',1)
    //         $people = User::where(function($query) use($search){
    //             $query->where('users.first_name', 'like', '%' . $search . '%')
    //                   ->orWhere('users.last_name', 'like', '%' . $search . '%')
    //                   ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%' . $search . '%']);

    //         })
    //         ->select(
    //             'users.id',
    //             'users.first_name',
    //             'users.last_name',
    //             'users.redline',
    //             'users.position_id',
    //             'users.self_gen_type',
    //             'users.sub_position_id',
    //             'users.social_sequrity_no',
    //             'users.tax_information',
    //             'users.name_of_bank',
    //             'users.routing_no',
    //             'users.account_no',
    //             'users.type_of_account',
    //             'users.work_email',
    //             'users.onboardProcess'
    //         );

    //         $people = $people->where(function($q) use ($people_keys){
    //             foreach($people_keys as $key){
    //                 $q->orWhereNull('users.'.$key)->orWhere('users.'.$key,'0')->orWhere('users.'.$key,'');
    //             }
    //         });

    //         //QUICK FILTERS
    //         if(!empty($quick_filter)){
    //             $people = $people->whereNull($quick_filter);
    //         }

    //         $people = $people->groupBy('users.id')->get();

    //         if(isset($people))
    //         {
    //             $people->transform(function ($peopleCountVal) {
    //                 $value = [];
    //                 $keys = [];
    //                 if (empty($peopleCountVal->social_sequrity_no)) {
    //                     $value[] = 'social sequrity no';
    //                     $keys[] = 'social_sequrity_no';
    //                 }
    //                 if (empty($peopleCountVal->tax_information)) {
    //                     $value[] = 'tax information';
    //                     $keys[] = 'tax_information';
    //                 }
    //                 if (empty($peopleCountVal->name_of_bank)) {
    //                     $value[] = 'name of bank';
    //                     $keys[] = 'name_of_bank';
    //                 }
    //                 if (empty($peopleCountVal->routing_no)) {
    //                     $value[] = 'routing no';
    //                     $keys[] = 'routing_no';
    //                 }
    //                 if (empty($peopleCountVal->account_no)) {
    //                     $value[] = 'account no';
    //                     $keys[] = 'account_no';
    //                 }
    //                 if (empty($peopleCountVal->type_of_account)) {
    //                     $value[] = 'type of account';
    //                     $keys[] = 'type_of_account';
    //                 }
    //                 if (empty($peopleCountVal->work_email)) {
    //                     $value[] = 'work email';
    //                     $keys[] = 'work_email';
    //                 }
    //                 if($peopleCountVal->onboardProcess==0){
    //                     $value[] = 'onboard process';
    //                     $keys[] = 'onboardProcess';
    //                 }
    //                 if($peopleCountVal->self_gen_type==2 && empty($peopleCountVal->self_gen_redline)){ //closer
    //                     $value[] = 'self gen redline';
    //                     $keys[] = 'self_gen_redline';
    //                 }elseif(empty($peopleCountVal->redline)){ //closer
    //                     $value[] = 'redline';
    //                     $keys[] = 'redline';
    //                 }

    //                 if($peopleCountVal->self_gen_type==2){
    //                     $position = 'Setter';
    //                 }else{
    //                     $position = 'Closer';
    //                 }
    //                 $update = implode(',',$value);
    //                 return [
    //                     'type_val' => 'People',
    //                     'id' => $peopleCountVal->id,
    //                     'pid' => $peopleCountVal->pid,
    //                     'alert_summary' => 'Update '.$update,
    //                     'keys' => $keys,
    //                     'updated' => $peopleCountVal->updated_at,
    //                     'position' => $position,
    //                     'user_name' => $peopleCountVal->first_name.' '.$peopleCountVal->last_name,
    //                     'user_id' => $peopleCountVal->id
    //                 ];
    //             });
    //         }
    //           $finalData6 = $people;
    //     }
    //     if($request->filter_type=='payroll')
    //     {
    //         $finalData = Arr::collapse([$finalData1, $finalData2, $finalData3, $finalData4, $finalData5]);
    //         $finalData = $this->paginate($finalData,$per_page);
    //     }else
    //     if($request->filter_type=='people'){
    //         $finalData = Arr::collapse([$finalData6]);
    //         $finalData = $this->paginate($finalData,$per_page);
    //     }

    //     return response()->json(['ApiName'=>'Alert Data Api', 'status' => true,'message'=>'Successfully', 'data' => $finalData], 200);

    // }
//     public function global_search(Request $request)
// {
//     $finalData = [
//         'sales' => [],
//         'missingRep' => [],
//         'closedPayroll' => [],
//         'locationRedline' => [],
//         'repRedline' => [],
//         'people' => []
//     ];

//     $per_page = $request->input('perpage', 10);
//     $search = $request->input('search', '');
//     $startDate = $request->start_date;
//     $endDate = $request->end_date;
//     $quick_filter = $request->input('quick_filter', '');
//     $filter = $request->input('filter', 'all');

//     $companyProfile = CompanyProfile::first();
//     $domainName = config('app.domain_name');

//     $sales_keys = ['pid','customer_signoff','epc','net_epc','customer_name','customer_state','kw'];
//     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
//         $sales_keys = ['pid', 'customer_signoff', 'customer_name', 'customer_state', 'location_code', 'gross_account_value'];
//     }

//     $queries = [
//         'sales' => LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type'),
//         'missingRep' => LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type'),
//         'closedPayroll' => LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')->where('type', 'Payroll')->where('status', '!=', 'Resolved'),
//         'locationRedline' => LegacyApiNullData::where('action_status', 0)->whereNotNull('data_source_type')->where('locationredline_alert', '!=', ''),
//         'repRedline' => LegacyApiNullData::whereNotNull('repredline_alert')->whereNotNull('data_source_type')->whereNotNull('setter_id'),
//         'people' => User::query()
//     ];

//     foreach ($queries as $key => $query) {
//         if (!empty($search)) {
//             $query->where(function($q) use ($search, $key) {
//                 if ($key === 'people') {
//                     $q->where('first_name', 'like', '%' . $search . '%')
//                       ->orWhere('last_name', 'like', '%' . $search . '%')
//                       ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $search . '%']);
//                 } else {
//                     $q->where('pid', 'like', '%' . $search . '%')
//                       ->orWhere('customer_name', 'like', '%' . $search . '%');
//                 }
//             });
//         }

//         if (!empty($quick_filter)) {
//             $query->whereNull($quick_filter);
//         }

//         if (isset($startDate) && !empty($startDate) && isset($endDate) && !empty($endDate)) {
//             $query->whereBetween('customer_signoff', [$startDate, $endDate]);
//         }

//         if ($key !== 'people') {
//             $query->where(function($q) use ($key, $sales_keys) {
//                 $keys = $key === 'sales' ? $sales_keys : config("keys.{$key}");
//                 foreach ($keys as $field) {
//                     $q->orWhereNull($field)->orWhere($field, '0')->orWhere($field, '');
//                 }
//             });
//         }

//         $data = $query->get();

//         if ($key === 'sales' || $key === 'closedPayroll') {
//             $data->transform(function($item) use ($key, $sales_keys) {
//                 $missingKeys = array_filter($sales_keys, fn($field) => empty($item->$field));
//                 $alertSummary = 'Update ' . implode(', ', array_map(fn($field) => ucfirst(str_replace('_', ' ', $field)), $missingKeys));
//                 return [
//                     'type_val' => ucfirst($key),
//                     'id' => $item->id,
//                     'pid' => $item->pid,
//                     'alert_summary' => $alertSummary,
//                     'keys' => $missingKeys,
//                     'updated' => $item->updated_at,
//                     'customer_name' => $item->customer_name
//                 ];
//             });
//         } elseif ($key === 'missingRep' || $key === 'locationRedline' || $key === 'repRedline') {
//             // handle specific transformations if necessary
//         } elseif ($key === 'people') {
//             $data->transform(function($item) {
//                 $missingKeys = collect(['entity_type','name_of_bank','routing_no','account_no','type_of_account','work_email','onboardProcess','redline','self_gen_redline'])
//                                 ->filter(fn($field) => empty($item->$field));
//                 $alertSummary = 'Update ' . implode(', ', array_map(fn($field) => ucfirst(str_replace('_', ' ', $field)), $missingKeys->toArray()));
//                 return [
//                     'type_val' => 'People',
//                     'id' => $item->id,
//                     'alert_summary' => $alertSummary,
//                     'keys' => $missingKeys->toArray(),
//                     'updated' => $item->updated_at,
//                     'name' => $item->first_name . ' ' . $item->last_name
//                 ];
//             });
//         }

//         $finalData[$key] = $data;
//     }

//     return response()->json($finalData);
// }
public function global_search(Request $request)
{
    $finalData = [];
    $finalData1 = [];
    $finalData2 = [];
    $finalData3 = [];
    $finalData4 = [];
    $finalData5 = [];
    $finalData6 = [];
    $per_page = !empty($request['perpage']) ? $request['perpage'] : 10;
    $result = array();
    $filter = isset($request->filter)?$request->filter:'all';
    $quick_filter = isset($request->quick_filter)?$request->quick_filter:'';
    $search = isset($request->search)?$request->search:'';
    $startDate = $request->start_date;
    $endDate = $request->end_date;

    $sales_keys = ['pid','customer_signoff','epc','net_epc','customer_name','customer_state','kw'];
    $missingRep_keys = ['sales_rep_email','setter_id'];
    $people_keys = ['entity_type','name_of_bank','routing_no','account_no','type_of_account','work_email','onboardProcess','redline','self_gen_redline'];
    $positions_array = ['2' => 'Closer', '3' => 'Setter'];

    $companyProfile = CompanyProfile::first();

    // Check if company type is PEST and domain is in PEST_TYPE_COMPANY_DOMAIN_CHECK
    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        $sales_keys = ['pid', 'customer_signoff', 'customer_name', 'customer_state', 'location_code', 'gross_account_value'];
        $people_keys = ['entity_type','name_of_bank','routing_no','account_no','type_of_account','work_email','onboardProcess'];
    }

    $people_keys_msg = [
        'entity_type' => 'Entity type (Individual / Business)',
        'name_of_bank' => 'Bank Name',
        'routing_no' => 'Routing Number',
        'account_no' => 'Bank Account Number',
        'type_of_account' => 'Type of Account ( Cheking / Savings )',
        'onboardProcess' => 'Incomplete Onboarding',
        'redline' => 'Redline',
        'self_gen_redline' => 'Self Gen Redline'
    ];

    // Get user data for rep alerts
    $user_email_data = DB::Select("select * from (SELECT uae.user_id,u.self_gen_accounts,u.self_gen_type,u.first_name,u.middle_name,u.last_name,uae.email,u.state_id,u.city_id,u.location,u.position_id,u.sub_position_id,u.is_super_admin,u.is_manager,u.entity_type,u.name_of_bank,u.routing_no,u.account_no,u.type_of_account,u.onboardProcess,u.redline,u.self_gen_redline,u.commission_type
        FROM `users_additional_emails` uae join users u on u.id = uae.user_id WHERE u.is_super_admin != 1
        union
        select id,self_gen_accounts,self_gen_type,first_name,middle_name,last_name,email,state_id,city_id,location,position_id,sub_position_id,is_super_admin,is_manager,entity_type,name_of_bank,routing_no,account_no,type_of_account,onboardProcess,redline,self_gen_redline,commission_type
        from users WHERE users.is_super_admin != 1
    ) as tbl");

    $arr = [];
    foreach ($user_email_data as $key => $ued) {
        $arr[] = [
            'id' => $ued->user_id,
            'email' => $ued->email,
            'self_gen_accounts' => $ued->self_gen_accounts,
            'self_gen_type' => $ued->self_gen_type,
            'first_name' => $ued->first_name,
            'middle_name' => $ued->middle_name,
            'last_name' => $ued->last_name,
            'state_id' => $ued->state_id,
            'city_id' => $ued->city_id,
            'location' => $ued->location,
            'position_id' => $ued->position_id,
            'sub_position_id' => $ued->sub_position_id,
            'is_super_admin' => $ued->is_super_admin,
            'is_manager' => $ued->is_manager,
            'entity_type' => $ued->entity_type,
            'name_of_bank' => $ued->name_of_bank,
            'routing_no' => $ued->routing_no,
            'account_no' => $ued->account_no,
            'type_of_account' => $ued->type_of_account,
            'onboardProcess' => $ued->onboardProcess,
            'redline' => $ued->redline,
            'self_gen_redline' => $ued->self_gen_redline,
            'commission_type' => $ued->commission_type
        ];
    }
    $user_email_data = $arr;
    $user_id_array = array_column($user_email_data, 'id');
    $user_email_array = array_column($user_email_data, 'email');

    // SALES ALERTS
    if('sales' == 'sales') {
        $sales = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type');

        if (!empty($search)) {
            $sales->where(function($query) use($search){
                $query->where('pid', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%');
            });
        }

        $sales = $sales->where(function($query) use ($sales_keys){
            foreach($sales_keys as $key){
                $query->orWhereNull($key)->orWhere($key,'0')->orWhere($key,'');
            }
        });

        // QUICK FILTERS
        if (!empty($quick_filter)) {
            $sales = $sales->whereNull($quick_filter);
        }

        if (isset($startDate) && $startDate != "" && isset($endDate) && $endDate != "") {
            $sales = $sales->where('m1_date','>=',$startDate)->where('m1_date','<=',$endDate)
                        ->orWhere('m2_date','>=',$startDate)->where('m2_date','<=',$endDate)->get();
        } else {
            $sales = $sales->get();
        }

        $finalData1 = []; // Initialize as empty array

        if ($sales && $sales->isNotEmpty()) {
            $filteredSales = $sales->map(function ($salesCountVal) use ($companyProfile) {
                $value = '';
                $keys = [];

                // Process sales alerts
                if (!empty($salesCountVal->sales_alert)) {
                    $salesAlerts = explode(',', $salesCountVal->sales_alert);
                    $alertMessages = [];
                    
                    foreach ($salesAlerts as $alert) {
                        // Apply PEST company type condition
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            $alert = str_replace('customer_signoff', 'sale_date', $alert);
                        }
                        $alertMessages[] = str_replace("_", " ", $alert);
                        $keys[] = $alert;
                    }
                    
                    $value = implode(', ', $alertMessages);
                }

                if (!empty($value)) {
                    return [
                        'type_val' => 'Sales',
                        'id' => $salesCountVal->id,
                        'pid' => $salesCountVal->pid,
                        'alert_summary' => 'Update '.$value,
                        'keys' => $keys,
                        'updated' => $salesCountVal->updated_at,
                        'customer_name' => $salesCountVal->customer_name,
                    ];
                }
                return null; // Return null for records with empty values
            })->filter(); // Remove null values

            $finalData1 = $filteredSales->values()->all(); // Get only non-null values
        }
    }
    // MISSING REP ALERTS
    if('missingRep' == 'missingRep') {
        $missingRep = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')
            ->where(function($query) use ($missingRep_keys){
                foreach($missingRep_keys as $key){
                    $query->orWhereNull($key)->orWhere($key,'0')->orWhere($key,'');
                }
            });

        if (!empty($search)) {
            $missingRep->where(function($query) use($search){
                $query->where('pid', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%');
            });
        }

        if (isset($startDate) && $startDate != "" && isset($endDate) && $endDate != "") {
            $missingRep = $missingRep->where('m1_date','>=',$startDate)->where('m1_date','<=',$endDate)
                                   ->orWhere('m2_date','>=',$startDate)->where('m2_date','<=',$endDate)->get();
        } else {
            $missingRep = $missingRep->get();
        }

        if (isset($missingRep)) {
            $missingRep->transform(function ($missingRepVal) use ($user_email_array, $user_email_data) {
                $value = [];
                $keys = [];
                $new_rep_email = null;

                if (empty($missingRepVal->sales_rep_email)) {
                    $keys[] = 'sales_rep_email';
                    $value[] = 'sales_rep_email';
                }

                // Check for terminated/dismissed reps
                if (!empty($missingRepVal->sales_rep_email)) {
                    $user = User::where('email', $missingRepVal->sales_rep_email)->first();
                    if ($user) {
                        if ($user->terminateHistoryOn($missingRepVal->customer_signoff)) {
                            $keys[] = 'sales_rep_terminated';
                            $value[] = 'sales_rep_terminated';
                        }
                        if ($user->dismissHistoryOn($missingRepVal->customer_signoff)) {
                            $keys[] = 'sales_rep_dismissed';
                            $value[] = 'sales_rep_dismissed';
                        }
                        if ($user->end_date && strtotime($user->end_date) < strtotime('now')) {
                            $keys[] = 'sales_rep_contract_ended';
                            $value[] = 'sales_rep_contract_ended';
                        }
                    }
                }

                if (!empty($missingRepVal->sales_setter_email)) {
                    $user = User::where('email', $missingRepVal->sales_setter_email)->first();
                    if ($user) {
                        if ($user->terminateHistoryOn($missingRepVal->customer_signoff)) {
                            $keys[] = 'sales_setter_terminated';
                            $value[] = 'sales_setter_terminated';
                        }
                        if ($user->dismissHistoryOn($missingRepVal->customer_signoff)) {
                            $keys[] = 'sales_setter_dismissed';
                            $value[] = 'sales_setter_dismissed';
                        }
                        if ($user->end_date && strtotime($user->end_date) < strtotime('now')) {
                            $keys[] = 'sales_setter_contract_ended';
                            $value[] = 'sales_setter_contract_ended';
                        }
                    }
                }

                $alert_summary = "Update " . implode(", ", array_map(function($v) use ($missingRepVal, $user_email_array, $user_email_data) {
                    if ($v == 'sales_rep_email') {
                        $closer_id_index = array_search($missingRepVal->sales_rep_email, $user_email_array);
                        if ($closer_id_index !== false && isset($user_email_data[$closer_id_index])) {
                            $closer = $user_email_data[$closer_id_index];
                            return 'sales rep ' . $closer['first_name'] . ' ' . $closer['last_name'] . ' for sale approval ' . date('m/d/Y', strtotime($missingRepVal->customer_signoff));
                        }
                        return 'sales rep email';
                    } 
                    elseif ($v == 'sales_rep_terminated') {
                        return 'Sales rep terminated';
                    }
                    elseif ($v == 'sales_rep_dismissed') {
                        return 'Sales rep dismissed';
                    }
                    elseif ($v == 'sales_rep_contract_ended') {
                        return 'Sale rep contract ended';
                    }
                    elseif ($v == 'sales_setter_terminated') {
                        return 'Sales setter terminated';
                    }
                    elseif ($v == 'sales_setter_dismissed') {
                        return 'Sales setter dismissed';
                    }
                    elseif ($v == 'sales_setter_contract_ended') {
                        return 'Sales setter contract ended';
                    }
                    return str_replace("_", " ", $v);
                }, $value));

                return [
                    'type_val' => 'Missing Rep',
                    'id' => $missingRepVal->id,
                    'pid' => $missingRepVal->pid,
                    'alert_summary' => $alert_summary,
                    'keys' => $keys,
                    'updated' => $missingRepVal->updated_at,
                    'customer_name' => $missingRepVal->customer_name,
                    'new_rep_email' => $new_rep_email
                ];
            });
        }
        $finalData2 = $missingRep;
    }

    // PEOPLE ALERTS
    if('people' == 'people') {
        $people_key_data = [];
        $people_user_data = [];

        $uniqueUserIDs = [];
        foreach ($user_email_data as $user_key => $user_data) {
            if (!in_array($user_data['id'], $uniqueUserIDs)) {
                $uniqueUserIDs[] = $user_data['id'];
                $key = [];
                foreach ($people_keys as $people_key) {
                    if (empty($user_data[$people_key])) {
                        if ($people_key == 'self_gen_redline' && $user_data['self_gen_accounts'] == 0) {
                            continue;
                        }
                        if ($people_key == 'self_gen_redline' && $user_data['commission_type'] == 'per kw') {
                            continue;
                        }
                        $key[] = $people_key;
                    }
                }
                if (!empty($key)) {
                    $people_key_data[$user_key] = $key;
                    $people_user_data[$user_key] = $user_data;
                }
            }
        }

        $peopleData = [];
        foreach ($people_key_data as $key => $row) {
            if (array_search($people_user_data[$key]['id'], array_column($peopleData, 'id')) === false) {
                $summary = [];
                foreach ($row as $r) {
                    $summary[] = isset($people_keys_msg[$r]) 
                        ? $people_keys_msg[$r] 
                        : strtoupper(str_replace("_", " ", $r));
                }


                $peopleData[] = [
                    "type_val" => "People",
                    "id" => $people_user_data[$key]['id'],
                    "alert_summary" => "Update " . join(', ', $summary),
                    "keys" => $row,
                    "user_name" => $people_user_data[$key]['first_name'] . ' ' . $people_user_data[$key]['last_name'],
                    "user_id" => $people_user_data[$key]['id'],
                    "position" => !empty($people_user_data[$key]['position_id']) ? $positions_array[$people_user_data[$key]['position_id']] : null,
                    "position_id" => $people_user_data[$key]['position_id'],
                    "sub_position_id" => $people_user_data[$key]['sub_position_id'],
                    "is_super_admin" => $people_user_data[$key]['is_super_admin'],
                    "is_manager" => $people_user_data[$key]['is_manager'],
                    "updated" => date('Y-m-d H:i:s') // Since people alerts don't have updated_at
                ];
            }
        }
        $finalData6 = $peopleData;
    }

    // Pagination and response
    if ($request->filter_type == 'payroll') {
        $finalData = Arr::collapse([$finalData1, $finalData2, $finalData3, $finalData4, $finalData5]);
        $finalData = $this->paginate($finalData, $per_page);
    } else if ($request->filter_type == 'people') {
        $finalData = Arr::collapse([$finalData6]);
        $finalData = $this->paginate($finalData, $per_page);
    }

    return response()->json(['ApiName'=>'Alert Data Api', 'status' => true,'message'=>'Successfully', 'data' => $finalData], 200);
}


    public function count_salesInfo_alert($sales_keys){
        $count = LegacyApiNullData::where('action_status',0)->where(function($query) use ($sales_keys){
            foreach($sales_keys as $key){
                $query->orWhereNull($key)->orWhere($key,'0')->orWhere($key,'');
            }
        })->count();
        return $count;
    }

    public function count_missingRep_alert($missingRep_keys){
        $sales = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')
        ->where(function($query) use ($missingRep_keys){
            foreach($missingRep_keys as $key){
                $query->orWhereNull($key)->orWhere($key,'0')->orWhere($key,'');
            }
        });
        $sales = $sales->get();
        $data = [];
        foreach($sales as $salesCountVal) {
            $value = [];
            $keys = [];
            if(empty($salesCountVal->sales_rep_email))
            {
                $value[] = 'Sales Rep Email';
                $keys[] = 'sales_rep_email';
            }
            if(empty($salesCountVal->setter_id))
            {
                $value[] = 'Setter';
                $keys[] = 'setter_id';
            }
            if($salesCountVal->sales_rep_email != null || $salesCountVal->sales_rep_email != '')
            {
                $user = User::where('email',$salesCountVal->sales_rep_email)->first();
                if (empty($user)) {
                    $additional_user_id = UsersAdditionalEmail::where('email',$salesCountVal->sales_rep_email)->value('user_id');
                    if(!empty($additional_user_id)){
                        $user = User::where('id', $additional_user_id)->first();
                    }
                }
                if(empty($user))
                {
                    $value[] = 'Closer '.$salesCountVal->sales_rep_email.' not in users';
                    $keys[] = 'sales_rep_email';
                }
            }
            if(!empty($value)){
                $update = implode(',',$value);
                $data[] = [
                    'type_val' => 'Missing Rep',
                    // 'id' => $salesCountVal->id,
                    // 'pid' => $salesCountVal->pid,
                    // // 'heading' => $salesCountVal->pid.'-'.$salesCountVal->sales_rep_name.' - Data Missing',
                    // // 'sales_rep_name' => $salesCountVal->sales_rep_name,
                    // 'alert_summary' => 'Update '.$update,
                    // 'keys' => $keys,
                    // // 'type' => isset($salesCountVal->type)?$salesCountVal->type:'Missing Info',
                    // // 'severity' => 'High',
                    // // 'status' => ($salesCountVal->onboardProcess==1)?'Resolve':'Pending',
                    // 'updated' => $salesCountVal->updated_at,
                    // 'customer_name' => $salesCountVal->customer_name,
                ];
            }
        }
        return count($data);
    }
    public function count_locationRedline_alert(){
        $sales =  LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')->get();
        $data = [];
        // $sales->transform(function ($salesCountVal) {
        foreach($sales as $salesCountVal){
            $value = [];
            $keys = [];
            $location_data = '';
            $state_data = '';

             $state = State::where('state_code',$salesCountVal->customer_state)->first();
                $location = Locations::with('State', 'Cities','additionalRedline')->where('general_code',$salesCountVal->customer_state)->first();

                $state_data = ['state_id'=>$state->id,'state_name'=>$state->name,'general_code'=>$state->state_code];

                if(empty($location)){

                    if(!empty($state)){
                        $location = Locations::where('state_id',$state->id)->first();
                        if(empty($location)){
                            $state_data = ['state_id'=>$state->id,'state_name'=>$state->name,'general_code'=>$salesCountVal->customer_state];
                        }
                    }else{
                        $state_data = ['state_id'=>'','general_code'=>$salesCountVal->customer_state];
                    }
                }

                if(empty($location)){
                    $value[] = 'location';
                    $keys[] = 'Location';
                }
                if(!empty($location) && empty($location->redline_standard)){
                    $value[] = 'location Redline';
                    $keys[] = 'Location_redline';
                    $location_data = $location;
                    $location_data->redline_data = $location->additionalRedline;
                    $location_data->effective_date = $location->date_effective;
                }
                $date_found = true;
                if(!empty($location->additionalRedline)){
                    foreach($location->additionalRedline as $redlinedata){
                        if(strtotime($redlinedata['effective_date']) < strtotime($salesCountVal->customer_signoff)){
                            $date_found = false;
                        }
                    }
                }

                if(!empty($location->date_effective) && ($date_found)){
                    $value[] = 'Location Redline missing for sale approval -'.$salesCountVal->customer_signoff;
                    $keys[] = 'Location_redline';
                    $location_data = $location;
                    $location_data->redline_data = $location->additionalRedline;
                    $location_data->effective_date = $location->date_effective;
                }

                if(empty($location) || empty($location->redline_standard) ||  ($date_found)){
                    $update = implode(',',$value);

                    $data[] =  [
                        'type_val' => 'location Redline',
                        // 'id' => $salesCountVal->id,
                        // 'pid' => $salesCountVal->pid,
                        // 'alert_summary' => 'Update '.$update,
                        // 'keys' => $keys,
                        // 'updated' => $salesCountVal->updated_at,
                        // 'customer_name' => $salesCountVal->customer_name,
                        // 'location_data' => $location_data,
                        // 'state_name' => isset($state->name)?$state->name:null,
                        // 'state_data' => $state_data
                    ];
                }
        }
        return count($data);
    }
    public function count_repRedline_alert(){
        $data = [];
            $sales = LegacyApiNullData::where('action_status',0)->whereNotNull('data_source_type')
            ->join('users','users.email','=','legacy_api_data_null.sales_rep_email')
            ->where('legacy_api_data_null.action_status',0)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.redline',
                'users.self_gen_redline',
                'users.redline_effective_date',
                'users.self_gen_redline_effective_date',
                'users.position_id',
                'users.self_gen_type',
                'users.sub_position_id',
                'legacy_api_data_null.pid',
                'legacy_api_data_null.updated_at',
                'legacy_api_data_null.customer_name',
                'legacy_api_data_null.customer_signoff'
            )->get();
            foreach($sales as $salesCountVal) {
                $value = [];
                $keys = [];
                $position = '';
                $position_name = ['2'=>'Closer','3'=>'Setter'];

                if($salesCountVal->sub_position_id == '2' || $salesCountVal->position_id == '2' ){
                    if($salesCountVal->redline == null || $salesCountVal->redline_effective_date == null || $salesCountVal->redline_effective_date > $salesCountVal->customer_signoff)
                    {
                        $value[] = 'Redline';
                        $keys[] = 'rep_redline';
                        $position = ($salesCountVal->sub_position_id==2)?$salesCountVal->sub_position_id:$salesCountVal->position_id;
                    }
                }elseif($salesCountVal->self_gen_type != null && $salesCountVal->self_gen_type == '2'){
                    if($salesCountVal->self_gen_redline == null || $salesCountVal->self_gen_redline_effective_date == null || $salesCountVal->self_gen_redline_effective_date > $salesCountVal->customer_signoff)
                    {
                        $value[] = 'Self gen redline';
                        $keys[] = 'self_gen_redline';
                        $position = $salesCountVal->self_gen_type;
                    }
                }
                if(!empty($position)){
                //$update = implode(',',$value);
                    $data[] = [
                        'type_val' => 'Rep Redline',
                        // 'id' => $salesCountVal->id,
                        // 'pid' => $salesCountVal->pid,
                        // 'alert_summary' => 'Update '.$update,
                        // 'keys' => $keys,
                        // 'updated' => $salesCountVal->updated_at,
                        // 'customer_name' => $salesCountVal->customer_name,
                        // 'position_name' => $position_name[$position],
                        // 'rep_name' => $salesCountVal->first_name .' '.$salesCountVal->last_name
                    ];
                }
            }
           return count($data);
    }
    public function count_people_alert($people_keys){
        $count = User::where(function($q) use ($people_keys){
            foreach($people_keys as $key){
                $q->orWhereNull('users.'.$key)->orWhere('users.'.$key,'0')->orWhere('users.'.$key,'');
            }
        })->count();
        return $count;
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

    // public function paginate($items, $perPage = 10, $page = null, $options = [])
    // {
    //     $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    //      $items = $items instanceof Collection ? $items : Collection::make($items);
    //     return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    // }

    public function sales_by_pid(Request $request)
    {
        if ($request->has('pid') && !empty($request->input('pid'))) {
            $pid = $request->pid;
            $value = SalesMaster::with('salesMasterProcess', 'userDetail')->where('pid', '=', $pid)->first();
            $customer_state = isset($value->customer_state) ? $value->customer_state : 0;
            $approvedDate = $value->customer_signoff;
            if (config('app.domain_name') == 'flex') {
                $location_code = isset($value->customer_state) ? $value->customer_state : 0;
            } else {
                $location_code = isset($value->location_code) ? $value->location_code : 0;
            }
            $location = Locations::with('State')->where('general_code', '=', $location_code)->first();

            if ($location) {
                $state_code = $location->state->state_code;
                $locationRedlines = LocationRedlineHistory::where('location_id', $location->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $redline_standard = $locationRedlines->redline_standard;
                } else {
                    $redline_standard = $location->redline_standard;
                }
            } else {
                $state = State::where('state_code', $location_code)->first();
                $state_code =$state?->state_code ?? "";
                $saleStateId = isset($state->id) ? $state->id : 0;
                $location = Locations::where('state_id', $saleStateId)->first();
                $locationId = isset($location->id) ? $location->id : 0;
                $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $redline_standard = $locationRedlines->redline_standard;
                } else {
                    $redline_standard = isset($location->redline_standard) ? $location->redline_standard : 0;
                }
            }
        }

        $data = array();
        if ($value) {
            $clawbackSettle = ClawbackSettlement::where(['pid' => $request->pid, 'type' => 'commission', 'status' => 3, 'is_displayed' => '1'])->first();
            $clawbackSettle1 = ClawbackSettlement::where(['pid' => $request->pid, 'type' => 'commission', 'status' => 1])->first();
            if ($clawbackSettle && empty($clawbackSettle1)) {
                $clawbackStatus = 3;
            } else {
                $clawbackStatus = 1;
            }

            if (isset($value->salesMasterProcess->closer1_id) && $value->salesMasterProcess->closer1_id != null) {
                $closer1Id = $value->salesMasterProcess->closer1_id;
                $closer1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm1')->where('is_displayed', '1')->first();
                $closer1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm2')->where('is_displayed', '1')->first();
                $closer1 = [
                    "closer1_paid_status_m1" => isset($closer1PeriodM1->status) ? $closer1PeriodM1->status : null,
                    "closer1_paid_status_m2" => isset($closer1PeriodM2->status) ? $closer1PeriodM2->status : null
                ];
            }

            if (isset($value->salesMasterProcess->setter1_id) && $value->salesMasterProcess->setter1_id != null) {
                $setter1Id = $value->salesMasterProcess->setter1_id;
                if ($value->salesMasterProcess->closer1_id != $value->salesMasterProcess->setter1_id) {
                    $setter1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm1')->where('is_displayed', '1')->first();
                    $setter1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm2')->where('is_displayed', '1')->first();
                }

                $setter1 = [
                    "setter1_paid_status_m1" => isset($setter1PeriodM1->status) ? $setter1PeriodM1->status : null,
                    "setter1_paid_status_m2" => isset($setter1PeriodM2->status) ? $setter1PeriodM2->status : null
                ];
            }

            if (isset($value->salesMasterProcess->closer2_id) && $value->salesMasterProcess->closer2_id != null) {
                $closer2Id = $value->salesMasterProcess->closer2_id;
                $closer2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm1')->where('is_displayed', '1')->first();
                $closer2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm2')->where('is_displayed', '1')->first();

                $closer2 = [
                    "closer2_paid_status_m1" => isset($closer2PeriodM1->status) ? $closer2PeriodM1->status : null,
                    "closer2_paid_status_m2" => isset($closer2PeriodM2->status) ? $closer2PeriodM2->status : null
                ];
            }

            if (isset($value->salesMasterProcess->setter2_id) && $value->salesMasterProcess->setter2_id != null) {
                $setter2Id = $value->salesMasterProcess->setter2_id;
                $setter2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm1')->where('is_displayed', '1')->first();
                $setter2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm2')->where('is_displayed', '1')->first();
                $setter2 = [
                    "setter2_paid_status_m1" => isset($setter2PeriodM1->status) ? $setter2PeriodM1->status : null,
                    "setter2_paid_status_m2" => isset($setter2PeriodM2->status) ? $setter2PeriodM2->status : null
                ];
            }

            if ($value->salesMasterProcess->closer1_id != null) {
                // $withhold = UserReconciliationWithholding::select('withhold_amount')->where('pid', '=', $pid)->where('closer_id', $value->salesMasterProcess->closer1_id)->first();
                $withhold = UserCommission::where('pid', $pid)->where('user_id', $value->salesMasterProcess->closer1_id)->where(['amount_type'=> 'reconciliation', 'settlement_type'=> 'reconciliation'])->where('is_displayed', '1')->first();
                $closer1_withhold_amount = isset($withhold->amount) ? $withhold->amount : null;
            }
            if ($value->salesMasterProcess->closer2_id != null) {
                // $withhold = UserReconciliationWithholding::select('withhold_amount')->where('pid', '=', $pid)->where('closer_id', $value->salesMasterProcess->closer2_id)->first();
                $withhold = UserCommission::where('pid', $pid)->where('user_id', $value->salesMasterProcess->closer2_id)->where(['amount_type'=> 'reconciliation', 'settlement_type'=> 'reconciliation'])->where('is_displayed', '1')->first();
                $closer2_withhold_amount = isset($withhold->amount) ? $withhold->amount : null;
            }
            if ($value->salesMasterProcess->setter1_id != null) {
                // $withhold = UserReconciliationWithholding::select('withhold_amount')->where('pid', '=', $pid)->where('setter_id', $value->salesMasterProcess->setter1_id)->first();
                $withhold = UserCommission::where('pid', $pid)->where('user_id', $value->salesMasterProcess->setter1_id)->where(['amount_type'=> 'reconciliation', 'settlement_type'=> 'reconciliation'])->where('is_displayed', '1')->first();
                $setter1_withhold_amount = isset($withhold->amount) ? $withhold->amount : null;
            }
            if ($value->salesMasterProcess->setter2_id != null) {
                // $withhold = UserReconciliationWithholding::select('withhold_amount')->where('pid', '=', $pid)->where('setter_id', $value->salesMasterProcess->setter2_id)->first();
                $withhold = UserCommission::where('pid', $pid)->where('user_id', $value->salesMasterProcess->setter2_id)->where(['amount_type'=> 'reconciliation', 'settlement_type'=> 'reconciliation'])->where('is_displayed', '1')->first();
                $setter2_withhold_amount = isset($withhold->amount) ? $withhold->amount : null;
            }
            $approveDate = $value->customer_signoff;
            $redline_amount_type = isset($value->userDetail->redline_amount_type) ? $value->userDetail->redline_amount_type : null;
            $closer1_detail = isset($value->salesMasterProcess->closer1_id) ? $value->salesMasterProcess->closer1Detail : null;
            $closer2_detail = isset($value->salesMasterProcess->closer2_id) ? $value->salesMasterProcess->closer2Detail : null;
            $setter1_detail = isset($value->salesMasterProcess->setter1_id) ? $value->salesMasterProcess->setter1Detail : null;
            $setter2_detail = isset($value->salesMasterProcess->setter2_id) ? $value->salesMasterProcess->setter2Detail : null;
            $milestonelist = [];
            for($i=1; $i<12;$i++){
                $milestone['name'] = 'M'.$i;
                $milestone['value'] = $i.'.00';
                $milestonelist[] = $milestone;
            }
            if($closer1_detail != null){
                $closer1_detail['milestones'] = $milestonelist;
            }
            if($closer2_detail != null){
                $closer1_detail['milestones'] = $milestonelist;
            }
            if($setter1_detail != null){
                $setter1_detail['milestones'] = $milestonelist;
            }
            if($setter1_detail != null){
                $setter2_detail['milestones'] = $milestonelist;
            }
            $closer1_m1 = $closer1_m2 = $setter1_m1 = $setter1_m2 = $closer2_m1 = $closer2_m2 = $setter2_m1 = $setter2_m2 = 0;
            $closer1_m1clawback = $closer1_m2clawback = $setter1_m1clawback = $setter1_m2clawback = $closer2_m1clawback = $closer2_m2clawback = $setter2_m1clawback= $setter2_m2clawback = 0;
            if ($value->salesMasterProcess->closer1_id != null) {
                $closer1_m1 = UserCommission::where(['user_id' => $value->salesMasterProcess->closer1_id, 'pid' => $pid, 'amount_type' => "m1", 'is_displayed' => '1'])->sum('amount');
                $closer1_m2 = UserCommission::where(['user_id' => $value->salesMasterProcess->closer1_id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $closer1_m1clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->closer1_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => 'm1', 'is_displayed' => '1'])->sum('clawback_amount');
                $closer1_m2clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->closer1_id, 'pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('adders_type', ['m2', 'm2 update'])->sum('clawback_amount');
            }
            if ($value->salesMasterProcess->setter1_id != null) {
                $setter1_m1 = UserCommission::where(['user_id' => $value->salesMasterProcess->setter1_id, 'pid' => $pid, 'amount_type' => "m1", 'is_displayed' => '1'])->sum('amount');
                $setter1_m2 = UserCommission::where(['user_id' => $value->salesMasterProcess->setter1_id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $setter1_m1clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->setter1_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => 'm1', 'is_displayed' => '1'])->sum('clawback_amount');
                $setter1_m2clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->setter1_id, 'pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('adders_type', ['m2', 'm2 update'])->sum('clawback_amount');
            }
            if ($value->salesMasterProcess->closer2_id != null) {
                $closer2_m1 = UserCommission::where(['user_id' => $value->salesMasterProcess->closer2_id, 'pid' => $pid, 'amount_type' => "m1", 'is_displayed' => '1'])->sum('amount');
                $closer2_m2 = UserCommission::where(['user_id' => $value->salesMasterProcess->closer2_id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $closer2_m1clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->closer2_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => 'm1', 'is_displayed' => '1'])->sum('clawback_amount');
                $closer2_m2clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->closer2_id, 'pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('adders_type', ['m2', 'm2 update'])->sum('clawback_amount');
            }
            if ($value->salesMasterProcess->setter2_id != null) {
                $setter2_m1 = UserCommission::where(['user_id' => $value->salesMasterProcess->setter2_id, 'pid' => $pid, 'amount_type' => "m1", 'is_displayed' => '1'])->sum('amount');
                $setter2_m2 = UserCommission::where(['user_id' => $value->salesMasterProcess->setter2_id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $setter2_m1clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->setter2_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => 'm1', 'is_displayed' => '1'])->sum('clawback_amount');
                $setter2_m2clawback = ClawbackSettlement::where(['user_id' => $value->salesMasterProcess->setter2_id, 'pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('adders_type', ['m2', 'm2 update'])->sum('clawback_amount');  
            }

            $dealerFeePer = isset($value->dealer_fee_percentage) ? ($value->dealer_fee_percentage) : null;
            if (is_numeric($dealerFeePer) && $dealerFeePer < 1) {
                $dealerFeePer = $dealerFeePer * 100;
            }

            if (config('app.domain_name') == 'flex') {
                $customer_state = isset($value->customer_state) ? $value->customer_state : null;
            } else {
                $customer_state = isset($value->location_code) ? $value->location_code : null;
            }

            $closer1Percentage = 0;
            $closer1Unit = "";
            $commissionHistory = UserCommissionHistory::where('user_id', $value->salesMasterProcess->closer1_id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approveDate)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $closer1Percentage = $commissionHistory->commission;
                $closer1Unit = $commissionHistory->commission_type;
            }

            $closer2Percentage = 0;
            $closer2Unit = "";
            $commissionHistory = UserCommissionHistory::where('user_id', $value->salesMasterProcess->closer2_id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approveDate)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $closer2Percentage = $commissionHistory->commission;
                $closer2Unit = $commissionHistory->commission_type;
            }
            $closer1Id = $value->salesMasterProcess->closer1_id;
            $setter1Id = $value->salesMasterProcess->setter1_id;
            //getproduct and milestone trigger date
            $product = Products::select('id','name','product_id')->where('id',$value->product)->first();
            $data = array(
                'id' => $value->id,
                'job_status' => $value->job_status,
                'pid' => $value->pid,
                'job_status' => $value->job_status,
                'installer' => $value->install_partner,
                'prospect_id' => $value->pid,
                'customer_name' => isset($value->customer_name) ? $value->customer_name : null,
                'customer_address' => $value->customer_address,
                'customer_address_2' => $value->customer_address_2,
                'state_id' => $value->state_id,
                'state_code' => $state_code??'',
                'homeowner_id' => $value->homeowner_id,
                'customer_city' => $value->customer_city,
                'state' => isset($value->customer_state) ? $value->customer_state : null,
                'customer_state' => $customer_state,
                'customer_zip' => $value->customer_zip,
                'customer_email' => $value->customer_email,
                'customer_phone' => $value->customer_phone,
                'proposal_id' => $value->proposal_id,
                'sale_state_redline' => $redline_standard,

                'closer1_detail' => $closer1_detail,
                'closer2_detail' => $closer2_detail,
                'setter1_detail' => $setter1_detail,
                'setter2_detail' => $setter2_detail,
                'closer1_m1' => ($closer1_m1 - $closer1_m1clawback),
                'closer1_m2' => ($closer1_m2 - $closer1_m2clawback),
                'closer2_m1' => ($closer2_m1 - $closer2_m1clawback),
                'closer2_m2' => ($closer2_m2 - $closer2_m2clawback),

                'setter1_m1' => ($closer1Id == $setter1Id) ? 0 : ($setter1_m1 - $setter1_m1clawback),
                'setter1_m2' => ($closer1Id == $setter1Id) ? 0 : ($setter1_m2 - $setter1_m2clawback),
                'setter2_m1' => ($setter2_m1 - $setter2_m1clawback),
                'setter2_m2' => ($setter2_m2 - $setter2_m2clawback),
                'closer1_commission' => isset($value->salesMasterProcess->closer1_commission) ? $value->salesMasterProcess->closer1_commission : null,
                'closer2_commission' => isset($value->salesMasterProcess->closer2_commission) ? $value->salesMasterProcess->closer2_commission : null,
                'setter1_commission' => isset($value->salesMasterProcess->setter1_commission) ? $value->salesMasterProcess->setter1_commission : null,
                'setter2_commission' => isset($value->salesMasterProcess->setter2_commission) ? $value->salesMasterProcess->setter2_commission : null,

                'closer1_reconcilliation' => isset($closer1_withhold_amount) ? $closer1_withhold_amount : null,
                'closer2_reconcilliation' => isset($closer2_withhold_amount) ? $closer2_withhold_amount : null,
                'setter1_reconcilliation' => isset($setter1_withhold_amount) ? $setter1_withhold_amount : null,
                'setter2_reconcilliation' => isset($setter2_withhold_amount) ? $setter2_withhold_amount : null,
                'epc' => isset($value->epc) ? $value->epc : null,
                'net_epc' => isset($value->net_epc) ? $value->net_epc : null,
                'kw' => isset($value->kw) ? $value->kw : null,
                'redline' => isset($value->redline) ? $value->redline : null,
                'redline_amount_type' => $redline_amount_type,
                'date_cancelled' => isset($value->date_cancelled) ? dateToYMD($value->date_cancelled) : null,
                'return_sales_date' => isset($value->return_sales_date) ? dateToYMD($value->return_sales_date) : null,
                'm1_date' =>  isset($value->m1_date) ? dateToYMD($value->m1_date) : null,
                'm2_date' => isset($value->m2_date) ? dateToYMD($value->m2_date) : null,
                'approved_date' => $approveDate,
                'last_date_pd' => $value->last_date_pd,
                'product' => $product->name??'',
                'product_code' => $product->product_id??'',
                'product_id' => $product->id??'',
                'milestone_trigger' => isset($value->milestone_trigger) && $value->milestone_trigger !='' ? json_decode($value->milestone_trigger,true) : null,
                'gross_account_value' => $value->gross_account_value,
                'dealer_fee_percentage' => $dealerFeePer,
                'dealer_fee_amount' => $value->dealer_fee_amount,
                'show' => isset($value->adders) ? (int)$value->adders : null,
                'adders_description' => $value->adders_description,
                'total_amount_for_acct' => $value->total_amount_for_acct,
                'prev_amount_paid' => $value->prev_amount_paid,
                'm1_amount' => $value->m1_amount,
                'm2_amount' => $value->m2_amount,
                'prev_deducted_amount' => $value->prev_deducted_amount,
                'cancel_fee' => $value->cancel_fee,
                'cancel_deduction' => $value->cancel_deduction,
                'adv_pay_back_amount' => $value->adv_pay_back_amount,
                'total_amount_in_period' => $value->total_amount_in_period,
                'data_source_type' => $value->data_source_type,
                'length_of_agreement' => $value->length_of_agreement,
                'service_schedule' => $value->service_schedule,
                'subscription_payment' => $value->subscription_payment,
                'service_completed' => $value->service_completed,
                'last_service_date' => isset($value->last_service_date) ? dateToYMD($value->last_service_date) : null,
                'bill_status' => $value->bill_status,
                'mark_account_status_id' => isset($value->salesMasterProcess->mark_account_status_id) ? $value->salesMasterProcess->mark_account_status_id : null,
                'account_status' => isset($value->salesMasterProcess->status) ? $value->salesMasterProcess->status->account_status : null,
                "closer1_paid_status" => isset($closer1) ? $closer1 : null,
                "setter1_paid_status" => isset($setter1) ? $setter1 : null,
                "closer2_paid_status" => isset($closer2) ? $closer2 : null,
                "setter2_paid_status" => isset($setter2) ? $setter2 : null,
                "clawback_paid_status" => $clawbackStatus,
                "closer_1_commission_percentage" => $closer1Percentage,
                'closer_1_commission_unit' => $closer1Unit,
                "closer_2_commission_percentage" => $closer2Percentage,
                'closer_2_commission_unit' => $closer2Unit,
                'initial_service_cost' => $value->initial_service_cost,
                'auto_pay' => $value->auto_pay,
                'card_on_file' => $value->card_on_file,
                //'total_commission' => $total_commission,
                'created_at' => $value->created_at,
                'updated_at' => $value->updated_at,
                //Add Siraj
                'panel_type' => $value->panel_type,
                'panel_id' => $value->panel_id,
                'customer_longitude' => $value->customer_longitude,
                'customer_latitude' => $value->customer_latitude,
                'custome_fields' => Crmsaleinfo::getcusomefields($value->pid)
            );

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $data['closer1_commission'] = 0;
                $data['closer2_commission'] = 0;
                if ($value->m2_date && $value->salesMasterProcess->closer1_id) {
                    $data['closer1_commission'] = ($closer1_m1 - $closer1_m1clawback) + ($closer1_m2 - $closer1_m2clawback);
                }

                if ($value->m2_date && $value->salesMasterProcess->closer2_id) {
                    $data['closer2_commission'] = ($closer2_m1 - $closer2_m1clawback) + ($closer2_m2 - $closer2_m2clawback);
                }
            }

            return response()->json([
                'ApiName' => 'sales_by_id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'ApiName' => 'sales_by_id',
                'status' => false,
                'message' => 'data not found'
            ]);
        }
    }

    public function customer_payment_by_pid(Request $request){
        $validator = Validator::make($request->all(), [
            'pid' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'customer_payment_by_pid',
                'status' => false,
                'error' => $validator->errors()
            ], 400);
        }

        // Fetch the latest record by pid
        $customerPayment = CustomerPayment::where('pid', $request->pid)
        ->orderBy('id', 'DESC')
        ->first();

        $customerPaymentJson = $customerPayment->customer_payment_json;
        $customerPayment->customer_payment_json = json_decode($customerPaymentJson);

        if ($customerPayment) {
            return response()->json([
                'ApiName' => 'customer_payment_by_pid',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $customerPayment,
            ], 200);
        }
        else{
            // Handle the case where no record is found
            return response()->json([
                'ApiName' => 'customer_payment_by_pid',
                'status' => true,
                'message' => 'No record found for the provided PID.',
                'data' => [],
            ], 400);
        }
        return $data;
    }

    public function salesAccountSummary_old(Request $request)
    {
        $Validator = Validator::make($request->all(),
        [
            'pid' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['error'=>$Validator->errors()], 400);
        }
        $data = [];
        $data1 = [];
        $saleMasterProcess = SaleMasterProcess::where('pid',$request->pid)->first();
        $clawback = '';
        if ($saleMasterProcess->mark_account_status_id==1) {
            $clawback = ' | Clawed Back';
        }
        $commission = UserCommission::with('userdata')->where('pid',$request->pid)->get();
        $paidCommission = 0;
        $unPaidCommission = 0;
        $totalCommission = 0;
        $adjustmentTotal = 0;
        $adjustmentPaid = 0;
        $adjustmentPending = 0;
        $totalPaidCommission=0;
        $totalUnPaidCommission=0;
        $data['total_commissions'] =[];
        $data['total_adjustment'] = [];
        foreach($commission as $commission)
        {
          $payRollHistory = PayrollHistory::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();

          if($payRollHistory)
          {

            $totalPaidCommission += isset($commission->amount)?$commission->amount:0;
            $paidCommission = isset($commission->amount)?$commission->amount:0;
          }else
          {
            $unPaidCommission = isset($commission->amount)?$commission->amount:0;
            $totalUnPaidCommission += isset($commission->amount)?$commission->amount:0;
          }
            $data['total_commissions'][]=
                [
                    //data Done ............
                    'date' => isset($commission->date)?$commission->date:null,
                    'user_id' => isset($commission->userdata->id)?$commission->userdata->id:null,
                    'employee' => isset($commission->userdata->first_name)?($commission->userdata->first_name.' '.$commission->userdata->last_name):null,
                    'type' => $commission->amount_type .' Payment'. $clawback,
                    'paid' => isset($paidCommission)?$paidCommission:0,
                    'unpaid' => isset($unPaidCommission)?$unPaidCommission:0,
                    'status' => $commission->status,
                    'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                ];


                $payRoll = Payroll::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();
                if($payRoll)
                {
                    $adjustment = PayrollAdjustment::with('detail','userDetail')->where('payroll_id',$payRoll->id)->first();

                    if($adjustment)
                    {
                        $adjustmentTotal +=isset($adjustment->commission_amount)?$adjustment->commission_amount:0;

                        $adjustmentPaid += isset($adjustment->commission_amount)?$adjustment->commission_amount:0;
                        $adjustmentPending += 0;
                        $data['total_adjustment'][]=
                                [
                                    'date'=> isset($commission->date)?$commission->date:null,
                                    'employee_id'=> isset($commission->userdata->id)?$commission->userdata->id:null,
                                    'employee'=> isset($commission->userdata->first_name)?($commission->userdata->first_name.' '.$commission->userdata->last_name):null,
                                    'type'=> isset($adjustment->commission_type)?$adjustment->commission_type:'',
                                    'paid'=> isset($adjustment->commission_amount)?$adjustment->commission_amount:'',
                                    'unpaid'=> 0,
                                    'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                                ];
                    }else{
                        $data['total_adjustment']=[];

                            //         $data['total_adjustment']=[
                            //             'date'=> '',
                            //             'employee_id'=> '',
                            //             'employee'=> '',
                            //             'type'=> '',
                            //             'paid'=> '',
                            //             'unpaid'=> '',
                            //             'date_paid' => '',
                            // ];
                    }
                }

                $paidCommission=0;
                $unPaidCommission=0;
        }


        $totalCommission = $totalPaidCommission+$totalUnPaidCommission;
        $data['commission_paid_total'] = $totalPaidCommission;
        $data['commission_unpaid_total'] = $totalUnPaidCommission;
        $data['commission_total'] = $totalCommission;

        $data['adjustment_paid_total'] = $adjustmentPaid;
        $data['adjustment_unpaid_total'] = $adjustmentPending;
        $data['adjustment_total'] = isset($adjustmentTotal)?$adjustmentTotal:0;

        $data['grand_total_commission'] = $totalCommission+$adjustmentTotal;

        // overrides,,,,,,,,,,,,,,,,,

          $overRideDatas = UserOverrides::with('userInfo','user')->where('pid',$request->pid)->get();
          $overridPaid = 0 ;
          $overridPending = 0 ;
          $overridTotal = 0;
          $paidOver = 0;
          $unPaidOver = 0;

          $adjustmentTotalOver = 0;
          $adjustmentPaidOver = 0;
          $adjustmentPendingOver = 0;
          $data1['total_overrides'] = [];
          $data1['total_adjustment_override'] = [];
          foreach($overRideDatas as $overRideData)
            {
                $payRollHistory = PayrollHistory::where('user_id',$overRideData->user_id)->where('pay_period_from',$overRideData->pay_period_from)->where('pay_period_to',$overRideData->pay_period_to)->first();

                if($payRollHistory)
                {
                    $overridPaid += isset($overRideData->amount)?$overRideData->amount:0;
                    $paidOver = isset($overRideData->amount)?$overRideData->amount:0;

                }else{
                    $overridPending += isset($overRideData->amount)?$overRideData->amount:0;
                    $unPaidOver = isset($overRideData->amount)?$overRideData->amount:0;
                }

                   $overridTotal += isset($overRideData->amount)?$overRideData->amount:0;

                // if($payRollHistory)
                // {
                //     $paidOver = isset($overRideData->amount)?$overRideData->amount:0;
                // }
                // else
                // {
                //     $unPaidOver = isset($overRideData->amount)?$overRideData->amount:0;
                // }
                $recipiant = isset($overRideData->user->first_name)?($overRideData->user->first_name.' '.$overRideData->user->last_name):null;
                 $newdate = date("Y-m-d", strtotime($overRideData->created_at));
                $data1['total_overrides'][]=
                    [
                        'override_over'=> isset($overRideData->userInfo->first_name)?($overRideData->userInfo->first_name.' '.$overRideData->userInfo->last_name):'',
                        'date'=> isset($overRideData->created_at)?$newdate:null,
                        'recipient'=>isset($overRideData->user->first_name)?($overRideData->user->first_name.' '.$overRideData->user->last_name):'',
                        'description'=> isset($overRideData->type)? $recipiant .'|'. $overRideData->type . $clawback:'',
                        'value'=> isset($overRideData->overrides_amount)?'$'.$overRideData->overrides_amount.'per kw':'',
                        'settlement'=> isset($overRideData->overrides_settlement_type)?$overRideData->overrides_settlement_type:'',
                        'PaidAmount'=> $paidOver,
                        'UnPaidAmount'=> $unPaidOver,
                        'date_paid'=> isset($overRideData->pay_period_from)?$overRideData->pay_period_from.' to '.$overRideData->pay_period_to:null
                    ];

                    $payRoll = Payroll::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();
                    if($payRoll)
                    {
                        $adjustmentOver = PayrollAdjustment::with('detail','userDetail')->where('payroll_id',$payRoll->id)->first();
                        if($adjustmentOver)
                        {
                            $adjustmentTotalOver +=isset($adjustmentOver->commission_amount)?$adjustmentOver->commission_amount:0;
                            $adjustmentPaidOver += isset($adjustmentOver->commission_amount)?$adjustmentOver->commission_amount:0;
                            $adjustmentPendingOver += 0;
                            $data1['total_adjustment_override'][]=
                                        [
                                            'date'=> isset($commission->date)?$commission->date:null,
                                            'employee_id'=> isset($commission->userdata->id)?$commission->userdata->id:null,
                                            'employee'=> isset($commission->userdata->first_name)?($commission->userdata->first_name.' '.$commission->userdata->last_name):null,
                                            'type'=> isset($adjustmentOver->commission_type)?$adjustmentOver->commission_type:'',
                                            'paid'=> isset($adjustmentPaidOver)?$adjustmentPaidOver:0,
                                            'unpaid'=> $adjustmentPendingOver,
                                            'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                                        ];

                        }
                        else{

                            $data1['total_adjustment_override']=  [];
                            // $data1['total_adjustment_override'][]=
                            //             [
                            //                 'date'=> '',
                            //                 'employee_id'=> '',
                            //                 'employee'=> '',
                            //                 'type'=> '',
                            //                 'paid'=> '',
                            //                 'unpaid'=> '',
                            //                 'date_paid' => '',
                            //             ];

                        }
                    }
                    $unPaidOver=0;
                    $paidOver =0;
            }

            $data1['total_overrides_amount']=$overridTotal;
            $data1['total_overrides_amount_paid']= $overridPaid;
            $data1['total_overrides_amount_pending'] = $overridPending;

            $data1['total_adjustment_amount']=$adjustmentTotalOver;
            $data1['total_adjustment_amount_paid']= $adjustmentPaidOver;
            $data1['total_adjustment_amount_pending'] = $adjustmentPendingOver;
            $data1['grand_total_override'] = $overridTotal+$adjustmentTotalOver;

         return response()->json([
            'ApiName' => 'sales_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'override' => $data1,

        ], 200);
    }

    public function salesAccountSummary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pid' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = [];
        $data1 = [];

        $paidCommission = 0;
        $unPaidCommission = 0;
        $totalCommission = 0;
        $adjustmentPaid = 0;
        $adjustmentPending = 0;
        $totalPaidCommission = 0;
        $totalUnPaidCommission = 0;
        $totalPaidAdjustment = 0;
        $totalUnPaidAdjustment = 0;
        $data['total_commissions'] = [];
        $data['total_adjustment'] = [];
        $totalPaidReconCommission = 0;
        $totalUnPaidReconCommission = 0;

        $companyProfile = CompanyProfile::first();
        // $commissions = UserCommission::with('userdata')->where('pid', $request->pid)->where("status", "!=", 6)->get();
        $commissions = UserCommission::with('userdata')->where('pid', $request->pid)->where("status", "!=", 6)->where('amount_type', '!=', 'reconciliation')->get();
        foreach ($commissions as $commission) {
            $recon = ($commission->status == 6) ? ' | Move To Recon' : '';
            $nextPayroll = ($commission->status == 4) ? ' | Move To Next Payroll' : '';

            $paidCommission = 0;
            $unPaidCommission = 0;
            if ($commission->status == 3) {
                $paidCommission = isset($commission->amount) ? $commission->amount : 0;
                $totalPaidCommission += $paidCommission;
            } else if ($commission->status == 6) {
                $paidCommission = ReconCommissionHistory::where("user_id", $commission->user_id)
                    ->where("pid", $commission->pid)
                    ->where("is_ineligible", "0")
                    ->where("move_from_payroll", 1)
                    ->whereIn("status", ["payroll", "clawback"])
                    ->where("type", $commission->amount_type)
                    ->sum("paid_amount");
                $unPaidCommission = $commission->amount - $paidCommission;
            } else {
                $unPaidCommission = isset($commission->amount) ? $commission->amount : 0;
                $totalUnPaidCommission += $unPaidCommission;
            }

            $type = '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($commission->amount_type == 'm1') {
                    $type = 'Upfront';
                } else if ($commission->amount_type == 'm2') {
                    $type = 'Commission';
                } else if ($commission->amount_type == 'm2 update') {
                    $type = 'Commission Update';
                }
            } else {
                $type = $commission->amount_type . ' Payment' . $recon . $nextPayroll;
            }

            $data['total_commissions'][] = [
                'date' => isset($commission->date) ? $commission->date : null,
                'user_id' => isset($commission->userdata->id) ? $commission->userdata->id : null,
                'employee' => isset($commission->userdata->first_name) ? ($commission->userdata->first_name . ' ' . $commission->userdata->last_name) : null,
                'position_id' => isset($commission->userdata->position_id) ? $commission->userdata->position_id : null,
                'sub_position_id' => isset($commission->userdata->sub_position_id) ? $commission->userdata->sub_position_id : null,
                'is_super_admin' => isset($commission->userdata->is_super_admin) ? $commission->userdata->is_super_admin : null,
                'is_manager' => isset($commission->userdata->is_manager) ? $commission->userdata->is_manager : null,
                'type' => $type,
                'paid' => isset($paidCommission) ? $paidCommission : 0,
                'unpaid' => isset($unPaidCommission) ? $unPaidCommission : 0,
                'status' => $commission->status,
                'stop_payroll' => ($commission->status != 3 && @$commission->userdata->stop_payroll) ? 'Payroll Stop' : null,
                'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : null
            ];
        }

        /* move to recon commission show based on the condition */
        $moveToReconCommission = UserCommission::with('userdata')->where('pid', $request->pid)->where("status", 6)->where("is_move_to_recon", 1)->get();
        foreach ($moveToReconCommission as $value) {
            $checkCommission = ReconCommissionHistory::where("pid", $value->pid)->where("move_from_payroll", 1)->where("is_ineligible", "0")->exists();
            $commissionType = ["m1", "m2", "m2 update"];
            $reconStatus = ($value->is_move_to_recon == 1) ? ' | Move To Recon' : '';
            $reconType = in_array($value->amount_type, $commissionType) ? $value->amount_type : '';
            $finalType = $reconType . $reconStatus;

            if(!$checkCommission){
                $data['total_commissions'][] = [
                    'date' => isset($commission->date) ? $commission->date : null,
                    'user_id' => $value->user->id,
                    'employee' => $value->user->first_name . " " . $value->user->last_name,
                    'position_id' => $value->user->position_id,
                    'sub_position_id' => $value->user->sub_position_id,
                    'is_super_admin' => $value->user->is_super_admin,
                    'is_manager' => $value->user->is_manager,
                    'type' => $finalType,
                    'paid' => 0,
                    'unpaid' => 0,
                    'status' => $value->status,
                    'stop_payroll' => ($value->user->stop_payroll == 1) ? 'Payroll Stop' : null,
                    'date_paid' => ""
                ];
            }
        }

        /* recon cmmission data */
        $reconCommission = ReconCommissionHistory::with("salesDetail")->where("is_ineligible", "0")->where("pid", $request->pid)->whereIn("status", ["payroll", "finalize", "clawback"])->get();
        foreach ($reconCommission as $value) {
            $unPaidAmount = 0;
            $paidAmount = 0;

            if(($value->status == "payroll" || $value->status == "clawback") && $value->payroll_execute_status == "3"){
                $paidAmount = $value->paid_amount;
            }else if($value->status == "finalize" || $value->status == "payroll" || $value->status == "clawback"){
                $unPaidAmount = $value->paid_amount;
            }
            
            $commissionType = ["m1", "m2", "m2 update"];
            $reconStatus = ($value->move_from_payroll == 1) ? ' | Move To Recon' : '';
            $reconType = in_array($value->type, $commissionType) ? $value->type. " | " : '';
            $finalType = $reconType . "Reconciliation" . $reconStatus;

            $data['total_commissions'][] = [
                'date' => isset($commission->date) ? $commission->date : null,
                'user_id' => $value->user->id,
                'employee' => $value->user->first_name . " " . $value->user->last_name,
                'position_id' => $value->user->position_id,
                'sub_position_id' => $value->user->sub_position_id,
                'is_super_admin' => $value->user->is_super_admin,
                'is_manager' => $value->user->is_manager,
                'type' => $finalType,
                'paid' => $paidAmount,
                'unpaid' => $unPaidAmount,
                'status' => $value->status,
                'stop_payroll' => ($value->user->stop_payroll == 1) ? 'Payroll Stop' : null,
                'date_paid' => ""
            ];
        }


        $adjustments = PayrollAdjustmentDetail::with('userDetail')->where(['pid' => $request->pid, 'payroll_type' => 'commission'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaid = isset($adjustment->amount) ? $adjustment->amount : 0;
                $totalPaidAdjustment += isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPending = isset($adjustment->amount) ? $adjustment->amount : 0;
                $totalUnPaidAdjustment += isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $type = '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($adjustment->type == 'm1') {
                    $type = 'Upfront';
                } else if ($adjustment->type == 'm2') {
                    $type = 'Commission';
                } else if ($adjustment->type == 'm2 update') {
                    $type = 'Commission Update';
                }
            } else {
                $type = isset($adjustment->type) ? $adjustment->type : '';
            }

            $adjustmentPending += 0;
            $data['total_adjustment'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : null,
                'employee_id' => isset($adjustment->userDetail->id) ? $adjustment->userDetail->id : null,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : null,
                'position_id' => isset($adjustment->userDetail->position_id) ? $adjustment->userDetail->position_id : null,
                'sub_position_id' => isset($adjustment->userDetail->sub_position_id) ? $adjustment->userDetail->sub_position_id : null,
                'is_super_admin' => isset($adjustment->userDetail->is_super_admin) ? $adjustment->userDetail->is_super_admin : null,
                'is_manager' => isset($adjustment->userDetail->is_manager) ? $adjustment->userDetail->is_manager : null,
                'type' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : '',
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : '',
                'status' => $adjustment->status,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : null,
                'stop_payroll' => (isset($commission->userdata->stop_payroll) && $commission->userdata->stop_payroll == 1) ? 'Payroll Stop' : null
            ];
        }

        $clawbacks = ClawbackSettlement::with('users', 'salesDetail')->where(['pid' => $request->pid])
            ->whereIn("type", ["recon-commission", "commission"])->whereIn('clawback_type', ['next payroll', 'm2 update'])->where("status", "!=", 6)->get();
        $totalPaidClawback = 0;
        $totalUnPaidClawback = 0;
        foreach ($clawbacks as $clawback) {
            $recon = '';
            $paidClawback = 0;
            $unPaidClawback = 0;
            if ($clawback->status == 3) {
                $paidClawback = isset($clawback->clawback_amount) ? $clawback->clawback_amount : 0;
                $totalPaidClawback += isset($clawback->clawback_amount) ? $clawback->clawback_amount : 0;
            }else {
                $unPaidClawback = isset($clawback->clawback_amount) ? $clawback->clawback_amount : 0;
                $totalUnPaidClawback += isset($clawback->clawback_amount) ? $clawback->clawback_amount : 0;
            }

            $returnSalesDate = isset($clawback->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawback->salesDetail->return_sales_date)) : null;
            $newdate = isset($clawback->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawback->salesDetail->date_cancelled)) : $returnSalesDate;
            $type = 'Payment | ClawedBack';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($clawback->adders_type == 'm2 update') {
                    $type = 'Payment | Commission Update | Clawed Back';
                }
            } else {
                if ($clawback->adders_type == 'm2 update') {
                    $type = 'Payment | M2 Update | Clawed Back';
                }
            }
            $type = $type . $recon;
            if($clawback->users->positionpayfrequencies->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                if($paidClawback != 0){
                    $datePaid = isset($clawback->pay_period_to) ? date('m/d/Y',strtotime($clawback->pay_period_to)) : null;
                } else{
                    $datePaid = "Pending Daily Pay";
                }
            } else {
                $datePaid = isset($clawback->pay_period_from) ? Carbon::parse($clawback->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($clawback->pay_period_from)->format('m/d/Y') : null;
            }

            $data['total_commissions'][] = [
                'date' => isset($newdate) ? $newdate : null,
                'user_id' => isset($clawback->user_id) ? $clawback->user_id : null,
                'employee' => isset($clawback->users->first_name) ? ($clawback->users->first_name . ' ' . $clawback->users->last_name) : null,
                'position_id' => isset($clawback->users->position_id) ? $clawback->users->position_id : null,
                'sub_position_id' => isset($clawback->users->sub_position_id) ? $clawback->users->sub_position_id : null,
                'is_super_admin' => isset($clawback->users->is_super_admin) ? $clawback->users->is_super_admin : null,
                'is_manager' => isset($clawback->users->is_manager) ? $clawback->users->is_manager : null,
                'type' => $type,
                'paid' => isset($paidClawback) ? (0 - $paidClawback) : 0,
                'unpaid' => isset($unPaidClawback) ? (0 - $unPaidClawback) : 0,
                'status' => $clawback->status,
                'stop_payroll' => ($clawback->status != 3 && @$clawback->users->stop_payroll) ? 'Payroll Stop' : null,
                'date_paid' => $datePaid ?? null,
                'stop_payroll' => (isset($clawback->users->stop_payroll) && $clawback->users->stop_payroll == 1) ? 'Payroll Stop' : null
            ];
        }

        /* move to recon commission show based on the condition */
        $moveToReconClawback = ClawbackSettlement::where("pid", $request->pid)->whereIn("type", ["recon-commission", "commission"])->whereIn('clawback_type', ['next payroll', 'm2 update'])->where("status", 6)->get();
        foreach ($moveToReconClawback as $value) {
            $clwabackReconHiistoryCheck = ReconClawbackHistory::where("pid", $value->pid)->whereIn("type", ["recon-commission", "commission"])->where("move_from_payroll", 1)->whereIn("status", ["payroll", "finalize"])->exists();
            if(!$clwabackReconHiistoryCheck){
                $paidAmount = 0;
                $unPaidAmount = 0;
                $type = ($value->type == "recon-commission" ? "Reconciliation": "") ." | Move To Recon | Clawback";
                
                $data['total_commissions'][] = [
                    'date' => $value->updated_at->format("m/d/Y"),
                    'user_id' => $value->user_id,
                    'employee' => $value->user->first_name ." ". $value->user->last_name,
                    'position_id' => $value->user->position_id,
                    'sub_position_id' => $value->user->sub_position_id,
                    'is_super_admin' => $value->user->is_super_admin,
                    'is_manager' => $value->user->is_manager,
                    'type' => $type,
                    'paid' => -1 * $paidAmount,
                    'unpaid' => -1 * $unPaidAmount,
                    'status' => $value->status,
                    'stop_payroll' => $value->user->is_stop_payroll ? 'Payroll Stop' : null,
                    'date_paid' => null,
                ];
            }
        }

        /* recon clawback calculation */
        $reconClawback = ReconClawbackHistory::where("pid", $request->pid)->whereIn("type", ["recon-commission", "commission"])->whereIn("status", ["payroll", "finalize"])->get();
        
        foreach ($reconClawback as $value) {
            $paidAmount = 0;
            $unPaidAmount = 0;
            $type = "Reconciliation | Clawback";
            if(($value->status == "payroll" || $value->status == "clawback") && $value->payroll_execute_status == 3){
                $paidAmount = $value->paid_amount;
            }elseif($value->status == "payroll" || $value->status == "finalize"){
                $unPaidAmount = $value->paid_amount;
            }
            $data['total_commissions'][] = [
                'date' => $value->updated_at->format("m/d/Y"),
                'user_id' => $value->user_id,
                'employee' => $value->user->first_name ." ". $value->user->last_name,
                'position_id' => $value->user->position_id,
                'sub_position_id' => $value->user->sub_position_id,
                'is_super_admin' => $value->user->is_super_admin,
                'is_manager' => $value->user->is_manager,
                'type' => $type,
                'paid' => -1 * $paidAmount,
                'unpaid' => -1 * $unPaidAmount,
                'status' => $value->status,
                'stop_payroll' => $value->user->is_stop_payroll ? 'Payroll Stop' : null,
                'date_paid' => null,
            ];
        }

        $clawBackAdjustments = PayrollAdjustmentDetail::with('userDetail')->where(['pid' => $request->pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->get();
        foreach ($clawBackAdjustments as $clawBackAdjustment) {
            $adjustmentPaid = 0;
            $adjustmentPending = 0;
            if ($clawBackAdjustment->status == 3) {
                $adjustmentPaid = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
                $totalPaidAdjustment += isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            } else {
                $adjustmentPending = isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
                $totalUnPaidAdjustment += isset($clawBackAdjustment->amount) ? $clawBackAdjustment->amount : 0;
            }

            $type = '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($clawBackAdjustment->type == 'm1') {
                    $type = 'Upfront';
                } else if ($clawBackAdjustment->type == 'm2') {
                    $type = 'Commission';
                } else if ($clawBackAdjustment->type == 'm2 update') {
                    $type = 'Commission Update';
                }
            } else {
                $type = $clawBackAdjustment->type ?? null;
            }

            $data['total_adjustment'][] = [
                'date' => isset($clawBackAdjustment->updated_at) ? date('Y-m-d', strtotime($clawBackAdjustment->updated_at)) : null,
                'employee_id' => isset($clawBackAdjustment->userDetail->id) ? $clawBackAdjustment->userDetail->id : null,
                'employee' => isset($clawBackAdjustment->userDetail->first_name) ? ($clawBackAdjustment->userDetail->first_name . ' ' . $clawBackAdjustment->userDetail->last_name) : null,
                'position_id' => isset($clawBackAdjustment->userDetail->position_id) ? $clawBackAdjustment->userDetail->position_id : null,
                'sub_position_id' => isset($clawBackAdjustment->userDetail->sub_position_id) ? $clawBackAdjustment->userDetail->sub_position_id : null,
                'is_super_admin' => isset($clawBackAdjustment->userDetail->is_super_admin) ? $clawBackAdjustment->userDetail->is_super_admin : null,
                'is_manager' => isset($clawBackAdjustment->userDetail->is_manager) ? $clawBackAdjustment->userDetail->is_manager : null,
                'type' => $type,
                'paid' => isset($adjustmentPaid) ? $adjustmentPaid : '',
                'unpaid' => isset($adjustmentPending) ? $adjustmentPending : '',
                'status' => $clawBackAdjustment->status,
                'date_paid' => isset($clawBackAdjustment->pay_period_from) ? $clawBackAdjustment->pay_period_from . ' to ' . $clawBackAdjustment->pay_period_to : null,
                'stop_payroll' => (isset($clawback->users->stop_payroll) && $clawback->users->stop_payroll == 1) ? 'Payroll Stop' : null
            ];
        }

        $reconAdjustment = ReconAdjustment::where([
            "pid" => $request->pid,
        ])->whereIn("adjustment_type", ["clawback", "commission"])
        ->whereIn("adjustment_override_type", ["m1", "m2", "m2 update", "recon-commission"])
        ->whereIn("payroll_status", ["payroll", "finalize"])->get();

        $totalPaidReconAdjustment = 0;
        $totalUnPaidReconAdjustment = 0;
        $saleReconUserIds = SaleMasterProcess::select("closer1_id", "closer2_id", "setter1_id", "setter2_id")->where("pid", $request->pid)->first()->toArray();
        $saleReconUserIds = array_filter($saleReconUserIds, function ($value) {
            return !is_null($value);
        });

        /* recon adjustment data */
        foreach ($reconAdjustment as $value) {
            $type = '';
            $paidAdjustment = 0;
            $unpaidAdjustment = 0;

            if($value->payroll_execute_status == 3 && $value->payroll_status == "payroll"){
                $paidAdjustment = $value->adjustment_amount;
            }elseif($value->payroll_status == "payroll" || $value->payroll_status == "finalize"){
                $unpaidAdjustment = $value->adjustment_amount;
            }
            if (in_array($value->user_id,  $saleReconUserIds)) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($value->type == 'm1') {
                        $type = 'Upfront';
                    } else if ($value->type == 'm2') {
                        $type = 'Commission';
                    } else if ($value->type == 'm2 update') {
                        $type = 'Commission Update';
                    }
                } else {
                    $type = $value->adjustment_type ?? null;
                }
                $adjustmentPending += 0;
                $totalPaidReconAdjustment += floatval($value->adjustment_amount);
                $type = $value->adjustment_type == "commission" ? " | Commission" : " | Clawback";
                $description = ($value->adjustment_override_type == "recon-commission" ? "Reconciliation" :$value->adjustment_override_type)  . $type;
                $data['total_adjustment'][] = [
                    'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : "-",
                    'employee_id' => isset($value->user->id) ? $value->user->id : null,
                    'employee' => isset($value->user->first_name) ? ($value->user->first_name . ' ' . $value->user->last_name) : null,
                    'position_id' => isset($value->user->position_id) ? $value->user->position_id : null,
                    'sub_position_id' => isset($value->user->sub_position_id) ? $value->user->sub_position_id : null,
                    'is_super_admin' => isset($value->user->is_super_admin) ? $value->user->is_super_admin : null,
                    'is_manager' => isset($value->user->is_manager) ? $value->user->is_manager : null,
                    'type' => $description,
                    'paid' => $paidAdjustment,
                    'unpaid' => $unpaidAdjustment,
                    'status' => $value->status ?? "-",
                    "date_paid" => null,
                ];
            }
        }

        $totalCommission = array_reduce($data["total_commissions"], function ($carry, $item) {
            $carry['paid'] += $item['paid'];
            $carry['unpaid'] += $item['unpaid'];
            return $carry;
        }, ['paid' => 0, 'unpaid' => 0]);
        $data['commission_paid_total'] = $totalCommission["paid"];
        $data['commission_unpaid_total'] = $totalCommission["unpaid"];
        $data['commission_total'] = array_sum($totalCommission);

        /* sub total calcluation for adjustment */
        $totalAdjustment = array_reduce($data["total_adjustment"], function ($carry, $item) {
            $carry['paid'] += $item['paid'];
            $carry['unpaid'] += $item['unpaid'];
            return $carry;
        }, ['paid' => 0, 'unpaid' => 0]);

        $data['adjustment_paid_total'] =  $totalAdjustment["paid"];
        $data['adjustment_unpaid_total'] = $totalAdjustment["unpaid"];
        $data['adjustment_total'] = array_sum($totalAdjustment);
        $data['grand_total_commission'] = array_sum($totalCommission) + $totalUnPaidAdjustment + $totalPaidAdjustment;

        // overrides,,,,,,,,,,,,,,,,,
        $overRideDatas = UserOverrides::with('userInfo', 'user')->where('pid', $request->pid)->where("status", "!=", 6)->where("overrides_settlement_type", "during_m2")->get();
        $overridPaid = 0;
        $overridPending = 0;
        $overridTotal = 0;
        $paidOver = 0;
        $unPaidOver = 0;

        $adjustmentPaidOver = 0;
        $adjustmentPendingOver = 0;
        $adjustmentPaidOverTotal = 0;
        $adjustmentUnPaidOverTotal = 0;
        $data1['total_overrides'] = [];
        $data1['total_adjustment_override'] = [];
        $overideType = [];
        foreach ($overRideDatas as $overRideData) {
            $overideType[] = $overRideData->type;
            $recon = ($overRideData->status == 6 && $overRideData->is_move_to_recon == 1) ? ' | Move To Recon' : '';
            $nextPayroll = ($overRideData->status == 4) ? ' | Move To Next Payroll' : '';

            $unPaidOver = 0;
            $paidOver = 0;
            if ($overRideData->status == 3) {
                $paidOver = isset($overRideData->amount) ? $overRideData->amount : 0;
                $overridPaid += $paidOver;
            }else {
                $unPaidOver = isset($overRideData->amount) ? $overRideData->amount : 0;
                $overridPending += $unPaidOver;
            }

            $recipiant = isset($overRideData->user->first_name) ? ($overRideData->user->first_name . ' ' . $overRideData->user->last_name) : null;

            $commM2date = UserCommission::where(['pid' => $request->pid, 'amount_type' => 'm2'])->first();
            if (!empty($commM2date)) {
                $newdate = date("Y-m-d", strtotime($commM2date->date));
            } else {
                $newdate = null;
            }

            $m2Update = '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Update = $overRideData->during == 'm2 update' ? ' | Commission Update' : '';
            } else {
                $m2Update = $overRideData->during == 'm2 update' ? ' | M2 Update' : '';
            }
            $data1['total_overrides'][] = [
                'override_over' => isset($overRideData->userInfo->first_name) ? ($overRideData->userInfo->first_name . ' ' . $overRideData->userInfo->last_name) : '',
                'date' => isset($overRideData->updated_at) ? $newdate : null,
                'recipient' => isset($overRideData->user->first_name) ? ($overRideData->user->first_name . ' ' . $overRideData->user->last_name) : '',
                'position_id' => isset($overRideData->user->position_id) ? $overRideData->user->position_id : null,
                'sub_position_id' => isset($overRideData->user->sub_position_id) ? $overRideData->user->sub_position_id : null,
                'is_super_admin' => isset($overRideData->user->is_super_admin) ? $overRideData->user->is_super_admin : null,
                'is_manager' => isset($overRideData->user->is_manager) ? $overRideData->user->is_manager : null,
                'description' => isset($overRideData->type) ? ($recipiant . ' | ' . $overRideData->type . $m2Update . $recon ) : '',
                'value' => isset($overRideData->overrides_amount) ? '$' . $overRideData->overrides_amount . 'per kw' : '',
                'settlement' => isset($overRideData->overrides_settlement_type) ? $overRideData->overrides_settlement_type : 'Reconciliation',
                'PaidAmount' => $paidOver,
                'UnPaidAmount' => $unPaidOver,
                'stop_payroll' => ($overRideData->status != 3 && @$overRideData->user->stop_payroll) ? 'Payroll Stop' : null,
                'date_paid' => isset($overRideData->pay_period_from) ? Carbon::parse($overRideData->pay_period_from)->format('m/d/Y') . ' to ' . Carbon::parse($overRideData->pay_period_to)->format('m/d/Y') : null,
                'stop_payroll' => (isset($overRideData->user->stop_payroll) && $overRideData->user->stop_payroll == 1) ? 'Payroll Stop' : null
            ];
        }

        /* move to recon overide datta */
        $moveToReconOverRideData = UserOverrides::with('userInfo', 'user')->where('pid', $request->pid)->where("status", 6)->where("is_move_to_recon", 1)->where("overrides_settlement_type", "during_m2")->get();
        foreach ($moveToReconOverRideData as $value) {
            $overideType[] = $value->type;
            $checkMoveToReconOverride = ReconOverrideHistory::where("pid", $value->pid)
                ->where("is_ineligible", "0")
                ->where("type", $value->type)
                ->where("overrider", $value->sale_user_id)
                ->where("user_id", $value->user_id)
                ->where("move_from_payroll", 1)
                ->whereIn("status", ["payroll", "finalize", "clawback"])
                ->exists();
            if(!$checkMoveToReconOverride){
                $unPaidOver = 0;
                $paidOver = 0;
    
                $recipiant = $value->user->first_name . ' ' . $value->user->last_name . " | ";
                $type = $value->type ? $value->type. " | " : "";
                $settlementType = $value->is_move_to_recon == 1 ? "Move To Recon" : "";
                $description = $recipiant . $type . $settlementType;
    
                $data1['total_overrides'][] = [
                    'override_over' => $value->userInfo->first_name . ' ' . $value->userInfo->last_name,
                    'date' => $value->updated_at->format("m/d/Y"),
                    'recipient' => $value->user->first_name . ' ' . $value->user->last_name,
                    'position_id' => $value->user->position_id,
                    'sub_position_id' => $value->user->sub_position_id,
                    'is_super_admin' => $value->user->is_super_admin,
                    'is_manager' => $value->user->is_manager,
                    'description' => $description,
                    'value' => '$' . $value->overrides_amount . 'per kw',
                    'settlement' => 'Reconciliation | Move To Recon',
                    'PaidAmount' => $paidOver,
                    'UnPaidAmount' => $unPaidOver,
                    'stop_payroll' => ($value->user->status != 3 && $value->user->stop_payroll) ? 'Payroll Stop' : "",
                    'date_paid' => null,
                ];
            }
        }

        /* recon override paid data */
        $reconOverrideData = ReconOverrideHistory::with("userData")->where("pid", $request->pid)
            ->where("is_ineligible", "0")
            ->whereIn("status", ["payroll", "finalize", "clawback"])
            ->get();
        foreach ($reconOverrideData as $value) {
            $overideType[] = $value->type;
            $recipiant = $value->userData->first_name . ' ' . $value->userData->last_name . " | ";
            $type = $value->type ? $value->type. " | " : "";
            $settlementType = $value->move_from_payroll == 1 ? "Move To Recon" : "Reconciliation";
            $description = $recipiant . $type . $settlementType;

            $unPaidOver = 0;
            $paidOver = 0;

            if(($value->reconOverrideHistoryData->status == "payroll" || $value->reconOverrideHistoryData->status == "clawback") && $value->reconOverrideHistoryData->payroll_execute_status == "3"){
                $paidOver = $value->paid;
            }elseif($value->reconOverrideHistoryData->status == "finalize" || $value->reconOverrideHistoryData->status == "payroll" || $value->reconOverrideHistoryData->status == "clawback"){
                $unPaidOver = $value->paid;
            }

            $data1['total_overrides'][] = [
                'override_over' => $value->overrideOverData->first_name . ' ' . $value->overrideOverData->last_name,
                'date' => $value->updated_at->format("m/d/Y"),
                'recipient' => $value->overrideOverData->first_name . ' ' . $value->overrideOverData->last_name,
                'position_id' => $value->userData->position_id,
                'sub_position_id' => $value->userData->sub_position_id,
                'is_super_admin' => $value->userData->is_super_admin,
                'is_manager' => $value->userData->is_manager,
                'description' => $description,
                'value' => '$' . $value->overrides_amount . 'per kw',
                'settlement' => 'Reconciliation',
                'PaidAmount' => $paidOver,
                'UnPaidAmount' => $unPaidOver,
                'stop_payroll' => ($value->userData->stop_payroll == 1) ? 'Payroll Stop' : "",
                'date_paid' => null,
            ];
        }

        $adjustments = PayrollAdjustmentDetail::with('userDetail')->where(['pid' => $request->pid, 'payroll_type' => 'overrides'])->where('type', '!=', 'clawback')->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentUnPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            }

            $data1['total_adjustment_override'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : null,
                'employee_id' => isset($adjustment->userDetail->id) ? $adjustment->userDetail->id : null,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : null,
                'position_id' => isset($adjustment->userDetail->position_id) ? $adjustment->userDetail->position_id : null,
                'sub_position_id' => isset($adjustment->userDetail->sub_position_id) ? $adjustment->userDetail->sub_position_id : null,
                'is_super_admin' => isset($adjustment->userDetail->is_super_admin) ? $adjustment->userDetail->is_super_admin : null,
                'is_manager' => isset($adjustment->userDetail->is_manager) ? $adjustment->userDetail->is_manager : null,
                'type' => isset($adjustment->type) ? $adjustment->type : '',
                'paid' => isset($adjustmentPaidOver) ? $adjustmentPaidOver : 0,
                'unpaid' => isset($adjustmentPendingOver) ? $adjustmentPendingOver : 0,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : null
            ];
        }

        /* recon override adjustments */
        $reconAdjustment = ReconAdjustment::where([
            "pid" => $request->pid,
        ])->whereIn("adjustment_type", ["clawback", "override"])
        ->whereIn("adjustment_override_type", ["Office", "Direct", "Stack", "recon-override", "Indirect", "Manual"])
        ->whereIn("payroll_status", ["payroll", "finalize"])->get();

        foreach ($reconAdjustment as $value) {
            $paidAdjustment = 0;
            $unpaidAdjustment = 0;

            if ($value->payroll_status == "payroll" && $value->payroll_execute_status == 3) {
                $paidAdjustment = $value->adjustment_amount;
            } elseif($value->payroll_status == "payroll" || $value->payroll_status == "finalize") {
                $unpaidAdjustment = $value->adjustment_amount;
            }

            $type = $value->adjustment_type == "override" ? " | Override" : " | Clawback";
            $overiderName = isset($value->saleUserInfo) ? $value->saleUserInfo->first_name ." ".$value->saleUserInfo->last_name . " | " : "";
            $description = $overiderName . $value->adjustment_override_type . $type;
            $data1['total_adjustment_override'][] = [
                "date" => $value->updated_at->format("Y-m-d"),
                "employee_id" => $value->user->id,
                "employee" => $value->user->first_name . " " . $value->user->last_name,
                "position_id" => $value->user->position_id,
                "sub_position_id" => $value->user->sub_position_id,
                "is_super_admin" => $value->user->is_super_admin,
                "is_manager" => $value->user->is_manager,
                "type" => $description,
                "paid" => $paidAdjustment,
                "unpaid" => $unpaidAdjustment,
                "date_paid" => null,
                "settlement" => "Reconciliation-Adjustment",
                "stop_payroll" => $value->user->stop_payroll
            ];
        }

        $clawbackover = ClawbackSettlement::with('userInfo', 'users', 'salesDetail')->where(['pid' => $request->pid])
            ->whereIn("type", ["overrides", "recon-override"])->whereIn('clawback_type', ['next payroll', 'm2 update'])->where("status", "!=", 6)->get();
        $totalPaidOverClawback = 0;
        $totalUnPaidOverClawback = 0;
        $clawdback = ' | Clawed Back';
        foreach ($clawbackover as $clawbacko) {
            $paidOverClawback = 0;
            $unPaidOverClawback = 0;
            $recon = '';

            if ($clawbacko->status == 3) {
                $paidOverClawback = isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
                $totalPaidOverClawback += isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
            } else if ($clawbacko->status == 6) {
                $paidOverClawback = ReconClawbackHistory::where("pid", $clawbacko->pid)
                    ->where("user_id", $clawbacko->user_id)
                    ->where("sale_user_id", $clawbacko->sale_user_id)
                    ->where("move_from_payroll", '1')
                    ->whereIn("status", ["payroll", "clawback"])
                    ->where("adders_type", $clawbacko->adders_type)
                    ->where("type", $clawbacko->type)
                    ->where("during", $clawbacko->during)
                    ->sum("paid_amount");
                $totalUnPaidOverClawback = $clawbacko->clawback_amount - $paidOverClawback;
                $recon = ' | Move to recon';
            } else {
                $unPaidOverClawback = isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
                $totalUnPaidOverClawback += isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
            }

            /* recon paid amount */
            if ($clawbacko->clawback_type == "reconciliation") {
                $paidOverClawback = ReconClawbackHistory::where("pid", $clawbacko->pid)
                    ->where("user_id", $clawbacko->user_id)
                    ->where("sale_user_id", $clawbacko->sale_user_id)
                    ->where("move_from_payroll", '0')
                    ->whereIn("status", ["payroll", "clawback"])
                    ->where("adders_type", $clawbacko->adders_type)
                    ->where("type", $clawbacko->type)
                    ->where("during", $clawbacko->during)
                    ->sum("paid_amount");
                $unPaidOverClawback = $clawbacko->clawback_amount != 0 ? $clawbacko->clawback_amount - $paidOverClawback : 0;
                $recon = ' | Reconciliation';
            }

            $recipiant = isset($clawbacko->users->first_name) ? ($clawbacko->users->first_name . ' ' . $clawbacko->users->last_name) : '';
            $returnSalesDate = isset($clawbacko->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawbacko->salesDetail->return_sales_date)) : null;
            $newdate = isset($clawbacko->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawbacko->salesDetail->date_cancelled)) : $returnSalesDate;

            $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . $clawdback) : '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($clawbacko->during == 'm2 update') {
                    $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . ' | Commission Update' . $clawdback) : '';
                }
            } else {
                if ($clawbacko->during == 'm2 update') {
                    $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . ' | M2 Update' . $clawdback) : '';
                }
            }
            $description = $description . $recon;
            $data1['total_overrides'][] = [
                'override_over' => isset($clawbacko->userInfo->first_name) ? ($clawbacko->userInfo->first_name . ' ' . $clawbacko->userInfo->last_name) : '',
                'date' => isset($newdate) ? $newdate : null,
                'recipient' => isset($clawbacko->users->first_name) ? ($clawbacko->users->first_name . ' ' . $clawbacko->users->last_name) : null,
                'description' => $description,
                'value' => '',
                'settlement' => $clawbacko->clawback_type == "reconciliation" ? "Reconciliation" : "",
                'PaidAmount' => (0 - $paidOverClawback),
                'UnPaidAmount' => (0 - $unPaidOverClawback),
                'stop_payroll' => ($clawbacko->status != 3 && @$clawbacko->users->stop_payroll) ? 'Payroll Stop' : null,
                'date_paid' => isset($clawbacko->pay_period_from) ? $clawbacko->pay_period_from . ' to ' . $clawbacko->pay_period_to : null
            ];
        }

        /* move to recon clawback */
        $clawbackover = ClawbackSettlement::with('userInfo', 'users', 'salesDetail')->where(['pid' => $request->pid])
            ->whereIn("type", ["overrides", "recon-override"])->whereIn('clawback_type', ['next payroll', 'm2 update'])->where("status", 6)->where("is_move_to_recon", 1)->get();
        $totalPaidOverClawback = 0;
        $totalUnPaidOverClawback = 0;
        $clawdback = ' | Clawed Back';
        foreach ($clawbackover as $clawbacko) {
            $paidOverClawback = 0;
            $unPaidOverClawback = 0;
            $recon = '';

            if ($clawbacko->status == 3) {
                $paidOverClawback = isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
                $totalPaidOverClawback += isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
            } else if ($clawbacko->status == 6) {
                $paidOverClawback = ReconClawbackHistory::where("pid", $clawbacko->pid)
                    ->where("user_id", $clawbacko->user_id)
                    ->where("sale_user_id", $clawbacko->sale_user_id)
                    ->where("move_from_payroll", '1')
                    ->whereIn("status", ["payroll", "clawback"])
                    ->where("adders_type", $clawbacko->adders_type)
                    ->where("type", $clawbacko->type)
                    ->where("during", $clawbacko->during)
                    ->sum("paid_amount");
                $totalUnPaidOverClawback = $clawbacko->clawback_amount - $paidOverClawback;
                $recon = ' | Move to recon';
            } else {
                $unPaidOverClawback = isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
                $totalUnPaidOverClawback += isset($clawbacko->clawback_amount) ? $clawbacko->clawback_amount : 0;
            }

            /* recon paid amount */
            if ($clawbacko->clawback_type == "reconciliation") {
                $paidOverClawback = ReconClawbackHistory::where("pid", $clawbacko->pid)
                    ->where("user_id", $clawbacko->user_id)
                    ->where("sale_user_id", $clawbacko->sale_user_id)
                    ->where("move_from_payroll", '0')
                    ->whereIn("status", ["payroll", "clawback"])
                    ->where("adders_type", $clawbacko->adders_type)
                    ->where("type", $clawbacko->type)
                    ->where("during", $clawbacko->during)
                    ->sum("paid_amount");
                $unPaidOverClawback = $clawbacko->clawback_amount != 0 ? $clawbacko->clawback_amount - $paidOverClawback : 0;
                $recon = ' | Reconciliation';
            }

            $recipiant = isset($clawbacko->users->first_name) ? ($clawbacko->users->first_name . ' ' . $clawbacko->users->last_name) : '';
            $returnSalesDate = isset($clawbacko->salesDetail->return_sales_date) ? date("Y-m-d", strtotime($clawbacko->salesDetail->return_sales_date)) : null;
            $newdate = isset($clawbacko->salesDetail->date_cancelled) ? date("Y-m-d", strtotime($clawbacko->salesDetail->date_cancelled)) : $returnSalesDate;

            $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . $clawdback) : '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($clawbacko->during == 'm2 update') {
                    $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . ' | Commission Update' . $clawdback) : '';
                }
            } else {
                if ($clawbacko->during == 'm2 update') {
                    $description = isset($clawbacko->adders_type) ? ($recipiant . ' | ' . $clawbacko->adders_type . ' | M2 Update' . $clawdback) : '';
                }
            }
            $description = $description . $recon;
            $data1['total_overrides'][] = [
                'override_over' => isset($clawbacko->userInfo->first_name) ? ($clawbacko->userInfo->first_name . ' ' . $clawbacko->userInfo->last_name) : '',
                'date' => isset($newdate) ? $newdate : null,
                'recipient' => isset($clawbacko->users->first_name) ? ($clawbacko->users->first_name . ' ' . $clawbacko->users->last_name) : null,
                'description' => $description,
                'value' => '',
                'settlement' => $clawbacko->clawback_type == "reconciliation" ? "Reconciliation" : "",
                'PaidAmount' => (0 - $paidOverClawback),
                'UnPaidAmount' => (0 - $unPaidOverClawback),
                'stop_payroll' => ($clawbacko->status != 3 && @$clawbacko->users->stop_payroll) ? 'Payroll Stop' : null,
                'date_paid' => isset($clawbacko->pay_period_from) ? $clawbacko->pay_period_from . ' to ' . $clawbacko->pay_period_to : null
            ];
        }

        /* recon clawback calculation */
        $reconClawback = ReconClawbackHistory::where("pid", $request->pid)->whereIn("type", ["recon-override", "overrides"])->whereIn("status", ["payroll", "finalize"])->get();
        foreach ($reconClawback as $value) {
            $paidAmount = 0;
            $unPaidAmount = 0;
            if(($value->status == "payroll" || $value->status == "clawback") && $value->payroll_execute_status == 3){
                $paidAmount = $value->paid_amount;
            }elseif($value->status == "payroll" || $value->status == "finalize" || $value->status == "clawback"){
                $unPaidAmount = $value->paid_amount;
            }
            $reconStatus = $value->move_from_payroll == 1 ? " | Move To Recon" : "";
            $overrideName = ucfirst($value->user->first_name." ".$value->user->last_name);
            $type = $value->adders_type ? $value->adders_type." | " : "";
            $description = $overrideName . " | ". $type . "Reconciliation" . $reconStatus;
            $data1['total_overrides'][] = [
                'override_over' => isset($value->overrideOver->first_name) ? ucfirst($value->overrideOver->first_name . ' ' . $value->overrideOver->last_name) : '',
                'date' => isset($newdate) ? $newdate : null,
                'recipient' => $value->user->first_name . " " . $value->user->last_name,
                'description' => $description,
                'value' => '',
                'settlement' => "Reconciliation",
                'PaidAmount' => -1 * $paidAmount,
                'UnPaidAmount' => -1 * $unPaidAmount,
                'stop_payroll' => $value->user->stop_payroll == 1 ? "Stop Payroll" : "",
                'date_paid' => null
            ];
        }

        $adjustments = PayrollAdjustmentDetail::with('userDetail')->where(['pid' => $request->pid, 'payroll_type' => 'overrides', 'type' => 'clawback'])->get();
        foreach ($adjustments as $adjustment) {
            $adjustmentPaidOver = 0;
            $adjustmentPendingOver = 0;
            if ($adjustment->status == 3) {
                $adjustmentPaidOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            } else {
                $adjustmentPendingOver = isset($adjustment->amount) ? $adjustment->amount : 0;
                $adjustmentUnPaidOverTotal += isset($adjustment->amount) ? $adjustment->amount : 0;
            }
            $data1['total_adjustment_override'][] = [
                'date' => isset($adjustment->updated_at) ? date('Y-m-d', strtotime($adjustment->updated_at)) : null,
                'employee_id' => isset($adjustment->userDetail->id) ? $adjustment->userDetail->id : null,
                'employee' => isset($adjustment->userDetail->first_name) ? ($adjustment->userDetail->first_name . ' ' . $adjustment->userDetail->last_name) : null,
                'position_id' => isset($adjustment->userDetail->position_id) ? $adjustment->userDetail->position_id : null,
                'sub_position_id' => isset($adjustment->userDetail->sub_position_id) ? $adjustment->userDetail->sub_position_id : null,
                'is_super_admin' => isset($adjustment->userDetail->is_super_admin) ? $adjustment->userDetail->is_super_admin : null,
                'is_manager' => isset($adjustment->userDetail->is_manager) ? $adjustment->userDetail->is_manager : null,
                'type' => isset($adjustment->type) ? $adjustment->type : '',
                'paid' => isset($adjustmentPaidOver) ? $adjustmentPaidOver : 0,
                'unpaid' => isset($adjustmentPendingOver) ? $adjustmentPendingOver : 0,
                'date_paid' => isset($adjustment->pay_period_from) ? $adjustment->pay_period_from . ' to ' . $adjustment->pay_period_to : null
            ];
        }

        $total = array_reduce($data1["total_overrides"], function ($carry, $item) {
            $carry['PaidAmount'] += $item['PaidAmount'];
            $carry['UnPaidAmount'] += $item['UnPaidAmount'];
            return $carry;
        }, ['PaidAmount' => 0, 'UnPaidAmount' => 0]);
        $data1['total_overrides_amount'] = array_sum($total);
        $data1['total_overrides_amount_paid'] = $total["PaidAmount"];
        $data1['total_overrides_amount_pending'] = $total["UnPaidAmount"];

        $totalAdjustment = array_reduce($data1["total_adjustment_override"], function ($carry, $item) {
            $carry['paid'] += $item['paid'];
            $carry['unpaid'] += $item['unpaid'];
            return $carry;
        }, ['paid' => 0, 'unpaid' => 0]);

        $data1['total_adjustment_amount'] = array_sum($totalAdjustment);
        $data1['total_adjustment_amount_paid'] = $totalAdjustment["paid"];
        $data1['total_adjustment_amount_pending'] =  $totalAdjustment["unpaid"];
        $data1['grand_total_override'] = $overridTotal + $adjustmentPaidOverTotal + $adjustmentUnPaidOverTotal;

        return response()->json([
            'ApiName' => 'sales_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'override' => $data1
        ]);
    }

    public function salesAccountSummaryByPosition(Request $request)
    {
        $Validator = Validator::make($request->all(),
            [
                'pid' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = [];
        $data1 = [];

        $paidCommissionTotal = 0;
        $unPaidCommissionTotal = 0;
        $totalCommission = 0;
        $totalCommissions = 0;
        $paidAdjustment = 0;
        $unPaidAdjustment = 0;
        $commissionUserIds = [];
        $paidAdjustmentTotal = 0;
        $unPaidAdjustmentTotal = 0;

        // $salesUserCommission = UserCommission::where('pid', $request->pid)->where("status", "!=", 6)->distinct()->pluck('user_id');
        $salesUserCommission = UserCommission::where('pid', $request->pid)->where("status", "!=", 6)->where('amount_type', '!=', 'reconciliation')->distinct()->pluck('user_id');

        if ($salesUserCommission != '[]') {
            $s = 1;
            $c = 1;
            foreach ($salesUserCommission as $salesUsers) {
                // $commissions = UserCommission::with('userdata', 'payrollAdjustment')->where('pid', $request->pid)->where("status", "!=", 6)->where('user_id', $salesUsers)->get();
                $commissions = UserCommission::with('userdata', 'payrollAdjustment')->where('pid', $request->pid)->where("status", "!=", 6)->where('amount_type', '!=', 'reconciliation')->where('user_id', $salesUsers)->get();

                foreach ($commissions as $key => $commission) {
                    $paidCommission = 0;
                    $unPaidCommission = 0;

                    $totalCommission += isset($commission->amount) ? $commission->amount : 0;
                    if ($commission->status == 3) {
                        $paidCommissionTotal += isset($commission->amount) ? $commission->amount : 0;
                        $paidCommission = isset($commission->amount) ? $commission->amount : 0;

                    } else {
                        $unPaidCommissionTotal += isset($commission->amount) ? $commission->amount : 0;
                        $unPaidCommission = isset($commission->amount) ? $commission->amount : 0;

                    }

                    $position = $commission->userdata->position_id;
                    if ($position == 3) {
                        $positions = 'setter' . $s;
                    } else {
                        $positions = 'closer' . $c;
                    }
                    $data[$positions]['info'] = [
                        'image' => $commission->userdata->image,
                        'name' => $commission->userdata->first_name . $commission->userdata->last_name,
                        'position_id' => isset($commission->userdata->position_id) ? $commission->userdata->position_id : null,
                        'sub_position_id' => isset($commission->userdata->sub_position_id) ? $commission->userdata->sub_position_id : null,
                        'is_super_admin' => isset($commission->userdata->is_super_admin) ? $commission->userdata->is_super_admin : null,
                        'is_manager' => isset($commission->userdata->is_manager) ? $commission->userdata->is_manager : null,
                        'stop_payroll' => (isset($commission->userdata->stop_payroll) && $commission->userdata->stop_payroll==1) ? 'Payroll Stop':null,
                    ];

                    $data[$positions]['data'][] =
                        [
                        //data Done ............
                        'type' => 'Commission',
                        'date' => isset($commission->date) ? $commission->date : null,
                        'description' => $commission->amount_type . ' Payment',
                        'paid_amount' => isset($paidCommission) ? $paidCommission : 0,
                        'unpaid_amount' => isset($unPaidCommission) ? $unPaidCommission : 0,
                        'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : null,
                    ];

                    $payRoll = Payroll::where('user_id', $commission->user_id)->where('pay_period_from', $commission->pay_period_from)->where('pay_period_to', $commission->pay_period_to)->first();
                    $payRollHistory = PayrollHistory::where('user_id', $commission->user_id)->where('pay_period_from', $commission->pay_period_from)->where('pay_period_to', $commission->pay_period_to)->first();
                    if ($payRoll) {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id', $payRoll->id)->where('pid', $request->pid)->where('payroll_type', 'commission')->get();
                    } elseif ($payRollHistory) {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id', $payRollHistory->payroll_id)->where('pid', $request->pid)->where('payroll_type', 'commission')->get();
                    }
                    // if($payRoll){
                    // $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id',$payRoll->id)->where('pid',$request->pid)->where('payroll_type','commission')->get();
                    if ($payrollAdjustmentDetail) {
                        if ($commission->status == 3) {
                            $paidAdjustmentTotal = $payrollAdjustmentDetail->sum('amount');
                        } else {
                            $unPaidAdjustmentTotal = $payrollAdjustmentDetail->sum('amount');
                        }

                        $data[$positions]['paid_total'] = $paidCommissionTotal + $paidAdjustmentTotal;
                        $data[$positions]['unpaid_total'] = $unPaidCommissionTotal + $unPaidAdjustmentTotal;
                        $data[$positions]['sub_total'] = $paidCommissionTotal + $unPaidCommissionTotal + $paidAdjustmentTotal + $unPaidAdjustmentTotal;

                        if (!in_array($commission->user_id, $commissionUserIds)) {
                            $commissionUserIds[] = $commission->user_id;

                            foreach ($payrollAdjustmentDetail as $payrollAdjustment) {
                                // $totalCommission +=isset($payrollAdjustment->amount)? $payrollAdjustment->amount:0;

                                if ($commission->status == 3) {
                                    $paidAdjustment = isset($payrollAdjustment->amount) ? $payrollAdjustment->amount : 0;
                                } else {
                                    $unPaidAdjustment = isset($payrollAdjustment->amount) ? $payrollAdjustment->amount : 0;
                                }

                                $data[$positions]['data'][] =
                                    [
                                    //data Done ............
                                    'type' => 'Adjustment',
                                    'date' => isset($payrollAdjustment->updated_at) ? date("Y-m-d", strtotime($payrollAdjustment->updated_at)) : null,
                                    'description' => $payrollAdjustment->type,
                                    'paid_amount' => isset($paidAdjustment) ? $paidAdjustment : 0,
                                    'unpaid_amount' => isset($unPaidAdjustment) ? $unPaidAdjustment : 0,
                                    'date_paid' => isset($commission->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : null,
                                ];
                            }
                            // $totalCommissions += $totalCommission;
                        }
                    }
                }

                /* recon commission code */
                $reconCommission = ReconCommissionHistory::where("pid", $request->pid)->whereIn("status", ["payroll", "finalize", "clawback"])->where("user_id", $salesUsers)->get();
                foreach ($reconCommission as $key => $value) {
                    $paidReconCommission = 0;
                    $unPaidReconCommission = 0;
    
                    if(($value->reconCommissionHistory->status == "payroll" || $value->reconCommissionHistory->status == "clawback") && $value->reconCommissionHistory->payroll_execute_status == "3"){
                        $paidReconCommission = $value->paid_amount;
                    }elseif($value->reconCommissionHistory->status == "finalize" || $value->reconCommissionHistory->status == "payroll"  || $value->reconCommissionHistory->status == "clawback" ){
                        $unPaidReconCommission = $value->paid_amount;
                    }
    
                    $position = $value->user->position_id;
                    if ($position == 3) {
                        $positions = 'setter' . $s;
                    } else {
                        $positions = 'closer' . $c;
                    };
    
                    $data[$positions]['info'] = [
                        'name' => $value->user->first_name . $value->user->last_name,
                        'image' => $value->user->image,
                        'position_id' => isset($value->user->position_id) ? $value->user->position_id : null,
                        'sub_position_id' => isset($value->user->sub_position_id) ? $value->user->sub_position_id : null,
                        'is_super_admin' => isset($value->user->is_super_admin) ? $value->user->is_super_admin : null,
                        'is_manager' => isset($value->user->is_manager) ? $value->user->is_manager : null,
                        'stop_payroll' => (isset($value->user->stop_payroll) && $value->user->stop_payroll==1) ? 'Payroll Stop':null,
                    ];
    
    
                    $commissionType = ["m1", "m2", "m2 update"];
                    $reconStatus = ($value->move_from_payroll == 1) ? ' | Move To Recon' : '';
                    $reconType = in_array($value->type, $commissionType) ? ucfirst($value->type). " | " : '';
                    $description = $reconType . "Reconciliation" . $reconStatus;
    
                    $data[$positions]['data'][] =[
                        'type' => 'Reconciliation',
                        'date' => $value->updated_at->format("m/d/Y"),
                        'description' => $description,
                        'paid_amount' => $paidReconCommission,
                        'unpaid_amount' => $unPaidReconCommission ,
                        'date_paid' => null,
                    ];
                }

                /* clawback amount calucation */
                $clawdbacks = ClawbackSettlement::with('users', 'salesDetail')->where('user_id', $salesUsers)->where(['pid' => $request->pid])->where("clawback_type", "next payroll")->where("status", "!=", 6)->whereIn("type", ["commission", "recon-commission"])->get();
                $paidClawbackCommissionTotal = 0;
                $unPaidClawbackCommissionTotal = 0;

                if (count($clawdbacks) > 0) {
                    foreach ($clawdbacks as $key => $clawdback) {
                        $paidClawbackCommission = 0;
                        $unPaidClawbackCommission = 0;
                        if ($clawdback->status == 3) {
                            $paidClawbackCommissionTotal += isset($clawdback->clawback_amount) ? $clawdback->clawback_amount : 0;
                            $paidClawbackCommission = isset($clawdback->clawback_amount) ? $clawdback->clawback_amount : 0;
                        } else {
                            $unPaidClawbackCommissionTotal += isset($clawdback->clawback_amount) ? $clawdback->clawback_amount : 0;
                            $unPaidClawbackCommission = isset($clawdback->clawback_amount) ? $clawdback->clawback_amount : 0;
                        }

                        $position = $clawdback->users->position_id;

                        if ($position == 3) {
                            $positions = 'setter1';
                        } else {
                            $positions = 'closer1';
                        }

                        $newdate = date("Y-m-d", strtotime($clawdback->salesDetail->date_cancelled));
                        $data[$positions]['data'][] =
                            [
                            'type' => 'Clawback',
                            'date' => isset($newdate) ? $newdate : null,
                            'description' => ' Payment | CLAWED BACK',
                            'paid_amount' => isset($paidClawbackCommission) ? (0 - $paidClawbackCommission) : 0,
                            'unpaid_amount' => isset($unPaidClawbackCommission) ? (0 - $unPaidClawbackCommission) : 0,
                            'date_paid' => isset($clawdback->pay_period_from) ? $clawdback->pay_period_from . ' to ' . $clawdback->pay_period_to : null,
                        ];

                        $totalCommission = ($totalCommission - $clawdback->clawback_amount);

                        $data[$positions]['paid_total'] = ($paidCommissionTotal - $paidClawbackCommissionTotal);
                        $data[$positions]['unpaid_total'] = ($unPaidCommissionTotal - $unPaidClawbackCommissionTotal);
                        $data[$positions]['sub_total'] = ($paidCommissionTotal + $unPaidCommissionTotal - $paidClawbackCommissionTotal - $unPaidClawbackCommissionTotal);

                    }
                }

                /* recon clawback calculation */
                $reconClawbackData = ReconClawbackHistory::where("pid", $request->pid)->where('user_id', $salesUsers)->whereIn("type", ["recon-commission", "commission"])->whereIn("status", ["payroll", "finalize"])->get();

                foreach ($reconClawbackData as $value) {
                    $paidAmount = 0;
                    $unPaidAmount = 0;
                    if($value->status == "payroll" && $value->payroll_execute_status == 3){
                        $paidAmount = $value->paid_amount;
                    }elseif ($value->status == "payroll" || $value->status == "finalize") {
                        $unPaidAmount = $value->paid_amount;
                    }

                    $position = $value->user->position_id;
                    $type = $value->type ? ucfirst($value->type) . " | " : "";
                    $reconStatus = $value->move_from_payroll == 1 ? " | Move To Recon" : "";
                    $description = $type . "Reconciliation" . $reconStatus;

                    if ($position == 3) {
                        $positions = 'setter1';
                    } else {
                        $positions = 'closer1';
                    }

                    $data[$positions]['data'][] =
                        [
                        'type' => 'Clawback',
                        'date' => $value->updated_at->format("m/d/Y"),
                        'description' => $description,
                        'paid_amount' => -1 * $paidAmount,
                        'unpaid_amount' => -1 * $unPaidAmount,
                        'date_paid' => null,
                    ];
                }

                $paidClawbackCommissionTotal = 0;
                $unPaidClawbackCommissionTotal = 0;
                $paidCommissionTotal = 0;
                $unPaidCommissionTotal = 0;
                $totalCommission = 0;

                /* Recon-adjustment calculation */
                $reconAdjustment = ReconAdjustment::where([
                    "pid" => $request->pid,
                ])->whereIn("payroll_status", ["payroll", "finalize"])
                ->where("adjustment_type", "commission");
                if($position == 3){
                    $reconAdjustment->where("user_id", $commission->user_id);
                }else{
                    $reconAdjustment->where("user_id", $commission->user_id);
                }
                $totalReconAdjustmentPaid = 0;
                $totalReconAdjustmentUnPaid = 0;
                $totalReconAdjustmentAmount = 0;
                
                foreach ($reconAdjustment->get() as $value) {
                    $unpaidAmount = 0;
                    $paidAmount = 0;

                    if($value->payroll_status == "payroll" && $value->payroll_execute_status == 3){
                        $paidAmount = $value->adjustment_amount;
                    }elseif($value->payroll_status == "payroll" || $value->payroll_status == "finalize" ){
                        $unpaidAmount = $value->adjustment_amount;
                    }
                    
                    $data[$positions]['data'][] =
                    [
                        'type' => "Reconciliation-Adjustment",
                        'date' => $value->updated_at,
                        'description' =>  "Reconciliation | Adjustment",
                        'paid_amount' => $paidAmount,
                        'unpaid_amount' => $unpaidAmount,
                        'date_paid' => "",
                    ];
                    $totalReconAdjustmentPaid += $paidAmount;
                    $totalReconAdjustmentUnPaid += $unpaidAmount;
                    $totalReconAdjustmentAmount += $paidAmount + $unpaidAmount;
                }
                $data[$positions]['paid_total'] +=  $totalReconAdjustmentPaid;
                $data[$positions]['unpaid_total'] +=  $totalReconAdjustmentUnPaid;
                $data[$positions]['sub_total'] += $totalReconAdjustmentAmount;
                
                /* total paid and unpaid amount calculation code */
                $totalCloser1 = array_reduce($data[$positions]["data"], function ($carry, $item) {
                    $carry['paid_amount'] += $item['paid_amount'];
                    $carry['unpaid_amount'] += $item['unpaid_amount'];
                    return $carry;
                }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
                $data[$positions]["paid_total"] = $totalCloser1["paid_amount"];
                $data[$positions]["unpaid_total"] = $totalCloser1["unpaid_amount"];
                $data[$positions]["sub_total"] = array_sum($totalCloser1);

                if ($position == 3) {
                    $s++;
                } else {
                    $c++;
                }
            }
            
            /* total amount calculation */
            if(isset($data["closer1"]["data"])){
                $totalCloser1 = array_reduce($data["closer1"]["data"], function ($carry, $item) {
                    $carry['paid_amount'] += $item['paid_amount'];
                    $carry['unpaid_amount'] += $item['unpaid_amount'];
                    return $carry;
                }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
                $data["closer1"]["paid_total"] = $totalCloser1["paid_amount"];
                $data["closer1"]["unpaid_total"] = $totalCloser1["unpaid_amount"];
                $data["closer1"]["sub_total"] = array_sum($totalCloser1);
                $totalCommissions += array_sum($totalCloser1);
            }
            if(isset($data["closer2"]["data"])){
                $totalCloser2 = array_reduce($data["closer2"]["data"], function ($carry, $item) {
                    $carry['paid_amount'] += $item['paid_amount'];
                    $carry['unpaid_amount'] += $item['unpaid_amount'];
                    return $carry;
                }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
                $data["closer2"]["paid_total"] = $totalCloser2["paid_amount"];
                $data["closer2"]["unpaid_total"] = $totalCloser2["unpaid_amount"];
                $data["closer2"]["sub_total"] = array_sum($totalCloser2);
                $totalCommissions += array_sum($totalCloser2);
            }
    
            if(isset($data["setter1"]["data"])){
                $totalSetter1 = array_reduce($data["setter1"]["data"], function ($carry, $item) {
                    $carry['paid_amount'] += $item['paid_amount'];
                    $carry['unpaid_amount'] += $item['unpaid_amount'];
                    return $carry;
                }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
                $data["setter1"]["paid_total"] = $totalSetter1["paid_amount"];
                $data["setter1"]["unpaid_total"] = $totalSetter1["unpaid_amount"];
                $data["setter1"]["sub_total"] = array_sum($totalSetter1);
                $totalCommissions += array_sum($totalSetter1);
            }
            if(isset($data["setter2"]["data"])){
                $totalSetter2 = array_reduce($data["setter2"]["data"], function ($carry, $item) {
                    $carry['paid_amount'] += $item['paid_amount'];
                    $carry['unpaid_amount'] += $item['unpaid_amount'];
                    return $carry;
                }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
                $data["setter2"]["paid_total"] = $totalSetter2["paid_amount"];
                $data["setter2"]["unpaid_total"] = $totalSetter2["unpaid_amount"];
                $data["setter2"]["sub_total"] = array_sum($totalSetter2);
                $totalCommissions += array_sum($totalSetter2);
            }
        }

        // overrides ....................
        $salesUserOverride = UserOverrides::where('pid', $request->pid)->distinct()->pluck('sale_user_id');
        $totalOverride = 0;
        $totalOverrides = 0;
        $payrollAdjustmentOverride = 0;
        $paidAdjustmentOverrideTotal = 0;
        $unPaidAdjustmentOverrideTotal = 0;
        $unPaidAdjustmentOverride = 0;
        $paidAdjustmentOverride = 0;
        $overrideUserIds = [];
        if ($salesUserOverride != '[]') {
            $s = 1;
            $c = 1;

            foreach ($salesUserOverride as $salesUserOverrides) {
                $userInfo = User::find( $salesUserOverrides);
                $overrides = UserOverrides::with('userInfo', 'payrollAdjustments')->where('pid', $request->pid)->where("status", "!=", 6)->where("overrides_settlement_type", "during_m2")->where('sale_user_id', $salesUserOverrides)->get();

                $position = $userInfo->position_id;
                if ($position == 3) {
                    $positions = 'setter' . $s;
                } else {
                    $positions = 'closer' . $c;
                }
                $data1[$positions]['info'] = [
                    'image' => $userInfo->image,
                    'name' => $userInfo->first_name . " ". $userInfo->last_name,
                    'position_id' => isset($userInfo->position_id) ? $userInfo->position_id : null,
                    'sub_position_id' => isset($userInfo->sub_position_id) ? $userInfo->sub_position_id : null,
                    'is_super_admin' => isset($userInfo->is_super_admin) ? $userInfo->is_super_admin : null,
                    'is_manager' => isset($userInfo->is_manager) ? $userInfo->is_manager : null,
                    'stop_payroll' => (isset($userInfo->stop_payroll) && $userInfo->stop_payroll==1) ? 'Payroll Stop':null,
                ];

                /* override data */
                foreach ($overrides as $key => $override) {
                    $paidOverride = 0;
                    $unPaidOverride = 0;

                    $payRoll = Payroll::where('user_id', $override->user_id)->where('pay_period_from', $override->pay_period_from)->where('pay_period_to', $override->pay_period_to)->first();
                    $payRollHistory = PayrollHistory::where('user_id', $override->user_id)->where('pay_period_from', $override->pay_period_from)->where('pay_period_to', $override->pay_period_to)->first();

                    $totalOverride += isset($override->amount) ? $override->amount : 0;

                    if ($override->status == 3) {
                        $paidOverride = isset($override->amount) ? $override->amount : 0;
                    } else {
                        $unPaidOverride = isset($override->amount) ? $override->amount : 0;
                    }

                    $recipiant = isset($override->user->first_name) ? ($override->user->first_name . ' ' . $override->user->last_name) . " | " . $override->type : null;

                    // $newdate = date("Y-m-d", strtotime($override->updated_at));
                    $commM2date = UserCommission::where(['pid' => $request->pid, 'amount_type' => 'm2'])->first();
                    if (!empty($commM2date)) {
                        $newdate = date("Y-m-d", strtotime($commM2date->date));
                    } else {
                        $newdate = null;
                    }
                    
                    $data1[$positions]['data'][] = [
                        'type' => $override->type,
                        'date' => isset($override->date)?$override->date:null,
                        // 'description' => isset($override->user_info->first_name)?$override->user_info->first_name.' '.$override->user_info->last_name:null, //$override->amount_type,
                        'date' => isset($override->updated_at) ? $newdate : null,
                        'description' => isset($override->type) ? ($recipiant) : '',
                        'paid_amount' => isset($paidOverride) ? $paidOverride : 0,
                        'unpaid_amount' => isset($unPaidOverride) ? $unPaidOverride : 0,
                        'date_paid' => isset($override->pay_period_from) ? $override->pay_period_from . ' to ' . $override->pay_period_to : null,
                        "settlement" => $override->overrides_settlement_type
                    ];

                    if (!empty($payRoll->id)) {
                        $payrollAdjustmentOverrides = PayrollAdjustmentDetail::where('payroll_id', $payRoll->id)->where('pid', $request->pid)->where('payroll_type', 'overrides')->get();
                    } elseif (!empty($payRollHistory)) {
                        $payrollAdjustmentOverrides = PayrollAdjustmentDetail::where('payroll_id', $payRollHistory->payroll_id)->where('pid', $request->pid)->where('payroll_type', 'overrides')->get();
                    }else{
                        $payrollAdjustmentOverrides = [];
                    }

                    if ($payrollAdjustmentOverrides) {
                        if ($override->status == 3) {
                            $paidAdjustmentOverrideTotal += $payrollAdjustmentOverrides->sum('amount');
                        } else {
                            $unPaidAdjustmentOverrideTotal += $payrollAdjustmentOverrides->sum('amount');
                        }

                        if (!in_array($override->user_id, $overrideUserIds)) {
                            $overrideUserIds[] = $override->user_id;

                            foreach ($payrollAdjustmentOverrides as $payrollAdjustmentOverride) {
                                if ($override->status == 3) {
                                    $paidAdjustmentOverride = isset($payrollAdjustmentOverride->amount) ? $payrollAdjustmentOverride->amount : 0;
                                } else {
                                    $unPaidAdjustmentOverride = isset($payrollAdjustmentOverride->amount) ? $payrollAdjustmentOverride->amount : 0;
                                }
                                $adUserId = $payrollAdjustmentOverride->user_id;
                                $userName = User::where('id', $adUserId)->first();
                                $data1[$positions]['data'][] =
                                    [
                                    //data Done ............
                                    'type' => 'Adjustment',
                                    'date' => isset($payrollAdjustment->updated_at) ? date("Y-m-d", strtotime($payrollAdjustment->updated_at)) : null,
                                    // 'description' => $override->payrollAdjustments->commission_type,
                                    'description' => (isset($userName->first_name)) ? $userName->first_name . ' ' . $userName->last_name . ' | Adjustment' : null, //$override->amount_type,
                                    'paid_amount' => isset($paidAdjustmentOverride) ? $paidAdjustmentOverride : 0,
                                    'unpaid_amount' => isset($unPaidAdjustmentOverride) ? $unPaidAdjustmentOverride : 0,
                                    'date_paid' => isset($override->pay_period_from) ? $commission->pay_period_from . ' to ' . $commission->pay_period_to : null,
                                ];
                            }
                        }
                    }
                }

                /* recon overide data */
                $reconOverride = ReconOverrideHistory::where("pid", $request->pid)->whereIn("status", ["payroll", "finalize"])->where("overrider", $salesUserOverrides)->get();
                foreach ($reconOverride as $reconOverrideValue) {
                    $paidReconOverrideAmount = 0;
                    $unPaidReconOverrideAmount = 0;

                    if($reconOverrideValue->reconOverrideHistoryData->status == "payroll" && $reconOverrideValue->reconOverrideHistoryData->payroll_execute_status == 3){
                        $paidReconOverrideAmount = $reconOverrideValue->paid;
                    }elseif($reconOverrideValue->reconOverrideHistoryData->status == "finalize" || $reconOverrideValue->reconOverrideHistoryData->status == "payroll" ){
                        $unPaidReconOverrideAmount = $reconOverrideValue->paid;
                    }

                    $position = $reconOverrideValue->overrideOverData->position_id;
                    if ($position == 3) {
                        $positions = 'setter' . $s;
                    } else {
                        $positions = 'closer' . $c;
                    };

                    $overriderName = $reconOverrideValue->userData->first_name. " ". $reconOverrideValue->userData->last_name;
                    $reconStatus = ($reconOverrideValue->move_from_payroll == 1) ? ' | Move To Recon' : '';
                    $reconType =  $reconOverrideValue->type ? ucfirst($reconOverrideValue->type). " | " : '';
                    $description = $overriderName . " | " . $reconType . "Reconciliation" . $reconStatus; 

                    $data1[$positions]['data'][] = [
                        'type' => $reconOverrideValue->type,
                        'date' => $reconOverrideValue->updated_at->format("m/d/Y"),
                        'description' => $description,
                        'paid_amount' => $paidReconOverrideAmount,
                        'unpaid_amount' => $unPaidReconOverrideAmount,
                        'date_paid' => null,
                        "settlement" => $reconOverrideValue->overrides_settlement_type
                    ];
                }

                /* override clawback data */
                $oclawdbacks = ClawbackSettlement::with('users', 'userInfo', 'salesDetail')->where('sale_user_id', $salesUserOverrides)->where(['pid' => $request->pid, /* 'type' => 'overrides', */ 'clawback_type' => 'next payroll'])->whereIn("type", ["overrides", "recon-override"])->where("status", "!=", 6)->get();
                $paidClawbackOverTotal = 0;
                $unPaidClawbackOverTotal = 0;

                if (count($oclawdbacks) > 0) {
                    foreach ($oclawdbacks as $key => $oclawdback) {
                        $paidClawbackOver = 0;
                        $unPaidClawbackOver = 0;
                        if ($oclawdback->status == 3) {
                            $paidClawbackOverTotal += isset($oclawdback->clawback_amount) ? $oclawdback->clawback_amount : 0;
                            $paidClawbackOver = isset($oclawdback->clawback_amount) ? $oclawdback->clawback_amount : 0;
                        } else {
                            $unPaidClawbackOverTotal += isset($oclawdback->clawback_amount) ? $oclawdback->clawback_amount : 0;
                            $unPaidClawbackOver = isset($oclawdback->clawback_amount) ? $oclawdback->clawback_amount : 0;
                        }

                        $position = $oclawdback->userInfo->position_id;

                        if ($position == 3) {
                            $positions = 'setter'.$s;
                        } else {
                            $positions = 'closer'.$c;

                        }

                        $newdate = date("Y-m-d", strtotime($oclawdback->salesDetail->date_cancelled));
                        $data1[$positions]['data'][] =
                            [
                            'type' => $oclawdback->adders_type ? $oclawdback->adders_type . ' | Clawback' : "Clawback",
                            'date' => isset($newdate) ? $newdate : null,
                            'description' => isset($oclawdback->userInfo->first_name) ? $oclawdback->userInfo->first_name . ' ' . $oclawdback->userInfo->last_name : null,
                            'paid_amount' => isset($paidClawbackOver) ? (0 - $paidClawbackOver) : 0,
                            'unpaid_amount' => isset($unPaidClawbackOver) ? (0 - $unPaidClawbackOver) : 0,
                            'date_paid' => isset($oclawdback->pay_period_from) ? $oclawdback->pay_period_from . ' to ' . $oclawdback->pay_period_to : null,
                        ];
                    }
                }

                /* recon override data */
                $reconClawbackData = ReconClawbackHistory::where("user_id", $salesUserOverrides)->where("pid", $request->pid)->whereIn("type", ["recon-override", "overrides"])->whereIn("status", ["payroll", "finalize"])->get();
                foreach ($reconClawbackData as $key => $value) {
                    $paidAmount = 0;
                    $unPaidAmount = 0;

                    if($value->status == "payroll" && $value->payroll_execute_status == 3){
                        $paidAmount = $value->paid_amount;
                    }elseif($value->status == "payroll" || $value->status == "finalize"){
                        $unPaidAmount = $value->paid_amount;
                    }

                    $position = $value->user->position_id;
                    if ($position == 3) {
                        $positions = 'setter'.$s;
                    } else {
                        $positions = 'closer'.$c;
                    }
                    $overriderName = $value->saleUserInfo ? $value->saleUserInfo->first_name . " " . $value->saleUserInfo->last_name. " | " : ""; 
                    $type = $value->adders_type ? $value->adders_type . " | " : "";
                    $reconStatus = $value->move_from_payroll == 1 ? " | Move To Recon" : "";
                    $description = $overriderName . $type . "Reconciliation". $reconStatus . " | Clawback ";

                    $data1[$positions]['data'][] =[
                        'type' => $value->adders_type ? $value->adders_type . ' | Clawback' : "Clawback",
                        'date' => isset($newdate) ? $newdate : null,
                        'description' => $description,
                        'paid_amount' => -1 * $paidAmount,
                        'unpaid_amount' => -1 * $unPaidAmount,
                        'date_paid' => null,
                    ];
                }

                /* recon override adjustments */
                $reconOverrideAdjustment = ReconAdjustment::where("pid", $request->pid)
                // ->where("sale_user_id", $salesUserOverrides)
                ->where("adjustment_type", "override")
                ->whereIn("payroll_status", ["finalize", "payroll"])
                ->get();

                foreach ($reconOverrideAdjustment as $key => $value) {
                    $paidAdjustment = 0;
                    $unpaidAdjustment = 0;

                    if($value->payroll_status == "payroll" && $value->payroll_execute_status == 3){
                        $paidAdjustment = $value->adjustment_amount;
                    }elseif($value->payroll_status == "payroll" || $value->payroll_status == "finalize" ){
                        $unpaidAdjustment = $value->adjustment_amount;
                    }
                    $type = $value->adjustment_override_type ? " | ". $value->adjustment_override_type . " | " : "";
                    $description = $value->user->first_name. " ".$value->user->last_name . $type .' | Adjustment';
                    if($value->sale_user_id == $salesUserOverrides){
                        $data1[$positions]['data'][] = [
                            'type' => 'Adjustment',
                            'date' => $value->updated_at->format("Y-m-d"),
                            'description' => $description,
                            'paid_amount' => $paidAdjustment,
                            'unpaid_amount' => $unpaidAdjustment,
                            'date_paid' => "",
                            "settlement" => "Reconciliationn-Adjustment",
                        ];
                    }
                }
                if ($position == 3) {
                    $s++;
                } else {
                    $c++;
                }
            }
        }

        foreach ($this->setter_closer_arr as $sc) {
            if (!array_key_exists($sc, $data)) {
                $data[$sc] = null;
            }
            if (!array_key_exists($sc, $data1)) {
                $data1[$sc] = null;
            }
        }
        
        
        if(isset($data1["closer1"]["data"])){
            $totalCloser1 = array_reduce($data1["closer1"]["data"], function ($carry, $item) {
                $carry['paid_amount'] += $item['paid_amount'];
                $carry['unpaid_amount'] += $item['unpaid_amount'];
                return $carry;
            }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
            $data1["closer1"]["paid_total"] = $totalCloser1["paid_amount"];
            $data1["closer1"]["unpaid_total"] = $totalCloser1["unpaid_amount"];
            $data1["closer1"]["sub_total"] = array_sum($totalCloser1);
            $totalOverrides += array_sum($totalCloser1);
        }
        if(isset($data1["closer2"]["data"])){
            $totalCloser2 = array_reduce($data1["closer2"]["data"], function ($carry, $item) {
                $carry['paid_amount'] += $item['paid_amount'];
                $carry['unpaid_amount'] += $item['unpaid_amount'];
                return $carry;
            }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
            $data1["closer2"]["paid_total"] = $totalCloser2["paid_amount"];
            $data1["closer2"]["unpaid_total"] = $totalCloser2["unpaid_amount"];
            $data1["closer2"]["sub_total"] = array_sum($totalCloser2);
            $totalOverrides += array_sum($totalCloser2);
        }

        if(isset($data1["setter1"]["data"])){
            $totalSetter1 = array_reduce($data1["setter1"]["data"], function ($carry, $item) {
                $carry['paid_amount'] += $item['paid_amount'];
                $carry['unpaid_amount'] += $item['unpaid_amount'];
                return $carry;
            }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
            $data1["setter1"]["paid_total"] = $totalSetter1["paid_amount"];
            $data1["setter1"]["unpaid_total"] = $totalSetter1["unpaid_amount"];
            $data1["setter1"]["sub_total"] = array_sum($totalSetter1);
            $totalOverrides += array_sum($totalSetter1);
        }
        if(isset($data1["setter2"]["data"])){
            $totalSetter2 = array_reduce($data1["setter2"]["data"], function ($carry, $item) {
                $carry['paid_amount'] += $item['paid_amount'];
                $carry['unpaid_amount'] += $item['unpaid_amount'];
                return $carry;
            }, ['paid_amount' => 0, 'unpaid_amount' => 0]);
            $data1["setter2"]["paid_total"] = $totalSetter2["paid_amount"];
            $data1["setter2"]["unpaid_total"] = $totalSetter2["unpaid_amount"];
            $data1["setter2"]["sub_total"] = array_sum($totalSetter2);
            $totalOverrides += array_sum($totalSetter2);
        }
        // dd($totalCloser1 , $totalSetter1);
        return response()->json([
            'ApiName' => 'sales_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'commission' => $data,
            'grandTotalCommission' => isset($totalCommissions) ? $totalCommissions : 0,
            'override' => $data1,
            'grandTotalOverride' => isset($totalOverrides) ? $totalOverrides : 0,

        ], 200);
    }
    
    public function salesAccountSummaryByPosition_old(Request $request)
    {
        $Validator = Validator::make($request->all(),
        [
            'pid' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['error'=>$Validator->errors()], 400);
        }
        $data = [];
        $data1 = [];
        $paidCommission = 0;
        $unPaidCommission = 0;
        $paidCommissionTotal = 0;
        $unPaidCommissionTotal = 0;
        $totalCommission = 0;
        $totalCommissions=0;
        $paidAdjustment = 0;
        $unPaidAdjustment = 0;
        $totalAdjustment = 0;

        $salesUserCommission = UserCommission::where('pid',$request->pid)->distinct()->pluck('user_id');
        if(count($salesUserCommission) > 0)
        {
            $s=1;
            $c=1;
            foreach($salesUserCommission as $salesUsers)
            {
                $commissions = UserCommission::with('userdata','payrollAdjustment')->where('pid',$request->pid)->where('user_id',$salesUsers)->get();

                foreach($commissions as $key => $commission)
                {
                    $payRollHistory = PayrollHistory::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();
                    $totalCommission +=isset($commission->amount)?$commission->amount:0;
                    if($payRollHistory)
                    {
                        $paidCommissionTotal += isset($commission->amount)?$commission->amount:0;
                        $paidCommission = isset($commission->amount)?$commission->amount:0;
                    }else
                    {
                        $unPaidCommissionTotal += isset($commission->amount)?$commission->amount:0;
                        $unPaidCommission = isset($commission->amount)?$commission->amount:0;
                    }
                    $position = $commission->userdata->position_id;
                    if($position==3)
                    {
                        $positions = 'setter'.$s;
                    }else{
                        $positions = 'closer'.$c;
                    }
                    $data[$positions]['info']= ['image'=>$commission->userdata->image,'name'=>$commission->userdata->first_name.$commission->userdata->last_name, 'stop_payroll' => (isset($commission->userdata->stop_payroll) && $commission->userdata->stop_payroll==1) ? 'Payroll Stop':null];
                    $data[$positions]['data'][]=
                        [
                            //data Done ............
                            'type' => 'Commission',
                            'date' => isset($commission->date)?$commission->date:null,
                            'description' => $commission->amount_type.' Payment',
                            'paid_amount' => isset($paidCommission)?$paidCommission:0,
                            'unpaid_amount' => isset($unPaidCommission)?$unPaidCommission:0,
                            'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                        ];

                    $clawdback = ClawbackSettlement::with('salesDetail')->where(['user_id'=> $commission->user_id, 'pid'=> $request->pid, 'type'=> 'commission', 'clawback_type'=> 'next payroll'])->first();
                    $paidClawbackCommissionTotal = 0;
                    $unPaidClawbackCommissionTotal = 0;

                    if (!empty($commission->amount) && $clawdback) {
                        $paidClawbackCommission = 0;
                        $unPaidClawbackCommission = 0;
                        if($payRollHistory)
                        {
                            $paidClawbackCommissionTotal += isset($clawdback->clawback_amount)?$clawdback->clawback_amount:0;
                            $paidClawbackCommission = isset($clawdback->clawback_amount)?$clawdback->clawback_amount:0;
                        }else
                        {
                            $unPaidClawbackCommissionTotal += isset($clawdback->clawback_amount)?$clawdback->clawback_amount:0;
                            $unPaidClawbackCommission = isset($clawdback->clawback_amount)?$clawdback->clawback_amount:0;
                        }

                        $newdate = date("Y-m-d", strtotime($clawdback->salesDetail->date_cancelled));
                        $data[$positions]['data'][]=
                        [
                            'type' => 'Commission',
                            'date' => isset($newdate)?$newdate : null,
                            'description' => ' Payment | CLAWED BACK',
                            'paid_amount' => isset($paidClawbackCommission)? (0-$paidClawbackCommission):0,
                            'unpaid_amount' => isset($unPaidClawbackCommission)? (0-$unPaidClawbackCommission):0,
                            'date_paid' => isset($clawdback->pay_period_from)?$clawdback->pay_period_from.' to '.$clawdback->pay_period_to:null,
                        ];

                        $totalCommission = ($totalCommission - $clawdback->clawback_amount);
                        
                    }   

                    $totalCommission +=isset( $commission->payrollAdjustment->adjustments_amount)? $commission->payrollAdjustment->adjustments_amount:0;
                    $data[$positions]['paid_total'] = ($paidCommissionTotal - $paidClawbackCommissionTotal);
                    $data[$positions]['unpaid_total'] = ($unPaidCommissionTotal - $unPaidClawbackCommissionTotal);
                    $data[$positions]['sub_total'] = ($paidCommissionTotal+$unPaidCommissionTotal - $paidClawbackCommissionTotal - $unPaidClawbackCommissionTotal);

                    if($commission->payrollAdjustment!='')
                    {

                        $data[$positions]['data'][]=
                        [
                            //data Done ............
                            'type' => 'Adjustment',
                            'date' => isset($commission->date)?$commission->date:null,
                            'description' => $commission->payrollAdjustment->commission_type,
                            'paid_amount' => isset( $commission->payrollAdjustment->adjustments_amount)? $commission->payrollAdjustment->adjustments_amount:0,
                            'unpaid_amount' => 0,
                            'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                        ];
                    }
                    $totalCommissions += $totalCommission;

                }

                $paidCommissionTotal=0;
                $unPaidCommissionTotal=0;
                $totalCommission = 0;

                if($position==3)
                {
                    $s++;
                }else{
                    $c++;
                }
            }
        }

        //overrides ....................
        $salesUserOverride = UserOverrides::where('pid',$request->pid)->distinct()->pluck('user_id');
        $paidOverride = 0;
        $paidOverrideTotal = 0;
        $unPaidOverride = 0;
        $unPaidOverrideTotal =0;
        $totalOverride = 0;
        $paidAdjustment = 0;
        $unPaidAdjustment = 0;
        $totalAdjustment = 0;
        if($salesUserOverride!='[]')
        {
            $s=1;
            $c=1;
            foreach($salesUserOverride as $salesUserOverrides)
            {

               $overrides = UserOverrides::with('userInfo','payrollAdjustments')->where('pid',$request->pid)->where('user_id',$salesUserOverrides)->where('overrides_settlement_type','during_m2')->get();

               foreach($overrides as $key => $override)
                {

                    $payRollHistory = PayrollHistory::where('user_id',$override->user_id)->where('pay_period_from',$override->pay_period_from)->where('pay_period_to',$override->pay_period_to)->first();
                    $totalOverride +=isset($override->amount)?$override->amount:0;
                    if($payRollHistory)
                    {
                        $paidOverrideTotal += isset($override->amount)?$override->amount:0;
                        $paidOverride = isset($override->amount)?$override->amount:0;
                    }else
                    {
                        $unPaidOverrideTotal += isset($override->amount)?$override->amount:0;
                        $unPaidOverride = isset($override->amount)?$override->amount:0;
                    }
                    $position = $override->userInfo->position_id;
                    if($position==3)
                    {
                        $positions = 'setter'.$s;
                    }else{
                        $positions = 'closer'.$c;
                    }
                    $data1[$positions]['info']= ['image'=>$override->userInfo->image,'name'=>$override->userInfo->first_name.$override->userInfo->last_name, 'stop_payroll' => (isset($override->userInfo->stop_payroll) && $override->userInfo->stop_payroll==1) ? 'Payroll Stop':null];

                    $data1[$positions]['data'][]=
                        [
                            //data Done ............
                            'type' => $override->type,
                            'date' => isset($override->date)?$override->date:null,
                            'description' => isset($override->user_info->first_name)?$override->user_info->first_name.' '.$override->user_info->last_name:null, //$override->amount_type,
                            'paid_amount' => isset($paidOverride)?$paidOverride:0,
                            'unpaid_amount' => isset($unPaidOverride)?$unPaidOverride:0,
                            'date_paid' => isset($override->pay_period_from)?$override->pay_period_from.' to '.$override->pay_period_to:null,
                        ];
                            $totalOverride +=isset($override->payrollAdjustments->adjustments_amount)? $override->payrollAdjustments->adjustments_amount:0;
                            $data1[$positions]['paid_total'] =$paidOverrideTotal;
                            $data1[$positions]['unpaid_total'] =$unPaidOverrideTotal;
                            $data1[$positions]['sub_total'] =$totalOverride;

                        $payRoll = payRoll::where('user_id',$override->user_id)->where('pay_period_from',$override->pay_period_from)->where('pay_period_to',$override->pay_period_to)->first();
                        if ($payRoll) {
                            $payrollAdjustments = PayrollAdjustment::where('payroll_id',$payRoll->id)->first();
                            if($override->payrollAdjustments!='')
                            {
                            $adUserId = $override->payrollAdjustments->user_id;
                            $userName = User::where('id',$adUserId)->first();
                                $data1[$positions]['data'][]=
                                [
                                    //data Done ............
                                    'type' => 'Adjustment',
                                    'date' => isset($override->pay_period_from)?$override->pay_period_from:null,
                                // 'description' => $override->payrollAdjustments->commission_type,
                                    'description' => (isset($userName->first_name))?$userName->first_name.' '.$userName->last_name.' | Adjustment':null, //$override->amount_type,
                                    'paid_amount' => isset($payrollAdjustments->adjustments_amount)? $payrollAdjustments->adjustments_amount:0,
                                    'unpaid_amount' => 0,
                                    'date_paid' => isset($override->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                                ];
                            }
                        }

                    $totalOverride = $totalOverride+$totalOverride;
                }
                if($position==3)
                    {
                        $s++;
                    }else{
                        $c++;
                    }
            }
        }

        foreach($this->setter_closer_arr as $sc){
            if(!array_key_exists($sc, $data)){
                $data[$sc] = null;
            }
            if(!array_key_exists($sc, $data1)){
                $data1[$sc] = null;
            }
        }


        //return $data1;

         return response()->json([
            'ApiName' => 'sales_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'commission' => $data,
            'grandTotalCommission' => isset($totalCommissions)?$totalCommissions:0,
            'override' => $data1,
            'grandTotalOverride' => isset($totalOverride)?$totalOverride:0,

        ], 200);
    }

    public function salesAccountSummaryByPosition_old_2(Request $request)
    {
        $Validator = Validator::make($request->all(),
        [
            'pid' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['error'=>$Validator->errors()], 400);
        }
        $data = [];
        $data1 = [];
        $paidCommission = 0;
        $unPaidCommission = 0;
        $paidCommissionTotal = 0;
        $unPaidCommissionTotal = 0;
        $totalCommission = 0;
        $totalCommissions=0;
        $paidAdjustment = 0;
        $unPaidAdjustment = 0;
        $totalAdjustment = 0;
        $saleMasterProcess = SaleMasterProcess::where('pid',$request->pid)->first();
        $clawback = '';
        if ($saleMasterProcess->mark_account_status_id==1) {
            $clawback = ' | Clawed Back';
        }

        $salesUserCommission = UserCommission::where('pid',$request->pid)->distinct()->pluck('user_id');
        if($salesUserCommission!='[]')
        {
            $s=1;
            $c=1;
            foreach($salesUserCommission as $salesUsers)
            {
                $commissions = UserCommission::with('userdata','payrollAdjustment')->where('pid',$request->pid)->where('user_id',$salesUsers)->get();

                foreach($commissions as $key => $commission)
                {
                    $payRollHistory = PayrollHistory::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();
                    $totalCommission +=isset($commission->amount)?$commission->amount:0;
                    if($payRollHistory)
                    {
                        $paidCommissionTotal += isset($commission->amount)?$commission->amount:0;
                        $paidCommission = isset($commission->amount)?$commission->amount:0;
                    }else
                    {
                        $unPaidCommissionTotal += isset($commission->amount)?$commission->amount:0;
                        $unPaidCommission = isset($commission->amount)?$commission->amount:0;
                    }
                    $position = $commission->userdata->position_id;
                    if($position==3)
                    {
                        $positions = 'setter'.$s;
                    }else{
                        $positions = 'closer'.$c;
                    }
                    $data[$positions]['info']= ['image'=>$commission->userdata->image,'name'=>$commission->userdata->first_name.$commission->userdata->last_name, 'stop_payroll' => (isset($commission->userdata->stop_payroll) && $commission->userdata->stop_payroll==1) ? 'Payroll Stop':null];
                    $data[$positions]['data'][]=
                        [
                            //data Done ............
                            'type' => 'Commission',
                            'date' => isset($commission->date)?$commission->date:null,
                            'description' => $commission->amount_type.' Payment'. $clawback,
                            'paid_amount' => isset($paidCommission)?$paidCommission:0,
                            'unpaid_amount' => isset($unPaidCommission)?$unPaidCommission:0,
                            'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                        ];
                            $totalCommission +=isset( $commission->payrollAdjustment->adjustments_amount)? $commission->payrollAdjustment->adjustments_amount:0;
                            $data[$positions]['paid_total'] =$paidCommissionTotal;
                            $data[$positions]['unpaid_total'] =$unPaidCommissionTotal;
                            $data[$positions]['sub_total'] = $paidCommissionTotal+$unPaidCommissionTotal;
                        if($commission->payrollAdjustment!='')
                        {

                            $data[$positions]['data'][]=
                            [
                                //data Done ............
                                'type' => 'Adjustment',
                                'date' => isset($commission->date)?$commission->date:null,
                                'description' => $commission->payrollAdjustment->commission_type,
                                'paid_amount' => isset( $commission->payrollAdjustment->adjustments_amount)? $commission->payrollAdjustment->adjustments_amount:0,
                                'unpaid_amount' => 0,
                                'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                            ];
                        }
                        $totalCommissions += $totalCommission;

                }
                    $paidCommissionTotal=0;
                    $unPaidCommissionTotal=0;
                    $totalCommission = 0;
                if($position==3)
                {
                    $s++;
                }else{
                    $c++;
                }
            }
        }

        //overrides ....................
        $salesUserOverride = UserOverrides::where('pid',$request->pid)->distinct()->pluck('user_id');
        $paidOverride = 0;
        $paidOverrideTotal = 0;
        $unPaidOverride = 0;
        $unPaidOverrideTotal =0;
        $totalOverride = 0;
        $paidAdjustment = 0;
        $unPaidAdjustment = 0;
        $totalAdjustment = 0;
        if($salesUserOverride!='[]')
        {
            $s=1;
            $c=1;
            foreach($salesUserOverride as $salesUserOverrides)
            {

               $overrides = UserOverrides::with('userInfo','payrollAdjustments')->where('pid',$request->pid)->where('user_id',$salesUserOverrides)->where('overrides_settlement_type','during_m2')->get();

               foreach($overrides as $key => $override)
                {

                    $payRollHistory = PayrollHistory::where('user_id',$override->user_id)->where('pay_period_from',$override->pay_period_from)->where('pay_period_to',$override->pay_period_to)->first();
                    $totalOverride +=isset($override->amount)?$override->amount:0;
                    if($payRollHistory)
                    {
                        $paidOverrideTotal += isset($override->amount)?$override->amount:0;
                        $paidOverride = isset($override->amount)?$override->amount:0;
                    }else
                    {
                        $unPaidOverrideTotal += isset($override->amount)?$override->amount:0;
                        $unPaidOverride = isset($override->amount)?$override->amount:0;
                    }
                    $position = $override->userInfo->position_id;
                    if($position==3)
                    {
                        $positions = 'setter'.$s;
                    }else{
                        $positions = 'closer'.$c;
                    }
                    $data1[$positions]['info']= ['image'=>$override->userInfo->image,'name'=>$override->userInfo->first_name.$override->userInfo->last_name, 'stop_payroll' => (isset($override->userInfo->stop_payroll) && $override->userInfo->stop_payroll==1) ? 'Payroll Stop':null];

                    $data1[$positions]['data'][]=
                        [
                            //data Done ............
                            'type' => $override->type,
                            'date' => isset($override->date)?$override->date:null,
                            'description' => isset($override->user_info->first_name)?$override->user_info->first_name.' '.$override->user_info->last_name:null, //$override->amount_type,
                            'paid_amount' => isset($paidOverride)?$paidOverride:0,
                            'unpaid_amount' => isset($unPaidOverride)?$unPaidOverride:0,
                            'date_paid' => isset($override->pay_period_from)?$override->pay_period_from.' to '.$override->pay_period_to:null,
                        ];
                            $totalOverride +=isset($override->payrollAdjustments->adjustments_amount)? $override->payrollAdjustments->adjustments_amount:0;
                            $data1[$positions]['paid_total'] =$paidOverrideTotal;
                            $data1[$positions]['unpaid_total'] =$unPaidOverrideTotal;
                            $data1[$positions]['sub_total'] =$totalOverride;

                        $payRoll = payRoll::where('user_id',$override->user_id)->where('pay_period_from',$override->pay_period_from)->where('pay_period_to',$override->pay_period_to)->first();
                        if ($payRoll) {
                            $payrollAdjustments = PayrollAdjustment::where('payroll_id',$payRoll->id)->first();
                            if($override->payrollAdjustments!='')
                            {
                            $adUserId = $override->payrollAdjustments->user_id;
                            $userName = User::where('id',$adUserId)->first();
                                $data1[$positions]['data'][]=
                                [
                                    //data Done ............
                                    'type' => 'Adjustment',
                                    'date' => isset($override->pay_period_from)?$override->pay_period_from:null,
                                // 'description' => $override->payrollAdjustments->commission_type,
                                    'description' => (isset($userName->first_name))?$userName->first_name.' '.$userName->last_name.' | Adjustment':null, //$override->amount_type,
                                    'paid_amount' => isset($payrollAdjustments->adjustments_amount)? $payrollAdjustments->adjustments_amount:0,
                                    'unpaid_amount' => 0,
                                    'date_paid' => isset($override->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
                                ];
                            }
                        }

                     $totalOverride = $totalOverride+$totalOverride;
                }
                if($position==3)
                    {
                        $s++;
                    }else{
                        $c++;
                    }
            }
        }

        foreach($this->setter_closer_arr as $sc){
            if(!array_key_exists($sc, $data)){
                $data[$sc] = null;
            }
            if(!array_key_exists($sc, $data1)){
                $data1[$sc] = null;
            }
        }


        //return $data1;

         return response()->json([
            'ApiName' => 'sales_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'commission' => $data,
            'grandTotalCommission' => isset($totalCommissions)?$totalCommissions:0,
            'override' => $data1,
            'grandTotalOverride' => isset($totalOverride)?$totalOverride:0,

        ], 200);
    }

    public function companyMarginSummary(Request $request)
    {
        $Validator = Validator::make($request->all(),
        [
            'pid' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['error'=>$Validator->errors()], 400);
        }

        $data = [];
        $total = 0;
        $commissions = UserCommission::with('userdata')->where('pid',$request->pid)->where('amount_type','m2')->get();
        $companyMargin = CompanyProfile::where('id',1)->first();
        if ($commissions) {

            foreach($commissions as $key => $commission)
            {
                $tocommission = UserCommission::where('pid',$request->pid)->where('user_id',$commission->user_id)->sum('amount');
                // $aa[] = $tocommission;
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    //$withheldAmount = (($net_pay * ($margin_percentage/100)) / $x);
                    $withheldAmount = ($tocommission * (($margin_percentage / 100) / $x));
                    //dd($withheldAmount);
                }else {
                    $withheldAmount = 0;
                }

                if ($key == 0) {
                    $ctype = 'Setter';
                }else{
                    $ctype = 'Closer';
                }

                // if ($commission->position_id == 2) {
                //     $ctype = 'Closer';
                // }else{
                //     $ctype = 'Setter';
                // }

                $total += $withheldAmount;

                $data[] =
                    [
                        'to' => 'Company',
                        'through' => isset($commission->userdata)?$commission->userdata->first_name.' '.$commission->userdata->last_name:null,
                        'position' => $ctype,
                        'date' => isset($commission->date)?$commission->date:null,
                        'description' => 'Company Margin',
                        'pay_period_from' => isset($commission->pay_period_from)?$commission->pay_period_from:null,
                        'pay_period_to' => isset($commission->pay_period_to)?$commission->pay_period_to:null,
                        'withheld_amount' => $withheldAmount,
                        //'employee' => isset($commission->userdata->first_name)?($commission->userdata->first_name.' '.$commission->userdata->last_name):null,
                        'type' => $ctype .' Commission',
                        'dismiss' => isset($commission->userdata->id) && isUserDismisedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($commission->userdata->id) && isUserTerminatedOn($commission->userdata->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($commission->userdata->id) && isUserContractEnded($commission->userdata->id) ? 1 : 0,
                    ];
            }
        }

        // return $data;

        //Stack overrides,,,,,,,,,,,,,,,,,

        $overRideDatas = UserOverrides::with('userInfo','user')->where('pid',$request->pid)->whereIn('type',['Stack'])->get();
        // return $overRideDatas;
        if ($overRideDatas) {
            foreach($overRideDatas as $overRideData)
            {
                if ($overRideData->user->position_id == 2) {
                    $ctype = 'Closer';
                }else{
                    $ctype = 'Setter';
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    //$withheldAmount = (($net_pay * ($margin_percentage/100)) / $x);
                    $withheldAmount = (($overRideData->amount * $margin_percentage / 100) / $x);
                    //dd($withheldAmount);
                }else {
                    $withheldAmount = 0;
                }

                $total += $withheldAmount;

                // $newdate = date("Y-m-d", strtotime($overRideData->created_at));
                $commM2date = UserCommission::where(['pid'=> $request->pid, 'amount_type'=> 'm2'])->first();
                if (!empty($commM2date)) {
                    $newdate = date("Y-m-d", strtotime($commM2date->date));
                }else {
                    $newdate = null;
                }
                
                $data[]=
                    [
                        'to'=> 'Company',
                        'through' => isset($overRideData->user)?$overRideData->user->first_name.' '.$overRideData->user->last_name:null,
                        'position' => $ctype,
                        'date'=> isset($overRideData->created_at)?$newdate:null,
                        'description' => 'Company Margin',
                        'pay_period_from' => isset($overRideData->pay_period_from)?$overRideData->pay_period_from:null,
                        'pay_period_to' => isset($overRideData->pay_period_to)?$overRideData->pay_period_to:null,
                        'withheld_amount' => $withheldAmount,
                        'type' => 'Stack Margin',
                        'dismiss' => isset($overRideData->user->id) && isUserDismisedOn($overRideData->user->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($overRideData->user->id) && isUserTerminatedOn($overRideData->user->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($overRideData->user->id) && isUserContractEnded($overRideData->user->id) ? 1 : 0,
                    ];

//                    $payRoll = Payroll::where('user_id',$commission->user_id)->where('pay_period_from',$commission->pay_period_from)->where('pay_period_to',$commission->pay_period_to)->first();
//                    if($payRoll)
//                    {
//                        $adjustmentOver = PayrollAdjustment::with('detail','userDetail')->where('payroll_id',$payRoll->id)->first();
//                        if($adjustmentOver)
//                        {
//                            $adjustmentTotalOver +=isset($adjustmentOver->commission_amount)?$adjustmentOver->commission_amount:0;
//                            $adjustmentPaidOver += isset($adjustmentOver->commission_amount)?$adjustmentOver->commission_amount:0;
//                            $adjustmentPendingOver += 0;
//                            $data1['total_adjustment_override'][]=
//                                        [
//                                            'date'=> isset($commission->date)?$commission->date:null,
//                                            'employee_id'=> isset($commission->userdata->id)?$commission->userdata->id:null,
//                                            'employee'=> isset($commission->userdata->first_name)?($commission->userdata->first_name.' '.$commission->userdata->last_name):null,
//                                            'type'=> isset($adjustmentOver->commission_type)?$adjustmentOver->commission_type:'',
//                                            'paid'=> isset($adjustmentPaidOver)?$adjustmentPaidOver:0,
//                                            'unpaid'=> $adjustmentPendingOver,
//                                            'date_paid' => isset($commission->pay_period_from)?$commission->pay_period_from.' to '.$commission->pay_period_to:null,
//                                        ];
//
//                        }
//                        else{
//
//                            $data1['total_adjustment_override']=  [];
//                            // $data1['total_adjustment_override'][]=
//                            //             [
//                            //                 'date'=> '',
//                            //                 'employee_id'=> '',
//                            //                 'employee'=> '',
//                            //                 'type'=> '',
//                            //                 'paid'=> '',
//                            //                 'unpaid'=> '',
//                            //                 'date_paid' => '',
//                            //             ];
//
//                        }
//                    }
                    $unPaidOver=0;
                    $paidOver =0;
            }
        }

        return response()->json([
            'ApiName' => 'company_margin_summary',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'total' => $total,

        ], 200);
    }

    public function sales_graph(Request $request)
    {
        $data = array();
        $location = $request->location;
        $filter   = $request->filter;

        $startDate = '';
        $endDate   = '';

        if ($request->has('office_id') && !empty($request->input('office_id')))
        {
            $office_id = $request->office_id;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');
            }
        }

        // if ($location!='all')
        // {
        //     $colmun = 'customer_state';
        //     $condition = '=';
        //     $values = $request->location;
        // }else{
        //     $colmun = 'customer_state';
        //     $condition = '<>';
        //     $values = $request->location;
        // }

        $clawbackPid = ClawbackSettlement::where('pid','!=',null)->groupBy('pid')->pluck('pid')->toArray();

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            if($filter=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                //$startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate =  date('Y-m-d', strtotime(now()));

                // if ($office_id!='all')
                // {
                //     $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->get();
                //     $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->groupBy('sales_rep_email')->count();
                //     $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('m2_date', '!=', null)->count();
                //     $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
                //     $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->count();
                //     $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                //     $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                //     ->whereBetween('customer_signoff',[$startDate,$endDate])
                //     ->whereIn('pid',$salesPid)
                //     ->orderBy('kw_total', 'desc')
                //     ->first();

                // }else{
                //     $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->get();
                //     $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->groupBy('sales_rep_email')->count();
                //     $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('m2_date', '!=', null)->count();
                //     $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
                //     $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->count();
                //     $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                //     $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                //     ->whereBetween('customer_signoff',[$startDate,$endDate])
                //     ->orderBy('kw_total', 'desc')
                //     ->first();
                // }

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

                $m1Amount = [];
                $m2Amount = [];
                for($i=1; $i<=$currentDate->dayOfWeek; $i++)
                {
                    $newDateTime = Carbon::now()->subDays($currentDate->dayOfWeek-$i);
                    $weekDate =  date('Y-m-d', strtotime($newDateTime));

                    $amountM1 = SalesMaster::where('customer_signoff', $weekDate)->sum('m1_amount');

                    $amountM2 = SalesMaster::where('customer_signoff', $weekDate)->sum('m2_amount');
                    $amount[] =[
                        'date'=> $weekDate,
                        'm1_amount'=> $amountM1,
                        'm2_amount'=> $amountM2
                    ];
                }
                //return $amount;
            }
            else if($filter=='this_month')
            {
                $month = \Carbon\Carbon::now()->daysInMonth;
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->orderBy('kw_total', 'desc')
                // ->first();

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->groupBy('year', 'month')
                // ->orderBy('kw_total', 'desc')
                // ->first();

                // if ($bestmonths) {
                //     $weekmonth = Carbon::parse($bestmonths->customer_signoff);
                //     $date = $weekmonth->copy()->firstOfMonth()->startOfDay();
                //     $eom = $weekmonth->copy()->endOfMonth()->startOfDay();
                // }

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='last_quarter')
            {

                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->groupBy('year', 'month')
                // ->orderBy('kw_total', 'desc')
                // ->first();
                // if ($bestmonths) {
                //     $weekmonth = Carbon::parse($bestmonths->customer_signoff);
                //     $date = $weekmonth->copy()->firstOfMonth()->startOfDay();
                //     $eom = $weekmonth->copy()->endOfMonth()->startOfDay();
                // }

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='this_year')
            {
                // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                // $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->groupBy('year', 'month')
                // ->orderBy('kw_total', 'desc')
                // ->first();
                // if ($bestmonths) {
                //     $weekmonth = Carbon::parse($bestmonths->customer_signoff);
                //     $date = $weekmonth->copy()->firstOfMonth()->startOfDay();
                //     $eom = $weekmonth->copy()->endOfMonth()->startOfDay();
                // }

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->groupBy('year', 'month')
                // ->orderBy('kw_total', 'desc')
                // ->first();
                // if ($bestmonths) {
                //     $weekmonth = Carbon::parse($bestmonths->customer_signoff);
                //     $date = $weekmonth->copy()->firstOfMonth()->startOfDay();
                //     $eom = $weekmonth->copy()->endOfMonth()->startOfDay();
                // }

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter == 'last_12_months')
            {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }
            elseif($filter=='custom')
            {
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');
                $now        = strtotime($endDate);
                $your_date  = strtotime($startDate);
                $dateDiff   = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                // $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->get();
                // $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->groupBy('sales_rep_email')->count();
                // $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('m2_date', '!=', null)->count();
                // $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '=', null)->where('m2_date', null)->count();
                // $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->count();
                // $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where($colmun, $condition, $values)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

                // $bestmonths = SalesMaster::selectRaw('customer_signoff, year(customer_signoff) year, monthname(customer_signoff) month, sum(kw) As kw_total')
                // ->whereBetween('customer_signoff',[$startDate,$endDate])
                // ->where($colmun, $condition, $values)
                // ->groupBy('year', 'month')
                // ->orderBy('kw_total', 'desc')
                // ->first();

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }

        }else{

            $data = array();
            $totalSales = SalesMaster::get();
            $m2Complete = SalesMaster::where('m2_date', '!=', null)->count();
            $m2Pending  = SalesMaster::where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            $cancelled  = SalesMaster::where('date_cancelled', '!=', null)->count();
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            Carbon::now()->subMonths(1)->endOfMonth();
        }

        if ($office_id!='all')
        {
            $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->get();
            $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->groupBy('sales_rep_email')->get();
            $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
            $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', null)->where('m2_date', '=', null)->count();
            $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereNotIn('pid',$clawbackPid)->count();
            $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(kw as decimal(5,2))) As kw')
            ->whereBetween('customer_signoff',[$startDate,$endDate])
            ->whereIn('pid',$salesPid)
            ->groupBy('month')
            ->orderBy('kw', 'desc')
            ->first();

            $bestweek =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
            sum(cast(kw as decimal(5,2))) As kw ,
            STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
            adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
                        ->whereBetween('customer_signoff',[$startDate,$endDate])
                        ->whereIn('pid',$salesPid)
                        ->groupBy('week')
                        ->orderBy('kw', 'desc')
                        ->first();
                        $bsDate = isset($bestweek->startweek)?$bestweek->startweek:null;
                        $beDate = isset($bestweek->endweek)?$bestweek->endweek:null;
            $bestweek['date'] = [$bsDate,$beDate];
            unset($bsDate);
            unset($beDate);

            //$bestDay = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->selectRaw("cast(kw as decimal(5,2)) as kw , customer_signoff as date")->whereIn('pid',$salesPid)->orderBy('kw', 'desc')->first();
            $bestDay = SalesMaster::select(DB::raw('SUM(kw) as kw'), 'customer_signoff as date')
            ->whereBetween('customer_signoff',[$startDate,$endDate])
            ->whereIn('pid',$salesPid)
            ->groupBy('customer_signoff')
            ->orderByDesc('kw')
            ->first();

        }else{
            $totalSales = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->get();
            //return count($totalSales);
            $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->groupBy('sales_rep_email')->get();
            $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
            $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->count();
            $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->whereNotIn('pid',$clawbackPid)->count();
            $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(kw as decimal(5,2))) As kw')
                                    ->whereBetween('customer_signoff',[$startDate,$endDate])
                                    ->groupBy('month')
                                    ->orderBy('kw', 'desc')
                                    ->first();

          $bestDay = SalesMaster::select(DB::raw('SUM(kw) as kw'), 'customer_signoff as date')
                                    ->whereBetween('customer_signoff',[$startDate,$endDate])
                                    ->groupBy('customer_signoff')
                                    ->orderByDesc('kw')
                                    ->first();
           // $bestDay = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->selectRaw("cast(kw as decimal(5,2)) as kw , customer_signoff as date")->orderBy('kw', 'desc')->first();





          $bestweek =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
            sum(cast(kw as decimal(5,2))) As kw ,
            STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
            adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
                        ->whereBetween('customer_signoff',[$startDate,$endDate])
                        ->groupBy('week')
                        ->orderBy('kw', 'desc')
                        ->first();


            $startweek = isset($bestweek->startweek)?$bestweek->startweek:null;
            $endweek = isset($bestweek->endweek)?$bestweek->endweek:null;
            $bestweek['date'] = [$startweek,$endweek];
            unset($bestweek->startweek);
            unset($bestweek->endweek);

        }
        if (count($totalSales) == 0) {
            return response()->json([
                'ApiName' => 'sales_graph_data',
                'status' => true,
                'message' => 'Successfully.',
                'data' => [],
            ], 200);
        }

        // $bestDay = [];
        // if ($sales) {
        //     $bestDay = array(
        //         'kw' => $sales->kw,
        //         'date' => date('Y-m-d', strtotime($sales->customer_signoff)),
        //     );
        // }


        // $bestMonth = [];
        // $bestweekdata = [];
        // if ($bestmonths) {
        //     $bestMonth = array(
        //         'kw' => round($bestmonths->kw_total),
        //         'date' => date('Y-m-d', strtotime($bestmonths->customer_signoff)),
        //     );


        //     $dates = [];
        //     $f = 'Y-m-d';
        //     for($i = 1; $date->lte($eom); $i++){
        //         //record start date
        //         $startDate = $date->copy();
        //         //loop to end of the week while not crossing the last date of month
        //         while($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)){
        //                 $date->addDay();
        //             }

        //         //$dates['w'.$i] = [$startDate->format($f), $date->format($f)];
        //         if ($date->format($f) < $eom->format($f)) {
        //             $dates['w'.$i] = [$startDate->format($f), $date->format($f)];
        //         }else{
        //             $dates['w'.$i] = [$startDate->format($f), $eom->format($f)];
        //         }
        //         $date->addDay();
        //     }

        //     $bestweeks = [];

        //     foreach($dates as $key => $weekdate) {

        //         if ($office_id!='all')
        //         {
        //             $kwtotals = SalesMaster::whereBetween('customer_signoff', $weekdate)->whereIn('pid',$salesPid)->sum('kw');
        //         }else{
        //             $kwtotals = SalesMaster::whereBetween('customer_signoff', $weekdate)->sum('kw');
        //         }
        //         if($kwtotals)
        //         {
        //             $bestweeks[] = array(
        //                 'kw' => round($kwtotals),
        //                 'date' => $weekdate,
        //                     );
        //         }

        //     }

        //     $keyid = array_search(max($bestweeks), $bestweeks);

        //     $bestweekdata = $bestweeks[$keyid];
        // }

        $total_kw_installed = 0;
        $total_kw_pending = 0;
        $total_kw = 0;
        $total_revenue_generated = 0;
        $total_revenue_pending = 0;
        $avg_profit_per_rep = 0;

        foreach ($totalSales as $key => $sale) {
            if($sale->m2_date != null && $sale->date_cancelled == null){
                $total_kw_installed = round(($total_kw_installed + $sale->kw),3);
            }elseif($sale->m2_date == null && $sale->date_cancelled == null){

                $total_kw_pending = round(($total_kw_pending + $sale->kw),3);
            }

            if($sale->m2_date == null && $sale->date_cancelled == null){
                $total_revenue_pending = round(($total_revenue_pending + $sale->gross_account_value),3);
            }else{
                //$total_revenue_generated = round(($total_revenue_generated + $sale->gross_account_value),3);
            }
            $total_revenue_generated = round(($total_revenue_generated + $sale->gross_account_value),3);
            $total_kw = round(($total_kw + $sale->kw),3);

        }

        $avg_profit_per_rep = round(($total_revenue_generated / count($totalSales)),3);
        $totalReps = User::where('is_super_admin','!=',1)->get();
        $totalKw = $total_kw_pending+$total_kw_installed;
        $data['best_avg'] = array(
            'bestDay' => $bestDay,
            'bestWeek' => $bestweek,
            'bestMonth'  => $bestMonth,
            'avg_account_per_rep'  => round(count($totalSales)/count($totalReps),2),
            'avg_kw_per_rep'  => round($totalKw/count($totalReps),2),

        );

        $data['accounts'] = array(
            'total_sales' => count($totalSales),
            'm2_complete' => $m2Complete,
            'm2_pending'  => $m2Pending,
            'cancelled'   => ($cancelled),
            'clawback'    => $clawback
        );

        if($m2Complete>0 && count($totalSales)>0)
        {
            $data['install_ratio'] = [
                'install' => round((($m2Complete / (count($totalSales)-$m2Pending) )* 100 ),3).'%',
                'uninstall' => round(100 - ($m2Complete / count($totalSales) * 100 ),3).'%',
                ];
        }
        else{
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '0%',
                ];
        }

        $data['contracts'] = [
            'avg_profit_per_rep' => $avg_profit_per_rep,
            'total_kw_installed' => $total_kw_installed,
            'total_kw_pending'   => $total_kw_pending,
            'total_revenue_generated' => $total_revenue_generated,
            'total_revenue_pending' => $total_revenue_pending,
            ];

        return response()->json([
                'ApiName' => 'sales_graph_data',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
    }

    public function sales_export(Request $request)
    {
        $companyProfile = CompanyProfile::first();
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '-1');
        $result = SalesMaster::with('salesMasterProcess.status',
            'salesMasterProcess.closer1Detail:id,first_name,last_name,email', 
            'salesMasterProcess.closer2Detail:id,first_name,last_name,email', 
            'salesMasterProcess.setter1Detail:id,first_name,last_name,email', 
            'salesMasterProcess.setter2Detail:id,first_name,last_name,email', 
            'userDetail', 
            'override.user');
        if ($request->has('filter') && !empty($request->input('filter'))) {
            $filterDataDateWise = $request->input('filter');
            $filterDate = getFilterDate($filterDataDateWise);
            if (!empty($filterDate['startDate']) && !empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterDataDateWise == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Get Sales Report API',
                    'status' => false,
                    'message' => 'Failed',
                ], 400);
            }
            $result = SalesMaster::with('productdata')->whereBetween('customer_signoff', [$startDate, $endDate]);
        }

        if ($request->has('order_by') && !empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('office_id') && !empty($request->input('office_id'))) {
            $office_id = $request->office_id;
            if ($office_id != 'all') {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');

                $result->where(function ($query) use ($request, $orderBy, $salesPid) {
                    return $query->whereIn('pid', $salesPid);
                });
            }
        }

        if ($request->has('location') && !empty($request->input('location')) && 1 == 2) {
            if ($request->location != 'all') {
                $result->where(function ($query) use ($request, $orderBy) {
                    return $query->where('customer_state', '=', $request->location);
                });
            }
        }

        if ($request->has('search') && !empty($request->input('search'))) {
            $result->where(function ($query) use ($request, $orderBy, $companyProfile) {
                return $query->where('customer_name', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('date_cancelled', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('customer_state', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('customer_city', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('sales_rep_name', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('net_epc', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('pid', 'LIKE', '%' . $request->input('search') . '%')
                    ->orWhere('job_status', 'LIKE', '%' . $request->input('search') . '%');
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $query->orWhere('gross_account_value', 'LIKE', '%' . $request->input('search') . '%');
                } else {
                    $query->orWhere('kw', 'LIKE', '%' . $request->input('search') . '%');
                }
            });
        }

        if ($request->has('date_filter') && !empty($request->input('date_filter'))) {
            if ($request->input('date_filter') == 'm1_date') {
                $result->whereNotNull('m1_date');
            } else if ($request->input('date_filter') == 'm2_date') {
                $result->whereNotNull('m2_date');
            } else if ($request->input('date_filter') == 'm1_date_m2_date') {
                $result->whereNotNull('m1_date')->whereNotNull('m2_date');
            } else if ($request->input('date_filter') == 'cancel_date') {
                $result->whereNotNull('date_cancelled');
            } else if ($request->input('date_filter') == 'm1_paid') {
                $result->whereHas('commission', function ($q) {
                    $q->where(['amount_type' => 'm1', 'status' => '3']);
                });
            } else if ($request->input('date_filter') == 'm2_paid') {
                $result->whereHas('commission', function ($q) {
                    $q->where(['amount_type' => 'm2', 'status' => '3']);
                });
            }
        }
        $data = $result->orderBy('id', $orderBy)->get();

        if (sizeof($data) == 0) {
            return response()->json([
                'ApiName' => 'sales_export',
                'status' => false,
                'message' => 'Data not found!'
            ], 400);
        }

        $data->transform(function ($data) {
            $closer1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->salesMasterProcess->closer1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            $closer1_m1 = 0;
            $closer1_m2 = 0;
            foreach ($closer1Commissions as $closer1Commission) {
                if ($closer1Commission->amount_type == 'm1') {
                    $closer1_m1 = $closer1Commission->commission;
                } else if ($closer1Commission->amount_type == 'm2') {
                    $closer1_m2 = $closer1Commission->commission;
                }
            }

            $closer2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->salesMasterProcess->closer2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            $closer2_m1 = 0;
            $closer2_m2 = 0;
            foreach ($closer2Commissions as $closer2Commission) {
                if ($closer2Commission->amount_type == 'm1') {
                    $closer2_m1 = $closer2Commission->commission;
                } else if ($closer2Commission->amount_type == 'm2') {
                    $closer2_m2 = $closer2Commission->commission;
                }
            }

            $setter1_m1 = 0;
            $setter1_m2 = 0;
            if ($data->salesMasterProcess->setter1_id && $data->salesMasterProcess->setter1_id != $data->salesMasterProcess->closer1_id) {
                $setter1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                    ->where(['user_id' => $data->salesMasterProcess->setter1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                foreach ($setter1Commissions as $setter1Commission) {
                    if ($setter1Commission->amount_type == 'm1') {
                        $setter1_m1 = $setter1Commission->commission;
                    } else if ($setter1Commission->amount_type == 'm2') {
                        $setter1_m2 = $setter1Commission->commission;
                    }
                }
            }

            $setter2_m1 = 0;
            $setter2_m2 = 0;
            if ($data->salesMasterProcess->setter2_id && $data->salesMasterProcess->setter2_id != $data->salesMasterProcess->closer2_id) {
                $setter2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                    ->where(['user_id' => $data->salesMasterProcess->setter2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
                foreach ($setter2Commissions as $setter2Commission) {
                    if ($setter2Commission->amount_type == 'm1') {
                        $setter1_m1 = $setter2Commission->commission;
                    } else if ($setter2Commission->amount_type == 'm2') {
                        $setter1_m2 = $setter2Commission->commission;
                    }
                }
            }

            $commissionData = UserCommission::where(['pid' => $data->pid, 'status' => 3])->first();
            if (!in_array($data->salesMasterProcess->mark_account_status_id, [1, 6]) && $commissionData) {
                $mark_account_status_name = ($commissionData) ? 'Paid' : null;
            } else {
                $mark_account_status_name = isset($data->salesMasterProcess->status->account_status) ? $data->salesMasterProcess->status->account_status : null;
            }

            $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
            $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
            $total_commission = $total_m1 + $total_m2;

            $stateCode = null;
            $locationCode = null;
            if (config("app.domain_name") == 'flex') {
                if ($state = State::where('state_code', $data->customer_state)->first()) {
                    $stateCode = $state->state_code;
                    $locationCode = $state->general_code;
                }
            } else {
                if ($location = Locations::with('State')->where('general_code', $data->location_code)->first()) {
                    $stateCode = $location->State->state_code;
                    $locationCode = $location->general_code;
                } else {
                    if ($state = State::where('state_code', $data->customer_state)->first()) {
                        $stateCode = $state->state_code;
                    }
                }
            }

            $accountOverrides = $data->override;
            if (count($accountOverrides) > 0) {
                $accountOverrides->transform(function ($override) use ($data) {
                    if ($override->sale_user_id == $data->salesMasterProcess->closer1_id || $override->sale_user_id == $data->salesMasterProcess->closer2_id) {
                        $positionName = 'Closer';
                    } else {
                        $positionName = 'Setter';
                    }
                    $image = $override->user->image ?? null;
                    $first_name = $override->user->first_name ?? null;
                    $last_name = $override->user->last_name ?? null;
                    return [
                        'through' => $positionName,
                        'image' => $image,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'type' => $override->type,
                        'amount' => $override->overrides_amount,
                        'weight' => $override->overrides_type,
                        'total' => $override->amount,
                        'calculated_redline' => $override->calculated_redline,
                        'assign_cost' => null
                    ];
                });
            } else {
                $accountOverrides = "";
            }

            $commissionData = UserCommission::where(['pid' => $data->pid, 'status' => 3])->first();
            if (!in_array($data->salesMasterProcess->mark_account_status_id, [1, 6]) && $commissionData) {
                $mark_account_status_name = ($commissionData) ? 'Paid' : null;
            } else {
                $mark_account_status_name = isset($data->salesMasterProcess->status->account_status) ? $data->salesMasterProcess->status->account_status : null;
            }

            return [
                'pid' => $data->pid,
                'closer_1_email' => (isset($data->salesMasterProcess->closer1Detail->email) ? $data->salesMasterProcess->closer1Detail->email : ''),
                'closer_2_email' => (isset($data->salesMasterProcess->closer2Detail->email) ? $data->salesMasterProcess->closer2Detail->email : ''),
                'setter_1_email' => (isset($data->salesMasterProcess->setter1Detail->email) ? $data->salesMasterProcess->setter1Detail->email : ''),
                'setter_2_email' => (isset($data->salesMasterProcess->setter2Detail->email) ? $data->salesMasterProcess->setter2Detail->email : ''),
                'customer_name' => $data->customer_name,
                'source' => $data->data_source_type,
                'state' => $stateCode,
                'closer_1' => $data?->salesMasterProcess?->closer1Detail?->first_name . " " . $data?->salesMasterProcess?->closer1Detail?->last_name,
                'closer_2' => $data?->salesMasterProcess?->closer2Detail?->first_name . " " . $data?->salesMasterProcess?->closer2Detail?->last_name,
                'setter_1' => $data?->salesMasterProcess?->setter1Detail?->first_name . " " . $data?->salesMasterProcess?->setter1Detail?->last_name,
                'setter_2' => $data?->salesMasterProcess?->setter2Detail?->first_name . " " . $data?->salesMasterProcess?->setter2Detail?->last_name,
                'kw' => $data->kw,
                'mark_account_status_name' => $mark_account_status_name,
                'm1' => $total_m1,
                'm1_date' => $data->m1_date,
                'm2' => $total_m2,
                'm2_date' => $data->m2_date,
                'epc' => $data->epc,
                'net_epc' => $data->net_epc,
                'adders' => $data->adders,
                'total_commission' => $total_commission,
                'installer' => $data->installer ?? "",
                'customer_state' => $stateCode ?? "",
                'location_code' => $locationCode ?? "",
                'customer_signoff' => $data->customer_signoff ?? "",
                'customer_address' => $data->customer_address ?? "",
                'customer_address_2' => $data->customer_address_2 ?? "",
                'homeowner_id' => $data->homeowner_id ?? "",
                'customer_city' => $data->customer_city ?? "",
                'customer_zip' => $data->customer_zip ?? "",
                'customer_email' => $data->customer_email ?? "",
                'customer_phone' => $data->customer_phone ?? "",
                'proposal_id' => $data->proposal_id ?? "",
                'date_cancelled' => $data->date_cancelled ?? "",
                'product' => isset($data->productdata)?$data->productdata->name:"",
                'product_code' => isset($data->productdata)?$data->productdata->product_id:"",
                'gross_account_value' => $data->gross_account_value ?? "",
                'dealer_fee_percentage' => $data->dealer_fee_percentage ?? "",
                'dealer_fee_amount' => $data->dealer_fee_amount ?? "",
                'show' => $data->show ?? "",
                'job_status' => $data->job_status ?? ""
            ];
        });

        if ($request->has('sort') && $request->input('sort') == 'kw') {
            $data = json_decode($data);
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'gross_account_value'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'gross_account_value'), SORT_ASC, $data);
                }
            } else {
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'kw'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'kw'), SORT_ASC, $data);
                }
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'epc') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'epc'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'epc'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'net_epc') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'net_epc'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'net_epc'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'adders') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'adders'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'adders'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'state') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'state'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'state'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'm1') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_m1'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_m1'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'm2') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_m2'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_m2'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'total_commission') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_commission'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_commission'), SORT_ASC, $data);
            }
        }

        $file_name = 'sales_export_' . date('Y-m-d') . '.xlsx';
        Excel::store(new \App\Exports\ExportReports\Sales\SalesReportExport($data), 'exports/reports/sales/' . $file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath('exports/reports/sales/' . $file_name);
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    // Function to fetch user commissions
    protected function fetchUserCommissions($userId, $pid) {
        $commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
            ->where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])
            ->groupBy('amount_type')
            ->get();
    
        $m1 = 0;
        $m2 = 0;
        foreach ($commissions as $commission) {
            if ($commission->amount_type == 'm1') {
                $m1 = $commission->commission;
            } else if ($commission->amount_type == 'm2') {
                $m2 = $commission->commission;
            }
        }
        return [$m1, $m2];
    }

    public function reconciliation_report_old(Request $request)
    {

    	$result = array();
        $office_id = $request->office_id;
        $location = $request->location;
    	$filter   = $request->filter;

        $startDate = '';
        $endDate   = '';

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            $filterDataDateWise = $request->input('filter');
            if($filterDataDateWise=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));

            }
            else if($filterDataDateWise=='last_week')
            {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
                $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
            }
            else if($filterDataDateWise=='this_month')
            {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            }

            else if($filterDataDateWise=='last_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='this_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            }
            else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }

            else if($filterDataDateWise=='custom')
            {
                $startDate = $request->input('start_date');
                $endDate   = $request->input('end_date');

            }

        }

        if ($request->has('start_date') && !empty($request->input('start_date')))
        {
            $startDate = $request->input('start_date');
        }
        if ($request->has('end_date') && !empty($request->input('end_date')))
        {
            $endDate   = $request->input('end_date');
        }

        if ($request->has('order_by') && !empty($request->input('order_by'))){
            $orderBy = $request->input('order_by');
            }else{
                $orderBy = 'asc';
            }

        $users = \DB::table('users as u')
                    ->select('u.id','u.first_name','u.last_name','u.image','u.position_id', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->whereIn('u.position_id', [1,2,3]);

        if ($request->has('office_id') && !empty($request->input('office_id')))
        {
            $office_id = $request->office_id;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');
                //$salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');

                $users->where(function($query) use ($request, $userId) {
                    return $query->whereIn('u.id', $userId);
                    });


            }

        }

        if ($request->has('location') && !empty($request->input('location')) && 1==2)
        {
            if ($location!='all')
            {
                $users->where(function($query) use ($request) {
                    return $query->where('s.state_code','=', $request->location);
                    });
            }
        }
        if ($request->has('search') && !empty($request->input('search')))
        {
            $users->where(function($query) use ($request) {
                return $query->where('u.first_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('u.last_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('s.name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('s.state_code', 'LIKE', '%'.$request->input('search').'%');
                });
        }
        $userdata = $users->orderBy('u.id', $orderBy)->get();
        // return $userdata;
        $data = array();
        if (count($userdata) > 0) {
            $total_reconciliation = 0;
            foreach ($userdata as $key => $user) {
                if($user->position_id==2){
                    $closer_earn = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->whereBetween('created_at', [$startDate,$endDate])
                    ->sum('withhold_amount');
                    $closer_paid = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$startDate,$endDate])
                    ->sum('withhold_amount');

                    $closer_unpaid = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->where('status', 'unpaid')
                    ->whereBetween('created_at', [$startDate,$endDate])
                    ->sum('withhold_amount');
                    $total_reconciliation = ($total_reconciliation + $closer_earn);
                    if($closer_earn){
                        $data[] = array(
                            'id' => $user->id,
                            'position_id' => $user->position_id,
                            'emp_img' => $user->image,
                            'emp_name' => $user->first_name.' '.$user->last_name,
                            'total_earn' => $closer_earn,
                            'total_paid' => $closer_paid,
                            'commission_due' => $closer_unpaid,
                            'override_due' => '0',
                            'deduction_due' => '0',
                            'total_due' => '0',
                        );
                    }

                }

                if($user->position_id==3){
                    $setter_earn = UserReconciliationWithholding::where('setter_id', $user->id)
                                ->whereBetween('created_at', [$startDate,$endDate])
                                ->sum('withhold_amount');

                    $setter_paid = UserReconciliationWithholding::where('setter_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$startDate,$endDate])
                    ->sum('withhold_amount');

                    $setter_unpaid = UserReconciliationWithholding::where('setter_id', $user->id)
                    ->where('status', 'unpaid')
                    ->whereBetween('created_at', [$startDate,$endDate])
                    ->sum('withhold_amount');

                    $total_reconciliation = ($total_reconciliation + $setter_earn);
                    if($setter_earn){
                        //$data[] = $setter_id;
                        $data[] = array(
                                'id' => $user->id,
                                'position_id' => $user->position_id,
                                'emp_img' => $user->image,
                                'emp_name' => $user->first_name.' '.$user->last_name,
                                'total_earn' => $setter_earn,
                                'total_paid' => $setter_paid,
                                'commission_due' => $setter_unpaid,
                                'override_due' => '0',
                                'deduction_due' => '0',
                                'total_due' => '0',
                            );
                    }

                }

            }
            $result['total_reconciliation'] = $total_reconciliation;
            $result['result'] = $data;
	    	return response()->json([
                'ApiName' => 'reconciliation_report',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $result,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'reconciliation_report',
                'status' => false,
                'message' => 'data not found',
                'data' => $result,
            ], 200);

    	}
    }

    public function reconciliation_report(Request $request)
    {
    	$resultdata = array();
        $office_id = $request->office_id;
    	$search    = $request->search;
        $startDate = $request->start_date;
        $endDate   = $request->end_date;
        $orderBy = 'asc';

        $reconciliation = UserReconciliationCommission::where('status','<>','pending')->where(['period_from'=> $startDate,'period_to'=>$endDate]);

        if ($office_id!='all' || $search){
            $users = User::orderBy('id', $orderBy);
            if ($request->has('office_id') && !empty($request->input('office_id')))
            {
                if ($office_id!='all')
                {
                    $users->where(function($query) use ($request, $office_id) {
                        return $query->where('office_id', $office_id);
                    });


                }

            }

            if ($request->has('search') && !empty($request->input('search')))
            {
                $users->where(function($query) use ($request) {
                    return $query->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%');
                    });
            }

            $userids = $users->pluck('id')->toArray();
            $reconciliation->where(function($query) use ($request,$userids) {
                return $query->whereIn('user_id', $userids);
            });
        }

        $result = $reconciliation->get();

        $data = array();
        if (count($result) > 0) {
            $total_reconciliation = 0;
            foreach ($result as $key => $val) {
                $total_reconciliation = ($total_reconciliation + $val->amount);

                $userdata = User::where('id', $val->user_id)->first();

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $val->id)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due)?$reconciliationsAdjustment->commission_due:0;
                $overridesDue  = isset($reconciliationsAdjustment->overrides_due)?$reconciliationsAdjustment->overrides_due:0;
                $clawbackDue = isset($reconciliationsAdjustment->clawback_due)?$reconciliationsAdjustment->clawback_due:0;

                $totalAdjustments = $commissionDue+$overridesDue+$clawbackDue;

                $myArray[] = array(
                    'id' => $val->id,
                    'user_id' => $val->user_id,
                    'emp_img' => $userdata->image,
                    'emp_name' => $userdata->first_name . ' ' . $userdata->last_name,
                    'commissionWithholding' => $val->amount,
                    'overrideDue' => $val->overrides,
                    'clawbackDue' => $val->clawbacks,
                    'totalAdjustments' => isset($totalAdjustments)?$totalAdjustments:0,
                    'total_due' => $val->total_due,
                );
            }

            //$data = $this->paginate($myArray);
            $data = $myArray;

            $resultdata['total_reconciliation'] = $total_reconciliation;
            $resultdata['result'] = $data;

    	}
        if(isset($request->is_export) && ($request->is_export == 1))
        {
        $file_name = 'reconciliation_export_'.date('Y_m_d_H_i_s').'.csv';
        return  Excel::download(new ReportReconciliationExport($office_id, $startDate, $endDate), $file_name);
        }
        else
        {
                return response()->json([
                    'ApiName' => 'reconciliation_report',
                    'status' => true,
                    'message' => 'Successfully.',
                    'data' => $resultdata,
                ], 200);
        }
    }

    // public function paginate($items, $perPage = 10, $page = null, $options = [])
    // {
    //     $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    //     $items = $items instanceof Collection ? $items : Collection::make($items);
    //     return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    // }

    public function reconciliation_export(Request $request)
    {
        $location = $request->location;
    	$filter   = $request->filter;
        $startDate = '';
        $endDate = '';

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            $filterDataDateWise = $request->input('filter');
            if($filterDataDateWise=='this_week')
            {
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));

            }
            else if($filterDataDateWise=='this_month')
            {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            }
            else if($filterDataDateWise=='last_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            }
            else if($filterDataDateWise=='this_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

            }
            else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }
            else if($filterDataDateWise=='custom')
            {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            }

        }

        if ($request->has('location') && !empty($request->input('location')))
        {
            $location = $request->location;
        }

        $file_name = 'reconciliation_export_'.date('Y_m_d_H_i_s').'.csv';
        if($location != '' && $startDate!='' && $endDate!='')
        {
            return  Excel::download(new ReportReconciliationExport($location, $startDate, $endDate), $file_name);
        }
        else{
            return Excel::download(new ReportReconciliationExport, $file_name);
        }

    }

    public function costs_report(Request $request, ApprovalsAndRequest $approvalsandrequest)
    {
    	$result = array();
        $location = $request->location;
    	$filter   = $request->filter;

       $employee = $request->employee_id;
       $costHead = $request->cost_tracking_id;
       $approvedBy = $request->approved_by;
       $requestedOn = $request->requested_on;
        if(!empty($request->perpage)){
            $perpage = $request->perpage;
        }else{
            $perpage = 10;
        }
        $startDate = '';
        $endDate   = '';

        if (isset($filter) && $request->filter!=null)
        {
            $filterDataDateWise = $request->filter;
            if($filterDataDateWise=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));

            }
            else if($filterDataDateWise=='last_week')
            {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
                $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
            }
            else if($filterDataDateWise=='this_month')
            {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfMonth()));
                //$endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            }
            else if($filterDataDateWise=='last_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='this_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfYear()));
            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            }
            else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }
            else if($filterDataDateWise=='custom')
            {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));

            }
        }
        if ($request->has('order_by') && !empty($request->input('order_by'))){
            $orderBy = $request->input('order_by');
        }else{
            $orderBy = 'DESC';
        }

        $users = \DB::table('users as u')
                    ->select('u.id','u.first_name','u.last_name','u.image','u.position_id','u.sub_position_id','u.is_super_admin', 'u.is_manager', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->whereIn('u.position_id', [1,2,3]);

        if (isset($request->office_id) && $request->office_id!=null)
        {
            $office_id = $request->office_id;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');
                //$salesPid = SaleMasterProcess::whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');
                $users->where(function($query) use ($request, $userId) {
                    return $query->whereIn('u.id', $userId);
                    });
            }
        }

        if (isset($request->location) && $request->location!=null && 1==2)
        {
            if ($location!='all')
            {
                $users->where(function($query) use ($request) {
                    return $query->where('s.state_code','=', $request->location);
                    });
            }
        }

        if (isset($request->search) && $request->search!=null)
        {
            $users->where(function($query) use ($request) {
                // return $query->where('u.first_name', 'LIKE', '%'.$request->search.'%')
                //     ->orWhere('u.last_name', 'LIKE', '%'.$request->search.'%')
                //     ->orWhere('s.name', 'LIKE', '%'.$request->search.'%')
                //     ->orWhere('s.state_code', 'LIKE', '%'.$request->search.'%');
                // });
                $search = $request->search;
                return $query->where(function($q) use ($search){
                        $q->where('u.first_name', 'LIKE', '%'.trim($search).'%');
                        $q->orWhere('u.last_name', 'LIKE', '%'.trim($search).'%');
                        $q->orWhereRaw('CONCAT(u.first_name, " ", u.last_name) LIKE ?', ['%' .trim($search). '%']);
                    })
                    ->orWhere('s.name', 'LIKE', '%'.$request->search.'%')
                    ->orWhere('s.state_code', 'LIKE', '%'.$request->search.'%');
                });
        }
        $userdata = $users->orderBy('u.id', $orderBy)->get();

        //return $CostCenter;
        $data = array();
        if (count($userdata) > 0) {
            foreach ($userdata as $key => $user) {
                $record = new ApprovalsAndRequest();
                $record = $record->newQuery();

                if ($employee && !empty($employee))
                {
                    $record->where(function($query) use ($employee) {
                        return $query->where('user_id', $employee);
                        });
                }
                if (isset($costHead) && $costHead!=null)
                {
                    $record->where(function($query) use ($costHead) {
                        return $query->where('cost_tracking_id', $costHead);
                        });
                }
                if (isset($approvedBy) && $approvedBy!=null)
                {
                    $record->where(function($query) use ($approvedBy) {
                        // return $query->where('approved_by', $approvedBy);
                        return $query->whereNotNull('manager_id')
                                    ->where('manager_id', $approvedBy)
                                    ->orWhere(function ($q) use ($approvedBy) {
                                        $q->whereNull('manager_id')
                                            ->where('approved_by', $approvedBy);
                                    });
                        });
                }
                if (isset($requestedOn) && $requestedOn!=null)
                {
                    $record->where(function($query) use ($requestedOn) {
                        return $query->where('request_date', $requestedOn);
                        });
                }

                $s = $startDate.' 00:00:00';
                $e = $endDate.' 00:00:00';
                $record->with('adjustment', 'costcenter');
                $record->where('status','Approved');
                $record->where('user_id', $user->id);
                $record->whereBetween('cost_date', [$s,$e]);
                $records =  $record->get();
                //->sum('withhold_amount');

                if(count($records) > 0){
                    foreach ($records as $key1 => $value) {
                        if ($value->manager_id!=null) {
                            $approveUser = User::select('first_name', 'last_name')->where('id',$value->manager_id)->first();
                            $approvedby  = $approveUser->first_name.' '.$approveUser->last_name;
                        }else{
                            $approveU = User::select('first_name', 'last_name')->where('id',$value->approved_by)->first();
                            if($approveU){
                                $fn = $approveU->first_name;
                                $ln = $approveU->last_name;
                                $approvedby  = $fn.' '.$ln;
                            }
                        }
                        if(isset($user->image) && $user->image!=null){
                            $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
                        }else{
                            $image_s3 = null;
                        }
                        $data[] = array(
                            'emp_id' => $user->id,
                            'position_id' => $user->position_id,
                            'sub_position_id' => $user->sub_position_id,
                            'is_super_admin' => $user->is_super_admin,
                            'is_manager' => $user->is_manager,
                            'emp_img' => $user->image,
                            'emp_img_s3' => $image_s3,
                            'emp_name' => $user->first_name.' '.$user->last_name,
                            //'requested_on' => date('Y-m-d', strtotime($value->created_at)),
                            'requested_on' => $value->request_date,
                            'approved_by' =>isset($approvedby)?$approvedby:null ,
                            'amount' => isset($value->amount)?$value->amount:null,
                            'cost_tracking' => isset($value->costcenter->code)?$value->costcenter->code:null,
                            'description' => isset($value->description)?$value->description:null,
                            'dismiss' => isUserDismisedOn($user->id,date('Y-m-d')) ? 1 : 0,
                            'terminate' => isUserTerminatedOn($user->id,date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isUserContractEnded($user->id) ? 1 : 0,
                        );
                    }
                }
            }

            $costCenter = CostCenter::with('chields')->where('parent_id',NULL)->get();
            //$costCenter = CostCenter::with('chields')->whereIn('id',[1,9,7,6,12])->get();
            if(count($costCenter) > 0){
                $corporate = array();
                foreach ($costCenter as $key2 => $cost) {
                    $costId = [];
                    if(count($cost->chields) > 0){
                        foreach ($cost->chields as $key1 => $cost1) {
                            $costId[] = $cost1->id;
                        }
                    }else{
                        $costId[] = $cost->id;
                    }
                    //return $costId;
                    $approveData = new ApprovalsAndRequest();
                    $approveData->where('status','Approved')->select('cost_date')->selectRaw('sum(amount) As amount');
                    $approveData->whereIn('cost_tracking_id', $costId);
                    $approveData->whereBetween('cost_date', [$startDate,$endDate]);



                        if ($employee && !empty($employee))
                        {
                            $approveData->where(function($query) use ($employee) {
                                return $query->where('user_id', $employee);
                                });
                        }
                        if ($costHead && !empty($costHead))
                        {
                            $approveData->where(function($query) use ($costHead) {
                                return $query->where('cost_tracking_id', $costHead);
                                });
                        }
                        if ($approvedBy && !empty($approvedBy))
                        {
                            $approveData->where(function($query) use ($approvedBy) {
                                return $query->where('approved_by', $approvedBy);
                                });
                        }
                        if ($requestedOn && !empty($requestedOn))
                        {
                            $approveData->where(function($query) use ($requestedOn) {
                                return $query->where('request_date', $requestedOn);
                                });
                        }
                        $approvedata = $approveData->first();

                    if($approvedata) {
                        $corporate[$key2] = array(
                            'name' => $cost->name,
                            'code' => $cost->code,
                            'amount' => $approvedata->amount,
                            'year-to-date' => $approvedata->request_date,
                            'date' => date('Y-m-d', strtotime($approvedata->cost_date)),
                        );
                    }else{
                        $corporate[$key2] = array(
                            'name' => $cost->name,
                            'code' => $cost->code,
                            'amount' => 0,
                            'year-to-date' => 0,
                        );
                    }

                    if(count($cost->chields) > 0){
                        $child = [];
                        foreach ($cost->chields as $key3 => $cost1) {

                            $approvedata1 = ApprovalsAndRequest::select('cost_date')->selectRaw('sum(amount) As amount')
                                ->where('status','Approved')
                                ->where('cost_tracking_id', $cost1->id)
                                ->whereBetween('cost_date', [$startDate,$endDate])
                                ->first();
                            if($approvedata1) {
                                $child[$key3] = array(
                                    'name' => $cost1->name,
                                    'code' => $cost1->code,
                                    'amount' => $approvedata1->amount,
                                    'year_to_date' => $approvedata1->amount,
                                    'date' => date('Y-m-d', strtotime($approvedata1->cost_date)),
                                );
                            }else{
                                $child[$key3] = array(
                                    'name' => $cost1->name,
                                    'code' => $cost1->code,
                                    'amount' => 0,
                                    'year_to_date' => 0,
                                );
                            }
                            $corporate[$key2]['childs'] = $child;
                        }

                    }
                }
            }
            // return $corporate;
            $corporate = $this->paginates($corporate, $perpage);
            $data = $this->paginates($data, $perpage);
            $result['corporate'] = $corporate;
            $result['list_data'] = $data;

	    	return response()->json([
                'ApiName' => 'costs_report',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $result,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'costs_report',
                'status' => false,
                'message' => 'data not found',
                'data' => $result,
            ], 200);

    	}
    }

    public function costs_graph(Request $request)
    {
    	$result = array();
        $office_id = $request->office_id;
        $location = $request->location;
    	$filter   = $request->filter;

        $startDate = '';
        $endDate   = '';

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            $filterDataDateWise = $request->input('filter');
            if($filterDataDateWise=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));

            }
            else if($filterDataDateWise=='last_week')
            {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
                $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
            }
            else if($filterDataDateWise=='this_month')
            {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='last_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='this_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfYear()));
            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            }
            else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }

            else if($filterDataDateWise=='custom')
            {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));

            }

        }

        if ($request->has('office_id') && !empty($request->input('office_id')))
        {
            $office_id = $request->office_id;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');

            }
        }

        if ($request->has('location') && !empty($request->input('location')) && 1==2)
        {
            if ($location!='all')
            {
                $state = State::where('state_code', $location)->first();
                $colmun = 'state_id';
                $condition = '=';
                $values = $state->id;
                // $records->where(function($query) use ($request,$state) {
                //     return $query->where('state_id','=', $state->id);
                //     });
            }else{
                $colmun = 'id';
                $condition = '<>';
                $values = '0';
            }
        }
        //$records = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->where($colmun, $condition, $values);

        $data = array();
         $costCenters = CostCenter::with('chields')->whereIn('id',[1,9,7,6,12])->where('parent_id',NULL)->get();
        if(count($costCenters) > 0){
            $totalCost = 0;
            foreach ($costCenters as $key => $cost) {
                if ($office_id!='all')
                {
                    //$userId = User::where('office_id', $office_id)->pluck('id');
                    $rentdata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                            ->where('cost_tracking_id',9)->sum('amount');

                }else{
                    $rentdata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                            ->where('cost_tracking_id',9)->sum('amount');
                }

                // return $rentdata;
                $costId = [];
                if(count($cost->chields) > 0){
                    $dd[$cost->id] = $cost->chields;
                    foreach ($cost->chields as $key1 => $cost1) {
                        $costId[] = $cost1->id;
                    }
                }else{
                    $costId[] = $cost->id;
                }

                if ($office_id!='all')
                {
                    $count = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)->whereIn('cost_tracking_id', $costId)->sum('amount');
                }else{
                    $count = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('cost_tracking_id', $costId)->sum('amount');
                }
                $totalCost = ($totalCost + $count);
                $data[] = array(
                    'name' => $cost->name,
                    'amount' => $count,
                );
            }

            $result['cost_tracking']['total_costs'] = $totalCost;
            $result['cost_tracking']['data'] = $data;

            if ($office_id!='all')
            {
                $allRecords = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                        ->orderBy('cost_date', 'asc')->get();
            }else{
                $allRecords = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                        ->orderBy('cost_date', 'asc')->get();
            }


            $travelId = CostCenter::where('parent_id', 1)->pluck('id');
            if ($office_id!='all')
            {
                $travelcount = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                        ->whereIn('cost_tracking_id', $travelId)->count();

                $traveldata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                        ->whereIn('cost_tracking_id', $travelId)->sum('amount');
            }else{
                $travelcount = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                        ->whereIn('cost_tracking_id', $travelId)->count();

                $traveldata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                        ->whereIn('cost_tracking_id', $travelId)->sum('amount');
            }
            //$rentId = CostCenter::where('parent_id', 9)->pluck('id');


            $housingId = CostCenter::where('parent_id', 3)->pluck('id');
            if ($office_id!='all')
            {
                $housingcount = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                            ->whereIn('cost_tracking_id', [9])->count();
                $housingdata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])->whereIn('user_id', $userId)
                            ->whereIn('cost_tracking_id', [9])->sum('amount');


            }else{
                $housingcount = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                            ->whereIn('cost_tracking_id', [9])->count();
                $housingdata = ApprovalsAndRequest::where('status','Approved')->whereBetween('cost_date', [$startDate,$endDate])
                            ->whereIn('cost_tracking_id', [9])->sum('amount');
            }

            $noOfRecord = count($allRecords);

            $totalAmount = 0;
            if($noOfRecord > 0){
                foreach ($allRecords as $keys => $record) {
                    $totalAmount = ($totalAmount + $record->amount);
                }
            }

            $avg_cost_per_rep = ($totalAmount!=0)?($totalAmount/$noOfRecord):0;
            $avg_travel_per_month = ($traveldata/12);
            $avg_rent_per_month = ($rentdata/12);

            $result['contracts']['avg_cost_per_rep'] = round($avg_cost_per_rep);
            $result['contracts']['avg_rent_per_month'] = round($avg_rent_per_month);
            $result['contracts']['avg_travel_per_month'] = round($avg_travel_per_month);
            $result['contracts']['total_costs_incurred'] = round($totalAmount);

            $avg_housing_cost_per_rep = ($housingdata!=0)?($housingdata/$housingcount):0;
            $avg_travel_cost_per_rep  = ($traveldata!=0)?($traveldata/$travelcount):0;
            $result['avg_costs']['avg_housing_cost_per_rep'] = round($avg_housing_cost_per_rep);
            $result['avg_costs']['avg_travel_cost_per_rep']  = round($avg_travel_cost_per_rep);

            // monthly costs trends code------

            $bestmonths = ApprovalsAndRequest::with('costcenter')->selectRaw('cost_tracking_id, sum(amount) As amount_total')
                ->where('status','Approved')
                ->whereNotNull('cost_tracking_id')
                ->whereYear('cost_date', date('Y'))->whereMonth('cost_date', date('m'))
                ->groupBy('cost_tracking_id')
                ->orderBy('amount_total', 'desc')
                ->get();
            
            if(count($bestmonths) > 0){

            $highAmount   = $bestmonths[0]['amount_total'];
            $highCostId   = $bestmonths[0]['cost_tracking_id'];
            // $highCostName = $bestmonths[0]['costcenter']['name'];
            $highCostName = isset($bestmonths[0]['costcenter']['name'])? $bestmonths[0]['costcenter']['name'] : null;

            $lowAmount   = $bestmonths[count($bestmonths) - 1]['amount_total'];
            $lowCostId   = $bestmonths[count($bestmonths) - 1]['cost_tracking_id'];
            // $lowCostName = $bestmonths[count($bestmonths) - 1]['costcenter']['name'];
            $lowCostName = isset($bestmonths[count($bestmonths) - 1]['costcenter']['name'])? $bestmonths[count($bestmonths) - 1]['costcenter']['name'] : null;

            $lastMonth = Carbon::now()->subMonth()->month;
            $lastmonthAmountHigh = ApprovalsAndRequest::where('cost_tracking_id', $highCostId)->where('status','Approved')
                ->whereYear('cost_date', date('Y'))->whereMonth('cost_date', $lastMonth)
                ->sum('amount');

            $lastmonthAmountLow = ApprovalsAndRequest::where('cost_tracking_id', $lowCostId)->where('status','Approved')
                ->whereYear('cost_date', date('Y'))->whereMonth('cost_date', $lastMonth)
                ->sum('amount');
                if($highAmount != 0 && $lastmonthAmountHigh != 0){
                    $highPercentage = (($highAmount - $lastmonthAmountHigh) / $lastmonthAmountHigh * 100);
                    $lowPercentage  = (($lowAmount - $lastmonthAmountLow) / $lastmonthAmountLow * 100);
                }
            }

            $high = array(
                'cost_name' => isset($highCostName)?$highCostName:null,
                'amount' => isset($highAmount)?$highAmount:'0',
                'percentage' => isset($highPercentage)?$highPercentage:'0',
            );
            $low = array(
                'cost_name' => isset($lowCostName)?$lowCostName:'',
                'amount' => isset($lowAmount)?$lowAmount:'0',
                'percentage' => isset($lowPercentage)?$lowPercentage:'0',
            );

            $result['monthly_costs_trends']['high'] = $high;
            $result['monthly_costs_trends']['low']  = $low;

            // End monthly costs trends code------

	    	return response()->json([
                'ApiName' => 'costs_graph',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $result,
            ], 200);
    	}
    	else{
    		return response()->json([
                'ApiName' => 'costs_graph',
                'status' => false,
                'message' => 'data not found',
                'data' => $result,
            ], 200);

    	}
    }

    public function costs_export(Request $request)
    {
        $location = $request->location;
    	$filter   = $request->filter;
        $startDate = '';
        $endDate = '';

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            $filterDataDateWise = $request->input('filter');
            $filterDate = getFilterDate($filterDataDateWise);
            if (!empty($filterDate['startDate']) && !empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterDataDateWise == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Get Sales Report API',
                    'status' => false,
                    'message' => 'Failed',                    
                ], 400);
            }
            /* if($filterDataDateWise=='this_week')
            {
                $currentDate = Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));

            }
            else if($filterDataDateWise=='last_week')
            {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
                $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
            }
            else if($filterDataDateWise=='this_month')
            {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));
            }
            else if($filterDataDateWise=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate   =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='last_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            }
            else if($filterDataDateWise=='this_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            }
            else if($filterDataDateWise=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            }
            else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()));
            }

            else if($filterDataDateWise=='custom')
            {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));

            } */

        }
        if ($request->has('location') && !empty($request->input('location')))
        {
            $location = $request->location;
        }

        $file_name = 'costs_export_'.date('Y_m_d_H_i_s').'.xlsx';

        if($location != '' && $startDate!='' && $endDate!='')
        {
            Excel::store(new ReportCostsExport($location, $startDate, $endDate),
            'exports/reports/costs/'.$file_name, 
            'public', 
            \Maatwebsite\Excel\Excel::XLSX);
            //return  Excel::download(new ReportCostsExport($location, $startDate, $endDate), $file_name);
        }
        else{
            Excel::store(new ReportCostsExport(), 
            'exports/reports/costs/'.$file_name, 
            'public', 
            \Maatwebsite\Excel\Excel::XLSX);
            // return Excel::download(new ReportCostsExport, $file_name);
        }
        $url = getStoragePath('exports/reports/costs/' . $file_name);
        // $url = getExportBaseUrl().'storage/exports/reports/costs/' . $file_name;
        // Get the URL for the stored file
        // Return the URL in the API response
        return response()->json(['url' => $url]);

    }

    public function api_log_report(Request $request){
        $crm_id = isset($request->crm_id)?$request->crm_id:0;
        $data_query = LegacyWeeklySheet::where('crm_id',$crm_id);
        $per_page = isset($request->per_page) && $request->per_page > 0 ? $request->per_page : config('app.paginate', 15);
        if(isset($request->date) && $request->date != ''){
            $data_query = $data_query->where('week_date' ,$request->date);
        }
        $data = $data_query->orderBy('id','DESC')->paginate($per_page);
        foreach($data as $key => $row){
            $row['week_date'] = $row['created_at'];
            if ($row['crm_id']==1) {
                $s3Folder = 'legacy-raw-data-files';
            }
            elseif ($row['crm_id'] == 2){
                $s3Folder = '';
            }
            elseif ($row['crm_id'] == 3){
                $s3Folder = '';
            }
            elseif ($row['crm_id'] == 4){
                $s3Folder = 'JobNimbus-raw-data-files';
            }
            else{
                $s3Folder = '';
            }
            if(!empty($row['log_file_name'])){
                // if(Storage::disk('s3_private')->exists('legacy-raw-data-files/'.$row['log_file_name'])){
                $file_url = s3_getTempUrl(config('app.domain_name').'/'.$s3Folder . '/'.$row['log_file_name']);
                if(!empty($row['log_file_name'])){
                    $getPrivateUrl =  s3_getTempUrl(config('app.domain_name').'/'.$s3Folder . '/'.$row['log_file_name']);
                    $data[$key]['log_file_name'] = $getPrivateUrl;
                }else{
                    $data[$key]['log_file_name'] = null;
                }
                //$data[$key]['log_file_name'] = 'https://sequifi.s3.us-west-1.amazonaws.com/Legacy_api_response/'.$row['log_file_name'];  //asset('storage/'.$row['log_file_name']);
            }else{
                $data[$key]['log_file_name'] = null;
            }
        }
        return response()->json([
            'ApiName' => 'api_log_report',
            'status' => true,
            'message' => 'Successfully.',
            'data_count' => count($data),
            'data' => $data,
        ], 200);

    }

    public function sales_import_validation(Request $request)
    {
        // return response()->json(['error' => "Under maintenance", 'message' => "Under maintenance"], 400); 
        // CHECK OTHER EXCEL IS BEING IMPORTED OR NOT
        if (LegacyApiRawDataHistory::where(['import_to_sales' => '0', 'data_source_type' => 'excel'])->first()) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to import sales information. Our system is currently importing the other excel. Please try again later. Thank you for your patience.'], 400);
        }

        // CHECK THE PAYROLL IS FINALIZED OR NOT
        if (Payroll::whereIn('finalize_status', ['1', '2'])->first()) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        /* recon finalize condition check */
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        $checkReconClawbackFinalizeData = ReconClawbackHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawbackFinalizeData) {
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not updated because the Recon amount has finalized or executed from recon'], 400);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if(($request->validate_only ?? 1) != 0) {
            $request['validate_only'] = 1;
        }

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $importSales = new PestSalesImport();
            $importSales->validate_only = $request->validate_only;
            $importSales->new_records = 0;
            $importSales->updated_records = 0;
            $importSales->error_records = 0;
            $importSales->total_records = 0;
            $importSales->salesErrorReport = [];
            $importSales->salesSuccessReport = [];
            $user_data = User::where('id', '!=', 1)->select('id', 'email')->get();
            $user_email_arr = [];
            foreach ($user_data as $ud) {
                $user_email_arr[strtolower($ud['email'])] = $ud['id'];
            }
            $additional_emails = UsersAdditionalEmail::select('user_id', 'email')->get();
            foreach ($additional_emails as $ad) {
                $user_email_arr[strtolower($ad['email'])] = $ad['user_id'];
            }

            $importSales->users = $user_email_arr;
            $state_locations = State::join('locations', 'locations.state_id', '=', 'states.id')->select('states.state_code', 'locations.general_code')->get();
            $state_locations_arr = [];
            if (!empty($state_locations)) {
                foreach ($state_locations as $st) {
                    $state_locations_arr[$st['state_code']][] =  $st['general_code'];
                }
            }
            $importSales->state_locations_arr = $state_locations_arr;
            $importSales->import_id = time();
            $importSales->ids = [];
            Excel::import($importSales, $request->file('file'));

            // WHEN EXCEL HAVE NO DATA TO IMPORT
            if (!$importSales->total_records) {
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => false,
                    'message' => "Apologies, The uploaded excel file doesn't have any data to import or the given file in invalid!!",
                    'error' => $importSales->errors,
                    'failed_all' => 2
                ], 400);
            }

            // WHEN EXCEL HAVE DATA & THE NUMBER OF ERROR IS NOT SAME AS TOTAL NUMBER OF IMPORTED RECORDS
            if ($importSales->total_records && $importSales->total_records != $importSales->error_records) {
                $status = true;
                $statusCode = 200;
                if ($importSales->error_records) {
                    $status = false;
                    $statusCode = 400;
                }

                $response = [
                    'ApiName' => 'import_api',
                    'status'  => $status,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                    'failed_all' => 0
                ];
                if ($request['validate_only'] == 0) {
                    $response['data'] = [
                        'new_records' => $importSales->new_records,
                        'updated_records' => $importSales->updated_records,
                        'error_records' => $importSales->error_records,
                        'total_records' => $importSales->total_records,
                        'ids' => $importSales->ids,
                        'salesErrorReport' => $importSales->salesErrorReport,
                        'salesSuccessReport' => $importSales->salesSuccessReport
                    ];
                }
                return response()->json($response, $statusCode);
            } else {
                // WHEN EXCEL DOESN'T HAVE DATA OR THE NUMBER OF ERROR IS SAME AS TOTAL NUMBER OF IMPORTED RECORDS
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => false,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                    'failed_all' => 1
                ], 400);
            }
        } else {
            // INSERTING DATA INTO HISTORY TABLE
            $importSales = new ImportSales();
            $importSales->validate_only = $request->validate_only;
            $importSales->new_records = 0;
            $importSales->updated_records = 0;
            $importSales->error_records = 0;
            $importSales->total_records = 0;
            $importSales->salesErrorReport = [];
            $importSales->salesSuccessReport = [];
            $user_data = User::withoutGlobalScope('notTerminated')
            ->where('id', '!=', 1)
            // ->select('id', 'email', DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))
            ->select(
                'id',
                DB::raw("SUBSTRING_INDEX(email, '~~~', 1) as email"), // Extract email before '~~~'
                DB::raw("CONCAT(first_name, ' ', last_name) AS full_name")
            )
            ->get();
            // return $user_data->toArray();
            $user_email_arr = [];
                foreach ($user_data as $ud) {
                    $user_email_arr[strtolower($ud['email'])] = $ud['id'];
                }
                $additional_emails = UsersAdditionalEmail::select('user_id', 'email')->get();
                foreach ($additional_emails as $ad) {
                    $user_email_arr[strtolower($ad['email'])] = $ad['user_id'];
                }

            $importSales->users = $user_email_arr;
            $state_locations = State::join('locations', 'locations.state_id', '=', 'states.id')->select('states.state_code', 'locations.general_code')->get();
            $state_locations_arr = [];
            foreach ($state_locations as $st) {
                $state_locations_arr[strtoupper($st['state_code'])][] =  strtoupper($st['general_code']);
            }
            $importSales->state_locations_arr = $state_locations_arr;
            $importSales->import_id = time();
            $importSales->ids = [];
            Excel::import($importSales, $request->file('file'));

            // WHEN EXCEL HAVE NO DATA TO IMPORT
            if (!$importSales->total_records) {
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => false,
                    'message' => "Apologies, The uploaded excel file doesn't have any data to import or the given file in invalid!!",
                    'error' => $importSales->errors,
                    'failed_all' => 2
                ], 400);
            }

            // WHEN EXCEL HAVE DATA & THE NUMBER OF ERROR IS NOT SAME AS TOTAL NUMBER OF IMPORTED RECORDS
            if ($importSales->total_records && $importSales->total_records != $importSales->error_records) {
                $status = true;
                $statusCode = 200;
                if ($importSales->error_records) {
                    $status = false;
                    $statusCode = 400;
                }

                $response = [
                    'ApiName' => 'import_api',
                    'status'  => $status,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                    'failed_all' => 0
                ];
                if ($request['validate_only'] == 0) {
                    $response['data'] = [
                        'new_records' => $importSales->new_records,
                        'updated_records' => $importSales->updated_records,
                        'error_records' => $importSales->error_records,
                        'total_records' => $importSales->total_records,
                        'ids' => $importSales->ids,
                        'salesErrorReport' => $importSales->salesErrorReport,
                        'salesSuccessReport' => $importSales->salesSuccessReport
                    ];
                }
                return response()->json($response, $statusCode);
            } else {
                // WHEN EXCEL DOESN'T HAVE DATA OR THE NUMBER OF ERROR IS SAME AS TOTAL NUMBER OF IMPORTED RECORDS
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => false,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                    'failed_all' => 1
                ], 400);
            }
        }
    }

    public function sales_import(Request $request)
    {
        // CHECK OTHER EXCEL IS BEING IMPORTED OR NOT
        if (LegacyApiRawDataHistory::where(['import_to_sales' => '0', 'data_source_type' => 'excel'])->first()) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to import sales information. Our system is currently importing the other excel. Please try again later. Thank you for your patience.'], 400);
        }

        // CHECK THE PAYROLL IS FINALIZED OR NOT
        if (Payroll::whereIn('finalize_status', ['1', '2'])->first()) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        /* recon finalize condition check */
        $checkReconOverrideFinalizeData = ReconOverrideHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        $checkReconCommissionFinalizeData = ReconCommissionHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        $checkReconClawbackFinalizeData = ReconClawbackHistory::where("pid", $request->pid)->where("status", "finalize")->exists();
        if ($checkReconOverrideFinalizeData || $checkReconCommissionFinalizeData || $checkReconClawbackFinalizeData) {
            return response()->json(['status' => false, 'Message' => 'Apologies, the sale is not updated because the Recon amount has finalized or executed from recon'], 400);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // DB::beginTransaction();
        // try {
            $request['validate_only'] = 0;
            $importSales = $this->sales_import_validation($request);
            $importSales = $importSales->getOriginalContent();
            if($importSales['failed_all']) {
                return response()->json($importSales, 400);
            }
            $importSale = $importSales['data'];

            // STORE FILE ON S3 PRIVATE BUCKET
            $original_file_name = str_replace(' ', '_', $request->file('file')->getClientOriginalName());
            $file_name = config('app.domain_name') . '/' . 'excel_uploads/' . time() . '_' . $original_file_name;
            s3_upload($file_name, $request->file('file'), true);

            $user_id = Auth::user()->id;
            $user = User::find($user_id);
            $excel = ExcelImportHistory::create([
                'user_id' => $user_id,
                'uploaded_file' => $file_name,
                'new_records' => 0,
                'updated_records' => 0,
                'error_records' => $importSale['error_records'],
                'total_records' => $importSale['total_records'],
                'created_at' => now()->setTimezone('UTC'),
                'updated_at' => now()->setTimezone('UTC')
            ]);

            // WHEN EXCEL HAVE DATA & THE NUMBER OF ERROR IS NOT SAME AS TOTAL NUMBER OF IMPORTED RECORDS
            if ($importSale['total_records'] && $importSale['total_records'] != $importSale['error_records']) {
                $data = [
                    'email' => $user->email,
                    'subject' => 'Imported Sales Report',
                    'template' => view('mail.saleImportFailed', ['errorReports' => $importSale['salesErrorReport'], 'successReports' => $importSale['salesSuccessReport'], 'user' => $user, 'valid' => true])
                ];
                $this->sendEmailNotification($data);

                LegacyApiRawDataHistory::whereIn('id', $importSale['ids'])->where('import_to_sales', '0')->update(['excel_import_id' => $excel->id]);
                // JOB QUEUE FOR INSERT INTO SALES MASTER
                $dataForPusher = array(
                    'user_id' => $user_id,
                    'file_name' => $file_name,
                ); // send this data to pusher event
                $companyProfile = CompanyProfile::first();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    dispatch(new SaleMasterJob($user, true, $dataForPusher));
                } else {
                    dispatch(new SaleMasterJob($user, false, $dataForPusher));
                }

                // $status = true;
                // $statusCode = 200;
                // if ($importSale['error_records']) {
                //     $status = false;
                //     $statusCode = 400;
                // }
                // DB::commit();
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => true,
                    'message' => "Thank you for initiating the Excel import process. We're working on it in the background. Once completed, we'll promptly send you an email notification. Your patience is appreciated!",
                    'error' => $importSales['error'],
                    'failed_all' => 0
                ]);
            } else {
                // DB::commit();
                // WHEN EXCEL DOESN'T HAVE DATA OR THE NUMBER OF ERROR IS SAME AS TOTAL NUMBER OF IMPORTED RECORDS
                return response()->json([
                    'ApiName' => 'import_api',
                    'status'  => false,
                    'message' => $importSales['message'],
                    'error' => $importSales['error'],
                    'failed_all' => 1
                ], 400);
            }
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         'ApiName' => 'import_api',
        //         'status'  => false,
        //         'message' => $e->getMessage(),
        //         'error' => [
        //             $e->getMessage(),
        //             $e->getFile(),
        //             $e->getLine()
        //         ],
        //         'failed_all' => 3
        //     ], 500);
        // }
    }

    public function download_sample()
    { 

        $file_name = 'sample_report_'.date('Y_m_d_H_i_s').'.csv';
        return  Excel::download(new ExportSampleReport(), $file_name);

    }
    public function getFilterDateNew($filterName){ 
        $startDate = "";
        $endDate = "";
        if ($filterName == 'this_year') {
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
        } elseif ($filterName == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterName == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        }else{
            $startDate = date('Y-m-d', strtotime(Carbon::createFromDate($filterName, 1, 1)->startOfDay()));
            $endDate = date('Y-m-d', strtotime(Carbon::createFromDate($filterName, 12, 31)->endOfDay()));
        }
        // dd($startDate, $endDate);
        return [
            "startDate" => $startDate,
            "endDate" => $endDate,
        ];
    }
    function getMonthName($monthNumber) { 
        return DateTime::createFromFormat('!m', $monthNumber)->format('F');
    }
    /* 
    * start code for company_graph_new
    */
    public function company_graph_new(Request $request){ 
        $office_id = $request->office_id;
        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            $filterDate = $this->getFilterDateNew($filterDataDateWise);
            // dd($filterDate);
            if(!empty($filterDate['startDate']) && !empty($filterDate['endDate'])){
               $startDate = $filterDate['startDate'];
               $endDate = $filterDate['endDate'];
            }
        }
        
        $payrollHistoryData = PayrollHistory::whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(pay_period_from) as year'),
                DB::raw('MONTH(pay_period_from) as month'),
                DB::raw('SUM(commission) as total_commission'),
                DB::raw('SUM(override) as total_override'),
                DB::raw('SUM(adjustment) as total_adjustment'),
                DB::raw('SUM(reimbursement) as total_reimbursement'),
                DB::raw('SUM(deduction) as total_deduction'),
                DB::raw('SUM(clawback) as total_clawback')
            );
        $payrollData = Payroll::whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate])
            ;
            if ($office_id!='all')
            {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $payrollHistoryData = $payrollHistoryData->whereIn('user_id',$userId);
                $payrollData = $payrollData->whereIn('user_id',$userId);
                
            }
        // return  $payrollHistoryData->get();
        $payrollHistoryData = $payrollHistoryData
                            ->groupBy(DB::raw('YEAR(pay_period_from)'), DB::raw('MONTH(pay_period_from)'))
                            ->orderBy(DB::raw('YEAR(pay_period_from)'), 'DESC')
                            ->orderBy(DB::raw('MONTH(pay_period_from)'), 'DESC')
                            ->get();
        // return  $payrollHistoryData;
        $graph = [];
        $graphdata = array();
        
        foreach ($payrollHistoryData as $key => $value) {
            $totalNetPay = 0.0;
            $commission = 0.0;
            $commission2 = 0.0;
            $mm = $this->getMonthName($value->month);
            $year = $value->year;
            $month1 = $value->month;
            $commission = $value->total_commission;
            $override = $value->total_override ;
            $adjustment = $value->total_adjustment;
            $reimbursement = $value->total_reimbursement;
            $deduction = $value->total_deduction;
            $clawback = $value->total_clawback;
            //$totalNetPay = $commission + $override + $adjustment + $reimbursement + $deduction + $clawback ;
            $totalNetPay = $commission + $override + $adjustment + $reimbursement + $deduction;
            //dd($monthName, $year, $month);
            $graph[$mm] = array(
                'year' => $year,
                'commission' => round($commission,2),
                'override' => round($value->total_override,2),
                'adjustment' => round($value->total_adjustment,2),
                'reimbursement' => round($value->total_reimbursement,2),
                'deduction' => round($value->total_deduction,2),
                'clawback' => round($value->total_clawback,2),
                'totalNetPay' => round($totalNetPay,2),
                );
                $months[] = $mm;

        }
        // dd($graph);
        if ($request->input('filter') == 'last_12_months') {
            for($i=1; $i<13; $i++)
            {
                $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
                
                //$eDate = date('Y-m-d', strtotime("+". $i+1 ." months", strtotime($startDate)));
                if($sDate <= $endDate){
                    $time=strtotime($sDate);
                    $months=date("F",$time);
                    $monthsArray[] = $months;
                }
            }
        } else {
            for($i=0; $i<12; $i++)
            {
                $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
                
                //$eDate = date('Y-m-d', strtotime("+". $i+1 ." months", strtotime($startDate)));
                if($sDate <= $endDate){
                    $time=strtotime($sDate);
                    $months=date("F",$time);
                    $monthsArray[] = $months;
                }
            }
        }
        // dd($monthsArray);
        foreach ($monthsArray as $keymonth => $vmonth) {

            if (isset($graph[$vmonth])) {
                $graphdata[$vmonth] = $graph[$vmonth];
            }else{
                $graphdata[$vmonth] = 0;
            }
        }
        if (count($graphdata) > 0) {
            $data1[] = $graphdata;
        }else{
            $data1 = $data;
        }
        $one_time_payment = $this->one_time_payment($startDate, $endDate);
        // return $one_time_payment; 
        $getAccountsData = $this->getAccountsData($request, $startDate, $endDate);
        // return $getAccountsData;
        if($request->has('overview_type')  && ($request->overview_type == 'all' || $request->overview_type == '' )){ // fixed for overview_type is blank 
            return response()->json([
                'ApiName' => 'company_graph_new',
                'status' => true,
                'message' => 'Successfully.',
                'executed_payroll' => $data1,
                'one_time_payment' => $one_time_payment,
                'account_data' => $getAccountsData,
            ], 200);
        }else{
            return response()->json([
                'ApiName' => 'company_graph_new',
                'status' => true,
                'message' => 'Successfully.',
                'executed_payroll' => (isset($request->overview_type ) && $request->overview_type == 'payments') ? $data1 : [],
                'one_time_payment' => (isset($request->overview_type ) && $request->overview_type == 'payments') ? $one_time_payment : [],
                'account_data' => (isset($request->overview_type ) && $request->overview_type == 'accounts') ? $getAccountsData : [],
            ], 200); 
        }
    }
    /* 
    * end code for company_graph_new
    */

    public function one_time_payment($startDate, $endDate){ 
        $res = OneTimePayments::select(
            DB::raw('YEAR(pay_date) as year'),
            DB::raw('MONTH(pay_date) as month'),
            DB::raw('SUM(amount) as total_amount')
            )
            ->whereBetween('pay_date', [Carbon::parse($startDate), Carbon::parse($endDate)])
            ->where('everee_status', 1); // fetch with everee_status 1
        $res = $res
            ->groupBy(DB::raw('YEAR(pay_date)'), DB::raw('MONTH(pay_date)'))
            ->orderBy(DB::raw('YEAR(pay_date)'), 'DESC')
            ->orderBy(DB::raw('MONTH(pay_date)'), 'DESC')
            ->get();
        // return $res;
        $monthsArray= [];
        $graph = array();
        $graphdata = array();
        foreach($res as $key => $value){
            //echo '<pre>'; print_r($value);
            $monthName = date('M', strtotime($value->month));
            $mm = $this->getMonthName($value->month);
            // echo $monthName.'</br>';
            $year = date('Y', strtotime($value->year));
            // $month = date('m', strtotime($value->month));
            // dd($monthName, $year,$month);
            $graph[$mm] = array(
                'amount' => round($value->total_amount,2),
                'month' => $value->month,
                'year' => $value->year,
                );
                $monthsss[] = $monthName;
        }
        // return $graph;
        if (request()->input('filter') == 'last_12_months') {
            for($i=1; $i<13; $i++)
            {
                $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
                
                //$eDate = date('Y-m-d', strtotime("+". $i+1 ." months", strtotime($startDate)));
                if($sDate <= $endDate){
                    $time=strtotime($sDate);
                    $months=date("F",$time);
                    $monthsArray[] = $months;
                }
            }
        } else {
            for($i=0; $i<12; $i++)
            {
                $sDate = date('Y-m-d', strtotime("+". $i ." months", strtotime($startDate)));
                if($sDate <= $endDate){
                    $time=strtotime($sDate);
                    $getMonth=date("F",$time);
                    $monthsArray[] = $getMonth;
                    //dd($time, $getMonth);
                }
            }
        }
            // return $monthsArray;
            foreach ($monthsArray as $keymonth => $vmonth) {

                if (isset($graph[$vmonth])) {
                    // print_r($graph[$vmonth]).'<\n>'; 
                    $graphdata[$vmonth] = $graph[$vmonth];
                }else{
                    //echo 'not'.'</br>'; 
                    $graphdata[$vmonth] = 0;
                }
            }
            if (count($graphdata) > 0) {
                $data1[] = $graphdata;
            }else{
                $data1 = $data;
            }
            return $data1;
    }

    public function getAccountsData($request, $startDate, $endDate)
    {
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDateNew($filterValue);

            if (!empty($filterDate['startDate']) && !empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'company_graph_new',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        }

        // Prepare base query
        $salesQuery = SalesMaster::with('salesMasterProcess');

        // Filter by office if provided
        if ($request->has('office_id') && !empty($request->input('office_id'))) {
            $office_id = $request->input('office_id');
            if ($office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
                $salesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                    ->orWhereIn('closer2_id', $userIds)
                    ->orWhereIn('setter1_id', $userIds)
                    ->pluck('pid');

                $salesQuery->whereIn('pid', $salesPids);
            }
        }

        $salesInstalledQuery = clone $salesQuery;
        // GET SOLD & INSTALLED SALES
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $salesAllData = $salesQuery->whereBetween('customer_signoff', [$startDate, $endDate])->select(
                DB::raw('MONTH(customer_signoff) as month'),
                DB::raw('YEAR(customer_signoff) as year'),
                DB::raw('COUNT(id) as total_sold')
            )->groupBy(DB::raw('YEAR(customer_signoff)'), DB::raw('MONTH(customer_signoff)'))
            ->orderBy(DB::raw('YEAR(customer_signoff)'), 'DESC')
            ->orderBy(DB::raw('MONTH(customer_signoff)'), 'DESC')->get();

            $salesInstalledAllData = $salesInstalledQuery->whereBetween('m1_date', [$startDate, $endDate])->select(
                DB::raw('MONTH(m1_date) as month'),
                DB::raw('YEAR(m1_date) as year'),
                DB::raw('COUNT(id) as total_installed')
            )->groupBy(DB::raw('YEAR(m1_date)'), DB::raw('MONTH(m1_date)'))
            ->orderBy(DB::raw('YEAR(m1_date)'), 'DESC')
            ->orderBy(DB::raw('MONTH(m1_date)'), 'DESC')->get();
        } else {
            $salesAllData = $salesQuery->whereBetween('customer_signoff', [$startDate, $endDate])->select(
                DB::raw('MONTH(customer_signoff) as month'),
                DB::raw('YEAR(customer_signoff) as year'),
                DB::raw('COUNT(id) as total_sold')
            )->groupBy(DB::raw('YEAR(customer_signoff)'), DB::raw('MONTH(customer_signoff)'))
            ->orderBy(DB::raw('YEAR(customer_signoff)'), 'DESC')
            ->orderBy(DB::raw('MONTH(customer_signoff)'), 'DESC')->get();

            $salesInstalledAllData = $salesInstalledQuery->whereBetween('m2_date', [$startDate, $endDate])->select(
                DB::raw('MONTH(m2_date) as month'),
                DB::raw('YEAR(m2_date) as year'),
                DB::raw('COUNT(id) as total_installed')
            )->groupBy(DB::raw('YEAR(m2_date)'), DB::raw('MONTH(m2_date)'))
            ->orderBy(DB::raw('YEAR(m2_date)'), 'DESC')
            ->orderBy(DB::raw('MONTH(m2_date)'), 'DESC')->get();
        }

        $monthsArray = [];
        $graph = array();
        $graphdata = array();
        foreach ($salesAllData as $value) {
            $mm = $this->getMonthName($value->month);
            $graph[$mm] = array(
                'total_installed' => 0,
                'total_sold' => $value->total_sold,
                'month' => $value->month,
                'year' => $value->year
            );
        }

        foreach ($salesInstalledAllData as $value) {
            $mm = $this->getMonthName($value->month);
            if (isset($graph[$mm])) {
                $graph[$mm]['total_installed'] = $value->total_installed;
            } else {
                $graph[$mm] = array(
                    'total_installed' => $value->total_installed,
                    'total_sold' => 0,
                    'month' => $value->month,
                    'year' => $value->year
                );
            }
        }

        if (request()->input('filter') == 'last_12_months') {
            for ($i = 1; $i < 13; $i++) {
                $sDate = date('Y-m-d', strtotime("+" . $i . " months", strtotime($startDate)));
                if ($sDate <= $endDate) {
                    $time = strtotime($sDate);
                    $months = date("F", $time);
                    $monthsArray[] = $months;
                }
            }
        } else {
            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime("+" . $i . " months", strtotime($startDate)));
                if ($sDate <= $endDate) {
                    $time = strtotime($sDate);
                    $getMonth = date("F", $time);
                    $monthsArray[] = $getMonth;
                }
            }
        }

        foreach ($monthsArray as $vmonth) {
            if (isset($graph[$vmonth])) {
                $graphdata[$vmonth] = $graph[$vmonth];
            } else {
                $graphdata[$vmonth] = 0;
            }
        }

        $data1 = [];
        if (count($graphdata) > 0) {
            $data1[] = $graphdata;
        }
        return $data1;
    }

    /* 
    * start code for personnel summary api
    */
    public function personnel_summary(Request $request)
    { 
        //$search =$request->filter;

        $office_id = $request->input('office_id');

        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDateNew($filterValue);
            if (!empty($filterDate['startDate']) && !empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'personnel_summary',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        }
        $startDate = Carbon::parse($startDate)->startOfDay(); // sets time to 00:00:00
        $endDate = Carbon::parse($endDate)->endOfDay(); // sets time to 23:59:59
       // \DB::enableQueryLog();
        $user = User::whereBetween('created_at', [$startDate, $endDate])->orderBy('id','desc');
        // dd($user->count());
        if ($request->has('office_id') && !empty($office_id)) {
            $addiUserId = AdditionalLocations::where('office_id', $office_id)->pluck('user_id');
            if($office_id!='all')
            {
                /*$user->where(function ($query) use ($office_id) { // bug fixed office_id
                    return $query->where('office_id', $office_id);
                });*/
                $user->where(function ($query) use ($office_id,$addiUserId) { // bug fixed office_id
                    return $query->where('office_id', $office_id)->orWhereIn('id', $addiUserId);
                });
                
            }

        }

        // User filtering - filter to specific user if provided
        if (!empty($request->input('user_id'))) {
            $user->where('id', $request->input('user_id'));
        }
        
        $totalUsers = $user->count();

        $user_data = $user->get();

        $user_data_array = $user_data->toArray();

        $totalActiveUsers = 0;
        $totalAdminUsers = 0;
        $totalInActiveUsers =0;
        $totalAdminActiveUsers = 0;
        $totalAdminInActiveUsers = 0;
        foreach ($user_data_array as $user_row) {
            //if ($user_row['dismiss'] == 0 && $user_row['is_super_admin'] == 0) {
            if ($user_row['dismiss'] == 0 && $user_row['is_super_admin'] == 0) {
                $totalActiveUsers++;
            }
            if ($user_row['dismiss'] == 1 && $user_row['is_super_admin'] == 0) {
                $totalInActiveUsers++;
            }
            if ($user_row['is_super_admin'] == 1 && $user_row['dismiss'] == 0) {
                $totalAdminActiveUsers++;
            }
            if ($user_row['is_super_admin'] == 1 && $user_row['dismiss'] == 1) {
                $totalAdminInActiveUsers++;
            }
        }

            return response()->json([
                'ApiName' => 'personnel_summary',
                'status' => true,
                'message' => 'Successfully.',
                'totalContractors' =>  $totalActiveUsers + $totalAdminActiveUsers,
                'totalEmployees' =>  0,
                'totalWorkForce' =>  $totalActiveUsers + $totalAdminActiveUsers + 0,
                'totalActiveUsers' =>  $totalActiveUsers,
                'totalInActiveUsers' =>  $totalInActiveUsers,
                'totalAdminActiveUsers' =>  $totalAdminActiveUsers,
                'totalAdminInActiveUsers' =>  $totalAdminInActiveUsers,
                //'totalUsers' =>  $totalUsers,
            ], 200);
    }
    /* 
    * end code for personnel summary api
    */

    /* 
    * start code for company snapshot new api
    */
    public function company_snapshot_new(Request $request){ 
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDateNew($filterValue);
            //$previousFilterDate = $this->getFilterLastDate($filterValue);
            if (!empty($filterDate['startDate']) && !empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
                //$lastStartDate = $previousFilterDate["startDate"];
                //$lastEndDate = $previousFilterDate["endDate"];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'company_snapshot_new',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        }

        $salesPids = null;
        // Filter by office if provided
        if ($request->has('office_id') && !empty($request->input('office_id'))) {
            $office_id = $request->input('office_id');
            if ($office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
                $salesPids = SaleMasterProcess::where(function($q) use ($userIds) {
                    $q->whereIn('closer1_id', $userIds)
                      ->orWhereIn('closer2_id', $userIds)
                      ->orWhereIn('setter1_id', $userIds)
                      ->orWhereIn('setter2_id', $userIds);
                })->pluck('pid');
            }
        }

        // User filtering - intersect with office filter if both exist
        if (!empty($request->input('user_id'))) {
            $userId = User::where('id', $request->input('user_id'))->pluck('id');
            $userSalesPids = SaleMasterProcess::where(function($q) use ($userId) {
                $q->whereIn('closer1_id', $userId)
                  ->orWhereIn('closer2_id', $userId)
                  ->orWhereIn('setter1_id', $userId)
                  ->orWhereIn('setter2_id', $userId);
            })->pluck('pid');
            
            // If office filter exists, intersect; otherwise use user PIDs
            $salesPids = !empty($salesPids) && $salesPids->isNotEmpty() 
                ? $salesPids->intersect($userSalesPids) 
                : $userSalesPids;
        }

        $clawbackPid = ClawbackSettlement::where('pid','!=',null)->groupBy('pid')->pluck('pid')->toArray();
        $totalSoldQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate]);

        $totalInstalledQuery = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '!=', null);

        $totalPendingQuery = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', null);
            
        // /* $lastTotalSoldQuery = SalesMaster::with('salesMasterProcess')->whereBetween('install_complete_date',[$lastStartDate,  $lastEndDate]); */
        //$lastTotalSoldQuery = SalesMaster::with('salesMasterProcess')
            //->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate]);
            
        //$totalKwQuery = SalesMaster::with('salesMasterProcess');

        /*$m1CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull("m1_date")
            ->whereNull('date_cancelled');
        $lastM1CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull("m1_date")
            ->whereNull('date_cancelled');

        $m2CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull("m2_date")
            ->whereNull('date_cancelled');
        $lastM2CompleteQuery = SalesMaster::with('salesMasterProcess')
            ->whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull("m2_date")
            ->whereNull('date_cancelled');*/

        $cancelledQuery = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull("date_cancelled");
            //->whereNotIn('pid',$clawbackPid);
        /*$lastCancelledQuery = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull("date_cancelled")
            ->whereNotIn('pid',$clawbackPid);*/

        /*$clawbackQuery = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNotNull("date_cancelled")
            ->whereIn('pid',$clawbackPid);
        $lastClawbackQuery = SalesMaster::whereBetween('customer_signoff', [$lastStartDate, $lastEndDate])
            ->whereNotNull("date_cancelled")
            ->whereIn('pid',$clawbackPid);*/

        
        if ($salesPids) {
            //$totalCurrentSalesQuery->whereIn('pid', $salesPids);
            //$totalLastSalesQuery->whereIn('pid', $salesPids);
            $totalInstalledQuery->whereIn('pid', $salesPids);
            $totalPendingQuery->whereIn('pid', $salesPids);
            $totalSoldQuery->whereIn('pid', $salesPids);
            //$lastTotalSoldQuery->whereIn('pid', $salesPids);
            //$totalKwQuery->whereIn('pid', $salesPids);
            //$m1CompleteQuery->whereIn('pid', $salesPids);
            //$lastM1CompleteQuery->whereIn('pid', $salesPids);
            //$m2CompleteQuery->whereIn('pid', $salesPids);
            //$lastM2CompleteQuery->whereIn('pid', $salesPids);
            $cancelledQuery->whereIn('pid', $salesPids);
            //$lastCancelledQuery->whereIn('pid', $salesPids);
            //$clawbackQuery->whereIn('pid', $salesPids);
            //$lastClawbackQuery->whereIn('pid', $salesPids);
        }
        $data = [
            //'totalCurrentSales' => $totalCurrentSalesQuery->count(),
            //'totalLastSales' => $totalLastSalesQuery->count(),
            'totalSold' => $totalSoldQuery->count(),
            'totalInstalled' => $totalInstalledQuery->count(),
            'totalPending' => $totalPendingQuery->count(),
            'cancelled' => $cancelledQuery->count(),
            //'totalSales' => $totalCurrentSalesQuery->count(),
            //'lastTotalSold' => $lastTotalSoldQuery->count(),
            //'totalKw' => $totalKwQuery->sum('kw'),
            //'m1Complete' => $m1CompleteQuery->count(),
            //'lastM1Complete' => $lastM1CompleteQuery->count(),
            //'m2Complete' => $m2CompleteQuery->count(),
            //'lastM2Complete' => $lastM2CompleteQuery->count(),
            //'lastCancelled' => $lastCancelledQuery->count(),
            //'clawback' => $clawbackQuery->count(),
            //'lastClawback' => $lastClawbackQuery->count(),
        ];

        return response()->json([
            'ApiName' => 'company_snapshot_new',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }
    /* 
    * end code for company snapshot new api
    */

    /* 
    * start code for company_report_new api
    */
    public function company_report_new(Request $request){ 
        $office_id = $request->input('office_id', 'all');
        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            $filterDate = $this->getFilterDateNew($filterDataDateWise);
            // dd($filterDate);

            if(!empty($filterDate['startDate']) && !empty($filterDate['endDate'])){
               $startDate = $filterDate['startDate'];
               $endDate = $filterDate['endDate'];
            }
        }
        $datetime1 = new DateTime($startDate);
        $datetime2 = new DateTime($endDate);
        $interval = $datetime1->diff($datetime2);
        $days = $interval->format('%a');
        if($days<7)
        {
            $sdays = $interval->format('%a')+1;
            $edays = $interval->format('%a');
        }else{
            $sdays = $interval->format('%a');
            $edays = $interval->format('%a')-1;
        }
        $priviesStartDate=Carbon::parse($startDate)->subDays($sdays);
        $priviesStartDate= date("Y-m-d", strtotime($priviesStartDate));
        $priviesEndDate=Carbon::parse($priviesStartDate)->addDays($edays);
        $priviesEndDate= date("Y-m-d", strtotime($priviesEndDate));
        //return [$days,$startDate,$endDate, $sdays, $edays,$priviesStartDate,$priviesEndDate];
        
        // Build user IDs array for filtering
        $userIds = null;
        if ($request->has('office_id') && $office_id != 'all') {
            $userIds = User::where('office_id', $office_id)->pluck('id');
        }
        
        // User filtering - intersect with office filter if both exist
        if (!empty($request->input('user_id'))) {
            $singleUserId = collect([$request->input('user_id')]);
            // If office filter exists, intersect; otherwise use single user ID
            $userIds = !empty($userIds) && $userIds->isNotEmpty() 
                ? $userIds->intersect($singleUserId) 
                : $singleUserId;
        }
        
        $payrollHistoryData = PayrollHistory::select(
            'payroll_history.user_id as u_id',
            DB::raw('SUM(payroll_history.commission) as total_commission'),
            DB::raw('SUM(payroll_history.override) as total_override'),
            DB::raw('SUM(payroll_history.adjustment) as total_adjustment'),
            DB::raw('SUM(payroll_history.reimbursement) as total_reimbursement'),
            DB::raw('SUM(payroll_history.deduction) as total_deduction'),
            DB::raw('SUM(payroll_history.clawback) as total_clawback'),
            DB::raw('SUM(payroll_history.net_pay) as total_netpay'),
            DB::raw('SUM(payroll_history.custom_payment) as total_custom_payment')
        )
        ->where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_to', [$startDate, $endDate])
                  ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
        });
        if (!empty($userIds)) {
            $payrollHistoryData = $payrollHistoryData->whereIn('payroll_history.user_id', $userIds);
        }
        $payrollHistoryData = $payrollHistoryData->get();
        //return $payrollHistoryData;
        $payrollData = Payroll::select(
            'payrolls.user_id as uid',
            DB::raw('SUM(payrolls.commission) as total_commission'),
            DB::raw('SUM(payrolls.override) as total_override'),
            DB::raw('SUM(payrolls.adjustment) as total_adjustment'),
            DB::raw('SUM(payrolls.reimbursement) as total_reimbursement'),
            DB::raw('SUM(payrolls.deduction) as total_deduction'),
            DB::raw('SUM(payrolls.clawback) as total_clawback'),
            DB::raw('SUM(payrolls.net_pay) as total_netpay'),
            DB::raw('SUM(payrolls.custom_payment) as total_custom_payment')
        )
        ->where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('payrolls.pay_period_to', [$startDate, $endDate])
                  ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
        });
        if (!empty($userIds)) {
            $payrollData = $payrollData->whereIn('payrolls.user_id', $userIds);
        }
        $payrollData = $payrollData->get(); 
        $commission1 = 0;
        $override1 = 0;
        $adjustment1 = 0;
        $reimbursement1 = 0;
        $deduction1 = 0;
        $clawback1 = 0;
        $totalNetPay1 = 0;
        $net_pay1 = 0;
        $custompayment1 = 0;

        $commission2 = 0;
        $override2 = 0;
        $adjustment2 = 0;
        $reimbursement2 = 0;
        $deduction2 = 0;
        $clawback2 = 0;
        $totalNetPay2 = 0;
        $net_pay2 = 0;
        $custompayment2 = 0;

        foreach($payrollHistoryData as $payrollHistory){
            $commission1 += $payrollHistory->total_commission;
            $override1 += $payrollHistory->total_override;
            $adjustment1 += $payrollHistory->total_adjustment;
            $reimbursement1 += $payrollHistory->total_reimbursement;
            $deduction1 += $payrollHistory->total_deduction;
            $clawback1 = $payrollHistory->total_clawback;
            $net_pay1 += $payrollHistory->total_netpay;
            $custompayment1 += $payrollHistory->total_custom_payment;
            $totalNetPay1 = $commission1 + $override1 + $adjustment1 + $reimbursement1;
        }
        // return $totalNetPay1;
        foreach($payrollData as $payrollData1){
            $commission2 += $payrollData1->total_commission;
            $override2 += $payrollData1->total_override;
            $adjustment2 += $payrollData1->total_adjustment;
            $reimbursement2 += $payrollData1->total_reimbursement;
            $deduction2 += $payrollData1->total_deduction;
            $clawback2 = $payrollData1->total_clawback;
            $net_pay2 += $payrollData1->total_netpay;
            $custompayment2 += $payrollData1->total_custom_payment;
            $totalNetPay2 = $commission2 + $override2 + $adjustment2 + $reimbursement2;
        }
        //return $totalNetPay1;
        $commission = $commission1+$commission2;
        $override  = $override1+$override2;
        $adjustment  = $adjustment1+$adjustment2;
        $reimbursement = $reimbursement1+$reimbursement2;
        $deduction  = ($deduction1 + $deduction2) * -1;
        $clawback  = $clawback1+$clawback2;
        $net_pay = $net_pay1+$net_pay2;
        $custompayment = $custompayment1+$custompayment2;
        $totalNetPay  = $totalNetPay1+$totalNetPay2;
        $totalNetPay = $totalNetPay+$deduction+$custompayment;

       
        $getPayrollData = $this->getPayrollData->whereBetween('pay_period_to', [$startDate, $endDate]);
        $payroll_status = round($getPayrollData->where('status',1)->orWhere('status',2)->count('id'),2);
        //dd($getPayrollData, $payroll_status);
        if($payroll_status > 0){
            $payroll_execute_status = false;
        }else{
            $payroll_execute_status = true;
        }

        $latesFailedPayroll = PayrollHistory::where('everee_payment_status', 2)->where('pay_type', 'Bank')->orderBy('id', 'DESC');
        $payrollFailedEvereeCount = $latesFailedPayroll->count(); 
        $payrollFailedEvereeData = $latesFailedPayroll->first(); 

        $is_payroll_failed = false;
        $pay_period_start = $pay_period_end = null;
        $payment_failed_count = 0;
        if($payrollFailedEvereeCount > 0){
            $is_payroll_failed = true;
            $pay_period_start = $payrollFailedEvereeData->pay_period_from ?? null;
            $pay_period_end = $payrollFailedEvereeData->pay_period_to ?? null;
            $payment_failed_count = $payrollFailedEvereeCount ?? 0;
        }

        $payroll_failed_data = [
            'is_payroll_failed' => $is_payroll_failed,
            'pay_period_start' => $pay_period_start,
            'pay_period_end' => $pay_period_end,
            'payment_failed_count' => $payment_failed_count
        ];

        $locationPayrollData = $this->topPayRollByLocation($request, $startDate, $endDate);
       //dd($locationPayrollData);
        $locationPayrollPercentage = $this->getPercentageByLocation($request, $priviesStartDate, $priviesEndDate, $locationPayrollData);
        //dd($locationPayrollPercentage);
        $locationPayrollDataArray['locations'] = $locationPayrollData;
        $locationPayrollDataArray['percentage'] = $locationPayrollPercentage;
        // dd($locationPayrollData);
        $totalPayrollByLocations = 0.0;
        $totalPayrollByLocationsNetpay = 0.0;
        foreach($locationPayrollData as $locationPayroll){
            $totalPayrollByLocations+=round($locationPayroll['value'],2);
        }
        $locationPayrollDataArray['totalnetPayout'] = $totalPayrollByLocations;

        $payRollByPositions['positions'] = $this->payRollByPositions($request, $startDate, $endDate);
        $payRollByPositionsPercentage = $this->getPercentageByPositions($request, $priviesStartDate, $priviesEndDate, $payRollByPositions['positions']);
        $payRollByPositions['percentage'] = $payRollByPositionsPercentage;
        $totalPayrollByPositions = 0.0;
        foreach($payRollByPositions['positions'] as $positionPayroll){
            $totalPayrollByPositions+=round($positionPayroll['value'],2);
        }
        $payRollByPositions['netPayout'] = round($totalPayrollByPositions,2);
        //dd($payRollByPositionsPercentage);
        //return $payRollByPositions;
        
        $data = [
            "commission" => round($commission,2),
            "override"   => round($override,2),
            "adjustment" => round($adjustment,2),
            "reimbursement" => round($reimbursement,2),
            "deduction" => round($deduction,2),
            "clawback" => round($clawback,2),
            "customFields" => round($custompayment,2),
            "netPayout" => round($totalNetPay,2),
            "totalPaidByOffice" => $locationPayrollDataArray,
            "totalPaidByPosition" => $payRollByPositions

        ]; 

        return response()->json([
            'ApiName' => 'get Payroll Summary company_report_new',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'payroll_failed_data' => $payroll_failed_data,
            'payroll_execute_status' => $payroll_execute_status
        ], 200);
    }
    /* 
    * end code for company_report_new api
    */

    public function topPayRollByLocation($request, $startDate, $endDate){ 
        $office_id = $request->input('office_id', 'all');
        if(!empty($startDate) && !empty($endDate) ){ 
            // Build user IDs array for filtering
            $userIds = null;
            if ($request->has('office_id') && $office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
            }
            
            // User filtering - intersect with office filter if both exist
            if (!empty($request->input('user_id'))) {
                $singleUserId = collect([$request->input('user_id')]);
                $userIds = !empty($userIds) && $userIds->isNotEmpty() 
                    ? $userIds->intersect($singleUserId) 
                    : $singleUserId;
            }
            
            $payrollHistoryData = PayrollHistory::select(
                'states.name as state_name',
                'locations.office_name as office_name',
                'payroll_history.user_id as u_id',
                DB::raw('SUM(payroll_history.commission) as total_commission'),
                DB::raw('SUM(payroll_history.override) as total_override'),
                DB::raw('SUM(payroll_history.adjustment) as total_adjustment'),
                DB::raw('SUM(payroll_history.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payroll_history.deduction) as total_deduction'),
                DB::raw('SUM(payroll_history.net_pay) as total_netpay'),
                DB::raw('SUM(payroll_history.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payroll_history.user_id')
            ->join('locations', 'locations.id', '=', 'users.office_id')
            ->join('states', 'states.id', '=', 'users.state_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollHistoryData = $payrollHistoryData->whereIn('payroll_history.user_id', $userIds);
            }
            // $payrollHistoryData = $payrollHistoryData->groupBy('states.name')->get();
            $payrollHistoryData = $payrollHistoryData->groupBy('locations.office_name')->get();
            //return $payrollHistoryData;
            $payrollData = Payroll::select(
                'states.name as state_name',
                'locations.office_name as office_name',
                'payrolls.user_id as uid',
                DB::raw('SUM(payrolls.commission) as total_commission'),
                DB::raw('SUM(payrolls.override) as total_override'),
                DB::raw('SUM(payrolls.adjustment) as total_adjustment'),
                DB::raw('SUM(payrolls.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payrolls.deduction) as total_deduction'),
                DB::raw('SUM(payrolls.net_pay) as total_netpay'),
                DB::raw('SUM(payrolls.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payrolls.user_id')
            ->join('locations', 'locations.id', '=', 'users.office_id')
            ->join('states', 'states.id', '=', 'users.state_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('payrolls.pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollData = $payrollData->whereIn('payrolls.user_id', $userIds);
            }
           //$payrollData = $payrollData->groupBy('states.name')->get();
           $payrollData = $payrollData->groupBy('locations.office_name')->get();
            //dd($payrollData);
            $combinedResult = [];
            $currentYear = Carbon::now()->year;
            foreach ($payrollHistoryData as $stateName => $data) {
                if (!isset($combinedResult[$data['office_name']])) {
                    $combinedResult[$data['office_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_netpay' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['office_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['office_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['office_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['office_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['office_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['office_name']]['total_netpay'] += $data['total_netpay'];
                $combinedResult[$data['office_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            
            foreach ($payrollData as $stateName => $data) {
                if (!isset($combinedResult[$data['office_name']])) {
                    $combinedResult[$data['office_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_netpay' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['office_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['office_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['office_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['office_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['office_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['office_name']]['total_netpay'] += $data['total_netpay'];
                $combinedResult[$data['office_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            $payrollHistoryFinalData = [];
            $totalPayrollByLocations = 0;
            $totalAmount = 0;
            foreach ($combinedResult as $stateName => $data) {
                $totalAmount = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'] + $data['total_custom_payment'];
                $payrollHistoryFinalData[] = [
                    "name" => $stateName,
                    "value" => round($totalAmount,2),
                ];
            }
            // dd($payrollHistoryFinalData);
            return $payrollHistoryFinalData;
        }
    }

    public function  getPercentageByLocation($request, $startDate,$endDate, $locationPayrollData){ 
        $office_id = $request->input('office_id', 'all');
        $stateTotall = 0;
        foreach($locationPayrollData as $k => $v){
            $stateTotall+= $v['value'];
        }
        if(!empty($startDate) && !empty($endDate) ){ 
            // Build user IDs array for filtering
            $userIds = null;
            if ($request->has('office_id') && $office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
            }
            
            // User filtering - intersect with office filter if both exist
            if (!empty($request->input('user_id'))) {
                $singleUserId = collect([$request->input('user_id')]);
                $userIds = !empty($userIds) && $userIds->isNotEmpty() 
                    ? $userIds->intersect($singleUserId) 
                    : $singleUserId;
            }
            
            $payrollHistoryData = PayrollHistory::select(
                'states.name as state_name',
                'locations.office_name as office_name',
                'payroll_history.user_id as u_id',
                DB::raw('SUM(payroll_history.commission) as total_commission'),
                DB::raw('SUM(payroll_history.override) as total_override'),
                DB::raw('SUM(payroll_history.adjustment) as total_adjustment'),
                DB::raw('SUM(payroll_history.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payroll_history.deduction) as total_deduction'),
                DB::raw('SUM(payroll_history.net_pay) as total_netpay'),
                DB::raw('SUM(payroll_history.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payroll_history.user_id')
            ->join('locations', 'locations.id', '=', 'users.office_id')
            ->join('states', 'states.id', '=', 'users.state_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollHistoryData = $payrollHistoryData->whereIn('payroll_history.user_id', $userIds);
            }
           //$payrollHistoryData = $payrollHistoryData->groupBy('states.name')->get();
           $payrollHistoryData = $payrollHistoryData->groupBy('locations.office_name')->get();
            // dd($payrollHistoryData);
            $payrollData = Payroll::select(
                'states.name as state_name',
                'locations.office_name as office_name',
                'payrolls.user_id as uid',
                DB::raw('SUM(payrolls.commission) as total_commission'),
                DB::raw('SUM(payrolls.override) as total_override'),
                DB::raw('SUM(payrolls.adjustment) as total_adjustment'),
                DB::raw('SUM(payrolls.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payrolls.deduction) as total_deduction'),
                DB::raw('SUM(payrolls.net_pay) as total_netpay'),
                DB::raw('SUM(payrolls.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payrolls.user_id')
            ->join('locations', 'locations.id', '=', 'users.office_id')
            ->join('states', 'states.id', '=', 'users.state_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('payrolls.pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollData = $payrollData->whereIn('payrolls.user_id', $userIds);
            }
           //$payrollData = $payrollData->groupBy('states.name')->get();
           $payrollData = $payrollData->groupBy('locations.office_name')->get();
            //dd($payrollData);
            $combinedResult = [];
            $currentYear = Carbon::now()->year;
            foreach ($payrollHistoryData as $stateName => $data) {
                if (!isset($combinedResult[$data['office_name']])) {
                    $combinedResult[$data['office_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_netpay' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['office_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['office_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['office_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['office_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['office_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['office_name']]['total_netpay'] += $data['total_netpay'];
                $combinedResult[$data['office_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            // dd($payrollHistoryData,$combinedResult);
            foreach ($payrollData as $stateName => $data) {
                if (!isset($combinedResult[$data['office_name']])) {
                    $combinedResult[$data['office_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_netpay' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['office_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['office_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['office_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['office_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['office_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['office_name']]['total_netpay'] += $data['total_netpay'];
                $combinedResult[$data['office_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            $payrollHistoryFinalData = [];
            $totalPayrollByLocations = 0;
            $totalAmount = 0;
            $priviesPayRollByStateTotal = 0;
            $priviesStateTotal = 0;
            foreach ($combinedResult as $stateName => $data) {
                $priviesPayRollByStateTotal = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'] + $data['total_custom_payment'];
                $payrollHistoryFinalData[] = [
                    "name" => $stateName,
                    "value" => round($priviesPayRollByStateTotal,2),
                ];
                $priviesStateTotal+= $priviesPayRollByStateTotal;
            }
        }
        if($priviesStateTotal>0){
            $stateTotalPercentage = ($stateTotall/$priviesStateTotal)*100;
            // return $stateTotalPercentage;
        }
        else{
            $stateTotalPercentage = 0;
        }
        //return $stateTotalPercentage;
        $stateTotalPercentages = $stateTotalPercentage > 100 ? "More" : "Less";

        if($stateTotall>0 && $priviesStateTotal>0)
            $percentageState = ($stateTotall - $priviesStateTotal)*100/$stateTotall;
        elseif($stateTotall<=0 && $priviesStateTotal>0)
            $percentageState = -100;
        elseif($stateTotall>0 && $priviesStateTotal<=0)
            $percentageState = ($stateTotall - $priviesStateTotal)*100/$stateTotall;
        else
            $percentageState = 0;
        if($percentageState <= -100)
            $percentageState = -100;
        return ['percentage'=>round($percentageState,1), 'previous_netpay'=>$priviesStateTotal];
    }

    public function payRollByPositions($request, $startDate, $endDate){ 
        $office_id = $request->input('office_id', 'all');
        if(!empty($startDate) && !empty($endDate) ){ 
            // Build user IDs array for filtering
            $userIds = null;
            if ($request->has('office_id') && $office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
            }
            
            // User filtering - intersect with office filter if both exist
            if (!empty($request->input('user_id'))) {
                $singleUserId = collect([$request->input('user_id')]);
                $userIds = !empty($userIds) && $userIds->isNotEmpty() 
                    ? $userIds->intersect($singleUserId) 
                    : $singleUserId;
            }
            
            $payrollHistoryData = PayrollHistory::select(
                'positions.position_name as position_name',
                'payroll_history.user_id as u_id',
                DB::raw('SUM(payroll_history.commission) as total_commission'),
                DB::raw('SUM(payroll_history.override) as total_override'),
                DB::raw('SUM(payroll_history.adjustment) as total_adjustment'),
                DB::raw('SUM(payroll_history.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payroll_history.deduction) as total_deduction'),
                DB::raw('SUM(payroll_history.net_pay) as total_netpay'),
                DB::raw('SUM(payroll_history.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payroll_history.user_id')
            ->join('positions', 'positions.id', '=', 'users.sub_position_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollHistoryData = $payrollHistoryData->whereIn('payroll_history.user_id', $userIds);
            }
           $payrollHistoryData = $payrollHistoryData->groupBy('positions.position_name')->get();
            //dd($payrollHistoryData);

            $payrollData = Payroll::select(
                'positions.position_name as position_name',
                'payrolls.user_id as uid',
                DB::raw('SUM(payrolls.commission) as total_commission'),
                DB::raw('SUM(payrolls.override) as total_override'),
                DB::raw('SUM(payrolls.adjustment) as total_adjustment'),
                DB::raw('SUM(payrolls.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payrolls.deduction) as total_deduction'),
                DB::raw('SUM(payrolls.net_pay) as total_netpay'),
                DB::raw('SUM(payrolls.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payrolls.user_id')
            ->join('positions', 'positions.id', '=', 'users.sub_position_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('payrolls.pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollData = $payrollData->whereIn('payrolls.user_id', $userIds);
            }
           $payrollData = $payrollData->groupBy('positions.position_name')->get();
            $combinedResult = [];
            $currentYear = Carbon::now()->year;
            foreach ($payrollHistoryData as $stateName => $data) {
                if (!isset($combinedResult[$data['position_name']])) {
                    $combinedResult[$data['position_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['position_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['position_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['position_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['position_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['position_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['position_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            // dd($payrollHistoryData,$combinedResult);
            foreach ($payrollData as $stateName => $data) {
                if (!isset($combinedResult[$data['position_name']])) {
                    $combinedResult[$data['position_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['position_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['position_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['position_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['position_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['position_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['position_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            $payrollHistoryFinalData = [];
            $totalPayrollByLocations = 0;
            $totalAmount = 0;
            foreach ($combinedResult as $stateName => $data) {
                $totalAmount = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'] + $data['total_custom_payment'];
                $payrollHistoryFinalData[] = [
                    "name" => $stateName,
                    "value" => round($totalAmount,2),
                ];
            }
            return $payrollHistoryFinalData;
        }
    }

    public function getPercentageByPositions($request, $startDate, $endDate, $payRollByPositions){ 
        $office_id = $request->input('office_id', 'all');
        $positionTotall = 0;
        foreach($payRollByPositions as $k => $v){
            $positionTotall+= $v['value'];
        }
        if(!empty($startDate) && !empty($endDate) ){ 
            // Build user IDs array for filtering
            $userIds = null;
            if ($request->has('office_id') && $office_id != 'all') {
                $userIds = User::where('office_id', $office_id)->pluck('id');
            }
            
            // User filtering - intersect with office filter if both exist
            if (!empty($request->input('user_id'))) {
                $singleUserId = collect([$request->input('user_id')]);
                $userIds = !empty($userIds) && $userIds->isNotEmpty() 
                    ? $userIds->intersect($singleUserId) 
                    : $singleUserId;
            }
            
            $payrollHistoryData = PayrollHistory::select(
                'positions.position_name as position_name',
                'payroll_history.user_id as u_id',
                DB::raw('SUM(payroll_history.commission) as total_commission'),
                DB::raw('SUM(payroll_history.override) as total_override'),
                DB::raw('SUM(payroll_history.adjustment) as total_adjustment'),
                DB::raw('SUM(payroll_history.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payroll_history.deduction) as total_deduction'),
                DB::raw('SUM(payroll_history.net_pay) as total_netpay'),
                DB::raw('SUM(payroll_history.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payroll_history.user_id')
            ->join('positions', 'positions.id', '=', 'users.sub_position_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollHistoryData = $payrollHistoryData->whereIn('payroll_history.user_id', $userIds);
            }
           $payrollHistoryData = $payrollHistoryData->groupBy('positions.position_name')->get();
            //dd($payrollHistoryData);
            $payrollData = Payroll::select(
                'positions.position_name as position_name',
                'payrolls.user_id as uid',
                DB::raw('SUM(payrolls.commission) as total_commission'),
                DB::raw('SUM(payrolls.override) as total_override'),
                DB::raw('SUM(payrolls.adjustment) as total_adjustment'),
                DB::raw('SUM(payrolls.reimbursement) as total_reimbursement'),
                DB::raw('SUM(payrolls.deduction) as total_deduction'),
                DB::raw('SUM(payrolls.net_pay) as total_netpay'),
                DB::raw('SUM(payrolls.custom_payment) as total_custom_payment')
            )
            ->join('users', 'users.id', '=', 'payrolls.user_id')
            ->join('positions', 'positions.id', '=', 'users.sub_position_id')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('payrolls.pay_period_to', [$startDate, $endDate])
                      ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
            });
            if (!empty($userIds)) {
                $payrollData = $payrollData->whereIn('payrolls.user_id', $userIds);
            }
           $payrollData = $payrollData->groupBy('positions.position_name')->get();
            $combinedResult = [];
            $currentYear = Carbon::now()->year;
            foreach ($payrollHistoryData as $stateName => $data) {
                if (!isset($combinedResult[$data['position_name']])) {
                    $combinedResult[$data['position_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['position_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['position_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['position_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['position_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['position_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['position_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
    
            foreach ($payrollData as $stateName => $data) {
                if (!isset($combinedResult[$data['position_name']])) {
                    $combinedResult[$data['position_name']] = [
                        'total_commission' => 0,
                        'total_override' => 0,
                        'total_adjustment' => 0,
                        'total_reimbursement' => 0,
                        'total_deduction' => 0,
                        'total_custom_payment' => 0
                    ];
                }
            
                $combinedResult[$data['position_name']]['total_commission'] += $data['total_commission'];
                $combinedResult[$data['position_name']]['total_override'] += $data['total_override'];
                $combinedResult[$data['position_name']]['total_adjustment'] += $data['total_adjustment'];
                $combinedResult[$data['position_name']]['total_reimbursement'] += $data['total_reimbursement'];
                $combinedResult[$data['position_name']]['total_deduction'] += $data['total_deduction'];
                $combinedResult[$data['position_name']]['total_custom_payment'] += $data['total_custom_payment'];
            }
            $payrollHistoryFinalData = [];
            $totalPayrollByLocations = 0;
            $priviesPayRollByPositionTotal = 0;
            $priviesPositionTotal = 0;
            $totalAmount = 0;
            foreach ($combinedResult as $stateName => $data) {
                $priviesPayRollByPositionTotal = $data['total_commission'] + $data['total_override'] + $data['total_adjustment'] + $data['total_reimbursement'] + $data['total_deduction'] + $data['total_custom_payment'];
                $payrollHistoryFinalData[] = [
                    "name" => $stateName,
                    "value" => round($priviesPayRollByPositionTotal,2),
                ];
                $priviesPositionTotal+= $priviesPayRollByPositionTotal;
            }
            if($priviesPositionTotal>0){
                $stateTotalPercentage = ($positionTotall/$priviesPositionTotal)*100;
                // return $stateTotalPercentage;
            }
            else{
                $stateTotalPercentage = 0;
            }
            
            //return $stateTotalPercentage;
    
            $stateTotalPercentages = $stateTotalPercentage > 100 ? "More" : "Less";
    
            if($positionTotall>0 && $priviesPositionTotal>0)
                $percentagePosition = ($positionTotall - $priviesPositionTotal)*100/$positionTotall;
            elseif($positionTotall<=0 && $priviesPositionTotal>0)
                $percentagePosition = -100;
            elseif($positionTotall>0 && $priviesPositionTotal<=0)
                $percentagePosition = ($positionTotall - $priviesPositionTotal)*100/$positionTotall;
            else
                $percentagePosition = 0;
            if($percentagePosition <= -100)
                $percentagePosition = -100;
            //return round($percentagePosition,1);
            return ['percentage'=>round($percentagePosition,1), 'previous_netpay'=>$priviesPositionTotal];
        }
    }

    /**
     * 
     * function for get projected payout Admin Graph
     */
    public function company_projected_payouts(Request $request)
    { 
        $res = app(ReportsProjectionController::class)->sales_report_for_admin_company_report($request);
        //dispatch(new ProjectedPayoutJob($request));
        return $res;
    }
    /**
     * 
     * function for get projected payout Admin Graph
     */

}
