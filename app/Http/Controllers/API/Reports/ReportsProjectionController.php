<?php

namespace App\Http\Controllers\API\Reports;

use App\Models\User;
use App\Models\State;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\SalesMaster;
use App\Models\UserRedlines;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\UserCommission;
use Illuminate\Support\Carbon;
use App\Models\LegacyApiNullData;
use App\Models\SaleMasterProcess;
use App\Models\ClawbackSettlement;
use App\Models\UserUpfrontHistory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\upfrontSystemSetting;
use Illuminate\Pagination\Paginator;
use App\Models\UserCommissionHistory;
use App\Models\LocationRedlineHistory;
use App\Traits\EmailNotificationTrait;
use App\Models\ProjectionUserOverrides;
use App\Models\UserOrganizationHistory;
use App\Models\ProjectionUserCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\UserSelfGenCommmissionHistory;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportsProjectionController extends Controller
{
    use EmailNotificationTrait;

    public function __construct(Request $request)
    {
    }

    public function sales_report(Request $request)
    {
        $result = array();
        if (!empty($request->perpage)) {
            $perPage = $request->perpage;
        } else {
            $perPage = 10;
        }

        $startDate = '';
        $endDate = '';
        $companyProfile = CompanyProfile::first();
        if ($request->has('filter') && !empty($request->input('filter'))) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } else if ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            } else if ($filterDataDateWise == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } else if ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } else if ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } else if ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } else if ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } else if ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else if ($filterDataDateWise == 'custom') {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate = date('Y-m-d', strtotime($eDate));
            }
        }

        $result = SalesMaster::with(['salesMasterProcessInfo',
            'legacyAPINull' => function ($q) {
                $q->whereNotNull('data_source_type');
            },
            'productdata'
        ])->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        });

        if ($request->has('sort_val') && !empty($request->input('sort_val'))) {
            $orderBy = $request->input('sort_val');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('office_id') && !empty($request->input('office_id'))) {
            $office_id = $request->office_id;
            if ($office_id != 'all') {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');

                $result->whereIn('pid', $salesPid);
            }
        }

        if ($request->has('date_filter') && !empty($request->input('date_filter'))) {
            if ($request->input('date_filter') == 'm1_date') {
                $result->whereNotNull('m1_date')
                ->whereNull('m2_date')
                ->whereNull('date_cancelled');
            } else if ($request->input('date_filter') == 'm2_date') {
                $result->whereNotNull('m2_date')
                ->whereNull('m1_date')
                ->whereNull('date_cancelled');
            } else if ($request->input('date_filter') == 'm1_date_m2_date') {
                $result->whereNotNull('m1_date')
                ->whereNotNull('m2_date')
                ->whereNull('date_cancelled');
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

        if ($request->has('search') && !empty($request->input('search'))) {
            $searchTerm = '%' . $request->input('search') . '%';
            $result->where(function ($query) use ($searchTerm) {
                $query->where('pid', 'LIKE', $searchTerm)
                    ->orWhere('customer_name', 'LIKE', $searchTerm)
                    ->orWhere('closer1_name', 'LIKE', $searchTerm)
                    ->orWhere('setter1_name', 'LIKE', $searchTerm);
                    // ->orWhere('closer2_name', 'LIKE', $searchTerm)
                    // ->orWhere('setter2_name', 'LIKE', $searchTerm);
            });
        }        

        if ($request->has('filter_product') && !empty($request->input('filter_product'))) {
            $product = $request->filter_product;
            $result->where(function ($query) use ($product) {
                return $query->where('product', $product);
            });
        }
        if ($request->has('filter_status') && !empty($request->input('filter_status'))) {
            $status = '%' . $request->input('filter_status') . '%';
            $result->where(function ($query) use ($status) {
                return $query->where('job_status', 'LIKE', $status);
            });
        }
        if ($request->has('filter_install') && !empty($request->input('filter_install'))) {
            $filter_install = '%' . $request->input('filter_install') . '%';
            $result->where(function ($query) use ($filter_install) {
                return $query->where('install_partner', 'LIKE', $filter_install);
            });
        }
        
        // if ($request->has('closed') && !empty($request->input('closed'))) {
        //     $result->whereNotNull('date_cancelled');
        // }

        // if ($request->has('m1') && !empty($request->input('m1'))) {
        //     $result->whereNotNull('m1_date');
        // }

        // if ($request->has('m2') && !empty($request->input('m2'))) {
        //     $result->whereNotNull('m2_date');
        // }

        if ($request->has('sort') && $request->input('sort') != '' && ($request->input('sort') == 'm1' || $request->input('sort') == 'm2' || $request->input('sort') == 'total_commission')) {
            $data = $result->orderBy('id', $orderBy)->get();
        } else {
            if ($request->has('sort') && $request->input('sort') == 'state') {
                $result->orderBy('customer_state', $orderBy);
            } else if ($request->has('sort') && $request->input('sort') == 'kw') {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $result->orderBy('gross_account_value', $orderBy);
                } else {
                    $result->orderBy(DB::raw('CAST(kw AS UNSIGNED)'), $orderBy);
                }
            } else if ($request->has('sort') && $request->input('sort') == 'epc') {
                $result->orderBy('epc', $orderBy);
            } else if ($request->has('sort') && $request->input('sort') == 'net_epc') {
                $result->orderBy('net_epc', $orderBy);
            } else if ($request->has('sort') && $request->input('sort') == 'adders') {
                $result->orderBy(DB::raw('CAST(adders AS UNSIGNED)'), $orderBy);
            } else {
                $result->orderBy('id', $orderBy);
            }
            $data = $result->paginate($perPage);
        }

        if (sizeof($data) == 0) {
            return response()->json([
                'ApiName' => 'sales_report_list',
                'status' => false,
                'message' => 'data not found'
            ]);
        }

        $data->transform(function ($data) use ($companyProfile) {
            $commissionData = UserCommission::where(['pid' => $data->pid, 'status' => 3])->first();
            if (!in_array($data->salesMasterProcessInfo->mark_account_status_id, [1, 6]) && $commissionData) {
                $mark_account_status_name = ($commissionData) ? 'Paid' : null;
            } else {
                $mark_account_status_name = isset($data->salesMasterProcessInfo->status->account_status) ? $data->salesMasterProcessInfo->status->account_status : null;
            }

            $closer1_m1 = isset($data->salesMasterProcessInfo->closer1_m1) ? $data->salesMasterProcessInfo->closer1_m1 : null;
            $closer1_m2 = isset($data->salesMasterProcessInfo->closer1_m2) ? $data->salesMasterProcessInfo->closer1_m2 : null;
            $closer2_m1 = isset($data->salesMasterProcessInfo->closer2_m1) ? $data->salesMasterProcessInfo->closer2_m1 : null;
            $closer2_m2 = isset($data->salesMasterProcessInfo->closer2_m2) ? $data->salesMasterProcessInfo->closer2_m2 : null;

            $setter1_m1 = isset($data->salesMasterProcessInfo->setter1_m1) ? $data->salesMasterProcessInfo->setter1_m1 : null;
            $setter1_m2 = isset($data->salesMasterProcessInfo->setter1_m2) ? $data->salesMasterProcessInfo->setter1_m2 : null;
            $setter2_m1 = isset($data->salesMasterProcessInfo->setter2_m1) ? $data->salesMasterProcessInfo->setter2_m1 : null;
            $setter2_m2 = isset($data->salesMasterProcessInfo->setter2_m2) ? $data->salesMasterProcessInfo->setter2_m2 : null;

            $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
            $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
            $total_commission = ($data->salesMasterProcessInfo->closer1_commission + $data->salesMasterProcessInfo->closer2_commission + $data->salesMasterProcessInfo->setter1_commission + $data->salesMasterProcessInfo->setter2_commission);

            $alertCenter = 0;
            if ($data->legacyAPINull) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($data->legacyAPINull) {
                        if (!empty($data->legacyAPINull->sales_alert) || !empty($data->legacyAPINull->missingrep_alert) || !empty($data->legacyAPINull->locationRedline_alert)) {
                            $alertCenter = 1;
                        }
                    }
                } else {
                    if ($data->legacyAPINull) {
                        if (!empty($data->legacyAPINull->sales_alert) || !empty($data->legacyAPINull->missingrep_alert) || !empty($data->legacyAPINull->closedpayroll_alert) || !empty($data->legacyAPINull->repredline_alert)) {
                            $alertCenter = 1;
                        }
                    }
                }
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $locationData = Locations::with('State')->whereHas('State', function ($q) use ($data) {
                    $q->where('state_code', $data->customer_state);
                })->where('general_code', $data->location_code)->first();
            } else {
                if (config('app.domain_name') == 'flex') {
                    $locationData = Locations::with('State')->where('general_code', '=', $data->customer_state)->first();
                } else {
                    $locationData = Locations::with('State')->where('general_code', '=', $data->location_code)->first();
                }
            }

            if ($locationData) {
                $state_code = $locationData->state->state_code;
            } else {
                $state_code = null;
            }

            $closerId = $data->closer1_id;
            $closer2Id = $data->closer2_id;
            $setterId = $data->setter1_id;
            $setter2Id = $data->setter2_id;
            $m1date = $data->m1_date;
            $m2date = $data->m2_date;
            $grossAmountValue = $data->gross_account_value;
            $customer_signoff = $data->customer_signoff;
            $kw = $data->kw;
            $pid = $data->pid;
            $net_epc = $data->net_epc;
            $location_code = $data->location_code;
            $customer_state = $data->customer_state;

            $sales_projection_m1_amount = '';
            $sales_projection_m2_amount = '';
            if (empty($data->m1_date)) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $sales_projection_m1_amount = $this->pestSalesProjectionM1([
                        'closer1_id' => $closerId,
                        'closer2_id' => $closer2Id,
                        'setter1_id' => $setterId,
                        'setter2_id' => $setter2Id,
                        'm1_date' => $m1date,
                        'customer_signoff' => $customer_signoff,
                        'kw' => $kw,
                        'pid' => $pid,
                        'date_cancelled' => $data->date_cancelled
                    ]);
                } else {
                    $sales_projection_m1_amount = $this->salesProjectionM1([
                        'closer1_id' => $closerId,
                        'closer2_id' => $closer2Id,
                        'setter1_id' => $setterId,
                        'setter2_id' => $setter2Id,
                        'm1_date' => $m1date,
                        'customer_signoff' => $customer_signoff,
                        'kw' => $kw,
                        'pid' => $pid,
                        'date_cancelled' => $data->date_cancelled
                    ]);
                }
            }

            if (empty($data->m2_date)) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $sales_projection_m2_amount = $this->pestSalesProjectionM2([
                        'closer1_id' => $closerId,
                        'closer2_id' => $closer2Id,
                        'setter1_id' => $setterId,
                        'setter2_id' => $setter2Id,
                        'm1_date' => $m1date,
                        'm2_date' => $m2date,
                        'customer_signoff' => $customer_signoff,
                        'kw' => $kw,
                        'pid' => $pid,
                        'net_epc' => $net_epc,
                        'location_code' => $location_code,
                        'customer_state' => $customer_state,
                        'date_cancelled' => $data->date_cancelled,
                        'gross_account_value' => $grossAmountValue
                    ]);
                } else {
                    $sales_projection_m2_amount = $this->salesProjectionM2([
                        'closer1_id' => $closerId,
                        'closer2_id' => $closer2Id,
                        'setter1_id' => $setterId,
                        'setter2_id' => $setter2Id,
                        'm1_date' => $m1date,
                        'm2_date' => $m2date,
                        'customer_signoff' => $customer_signoff,
                        'kw' => $kw,
                        'pid' => $pid,
                        'net_epc' => $net_epc,
                        'location_code' => $location_code,
                        'customer_state' => $customer_state,
                        'date_cancelled' => $data->date_cancelled,
                        'gross_account_value' => $grossAmountValue
                    ]);
                }
            }

            $sales_projection_total_m2 = 0;
            if (empty($data->date_cancelled) && $sales_projection_m2_amount) {
                if (empty($data->m1_date) && empty($data->m2_date)) {
                    $m1_amount_projected = $sales_projection_m1_amount ? $sales_projection_m1_amount['amount'] : 0;
                    $sales_projection_total_m2 = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                } else if (empty($data->m2_date)) {
                    $m1_amount_projected = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount') ?? 0;
                    $sales_projection_total_m2 = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                }
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                unset($data['net_epc']);
                unset($data['epc']);
                unset($data['kw']);
                unset($data['setter1_id']);
                unset($data['setter2_id']);
            }
            $all_milestone = [];
            for($i=1; $i<5;$i++){
                $milestone['name'] = 'Payment '.$i.'(M'.$i.')';
                $milestone['value'] = $i.'00';
                $milestone['last_milestone_projection'] = '';
                $milestone['date'] = date('d/m/Y');
                $all_milestone[] = $milestone;
            }
            return [
                'id' => $data->id,
                'pid' => $data->pid,
                'job_status' => $data->job_status,
                'alertcentre_status' => $alertCenter,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'state_id' => $state_code,
                'mark_account_status_name' => $mark_account_status_name,
                'closer1_detail' => [
                    'id' => $data->closer1_id,
                    'first_name' => $data->closer1_name,
                    'last_name' => ''
                ],
                'closer2_detail' => [
                    'id' => $data->closer2_id,
                    'first_name' => $data->closer2_name,
                    'last_name' => ''
                ],
                'setter1_detail' => [
                    'id' => $data->setter1_id,
                    'first_name' => $data->setter1_name,
                    'last_name' => ''
                ],
                'setter2_detail' => [
                    'id' => $data->setter2_id,
                    'first_name' => $data->setter2_name,
                    'last_name' => ''
                ],
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'adders' => isset($data->adders) ? $data->adders : null,
                'kw' => isset($data->kw) ? $data->kw : null,
                'date_cancelled' => isset($data->date_cancelled) ? dateToYMD($data->date_cancelled) : null,
                'total_m1' => $total_m1,
                'total_m2' => $total_m2,
                'm1_date' =>  isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                'total_commission' => $total_commission,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
                'data_source_type' => $data->data_source_type,
                'sales_projection_m1_amount' => $sales_projection_m1_amount,
                'sales_projection_m2_amount' => $sales_projection_m2_amount,
                'sales_projection_total_m2' => isset($sales_projection_total_m2) ? $sales_projection_total_m2 : '',
                'sales_projection_total_commission' => isset($sales_projection_m2_amount['commission']) ? $sales_projection_m2_amount['commission'] : '',
                'services_done' => $data->service_completed,
                'gross_account_value' => $data->gross_account_value,
                'product'=>isset($data->productdata)?$data->productdata->name:'',
                'product_code'=>isset($data->productdata)?$data->productdata->product_id:'',
                'last_milestone' => array('name'=>'M1','value'=>'0.12','date'=>date('d/m/Y')),
                'last_milestone_projection' => '',
                'all_milestone' => $all_milestone,
                'total_override' => 100,
                'sales_projection_total_override' => '',
            ];
        });

        if ($request->has('sort') &&  $request->input('sort') == 'm1') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_m1'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_m1'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }
        if ($request->has('sort') &&  $request->input('sort') == 'm2') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_m2'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_m2'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }
        if ($request->has('sort') &&  $request->input('sort') == 'total_commission') {
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_commission'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_commission'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perPage);
        }

        return response()->json([
            'ApiName' => 'sales_report_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
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

    public function salesProjectionM1($val)
    {
        if ($val['date_cancelled']) {
            return 0;
        }
        $closerId = $val['closer1_id'];
        $closer2Id = $val['closer2_id'];
        $setterId = $val['setter1_id'];
        $setter2Id = $val['setter2_id'];
        $customer_signoff = $val['customer_signoff'];
        $kw = $val['kw'];
        $total = 0;

        if ($closerId != null && $closer2Id != null) {
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $closerUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();
            $upfrontAmount = '';
            $upfrontType = '';

            if ($closerUpfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                }
            }

            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $closer2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
            $upfrontAmount2 = '';
            $upfrontType2 = '';
            if ($closer2Upfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                }
            }

            if (!empty($closerUpfront) && !empty($upfrontAmount) && !empty($upfrontType)) {
                if ($closer2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }
                }

                if (!empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }
                $total += $amount;
            }

            if (!empty($closer2Upfront) && !empty($upfrontAmount2) && !empty($upfrontType2)) {
                if ($closerUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    } else {
                        $amount2 = ($upfrontAmount2 * $kw);
                    }
                }

                if (!empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }
                $total += $amount2;
            }
        } else if ($closerId) {
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == '1') {
                $primaryUpfront = PositionCommissionUpfronts::where(['position_id' => $userOrganizationHistory->sub_position_id, 'upfront_status' => 1])->first();
                $amount1 = 0;
                if ($primaryUpfront) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;

                    if ($upfrontAmount) {
                        if ($upfrontType == 'per sale') {
                            $amount1 = $upfrontAmount;
                        } else {
                            $amount1 = ($upfrontAmount * $kw);
                        }
                    }
                }

                $amount2 = 0;
                $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                    ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                $selfUpFrontAmount = @$upfrontHistory->upfront_pay_amount;
                $selfUpFrontType = @$upfrontHistory->upfront_sale_type;

                if ($selfUpFrontAmount) {
                    if ($selfUpFrontType == 'per sale') {
                        $amount2 = $selfUpFrontAmount;
                    } else {
                        $amount2 = ($selfUpFrontAmount * $kw);
                    }
                }

                $upfrontSetting = upfrontSystemSetting::first();
                if ($upfrontSetting && $upfrontSetting->upfront_for_self_gen == 'Pay sum of setter and closer upfront') {
                    $amount = $amount1 + $amount2;
                } else {
                    $amount = max($amount1, $amount2);
                }
                $total += $amount;
            } else {
                $closerUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                if ($closerUpfront) {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;
                    } else {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;
                    }

                    if ($upfrontAmount && $upfrontType) {
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        } else {
                            $amount = ($upfrontAmount * $kw);
                        }

                        if (!empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        }
                        $total += $amount;
                    }
                }
            }
        }

        if ($setterId != null && $setter2Id != null) {
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $setterUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();
            $upfrontAmount = '';
            $upfrontType = '';
            if ($setterUpfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                }
            }

            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $setter2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
            $upfrontAmount2 = '';
            $upfrontType2 = '';
            if ($setter2Upfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                }
            }

            if (!empty($setterUpfront) && !empty($upfrontAmount) && !empty($upfrontType)) {
                if ($setter2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }
                }

                if (!empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                    $amount = $setterUpfront->upfront_limit;
                }

                $total += $amount;
            }

            if (!empty($setter2Upfront) && !empty($upfrontAmount2) && !empty($upfrontType2)) {
                if ($setterUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    } else {
                        $amount2 = ($upfrontAmount2 * $kw);
                    }
                }

                if (!empty($setter2Upfront->upfront_limit) && $amount2 > $setter2Upfront->upfront_limit) {
                    $amount2 = $setter2Upfront->upfront_limit;
                }

                $total += $amount2;
            }
        } else if ($setterId) {
            $upfrontAmount = '';
            $upfrontType = '';
            $setter = User::where('id', $setterId)->first();
            if ($setter && $setterId != $closerId) {
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                $setterUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                if ($setterUpfront) {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;
                    } else {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;
                    }
                }

                if ($upfrontAmount && $upfrontType) {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }

                    if (!empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                        $amount = $setterUpfront->upfront_limit;
                    }
                    $total += $amount;
                }
            }
        }
        return [
            'user_id'  => '',
            'position_id'  => '',
            'amount_type'  => "m1",
            'amount'  => $total
        ];
    }

    public function pestSalesProjectionM1($val)
    {
        if($val['date_cancelled']){
            return 0;
        }
        $closer1 = $val['closer1_id'];
        $closer2 = $val['closer2_id'];
        $customerSignOff = $val['customer_signoff'];
        $accountSummary = @$val['from'];
        $total =0;

        if ($closer1 != null && $closer2 != null){
            $closer = User::where('id', $closer1)->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $closerUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();

            $upfrontAmount = '';
            $upfrontType = '';
            if ($closerUpfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                }
            }

            $closer2User = User::where('id', $closer2)->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $closer2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
            $upfrontAmount2 = '';
            $upfrontType2 = '';
            if ($closer2Upfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                }
            }

            if (!empty($closerUpfront) && !empty($upfrontAmount) && !empty($upfrontType)) {
                if ($closer2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    }
                }

                if (!empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }
                $total += $amount;

            }

            if (!empty($closer2Upfront) && !empty($upfrontAmount2) && !empty($upfrontType2)) {
                if ($closerUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    }
                }

                if (!empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }
                $total += $amount2;

            }
        }

        else if ($closer1) {
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $closerUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
            if ($closerUpfront) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                }

                if ($upfrontAmount && $upfrontType) {
                    $amount = 0;
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    }

                    if (!empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    }
                    $total += $amount;
                }
            }
        }

        if ($accountSummary == 'm1_amount') {
            return [
                'closer1_m1' => isset($amount)? $amount:0,
                'closer2_m1' => isset($amount2)? $amount2:0
            ]; 
        }else {
            return [
                'user_id'  => '',
                'position_id'  => '',
                'amount_type'  => "m1",
                'amount'  => $total
            ];
        }
    }

    public function pestSalesProjectionM2($checked)
    {
        if ($checked['date_cancelled']) {
            return 0;
        }
        $closer1  = $checked['closer1_id'];
        $closer2  = $checked['closer2_id'];
        $grossAmountValue = $checked['gross_account_value'];
        $approvedDate = $checked['customer_signoff'];

        $companyMargin = CompanyProfile::where('id', 1)->first();
        // Calculate setter & closer commission
        $dataCommission['closer1_commission'] = 0;
        $dataCommission['closer2_commission'] = 0;
        $dataCommission['setter1_commission'] = 0;
        $dataCommission['setter2_commission'] = 0;

        $closerCommission = 0;
        if ($closer1 != null && $closer2 != null) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commission_type = $commissionHistory->commission_type;
            }

            $commissionPercentage2 = 0;
            $commission2History = UserCommissionHistory::where('user_id', $closer2)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
                $commission_type2 = $commission2History->commission_type;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentage && $commissionPercentage2) {
                    if ($commission_type == 'per sale') {
                        $closer1Commission = (($commissionPercentage * $x) / 2);
                    } else {
                        $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                    } 

                    if ($commission_type2 == 'per sale') {
                        $closer2Commission = (($commissionPercentage2 * $x) / 2);
                    } else {
                        $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                    } 
                } else if ($commissionPercentage) {
                    if ($commission_type == 'per sale') {
                        $closer1Commission = $commissionPercentage * $x;
                    } else {
                        $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                    } 
                } else if ($commissionPercentage2) {
                    if ($commission_type2 == 'per sale') {
                        $closer2Commission = $commissionPercentage2 * $x;
                    } else {
                        $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                    } 
                }
            } else {
                if ($commissionPercentage && $commissionPercentage2) {
                    if ($commission_type == 'per sale') {
                        $closer1Commission = (($commissionPercentage) / 2);
                    } else {
                        $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                    } 

                    if ($commission_type2 == 'per sale') {
                        $closer2Commission = (($commissionPercentage2) / 2);
                    } else {
                        $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                    } 
                } else if ($commissionPercentage) {
                    if ($commission_type == 'per sale') {
                        $closer1Commission = $commissionPercentage;
                    } else {
                        $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                    } 
                } else if ($commissionPercentage2) {
                    if ($commission_type2 == 'per sale') {
                        $closer2Commission = $commissionPercentage2;
                    } else {
                        $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                    } 
                }
            }

            $closer1Commission = ($closer1Commission);
            $closer2Commission = ($closer2Commission);
            $closerCommission = ($closer1Commission + $closer2Commission);
            $dataCommission['closer1_commission'] = $closer1Commission;
            $dataCommission['closer2_commission'] = $closer2Commission;

        } else if ($closer1) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commission_type = $commissionHistory->commission_type;
            }
         
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commission_type == 'per sale') {
                    $closerCommission = $commissionPercentage * $x;
                } else {
                    $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } 
            } else {
                if ($commission_type == 'per sale') {
                    $closerCommission = $commissionPercentage;
                } else {
                    $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
                } 
            }

            $dataCommission['closer1_commission'] = $closerCommission;
        }

        if (isset($accountSummary) && $accountSummary == 'commission') {
            return $dataCommission;
        }else {
            $commissiondata['commission'] =  $closerCommission;
            $commissiondata['closer_commission'] =  $closerCommission;
            $commissiondata['setter_commission'] =  0;
            return $commissiondata;
        }
    }

    public function salesProjectionM2($checked)
    {
        if($checked['date_cancelled']){
            return 0;
        }
        return $this->subroutineEight($checked);
    }

    public function subroutineEight($checked)
    {
        $companyProfile = CompanyProfile::where('id', 1)->first();
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
            $commission11 = $this->subroutineEightForFlex($checked);
        }else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            $commission11 = $this->subroutineEightForTurf($checked);
        }else if ($companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $commission11 = $this->subroutineEightForSolar($checked);
        }
        return $commission11;
    }


    public function subroutineSix($checked)
    {
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $approvedDate = $checked['customer_signoff'];
        
        if(config("app.domain_name") == 'flex') {
            $saleState = $checked['customer_state'];
        }else{
            $saleState = $checked['location_code'];
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
            //customer state Id..................................................
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
            $data['closer2_redline'] = '0';
            $data['setter1_redline'] = '0';
            $data['setter2_redline'] = '0';

            if ($setterId && $setter2Id) {
                // setter1
                $setter = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                    //                    if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter_redline = $setter->self_gen_redline;
                        $redline_amount_type = $setter->self_gen_redline_amount_type;
                    }
                } else {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter_redline = $setter->redline;
                        $redline_amount_type = $setter->redline_amount_type;
                    }
                }

                $setterOfficeId = $setter->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['setter1_redline'] = $setter_redline;
                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setterStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setterStateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter_redline - $setterStateRedline);
                    $data['setter1_redline'] = $redline;
                }

                // setter2 
                $setter2 = User::where('id', $setter2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                    //if ($setter2->self_gen_accounts == 1 && $setter2->self_gen_type == 3) {
                    $userRedlines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter2_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter2_redline = $setter2->self_gen_redline;
                        $redline_amount_type = $setter2->self_gen_redline_amount_type;
                    }
                } else {
                    $user2Redlines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $setter2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $setter2_redline = $setter2->redline;
                        $redline_amount_type = $setter2->redline_amount_type;
                    }
                }

                $setter2OfficeId = $setter2->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['setter2_redline'] = $setter2_redline;
                } else {
                    $setterLocation = Locations::where('id', $setter2OfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setter2StateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setter2StateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter2_redline - $setter2StateRedline);
                    $data['setter2_redline'] = $redline;
                }
            }
            else if ($setterId) {
                $setter = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                //                if ($closerId == $setterId && $setter->self_gen_accounts == 1) {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        if ($setter->position_id == 3) {
                            $setter_redline = $setter->self_gen_redline;
                            $redline_amount_type = $setter->self_gen_redline_amount_type;
                        } else {
                            $setter_redline = $setter->redline;
                            $redline_amount_type = $setter->redline_amount_type;
                        }
                    }
                } else {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && @$userOrganizationHistory['position_id'] == 2) {
                        $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $setter_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $setter_redline = $setter->self_gen_redline;
                            $redline_amount_type = $setter->self_gen_redline_amount_type;
                        }
                    } else {
                        $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $setter_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $setter_redline = $setter->redline;
                            $redline_amount_type = $setter->redline_amount_type;
                        }
                    }
                }

                $setterOfficeId = $setter->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['setter1_redline'] = $setter_redline;
                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setterStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setterStateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter_redline - $setterStateRedline);
                    $data['setter1_redline'] = $redline;
                }
            }

            if ($closerId && $closer2Id) {
                // closer1
                $closer1 = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                //                if ($closer1->self_gen_accounts == 1 && $closer1->self_gen_type == 2) {
                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer1_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $closer1_redline = $closer1->self_gen_redline;
                        $redline_amount_type = $closer1->self_gen_redline_amount_type;
                    }
                } else {
                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer1_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $closer1_redline = $closer1->redline;
                        $redline_amount_type = $closer1->redline_amount_type;
                    }
                }

                $closer1OfficeId = $closer1->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['closer1_redline'] = $closer1_redline;
                } else {
                    $closerLocation = Locations::where('id', $closer1OfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                        //$closerStateRedline = $closerLocation->redline_standard;
                    }
                    //closer_redline
                    $redline = $saleStandardRedline + ($closer1_redline - $closerStateRedline);
                    $data['closer1_redline'] = $redline;
                }

                // closer2
                $closer2 = User::where('id', $closer2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                //                if ($closer2->self_gen_accounts == 1 && $closer2->self_gen_type == 2) {
                    $user2Redlines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $closer2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $closer2_redline = $closer2->self_gen_redline;
                        $redline_amount_type = $closer2->self_gen_redline_amount_type;
                    }
                } else {
                    $user2Redlines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $closer2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $closer2_redline = $closer2->redline;
                        $redline_amount_type = $closer2->redline_amount_type;
                    }
                }

                $closer2OfficeId = $closer2->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['closer2_redline'] = $closer2_redline;
                } else {
                    $closerLocation = Locations::where('id', $closer2OfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }

                    //closer_redline
                    $redline = $saleStandardRedline + ($closer2_redline - $closerStateRedline);
                    $data['closer2_redline'] = $redline;
                }
            }
            else if ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                //                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        if ($closer->position_id == 3) {
                            $closer_redline = $closer->self_gen_redline;
                            $redline_amount_type = $closer->self_gen_redline_amount_type;
                        } else {
                            $closer_redline = $closer->redline;
                            $redline_amount_type = $closer->redline_amount_type;
                        }
                    }
                } else {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && @$userOrganizationHistory['position_id'] == 3) {
                        $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $closer_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $closer_redline = $closer->self_gen_redline;
                            $redline_amount_type = $closer->self_gen_redline_amount_type;
                        }
                    } else {
                        $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $closer_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $closer_redline = $closer->redline;
                            $redline_amount_type = $closer->redline_amount_type;
                        }
                    }
                }

                $closerOfficeId = $closer->office_id;
                if ($redline_amount_type == 'Fixed') {
                    $data['closer1_redline'] = $closer_redline;
                } else {
                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    //closer_redline
                    $redline = $saleStandardRedline + ($closer_redline - $closerStateRedline);
                    $data['closer1_redline'] = $redline;
                }
                //                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                    $redline1 = $data['setter1_redline'];
                    $redline2 = $data['closer1_redline'];
                    if ($redline1 > $redline2) {
                        $data['closer1_redline'] = $redline2;
                    } else {
                        $data['closer1_redline'] = $redline1;
                    }
                }
            }
            return $data;
        }
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

        $clawbackPid = ClawbackSettlement::where('pid','!=',null)->groupBy('pid')->pluck('pid')->toArray();
        $companyProfile = CompanyProfile::first();

        if ($request->has('filter') && !empty($request->input('filter')))
        {
            if($filter=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate =  date('Y-m-d', strtotime(now()));

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
               
            }
            else if($filter=='this_month')
            {
                $month = \Carbon\Carbon::now()->daysInMonth;
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate =  date('Y-m-d', strtotime($startOfMonth));
                $endDate =  date('Y-m-d', strtotime($endOfMonth));

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='this_quarter')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='last_quarter')
            {

                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='this_year')
            {

                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
            else if($filter=='last_year')
            {
                $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

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

                $date = Carbon::parse($startDate);
                $eom = Carbon::parse($endDate);

            }
        }else{
            $data = array();
            $totalSales = SalesMaster::with('salesMasterProcess')->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::where('m1_date', '!=', null)->whereNull('date_cancelled')->count();
                $m2Pending  = SalesMaster::whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::where('m2_date', '!=', null)->count();
                $m2Pending  = SalesMaster::where('date_cancelled', '=', null)->where('m2_date', '=', null)->count();
            }
            $cancelled  = SalesMaster::where('date_cancelled', '!=', null)->count();
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            Carbon::now()->subMonths(1)->endOfMonth();
        }

        if ($office_id!='all')
        {
            $totalSales = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->get();
            $totalReps  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->groupBy('sales_rep_email')->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', null)->where('m2_date', '=', null)->count();
            }
            $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereNotIn('pid',$clawbackPid)->count();
            $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$salesPid)->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(gross_account_value as decimal(10,2))) As kw')
                ->whereBetween('customer_signoff',[$startDate,$endDate])
                ->whereIn('pid',$salesPid)
                ->groupBy('month')
                ->orderBy('kw', 'desc')
                ->first();

                $bestweek =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10,2))) As kw ,
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
                
                $bestDay = SalesMaster::select(DB::raw('SUM(gross_account_value) as kw'), 'customer_signoff as date')
                ->whereBetween('customer_signoff',[$startDate,$endDate])
                ->whereIn('pid',$salesPid)
                ->groupBy('customer_signoff')
                ->orderByDesc('kw')
                ->first();
            } else {
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
                
                $bestDay = SalesMaster::select(DB::raw('SUM(kw) as kw'), 'customer_signoff as date')
                ->whereBetween('customer_signoff',[$startDate,$endDate])
                ->whereIn('pid',$salesPid)
                ->groupBy('customer_signoff')
                ->orderByDesc('kw')
                ->first();
            }

        }else{
            // Apply user filtering when office_id = 'all' but user_id is specified
            if (!empty($request->user_id)) {
                $userId = User::where('id', $request->user_id)->pluck('id');
                $userSalesPid = SaleMasterProcess::whereIn('closer1_id', $userId)
                    ->orWhereIn('closer2_id', $userId)
                    ->orWhereIn('setter1_id', $userId)
                    ->orWhereIn('setter2_id', $userId)
                    ->pluck('pid');
                $totalSales = SalesMaster::with('salesMasterProcess')
                    ->whereBetween('customer_signoff',[$startDate,$endDate])
                    ->whereIn('pid', $userSalesPid)
                    ->get();
            } else {
                $totalSales = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff',[$startDate,$endDate])->get();
            }
            //return count($totalSales);
            $totalReps = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->groupBy('sales_rep_email')->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                $m2Pending  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->count();
            }
            $cancelled  = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->whereNotIn('pid',$clawbackPid)->count();
            $clawback   = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->where('date_cancelled', '!=', null)->whereIn('pid',$clawbackPid)->count();

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(gross_account_value as decimal(10,2))) As kw')
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('month')
                    ->orderBy('kw', 'desc')
                    ->first();

                $bestDay = SalesMaster::select(DB::raw('SUM(gross_account_value) as kw'), 'customer_signoff as date')
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('customer_signoff')
                    ->orderByDesc('kw')
                    ->first();

                $bestweek =  SalesMaster::selectRaw("id,customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10,2))) As kw ,
                STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
                adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('week')
                    ->orderBy('kw', 'desc')
                    ->first();
            } else {
                $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(kw as decimal(5,2))) As kw')
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('month')
                    ->orderBy('kw', 'desc')
                    ->first();

                $bestDay = SalesMaster::select(DB::raw('SUM(kw) as kw'), 'customer_signoff as date')
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('customer_signoff')
                    ->orderByDesc('kw')
                    ->first();

                $bestweek =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(kw as decimal(5,2))) As kw ,
                STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
                adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->groupBy('week')
                    ->orderBy('kw', 'desc')
                    ->first();
            }


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

        $total_kw_installed = 0;
        $total_kw_pending = 0;
        $total_kw = 0;
        $total_revenue_generated = 0;
        $total_revenue_pending = 0;
        $avg_profit_per_rep = 0;

        /* code for projected values */
        $total_projected_comission = 0;
        
        // Calculate paid commissions using UserCommission table (replaces outdated loop)
        $salesPids = $totalSales->pluck('pid');
        $commissionQuery = UserCommission::whereIn('pid', $salesPids)->where('status', '3');
        if (!empty($request->user_id)) {
            $userId = User::where('id', $request->user_id)->pluck('id');
            if ($userId->isNotEmpty()) {
                $commissionQuery->whereIn('user_id', $userId);
            }
        }
        $total_paid_comissions = $commissionQuery->sum('amount');

        foreach ($totalSales as $key => $sale) {
            $m1_comission = 0;
            $m2_setter_comission = 0;
            $m2_closer_comission = 0;
            $closerId = $sale->salesMasterProcess->closer1_id;
            $closer2Id = $sale->salesMasterProcess->closer2_id;
            $setterId = $sale->salesMasterProcess->setter1_id;
            $setter2Id = $sale->salesMasterProcess->setter2_id;
            $m1date = $sale->m1_date;
            $m2date = $sale->m2_date;
            $grossAmountValue = $sale->gross_account_value;
            $customer_signoff = $sale->customer_signoff;
            $kw = $sale->kw;
            $pid = $sale->pid;
            $net_epc = $sale->net_epc;
            $location_code = $sale->location_code;
            $customer_state = $sale->customer_state;
            $sales_projection_m1_amount = '';
            $sales_projection_m2_amount = '';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if(empty($sale->m2_date)){
                    $sales_projection_m2_amount = $this->pestSalesProjectionM2(['closer1_id'=>$closerId,'closer2_id'=>$closer2Id,'setter1_id'=>$setterId,'setter2_id'=>$setter2Id,
                    'm1_date'=>$m1date,'m2_date'=>$m2date,'customer_signoff'=>$customer_signoff,'kw'=>$kw,'pid'=>$pid,'net_epc'=>$net_epc,'location_code'=>$location_code, 'customer_state'=>$customer_state, 'date_cancelled'=>$sale->date_cancelled, 'gross_account_value'=>$grossAmountValue]);
                }
            } else {
                if(empty($sale->m2_date)){
                    $sales_projection_m2_amount = $this->salesProjectionM2(['closer1_id'=>$closerId,'closer2_id'=>$closer2Id,'setter1_id'=>$setterId,'setter2_id'=>$setter2Id,
                    'm1_date'=>$m1date,'m2_date'=>$m2date,'customer_signoff'=>$customer_signoff,'kw'=>$kw,'pid'=>$pid,'net_epc'=>$net_epc,'location_code'=>$location_code, 'customer_state'=>$customer_state, 'date_cancelled'=>$sale->date_cancelled, 'gross_account_value'=>$grossAmountValue]);
                }
            }

            $m1_m2_comission = 0;
            if (empty($sale->date_cancelled) && $sales_projection_m2_amount) {
                if (empty($sale->m1_date) && empty($sale->m2_date)) {
                    $m1_m2_comission = $sales_projection_m2_amount['commission'];
                } else if (empty($sale->m2_date)) {
                    $m1_amount_projected = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount') ?? 0;
                    $sales_projection_m2_amount['commission'] = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                    $m1_m2_comission = $sales_projection_m2_amount['commission'];
                }
            }

            $total_projected_comission += $m1_m2_comission;

            $closer1_m1 = isset($sale->salesMasterProcess->closer1_m1)?$sale->salesMasterProcess->closer1_m1:0;
            $closer1_m2 = isset($sale->salesMasterProcess->closer1_m2)?$sale->salesMasterProcess->closer1_m2:0;
            $closer2_m1 = isset($sale->salesMasterProcess->closer2_m1)?$sale->salesMasterProcess->closer2_m1:0;
            $closer2_m2 = isset($sale->salesMasterProcess->closer2_m2)?$sale->salesMasterProcess->closer2_m2:0;

            $setter1_m1 = isset($sale->salesMasterProcess->setter1_m1)?$sale->salesMasterProcess->setter1_m1:0;
            $setter1_m2 = isset($sale->salesMasterProcess->setter1_m2)?$sale->salesMasterProcess->setter1_m2:0;
            $setter2_m1 = isset($sale->salesMasterProcess->setter2_m1)?$sale->salesMasterProcess->setter2_m1:0;
            $setter2_m2 = isset($sale->salesMasterProcess->setter2_m2)?$sale->salesMasterProcess->setter2_m2:0;

            // Apply user filtering to commission calculation in loop
            if (!empty($request->user_id)) {
                $userId = $request->user_id;
                $total_m1 = 0;
                $total_m2 = 0;
                
                // Only include commissions for the filtered user
                if ($sale->salesMasterProcess->closer1_id == $userId) {
                    $total_m1 += $closer1_m1;
                    $total_m2 += $closer1_m2;
                }
                if ($sale->salesMasterProcess->closer2_id == $userId) {
                    $total_m1 += $closer2_m1;
                    $total_m2 += $closer2_m2;
                }
                if ($sale->salesMasterProcess->setter1_id == $userId) {
                    $total_m1 += $setter1_m1;
                    $total_m2 += $setter1_m2;
                }
                if ($sale->salesMasterProcess->setter2_id == $userId) {
                    $total_m1 += $setter2_m1;
                    $total_m2 += $setter2_m2;
                }
            } else {
                // When no user filter, include all users (original behavior)
                $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
                $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
            }
            
            $clawbackss = ClawbackSettlement::where(['pid'=> $sale->pid, 'type'=> 'commission', 'status'=> 3])->first();
            if ($clawbackss) {
                $clawbackAmount = $clawbackss->clawback_amount;
            }else {
                $clawbackAmount = 0;
            }
           
            // Commission calculation moved outside loop for efficiency and accuracy
            // $total_paid_comissions is now calculated using UserCommission table before the loop
            
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($sale->m2_date != null && $sale->date_cancelled == null) {
                    $total_kw_installed = round(($total_kw_installed + $sale->gross_account_value), 3);
                }
                if ($sale->m2_date == null && $sale->date_cancelled == null) {
                    $total_kw_pending = round(($total_kw_pending + $sale->gross_account_value), 3);
                    $total_revenue_pending = $total_kw_pending;
                }
                $total_revenue_generated = round(($total_revenue_generated + $sale->gross_account_value), 3);
                $total_kw = round(($total_kw + $sale->gross_account_value), 3);
            } else {
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

        if ($m2Complete > 0 && count($totalSales) > 0) {
            $inatll = round((($m2Complete / count($totalSales)) * 100), 5);
            $data['install_ratio'] = [
                'install' => $inatll . '%',
                'uninstall' => round(100 - $inatll, 5) . '%'
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '0%'
            ];
        }

        $data['contracts'] = [
            'avg_profit_per_rep' => $avg_profit_per_rep,
            'total_kw' => $total_kw,
            'total_kw_installed' => $total_kw_installed,
            'total_kw_pending'   => $total_kw_pending,
            'total_revenue_generated' => $total_revenue_generated,
            'total_revenue_pending' => $total_revenue_pending,
            'paid_comissions' => $total_paid_comissions,
            'projected_comissions' => $total_projected_comission
        ];

        return response()->json([
                'ApiName' => 'sales_graph_data',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
    }

    public function sales_account(Request $request)
    {
        $filter = $request->filter;
        $startDate = '';
        $endDate = '';

        $salesPid = [];
        $office_id = $request->office_id;
        if ($office_id != 'all') {
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        }

        if ($request->has('filter') && !empty($request->input('filter'))) {
            if ($filter == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } else if ($filter == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } else if ($filter == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } else if ($filter == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } else if ($filter == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } else if ($filter == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } else if ($filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else if ($filter == 'custom') {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            }
        }
        $totalSales = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->count();

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $m2Complete = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
            $m2Pending = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
        } else {
            $m2Complete = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
            $m2Pending = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNull('m2_date')->count();
        }

        $clawbackPid = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
        $cancelled = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->whereNotNull('date_cancelled')->whereNotIn('pid', $clawbackPid)->count();
        $clawback = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->whereNotNull('date_cancelled')->whereIn('pid', $clawbackPid)->count();

        $data['accounts'] = array(
            'total_sales' => $totalSales,
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $clawback
        );

        return response()->json([
            'ApiName' => 'sales_accounts_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function sales_best_avg(Request $request)
    {
        $filter = $request->filter;
        $startDate = '';
        $endDate = '';

        $salesPid = [];
        $office_id = $request->office_id;
        if ($office_id != 'all') {
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        }

        if ($request->has('filter') && !empty($request->input('filter'))) {
            if ($filter == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } else if ($filter == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } else if ($filter == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } else if ($filter == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } else if ($filter == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } else if ($filter == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } else if ($filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else if ($filter == 'custom') {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            }
        }

        $companyProfile = CompanyProfile::first();
        $totalSales = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->count();

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $totalKw = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('gross_account_value');

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(gross_account_value as decimal(10, 2))) As kw')
                ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($office_id != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('gross_account_value')->first();

            $bestweek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(gross_account_value as decimal(10, 2))) As kw ,
                STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
                adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
            ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->groupBy('week')->orderByDesc('gross_account_value')->first();

            $bsDate = isset($bestweek->startweek) ? $bestweek->startweek : null;
            $beDate = isset($bestweek->endweek) ? $bestweek->endweek : null;
            $bestweek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(gross_account_value as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($office_id != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('gross_account_value')->first();
        } else {
            $totalKw = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('kw');

            $bestMonth = SalesMaster::selectRaw('customer_signoff as date, year(customer_signoff) year, monthname(customer_signoff) month, sum(cast(kw as decimal(10, 2))) As kw')
                ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($office_id != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('month')->orderByDesc('kw')->first();

            $bestweek = SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
                sum(cast(kw as decimal(10, 2))) As kw ,
                STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
                adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
            ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->groupBy('week')->orderByDesc('kw')->first();

            $bsDate = isset($bestweek->startweek) ? $bestweek->startweek : null;
            $beDate = isset($bestweek->endweek) ? $bestweek->endweek : null;
            $bestweek['date'] = [$bsDate, $beDate];

            $bestDay = SalesMaster::select(DB::raw('sum(cast(kw as decimal(10, 2))) As kw'), 'customer_signoff as date')
                ->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })->when($office_id != 'all', function ($q) use ($salesPid) {
                    $q->whereIn('pid', $salesPid);
                })->groupBy('customer_signoff')->orderByDesc('kw')->first();
        }

        $totalReps = User::where('is_super_admin', '!=', 1)->count();
        $data['best_avg'] = array(
            'bestDay' => $bestDay,
            'bestWeek' => $bestweek,
            'bestMonth' => $bestMonth,
            'avg_account_per_rep' => ($totalSales && $totalReps) ? round($totalSales / $totalReps, 2) : 0,
            'avg_kw_per_rep' => ($totalKw && $totalReps) ? round($totalKw / $totalReps, 2) : 0
        );

        return response()->json([
            'ApiName' => 'sales_best_avg_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function sales_contracts(Request $request)
    {
        $filter = $request->filter;
        $startDate = '';
        $endDate = '';

        $salesPid = [];
        $office_id = $request->office_id;
        if ($office_id != 'all') {
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        }

        if ($request->has('filter') && !empty($request->input('filter'))) {
            if ($filter == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } else if ($filter == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } else if ($filter == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } else if ($filter == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } else if ($filter == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } else if ($filter == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } else if ($filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else if ($filter == 'custom') {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            }
        }

        $totalM1Sales = SalesMaster::whereNull('m1_date')->whereNull('date_cancelled')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->pluck('pid');

        $totalM2Sales = SalesMaster::whereNull('m2_date')->whereNull('date_cancelled')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->pluck('pid');

        $projectedM1 = ProjectionUserCommission::whereIn('pid', $totalM1Sales)->where('type', 'M1')->sum('amount') ?? 0;
        $projectedM2 = ProjectionUserCommission::whereIn('pid', $totalM2Sales)->where('type', 'M2')->sum('amount') ?? 0;

        $totalSales = SalesMaster::with('salesMasterProcess')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->get();

        // Include ALL sales (active + cancelled) to match paystub Commission YTD logic
        $totalSales = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->when(!empty($request->user_id) && !empty($salesPid), function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->pluck('pid');
        
        // Apply user filtering to commission calculations
        $commissionQuery = UserCommission::whereIn('pid', $totalSales)->where('status', '3');
        $clawbackQuery = ClawbackSettlement::whereIn('pid', $totalSales)->where(['type' => 'commission', 'status' => '3']);
        
        if (!empty($request->user_id)) {
            $userId = User::where('id', $request->user_id)->pluck('id');
            if ($userId->isNotEmpty()) {
                $commissionQuery->whereIn('user_id', $userId);
                $clawbackQuery->whereIn('user_id', $userId);
            }
        }
        
        $commission = $commissionQuery->sum('amount');
        $clawback = $clawbackQuery->sum('clawback_amount');

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $total_kw_installed = SalesMaster::whereNull('date_cancelled')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('gross_account_value');

            $total_kw_pending = SalesMaster::whereNull('date_cancelled')->whereNotNull('m1_date')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('gross_account_value');
        } else {
            $total_kw_installed = SalesMaster::whereNull('date_cancelled')->whereNotNull('m2_date')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('kw');

            $total_kw_pending = SalesMaster::whereNull('date_cancelled')->whereNull('m2_date')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->sum('kw');
        }

        $data['contracts'] = [
            'total_kw_installed' => $total_kw_installed,
            'total_kw_pending' => $total_kw_pending,
            'paid_comissions' => ($commission - $clawback),
            'projected_comissions' => ($projectedM1 + $projectedM2)
        ];

        return response()->json([
            'ApiName' => 'sales_contracts_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function sales_install_ratio(Request $request)
    {
        $filter = $request->filter;
        $startDate = '';
        $endDate = '';

        $salesPid = [];
        $office_id = $request->office_id;
        if ($office_id != 'all') {
            $userId = User::where('office_id', $office_id)->pluck('id');
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        }

        if ($request->has('filter') && !empty($request->input('filter'))) {
            if ($filter == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } else if ($filter == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } else if ($filter == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } else if ($filter == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } else if ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } else if ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } else if ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } else if ($filter == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } else if ($filter == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } else if ($filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else if ($filter == 'custom') {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            }
        }

        $totalSales = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when($office_id != 'all', function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->count();

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $m2Complete = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
        } else {
            $m2Complete = SalesMaster::when(!empty($startDate), function ($q) use ($startDate, $endDate) {
                $q->whereBetween('customer_signoff', [$startDate, $endDate]);
            })->when($office_id != 'all', function ($q) use ($salesPid) {
                $q->whereIn('pid', $salesPid);
            })->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
        }

        if ($m2Complete > 0 && $totalSales > 0) {
            $inatll = round((($m2Complete / $totalSales) * 100), 5);
            $data['install_ratio'] = [
                'install' => $inatll . '%',
                'uninstall' => round(100 - $inatll, 5) . '%'
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '100%'
            ];
        }

        return response()->json([
            'ApiName' => 'sales_install_ratio_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }
  

    public function subroutineEightForSolar($checked)
    {
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];    
        $kw = $checked['kw'];
        $netEpc = $checked['net_epc'];
        $approvedDate = $checked['customer_signoff'];
        $companyMargin = CompanyProfile::where('id', 1)->first();
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $margin_percentage = $companyMargin->company_margin;
            $x = ((100 - $margin_percentage) / 100);
        }else {
            $x = 1;
        }
        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        if ($setterId != null && $setter2Id != null) {
            $setter = User::where('id', $setterId)->first();
            $user_name = $setter->first_name.' '.$setter->last_name;
            $user_image = $setter->image;
            $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $setter = $organizationHistory;
            }
            if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                $commission_percentage = 0;
                $commission_type = null;
                
                $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0;
                $commission_type = null;
                
                $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            $user_name2 = $setter2->first_name.' '.$setter2->last_name;
            $user_image2 = $setter2->image;
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $setter2 = $organizationHistory2;
            }
            if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                
                $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                
                $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if ($commission_type == 'per kw') {
                $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
            } else {
                $setter1_commission = (($netEpc - $redline['setter1_redline']) * $x * $kw * 1000 * $commission_percentage/100) * 0.5;
            }

            if ($commission_type2 == 'per kw') {
                $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
            } else {
                $setter2_commission = (($netEpc - $redline['setter2_redline']) * $x * $kw * 1000 * $commission_percentage2/100) * 0.5;
            }

            if(isset($checked['uid'])){
                if($setterId == $checked['uid']){
                    $commissiondata['commission'] =  $setter1_commission;
                    $commissiondata['closer_commission'] =  0;
                    $commissiondata['setter_commission'] =  $setter1_commission;
                    return $commissiondata;
                }else{
                    $commissiondata['commission'] =  $setter2_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter2_commission;
                    return $commissiondata;
                }  
            }else if(!isset($checked['amount_data'])){
              if(!empty($accountSummary)){
                  $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                  $setter1Result = array(
                      'user_id' => $setterId,
                      'user_name' => $user_name,
                      'image' => $user_image,
                      'position_id' => $setter->position_id,
                      'position_name' => @$positionData->position_name,
                      'amount_type' => 'm2',
                      'amount' => $setter1_commission
                  );

                  $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                  $setter2Result = array(
                      'user_id' => $setter2Id,
                      'user_name' => $user_name2,
                      'image' => $user_image2,
                      'position_id' => $setter2->position_id,
                      'position_name' => @$positionData2->position_name,
                      'amount_type' => 'm2',
                      'amount' => $setter2_commission
                  );

                  return [
                      'setter1' => $setter1Result,
                      'setter2' => $setter2Result
                  ];
              }
            }
        }
        else if ($setterId) {
            if ($closerId != $setterId) {
                $setter = User::where('id', $setterId)->first();
                $user_name = $setter->first_name.' '.$setter->last_name;
                $user_image = $setter->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $setter = $organizationHistory;
                }

                if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                    $commission_percentage = 0; // percenge
                    $commission_type = null;
                    
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;// percenge
                    $commission_type = null;
                    
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                if ($commission_type == 'per kw') {
                    $setter_commission = (($kw * $commission_percentage) * $x);
                } else {
                    $setter_commission = (($netEpc - $redline['setter1_redline']) * $x * $kw * 1000 * $commission_percentage/100);
                }

                if(isset($checked['uid']) && $setterId == $checked['uid']){
                    $commissiondata['commission'] =  $setter_commission;
                    $commissiondata['closer_commission'] =  0;
                    $commissiondata['setter_commission'] =  $setter_commission;
                    return $commissiondata;
                }else if(!isset($val['amount_data'])){
                
                    // $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage/100); 

                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = array(
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter_commission
                        );
                        return [
                            'setter1' => $setter1Result
                        ];
                    }
                }
            }
        }

        $closer_commission = 0;
        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                $commission_percentage = 0;
                $commission_type = null;
                
                $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0;// percenge
                $commission_type = null;
                
                $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $user_name2 = $closer2->first_name.' '.$closer2->last_name;
            $user_image2 = $closer2->image;
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $closer2 = $organizationHistory2;
            }
            if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                
                $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0;// percenge
                $commission_type2 = null;
                
                $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if ($commission_type == 'per kw') {
                $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
            } else {
                $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $x * $kw * 1000) - ($setter_commission/2)) * 0.5;
            }

            if ($commission_type2 == 'per kw') {
                $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
            } else {
                $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $x * $kw * 1000) - ($setter_commission/2)) * 0.5;
            }

            if(isset($checked['uid'])){
                if($closerId == $checked['uid']){
                    $commissiondata['commission'] =  $closer1_commission;
                    $commissiondata['closer_commission'] =  $closer1_commission;
                    $commissiondata['setter_commission'] =  0;
                    return $commissiondata;
                }else{
                    $commissiondata['commission'] =  $closer2_commission;
                    $commissiondata['closer_commission'] =  $closer2_commission;
                    $commissiondata['setter_commission'] =  0;
                    return $commissiondata;
                }  
            }else if(!isset($val['amount_data'])){
                if(!empty($accountSummary)){
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = array(
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer1_commission
                    );

                    $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                    $closer2Result = array(
                        'user_id' => $closer2Id,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $closer2->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer2_commission
                    );

                    return [
                        'closer1' => $closer1Result,
                        'closer2' => $closer2Result
                    ];
                }
            }
        }
        else if ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $commission_percentage = 100;
                $commission_type = null;
            } else {
                if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }
                else {
                    $commission_percentage = 0;// percenge
                    $commission_type = null;
                    
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }
            }

            if ($commission_type == 'per kw') {
                $closer_commission = (($kw * $commission_percentage) * $x);
            } else {
                $closer_commission = (($netEpc - $redline['closer1_redline']) * $x * $kw * 1000) - $setter_commission;
            }

            // $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage/100);
            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                    $selfgen_percentage = $commissionSelfgen->commission;
                    if ($commissionSelfgen->commission_type == 'per kw') {
                        $x = isset($x) && !empty($x) ? $x : 1;
                        $closer_commission = ($kw * $selfgen_percentage * $x);
                    } else {
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            if(isset($checked['uid']) && $closerId == $checked['uid']){
                $commissiondata['commission'] =  $closer_commission;
                $commissiondata['closer_commission'] =  $closer_commission;
                $commissiondata['setter_commission'] =  0;
                return $commissiondata;
            }else if(!isset($val['amount_data'])){
                if(!empty($accountSummary)){
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = array(
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer_commission
                    );
                    return [
                        'closer1' => $closer1Result
                    ];
                }
            }
        }

        $commissiondata['commission'] =  $closer_commission+$setter_commission;
        $commissiondata['closer_commission'] =  $closer_commission;
        $commissiondata['setter_commission'] =  $setter_commission;


        return $commissiondata;
    }

    public function subroutineEightForFlex($checked)
    {
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $kw = $checked['kw'];
        $netEpc = $checked['net_epc'];
        $approvedDate = $checked['customer_signoff'];

        // if ($setterId) {
            $companyMargin = CompanyProfile::where('id', 1)->first();
            // Get Pull user Redlines from subroutineSix
            $redline = $this->subroutineSix($checked);

            // Calculate setter & closer commission
            $setter_commission = 0;
            if ($setterId != null && $setter2Id != null) {
                $setter = User::where('id', $setterId)->first();
                $user_name = $setter->first_name.' '.$setter->last_name;
                $user_image = $setter->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $setter = $organizationHistory;
                }
                if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $user_name2 = $setter2->first_name.' '.$setter2->last_name;
                $user_image2 = $setter2->image;
                $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory2) {
                    $setter2 = $organizationHistory2;
                }
                if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                } else {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);

                    if ($commission_type == 'per kw') {
                        $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
                    } else {
                        $setter1_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                    } else {
                        $setter2_commission = ((($netEpc - $redline['setter2_redline']) * $x) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $setter1_commission = ($kw * $commission_percentage * 0.5);
                    } else {
                        $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $setter2_commission = ($kw * $commission_percentage2 * 0.5);
                    } else {
                        $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                    }
                }

                if(isset($checked['uid'])){
                    if($setterId == $checked['uid']){
                        $commissiondata['commission'] =  $setter1_commission;
                        $commissiondata['closer_commission'] =  0;
                        $commissiondata['setter_commission'] =  $setter1_commission;
                        return $commissiondata;
                    }else{
                        $commissiondata['commission'] =  $setter2_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter2_commission;
                        return $commissiondata;
                    }  
                }else if(!isset($checked['amount_data'])){
                  if(!empty($accountSummary)){
                      $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                      $setter1Result = array(
                          'user_id' => $setterId,
                          'user_name' => $user_name,
                          'image' => $user_image,
                          'position_id' => $setter->position_id,
                          'position_name' => @$positionData->position_name,
                          'amount_type' => 'm2',
                          'amount' => $setter1_commission
                      );
    
                      $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                      $setter2Result = array(
                          'user_id' => $setter2Id,
                          'user_name' => $user_name2,
                          'image' => $user_image2,
                          'position_id' => $setter2->position_id,
                          'position_name' => @$positionData2->position_name,
                          'amount_type' => 'm2',
                          'amount' => $setter2_commission
                      );
    
                      return [
                          'setter1' => $setter1Result,
                          'setter2' => $setter2Result
                      ];
                  }
                }
                $setter_commission = ($setter1_commission + $setter2_commission);
            }
            else if ($setterId) {
                if ($closerId != $setterId) {
                    $setter = User::where('id', $setterId)->first();
                    $user_name = $setter->first_name.' '.$setter->last_name;
                    $user_image = $setter->image;
                    $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($organizationHistory) {
                        $setter = $organizationHistory;
                    }

                    if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                        $commission_percentage = 0; // percenge
                        $commission_type = null;
                        $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    } else {
                        $commission_percentage = 0;// percenge
                        $commission_type = null;
                        $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }

                    if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                        $margin_percentage = $companyMargin->company_margin;
                        $x = ((100 - $margin_percentage) / 100);
                        if ($commission_type == 'per kw') {
                            $setter_commission = (($kw * $commission_percentage) * $x);
                        } else {
                            $setter_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                        }
                    } else {
                        if ($commission_type == 'per kw') {
                            $setter_commission = ($kw * $commission_percentage);
                        } else {
                            $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);
                        }
                    }
                    if(isset($checked['uid']) && $setterId == $checked['uid']){
                        $commissiondata['commission'] =  $setter_commission;
                        $commissiondata['closer_commission'] =  0;
                        $commissiondata['setter_commission'] =  $setter_commission;
                        return $commissiondata;
                    }else if(!isset($val['amount_data'])){    
                        if(!empty($accountSummary)){
                            $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                            $setter1Result = array(
                                'user_id' => $setterId,
                                'user_name' => $user_name,
                                'image' => $user_image,
                                'position_id' => $setter->position_id,
                                'position_name' => @$positionData->position_name,
                                'amount_type' => 'm2',
                                'amount' => $setter_commission
                            );
                            return [
                                'setter1' => $setter1Result
                            ];
                        }
                    }
                }
            }

            $closer_commission = 0;
            if ($closerId != null && $closer2Id != null) {
                $closer = User::where('id', $closerId)->first();
                $user_name = $closer->first_name.' '.$closer->last_name;
                $user_image = $closer->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $closer = $organizationHistory;
                }

                if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;// percenge
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                $closer2 = User::where('id', $closer2Id)->first();
                $user_name2 = $closer2->first_name.' '.$closer2->last_name;
                $user_image2 = $closer2->image;
                $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory2) {
                    $closer2 = $organizationHistory2;
                }
                if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                } else {
                    $commission_percentage2 = 0;// percenge
                    $commission_type2 = null;
                    $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
                    } else {
                        $closer1_commission = (((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                    } else {
                        $closer2_commission = (((($netEpc - $redline['closer2_redline']) * $x) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $closer1_commission = ($kw * $commission_percentage * 0.5);
                    } else {
                        $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $closer2_commission = ($kw * $commission_percentage2 * 0.5);
                    } else {
                        $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                    }
                }

                if(isset($checked['uid'])){
                    if($closerId == $checked['uid']){
                        $commissiondata['commission'] =  $closer1_commission;
                        $commissiondata['closer_commission'] =  $closer1_commission;
                        $commissiondata['setter_commission'] =  0;
                        return $commissiondata;
                    }else{
                        $commissiondata['commission'] =  $closer2_commission;
                        $commissiondata['closer_commission'] =  $closer2_commission;
                        $commissiondata['setter_commission'] =  0;
                        return $commissiondata;
                    }  
                }else if(!isset($val['amount_data'])){
                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = array(
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer1_commission
                        );
    
                        $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                        $closer2Result = array(
                            'user_id' => $closer2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $closer2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer2_commission
                        );
    
                        return [
                            'closer1' => $closer1Result,
                            'closer2' => $closer2Result
                        ];
                    }
                }
                $closer_commission = ($closer1_commission + $closer2_commission);
            }
            else if ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $user_name = $closer->first_name.' '.$closer->last_name;
                $user_image = $closer->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $closer = $organizationHistory;
                }

                if ($closerId == $setterId) {
                    $commission_percentage = 100;
                    $commission_type = null;
                } else {
                    if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                        $commission_percentage = 0;
                        $commission_type = null;
                        $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }
                    else {
                        $commission_percentage = 0;// percenge
                        $commission_type = null;
                        $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $closer_commission = (($kw * $commission_percentage) * $x);
                    } else {
                        $closer_commission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $closer_commission = ($kw * $commission_percentage);
                    } else {
                        $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);
                    }
                }

                if ($closerId == $setterId) {
                    $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                        $selfgen_percentage = $commissionSelfgen->commission;
                        if ($commissionSelfgen->commission_type == 'per kw') {
                            $x = isset($x) && !empty($x) ? $x : 1;
                            $closer_commission = ($kw * $selfgen_percentage * $x);
                        } else {
                            $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                        }
                    }
                }

                if(isset($checked['uid']) && $closerId == $checked['uid']){
                    $commissiondata['commission'] =  $closer_commission;
                    $commissiondata['closer_commission'] =  $closer_commission;
                    $commissiondata['setter_commission'] =  0;
                    return $commissiondata;
                }else if(!isset($val['amount_data'])){
                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = array(
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer_commission
                        );
                        return [
                            'closer1' => $closer1Result
                        ];
                    }
                }
            }

            $commissiondata['commission'] =  $closer_commission+$setter_commission;
            $commissiondata['closer_commission'] =  $closer_commission;
            $commissiondata['setter_commission'] =  $setter_commission;
            return $commissiondata;
        // }
    }


    public function subroutineEightForTurf($checked)
    {
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $kw = $checked['kw'];
        $netEpc = $checked['net_epc'];
        $approvedDate = $checked['customer_signoff'];

        if ($setterId) {
            $overrideSetting = CompanySetting::where('type', 'overrides')->first();
            $companyMargin = CompanyProfile::where('id', 1)->first();
            // Get Pull user Redlines from subroutineSix
            $redline = $this->subroutineSix($checked);

            // Calculate setter & closer commission
            $setter_commission = 0;
            if ($setterId != null && $setter2Id != null) {
                $setter = User::where('id', $setterId)->first();
                $user_name = $setter->first_name.' '.$setter->last_name;
                $user_image = $setter->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $setter = $organizationHistory;
                }
                if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    // $positionId = ($setter->position_id==2)? '3':$setter->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;
                    $commission_type = null;
                    // $positionId = $setter->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $user_name2 = $setter2->first_name.' '.$setter2->last_name;
                $user_image2 = $setter2->image;
                $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory2) {
                    $setter2 = $organizationHistory2;
                }
                if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    // $positionId = ($setter2->position_id==2)? '3':$setter2->position_id;
                    $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                } else {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    // $positionId = $setter2->position_id;
                    $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);

                    if ($commission_type == 'per kw') {
                        $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
                    } else {
                        $setter1_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * $commission_percentage / 100) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                    } else {
                        $setter2_commission = ((($netEpc - $redline['setter2_redline']) * $x) * $kw * $commission_percentage2 / 100) * 0.5;
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $setter1_commission = ($kw * $commission_percentage * 0.5);
                    } else {
                        $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * $commission_percentage / 100) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $setter2_commission = ($kw * $commission_percentage2 * 0.5);
                    } else {
                        $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * $commission_percentage2 / 100) * 0.5;
                    }
                }

                if(isset($checked['uid'])){
                    if($setterId == $checked['uid']){
                        $commissiondata['commission'] =  $setter1_commission;
                        $commissiondata['closer_commission'] =  0;
                        $commissiondata['setter_commission'] =  $setter1_commission;
                        return $commissiondata;
                    }else{
                        $commissiondata['commission'] =  $setter2_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter2_commission;
                        return $commissiondata;
                    }  
                }else if(!isset($checked['amount_data'])){
                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = array(
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter1_commission
                        );
        
                        $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                        $setter2Result = array(
                            'user_id' => $setter2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $setter2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter2_commission
                        );
        
                        return [
                            'setter1' => $setter1Result,
                            'setter2' => $setter2Result
                        ];
                    }
                }
                $setter_commission = ($setter1_commission + $setter2_commission);
            }
            else if ($setterId) {
                if ($closerId != $setterId) {
                    $setter = User::where('id', $setterId)->first();
                    $user_name = $setter->first_name.' '.$setter->last_name;
                    $user_image = $setter->image;
                    $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($organizationHistory) {
                        $setter = $organizationHistory;
                    }

                    if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                        $commission_percentage = 0; // percenge
                        $commission_type = null;
                        // $positionId = ($setter->position_id==2)? '3':$setter->position_id;
                        $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    } else {
                        $commission_percentage = 0;// percenge
                        $commission_type = null;
                        // $positionId = $setter->position_id;
                        $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }

                    if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                        $margin_percentage = $companyMargin->company_margin;
                        $x = ((100 - $margin_percentage) / 100);
                        if ($commission_type == 'per kw') {
                            $setter_commission = (($kw * $commission_percentage) * $x);
                        } else {
                            $setter_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * $commission_percentage / 100);
                        }
                    } else {
                        if ($commission_type == 'per kw') {
                            $setter_commission = ($kw * $commission_percentage);
                        } else {
                            $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * $commission_percentage / 100);
                        }
                    }
                    if(isset($checked['uid']) && $setterId == $checked['uid']){
                        $commissiondata['commission'] =  $setter_commission;
                        $commissiondata['closer_commission'] =  0;
                        $commissiondata['setter_commission'] =  $setter_commission;
                        return $commissiondata;
                    }else if(!isset($val['amount_data'])){
                    
                        // $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage/100); 
    
                        if(!empty($accountSummary)){
                            $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                            $setter1Result = array(
                                'user_id' => $setterId,
                                'user_name' => $user_name,
                                'image' => $user_image,
                                'position_id' => $setter->position_id,
                                'position_name' => @$positionData->position_name,
                                'amount_type' => 'm2',
                                'amount' => $setter_commission
                            );
                            return [
                                'setter1' => $setter1Result
                            ];
                        }
                    }
                }
            }

            $closer_commission = 0;
            if ($closerId != null && $closer2Id != null) {
                $closer = User::where('id', $closerId)->first();
                $user_name = $closer->first_name.' '.$closer->last_name;
                $user_image = $closer->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $closer = $organizationHistory;
                }

                if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    // $positionId = ($closer->position_id==3)? '2':$closer->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;// percenge
                    $commission_type = null;
                    // $positionId = $closer->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                $closer2 = User::where('id', $closer2Id)->first();
                $user_name2 = $closer2->first_name.' '.$closer2->last_name;
                $user_image2 = $closer2->image;
                $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory2) {
                    $closer2 = $organizationHistory2;
                }
                if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                    $commission_percentage2 = 0;
                    $commission_type2 = null;
                    // $positionId = ($closer2->position_id==3)? '2':$closer2->position_id;
                    $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                } else {
                    $commission_percentage2 = 0;// percenge
                    $commission_type2 = null;
                    // $positionId = $closer2->position_id;
                    $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commission2History) {
                        $commission_percentage2 = $commission2History->commission;
                        $commission_type2 = $commission2History->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
                    } else {
                        $closer1_commission = (((($netEpc - $redline['closer1_redline']) * $x) * $kw) * ($commission_percentage / 100)) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                    } else {
                        $closer2_commission = (((($netEpc - $redline['closer2_redline']) * $x) * $kw) * ($commission_percentage2 / 100)) * 0.5;
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $closer1_commission = ($kw * $commission_percentage * 0.5);
                    } else {
                        $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw) * ($commission_percentage / 100)) * 0.5;
                    }

                    if ($commission_type2 == 'per kw') {
                        $closer2_commission = ($kw * $commission_percentage2 * 0.5);
                    } else {
                        $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw) * ($commission_percentage2 / 100)) * 0.5;
                    }
                }

                if(isset($checked['uid'])){
                    if($closerId == $checked['uid']){
                        $commissiondata['commission'] =  $closer1_commission;
                        $commissiondata['closer_commission'] =  $closer1_commission;
                        $commissiondata['setter_commission'] =  0;
                        return $commissiondata;
                    }else{
                        $commissiondata['commission'] =  $closer2_commission;
                        $commissiondata['closer_commission'] =  $closer2_commission;
                        $commissiondata['setter_commission'] =  0;
                        return $commissiondata;
                    }  
                }else if(!isset($val['amount_data'])){
                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = array(
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer1_commission
                        );
    
                        $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                        $closer2Result = array(
                            'user_id' => $closer2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $closer2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer2_commission
                        );
    
                        return [
                            'closer1' => $closer1Result,
                            'closer2' => $closer2Result
                        ];
                    }
                }
            }
            else if ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $user_name = $closer->first_name.' '.$closer->last_name;
                $user_image = $closer->image;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $closer = $organizationHistory;
                }

                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                    $commission_percentage = 100;
                    $commission_type = null;
                } else {
                    if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                        $commission_percentage = 0;
                        $commission_type = null;
                        // $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                        $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }
                    else {
                        $commission_percentage = 0;// percenge
                        $commission_type = null;
                        // $positionId = $closer->position_id;
                        $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                        if ($commissionHistory) {
                            $commission_percentage = $commissionHistory->commission;
                            $commission_type = $commissionHistory->commission_type;
                        }
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $closer_commission = (($kw * $commission_percentage) * $x);
                    } else {
                        $closer_commission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $closer_commission = ($kw * $commission_percentage);
                    } else {
                        $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * $commission_percentage / 100);
                    }
                }

                // $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage/100);
                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                    $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                        $selfgen_percentage = $commissionSelfgen->commission;
                        if ($commissionSelfgen->commission_type == 'per kw') {
                            $x = isset($x) && !empty($x) ? $x : 1;
                            $closer_commission = ($kw * $selfgen_percentage * $x);
                        } else {
                            $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                        }
                    }
                }

                if(isset($checked['uid']) && $closerId == $checked['uid']){
                    $commissiondata['commission'] =  $closer_commission;
                    $commissiondata['closer_commission'] =  $closer_commission;
                    $commissiondata['setter_commission'] =  0;
                    return $commissiondata;
                }else if(!isset($val['amount_data'])){
                    if(!empty($accountSummary)){
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = array(
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer_commission
                        );
                        return [
                            'closer1' => $closer1Result
                        ];
                    }
                }
            }
        }
        

        $commissiondata['commission'] =  $closer_commission+$setter_commission;
        $commissiondata['closer_commission'] =  $closer_commission;
        $commissiondata['setter_commission'] =  $setter_commission;
        return $commissiondata;
    }

    public function sales_report_for_admin_company_report(Request $request)
    {
        $data = array();
        $filter = $request->input('filter');
        $startDate = '';
        $endDate = '';
        $salesPid = [];
        $office_id = $request->input('office_id', 'all'); // Fix: Initialize to prevent undefined variable
        
        // Office filtering
        if ($request->has('office_id') && !empty($request->input('office_id'))) {
            $office_id = $request->input('office_id');
            if ($office_id != 'all') {
                $officeUserIds = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::where(function($q) use ($officeUserIds) {
                    $q->whereIn('closer1_id', $officeUserIds)
                      ->orWhereIn('closer2_id', $officeUserIds)
                      ->orWhereIn('setter1_id', $officeUserIds)
                      ->orWhereIn('setter2_id', $officeUserIds);
                })->pluck('pid');
            }
        }

        // User filtering - intersect with office filter if both exist
        if (!empty($request->input('user_id'))) {
            $userId = User::where('id', $request->input('user_id'))->pluck('id');
            $userSalesPid = SaleMasterProcess::where(function($q) use ($userId) {
                $q->whereIn('closer1_id', $userId)
                  ->orWhereIn('closer2_id', $userId)
                  ->orWhereIn('setter1_id', $userId)
                  ->orWhereIn('setter2_id', $userId);
            })->pluck('pid');
            
            // If office filter exists, intersect; otherwise use user PIDs
            $salesPid = !empty($salesPid) && $salesPid->isNotEmpty() 
                ? $salesPid->intersect($userSalesPid) 
                : $userSalesPid;
        }

        // Date filtering
        if ($request->has('filter') && !empty($request->input('filter'))) {
            if ($filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } else {
                $startDate = date('Y-m-d', strtotime('first day of January '. $filter));
                $endDate = date('Y-m-d', strtotime('last day of December '. $filter));
            }
        }

        // M1 Sales filtering
        $totalM1Sales = SalesMaster::whereNull('m1_date')->whereNull('date_cancelled')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when(($office_id != 'all' || !empty($request->input('user_id'))) && !empty($salesPid), function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->pluck('pid');

        // M2 Sales filtering
        $totalM2Sales = SalesMaster::whereNull('m2_date')->whereNull('date_cancelled')->when(!empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when(($office_id != 'all' || !empty($request->input('user_id'))) && !empty($salesPid), function ($q) use ($salesPid) {
            $q->whereIn('pid', $salesPid);
        })->pluck('pid');

        $projectedM1 = ProjectionUserCommission::whereIn('pid', $totalM1Sales)->where('type', 'M1')->sum('amount') ?? 0;
        $projectedM2 = ProjectionUserCommission::whereIn('pid', $totalM2Sales)->where('type', 'M2')->sum('amount') ?? 0;
        $projectionOverrides = ProjectionUserOverrides::whereIn('pid', $totalM2Sales)->sum('total_override') ?? 0;

        $data['contracts'] = [
            'projected_comissions' => round(($projectedM1 + $projectedM2), 2),
            'projected_override' => round($projectionOverrides, 2),
            'total_payouts' => round(($projectedM1 + $projectedM2 + $projectionOverrides), 2)
        ];

        return response()->json([
            'ApiName' => 'company_projected_payouts',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function getOverride($val)
    {
        if ($val['date_cancelled']) {
            return 0;
        }
        $projectionOverrides = ProjectionUserOverrides::where('pid', $val['pid'])->get();
        $sumOverrides = $projectionOverrides->sum("total_override");
        return $sumOverrides;
    }
}

