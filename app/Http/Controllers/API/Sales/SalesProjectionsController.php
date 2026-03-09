<?php

namespace App\Http\Controllers\API\Sales;

use App\Console\Commands\CalculateOverrideProjections;
use App\Core\Traits\PermissionCheckTrait;
use App\Exports\ExportReportMySalesStandard;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\PositionCommissionUpfronts;
use App\Models\Positions;
use App\Models\ProjectionUserCommission;
use App\Models\ProjectionUserOverrides;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\upfrontSystemSetting;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserUpfrontHistory;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SalesProjectionsController extends Controller
{
    use EmailNotificationTrait, PermissionCheckTrait;

    public function __construct(Request $request) {}

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
        if (isset($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 0;
        }

        $companyProfile = CompanyProfile::first();
        $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid')->toArray();
        $filterDataDateWise = $request->input('filter');
        $result = SalesMaster::with('salesMasterProcess', 'userDetail');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'this_month') {

            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_quarter') {

            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');
            $result->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
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

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($request->has('filter_type') && $request->input('filter_type') == 'olny_sale_date') {
                $result->whereNotNull('customer_signoff')->whereNull('m1_date')->whereNull('m2_date')->whereNull('date_cancelled');
            }

            if ($request->has('filter_type') && $request->input('filter_type') == 'cancel_date') {
                $result->whereNotNull('date_cancelled');
            }

            if ($request->has('filter_type') && $request->input('filter_type') == 'initial_service_date') {
                $result->whereNotNull('m1_date');
            }

            if ($request->has('filter_type') && $request->input('filter_type') == 'service_complete_date') {
                $result->whereNotNull('m2_date');
            }
        } else {
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
        }
        if ($request->has('filter_product') && ! empty($request->input('filter_product'))) {
            $product = $request->filter_product;
            $result->where(function ($query) use ($product) {
                return $query->where('product', $product);
            });
        }
        if ($request->has('filter_status') && ! empty($request->input('filter_status'))) {
            $status = '%'.$request->input('filter_status').'%';
            $result->where(function ($query) use ($status) {
                return $query->where('job_status', 'LIKE', $status);
            });
        }
        if ($request->has('filter_install') && ! empty($request->input('filter_install'))) {
            $filter_install = '%'.$request->input('filter_install').'%';
            $result->where(function ($query) use ($filter_install) {
                return $query->where('install_partner', 'LIKE', $filter_install);
            });
        }
        if ($request->has('location') && ! empty($request->input('location')) && 1 == 2) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_state', '=', $request->location);
            });
        }

        if ($request->has('sort') && $request->input('sort') != '') {
            $data = $result->orderBy('id', 'asc')->get();
        } else {
            $data = $result->orderBy('id', 'asc')->paginate($perpage);
        }

        $data->transform(function ($data) use ($uid, $position, $companyProfile) {
            $userM1 = 0;
            $userM2 = 0;

            $salesUserCloserId = null;
            $salesUserCloserId2 = null;
            $salesUserSetterId = null;
            $salesUserSetterId2 = null;
            if ($data->salesMasterProcess->closer1_id == $uid) {
                $salesUserCloserId = $data->salesMasterProcess->closer1_id;
            }
            if ($data->salesMasterProcess->closer2_id == $uid) {
                $salesUserCloserId2 = $data->salesMasterProcess->closer2_id;
            }
            if ($data->salesMasterProcess->setter1_id == $uid) {
                $salesUserSetterId = $data->salesMasterProcess->setter1_id;
            }
            if ($data->salesMasterProcess->setter2_id == $uid) {
                $salesUserSetterId2 = $data->salesMasterProcess->setter2_id;
            }

            if (@$salesUserCloserId) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $userM1 = $data->salesMasterProcess->closer1_m1;
                    $userM2 = $data->salesMasterProcess->closer1_commission;
                } else {
                    $userM1 = $data->salesMasterProcess->closer1_m1;
                    $userM2 = $data->salesMasterProcess->closer1_m2;
                }
            }

            if (@$salesUserCloserId2) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $userM1 = $data->salesMasterProcess->closer2_m1;
                    $userM2 = $data->salesMasterProcess->closer2_commission;
                } else {
                    $userM1 = $data->salesMasterProcess->closer2_m1;
                    $userM2 = $data->salesMasterProcess->closer2_m2;
                }
            }

            if (@$salesUserSetterId && $salesUserCloserId != $salesUserSetterId) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // No Setter For Pest
                } else {
                    $userM1 = $data->salesMasterProcess->setter1_m1;
                    $userM2 = $data->salesMasterProcess->setter1_m2;
                }
            }

            if (@$salesUserSetterId2 && $salesUserCloserId2 != $salesUserSetterId2) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // No Setter For Pest
                } else {
                    $userM1 = $data->salesMasterProcess->setter2_m1;
                    $userM2 = $data->salesMasterProcess->setter2_m2;
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

            $pid_status = isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null;
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
            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $location_data = Locations::with('State')->whereHas('State', function ($q) use ($data) {
                    $q->where('state_code', $data->customer_state);
                })->where('general_code', $data->location_code)->first();
            } else {
                $location_data = Locations::with('State')->where('general_code', '=', $data->customer_state)->first();
            }
            if ($location_data) {
                $state_code = $location_data->state->state_code;
            } else {
                $state_code = null;
            }

            $closerId = $data->salesMasterProcess->closer1_id;
            $closer2Id = $data->salesMasterProcess->closer2_id;
            $setterId = $data->salesMasterProcess->setter1_id;
            $setter2Id = $data->salesMasterProcess->setter2_id;
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
            $m1_amount_projected = 0;
            $m2_amount_projected = 0;
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // No Projection For Pest
                $kw = $data->gross_account_value;
                if (empty($data->m1_date)) {
                    $sales_projection_m1_amount = $this->pestSalesProjectionM1([
                        'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'uid' => $uid,
                    ]);
                }

                if (empty($data->m2_date)) {
                    $sales_projection_m2_amount = $this->pestSalesProjectionM2([
                        'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'gross_account_value' => $grossAmountValue, 'uid' => $uid,
                    ]);
                }
            } else {
                if (empty($data->m1_date)) {
                    $sales_projection_m1_amount = $this->salesProjectionM1([
                        'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'uid' => $uid,
                    ]);
                }

                if (empty($data->m2_date)) {
                    $sales_projection_m2_amount = $this->salesProjectionM2([
                        'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'gross_account_value' => $grossAmountValue, 'uid' => $uid,
                    ]);
                }

                if (empty($data->date_cancelled) && $sales_projection_m2_amount) {
                    if (empty($data->m1_date) && empty($data->m2_date)) {
                        $m1_amount_projected = $sales_projection_m1_amount ? $sales_projection_m1_amount['amount'] : 0;
                        $m2_amount_projected = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                        $sales_projection_m2_amount['commission'] = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                    } elseif (empty($data->m2_date)) {
                        $m1_amount_projected = ($userM1 > 0) ? $userM1 : 0;
                        $m2_amount_projected = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                        $sales_projection_m2_amount['commission'] = $sales_projection_m2_amount ? $sales_projection_m2_amount['commission'] - $m1_amount_projected : 0;
                    }
                }
            }
            $all_milestone = [];
            for ($i = 1; $i < 5; $i++) {
                $milestone['name'] = 'Payment '.$i.'(M'.$i.')';
                $milestone['value'] = $i.'00';
                $milestone['last_milestone_projection'] = '';
                $milestone['date'] = date('d/m/Y');
                $all_milestone[] = $milestone;
            }

            return [
                'id' => $data->id,
                'pid' => $data->pid,
                'installer' => $data->install_partner,
                'customer_signoff' => $data->customer_signoff,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'state_id' => $state_code,
                'state' => isset($data->customer_state) ? $data->customer_state : null,
                'closer_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                'closer' => isset($data->salesMasterProcess->closer1Detail->first_name) ? $data->salesMasterProcess->closer1Detail->first_name : null,
                'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                'setter' => isset($data->salesMasterProcess->setter1Detail->first_name) ? $data->salesMasterProcess->setter1Detail->first_name : null,
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'kw' => isset($kw) ? $kw : null,
                'status' => isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null,
                'job_status' => isset($data->salesMasterProcess->job_status) ? $data->salesMasterProcess->job_status : null,
                'date_cancelled' => isset($data->date_cancelled) ? dateToYMD($data->date_cancelled) : null,
                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                'm1_amount' => $userM1,
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                'm2_amount' => $userM2,
                'adders' => isset($data->adders) ? $data->adders : '',
                'progress_bar' => isset($progress_bar) ? $progress_bar : 0,
                'dealer_fee' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : '',
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
                'sales_projection_m1_amount' => $sales_projection_m1_amount,
                'sales_projection_m2_amount' => $sales_projection_m2_amount,
                'm2_amount_projected' => $m2_amount_projected,
                'm1_amount_projected' => $m1_amount_projected,
                'product' => isset($data->productdata) ? $data->productdata->name : '',
                'product_code' => isset($data->productdata) ? $data->productdata->product_id : '',
                'last_milestone' => ['name' => 'M1', 'value' => 1.00, 'date' => date('d/m/Y')],
                'last_milestone_projection' => '',
                'all_milestone' => $all_milestone,
                'total_commission' => 100,
                'sales_projection_total_commission' => '',
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
                $data = $this->paginates($data, $perpage);
            }
            if ($request->has('sort') && $request->input('sort') == 'kw') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'kw'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'kw'), SORT_ASC, $data);
                }
                $data = $this->paginates($data, $perpage);
            }
            if ($request->has('sort') && $request->input('sort') == 'm1') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'm1_amount_projected'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'm1_amount_projected'), SORT_ASC, $data);
                }
                $data = $this->paginates($data, $perpage);
            }
            if ($request->has('sort') && $request->input('sort') == 'm2') {
                $data = json_decode($data);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($data, 'm2_amount_projected'), SORT_DESC, $data);
                } else {
                    array_multisort(array_column($data, 'm2_amount_projected'), SORT_ASC, $data);
                }
                $data = $this->paginates($data, $perpage);
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

    public function pestSalesProjectionM1($val)
    {
        if ($val['date_cancelled']) {
            return 0;
        }
        $closer1 = $val['closer1_id'];
        $closer2 = $val['closer2_id'];
        $customerSignOff = $val['customer_signoff'];
        $accountSummary = @$val['from'];
        $total = 0;
        if ($closer1 != null && $closer2 != null) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
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
            $user_name2 = $closer2User->first_name.' '.$closer2User->last_name;
            $user_image2 = $closer2User->image;
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

            if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
                if ($closer2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    }
                }

                if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer1,
                    'position_id' => $closer->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];
                if (isset($val['uid']) && $closer1 == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $total += $amount;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name;
                        $data['image'] = $user_image;

                        return ['closer1' => $data];
                    }

                    return $data;
                }
            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
                if ($closerUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    }
                }

                if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer2,
                    'position_id' => $closer2User->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                ];
                if (isset($val['uid']) && $closer2 == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $total += $amount2;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer2User->position_id)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name2;
                        $data['image'] = $user_image2;

                        return ['closer1' => $data];
                    }

                    return $data;
                }
            }
        } elseif ($closer1) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
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

                    if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    }

                    $data = [
                        'user_id' => $closer1,
                        'position_id' => $closer->position_id,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                    ];
                    if (isset($val['uid']) && $closer1 == $val['uid']) {
                        return $data;
                    } elseif (isset($val['amount_data'])) {
                        $total += $amount;
                    } else {
                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                            $data['position_name'] = @$positionData->position_name;
                            $data['user_name'] = $user_name;
                            $data['image'] = $user_image;

                            return ['closer1' => $data];
                        }

                        return $data;
                    }
                }
            }
        }

        return $data = [
            'user_id' => '',
            'position_id' => '',
            'amount_type' => 'm1',
            'amount' => $total,
        ];
    }

    public function pestSalesProjectionM2($checked)
    {
        if ($checked['date_cancelled']) {
            return 0;
        }
        $closer1 = $checked['closer1_id'];
        $closer2 = $checked['closer2_id'];
        $grossAmountValue = $checked['gross_account_value'];
        $approvedDate = $checked['customer_signoff'];
        $accountSummary = @$checked['from'];

        $companyMargin = CompanyProfile::where('id', 1)->first();

        // Calculate setter & closer commission
        $closerCommission = 0;
        if ($closer1 != null && $closer2 != null) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

            $closer2data = User::where('id', $closer2)->first();
            $user_name2 = $closer2data->first_name.' '.$closer2data->last_name;
            $user_image2 = $closer2data->image;

            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            $commissionPercentage2 = 0;
            $commission2History = UserCommissionHistory::where('user_id', $closer2)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                }
            } else {
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                }
            }

            if (isset($checked['uid'])) {
                if ($closer1 == $checked['uid']) {
                    $commissiondata['commission'] = $closer1Commission;
                    $commissiondata['closer_commission'] = $closer1Commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                } else {
                    $commissiondata['commission'] = $closer2Commission;
                    $commissiondata['closer_commission'] = $closer2Commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                }
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = [
                        'user_id' => $closer1,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer1Commission,
                    ];

                    $positionData2 = Positions::select('position_name')->where('id', '=', $closer2data->position_id)->first();
                    $closer2Result = [
                        'user_id' => $closer2,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $closer2data->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer2Commission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                        'closer2' => $closer2Result,
                    ];
                }
            }
            $closerCommission = ($closer1Commission + $closer2Commission);
        } elseif ($closer1) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
            } else {
                $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
            }

            if (isset($checked['uid']) && $closer1 == $checked['uid']) {
                $commissiondata['commission'] = $closerCommission;
                $commissiondata['closer_commission'] = $closerCommission;
                $commissiondata['setter_commission'] = 0;

                return $commissiondata;
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                    $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                    $closer1Result = [
                        'user_id' => $closer1,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closerCommission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                    ];
                }
            }
        }

        $commissiondata['commission'] = $closerCommission;
        $commissiondata['closer_commission'] = $closerCommission;
        $commissiondata['setter_commission'] = 0;

        return $commissiondata;
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
        $accountSummary = @$val['from'];
        $total = 0;
        $setter_amount = 0;
        $closer_amount = 0;

        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $positionId = @$userOrganizationHistory['position_id'];
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

            $closer2 = User::where('id', $closer2Id)->first();
            $user_name2 = $closer2->first_name.' '.$closer2->last_name;
            $user_image2 = $closer2->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $positionId2 = @$userOrganizationHistory['position_id'];
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

            if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
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

                if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closerId,
                    'position_id' => $positionId,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];
                if (isset($val['uid']) && $closerId == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $closer_amount += $amount;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name;
                        $data['image'] = $user_image;

                        return ['closer1' => $data];
                    }

                    return $data;
                }
            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
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

                if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer2Id,
                    'position_id' => @$positionId2,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                ];
                if (isset($val['uid']) && $closer2Id == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $closer_amount += $amount2;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', @$positionId2)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name2;
                        $data['image'] = $user_image2;

                        return ['closer2' => $data];
                    }

                    return $data;
                }
            }
        } elseif ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $positionId = @$userOrganizationHistory['position_id'];
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

                $data = [
                    'user_id' => $closerId,
                    'position_id' => $userOrganizationHistory->sub_position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];

                if (isset($val['uid']) && $closerId == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $closer_amount += $amount;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $userOrganizationHistory->sub_position_id)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name;
                        $data['image'] = $user_image;

                        return ['closer1' => $data];
                    }

                    return $data;
                }
            } else {
                $closerUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                if ($closerUpfront) {
                    $positionId = @$userOrganizationHistory['position_id'];
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

                        if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        }

                        $data = [
                            'user_id' => $closerId,
                            'position_id' => $positionId,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                        ];
                        if (isset($val['uid']) && $closerId == $val['uid']) {
                            return $data;
                        } elseif (isset($val['amount_data'])) {
                            $closer_amount += $amount;
                        } else {
                            if (! empty($accountSummary)) {
                                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                                $data['position_name'] = @$positionData->position_name;
                                $data['user_name'] = $user_name;
                                $data['image'] = $user_image;

                                return ['closer1' => $data];
                            }

                            return $data;
                        }
                    }
                }
            }
        }

        if ($setterId != null && $setter2Id != null) {
            $setter = User::where('id', $setterId)->first();
            $user_name = $setter->first_name.' '.$setter->last_name;
            $user_image = $setter->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $positionId = @$userOrganizationHistory['position_id'];
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

            $setter2 = User::where('id', $setter2Id)->first();
            $user_name2 = $setter2->first_name.' '.$setter2->last_name;
            $user_image2 = $setter2->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $positionId2 = @$userOrganizationHistory['position_id'];
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

            if (! empty($setterUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
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

                if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                    $amount = $setterUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $setterId,
                    'position_id' => $positionId,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];
                if (isset($val['uid']) && $setterId == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $setter_amount += $amount;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name;
                        $data['image'] = $user_image;

                        return ['setter1' => $data];
                    }

                    return $data;
                }
            }

            if (! empty($setter2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
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

                if (! empty($setter2Upfront->upfront_limit) && $amount2 > $setter2Upfront->upfront_limit) {
                    $amount2 = $setter2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $setter2Id,
                    'position_id' => $positionId2,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                ];
                if (isset($val['uid']) && $setter2Id == $val['uid']) {
                    return $data;
                } elseif (isset($val['amount_data'])) {
                    $setter_amount += $amount2;
                } else {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $positionId2)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name2;
                        $data['image'] = $user_image2;

                        return ['setter2' => $data];
                    }

                    return $data;
                }
            }
        } elseif ($setterId) {
            $setter = User::where('id', $setterId)->first();
            if ($setter && $setterId != $closerId) {
                $user_name = $setter->first_name.' '.$setter->last_name;
                $user_image = $setter->image;
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                $positionId = @$userOrganizationHistory['position_id'];
                $setterUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                $upfrontAmount = 0;
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

                    if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                        $amount = $setterUpfront->upfront_limit;
                    }

                    $data = [
                        'user_id' => $setterId,
                        'position_id' => @$positionId,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                    ];
                    if (isset($val['uid']) && $setterId == $val['uid']) {
                        return $data;
                    } elseif (isset($val['amount_data'])) {
                        $setter_amount += $amount;
                    } else {
                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', @$positionId)->first();
                            $data['position_name'] = @$positionData->position_name;
                            $data['user_name'] = $user_name;
                            $data['image'] = $user_image;

                            return ['setter1' => $data];
                        }

                        return $data;
                    }
                }
            }
        }

        $total = $setter_amount + $closer_amount;

        return $data = [
            'user_id' => '',
            'position_id' => '',
            'amount_type' => 'm1',
            'amount' => $total,
        ];
    }

    public function salesProjectionM2($checked)
    {
        if ($checked['date_cancelled']) {
            return 0;
        }
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $kw = $checked['kw'];
        $netEpc = $checked['net_epc'];
        $approvedDate = $checked['customer_signoff'];
        $accountSummary = @$checked['from'];

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

            if (isset($checked['uid'])) {
                if ($setterId == $checked['uid']) {
                    $commissiondata['commission'] = $setter1_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter1_commission;

                    return $commissiondata;
                } else {
                    $commissiondata['commission'] = $setter2_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter2_commission;

                    return $commissiondata;
                }
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                    $setter1Result = [
                        'user_id' => $setterId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $setter->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $setter1_commission,
                    ];

                    $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                    $setter2Result = [
                        'user_id' => $setter2Id,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $setter2->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $setter2_commission,
                    ];

                    return [
                        'setter1' => $setter1Result,
                        'setter2' => $setter2Result,
                    ];
                }
            }
            $setter_commission = ($setter1_commission + $setter2_commission);
        } elseif ($setterId) {
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
                    $commission_percentage = 0; // percenge
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

                if (isset($checked['uid']) && $setterId == $checked['uid']) {
                    $commissiondata['commission'] = $setter_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter_commission;

                    return $commissiondata;
                } elseif (! isset($val['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = [
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter_commission,
                        ];

                        return [
                            'setter1' => $setter1Result,
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
                $commission_percentage = 0; // percenge
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
                $commission_percentage2 = 0; // percenge
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

            if (isset($checked['uid'])) {
                if ($closerId == $checked['uid']) {
                    $commissiondata['commission'] = $closer1_commission;
                    $commissiondata['closer_commission'] = $closer1_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                } else {
                    $commissiondata['commission'] = $closer2_commission;
                    $commissiondata['closer_commission'] = $closer2_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                }
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = [
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer1_commission,
                    ];

                    $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                    $closer2Result = [
                        'user_id' => $closer2Id,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $closer2->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer2_commission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                        'closer2' => $closer2Result,
                    ];
                }
            }
            $closer_commission = ($closer1_commission + $closer2_commission);
        } elseif ($closerId) {
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
                } else {
                    $commission_percentage = 0; // percenge
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
                        $x = isset($x) && ! empty($x) ? $x : 1;
                        $closer_commission = ($kw * $selfgen_percentage * $x);
                    } else {
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            if (isset($checked['uid']) && $closerId == $checked['uid']) {
                $commissiondata['commission'] = $closer_commission;
                $commissiondata['closer_commission'] = $closer_commission;
                $commissiondata['setter_commission'] = 0;

                return $commissiondata;
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = [
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer_commission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                    ];
                }
            }
        }

        $commissiondata['commission'] = $closer_commission + $setter_commission;
        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

        return $commissiondata;
    }

    public function subroutineSix($checked)
    {
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $approvedDate = $checked['customer_signoff'];

        if (config('app.domain_name') == 'flex') {
            $saleState = $checked['customer_state'];
        } else {
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
                    // if ($setter2->self_gen_accounts == 1 && $setter2->self_gen_type == 3) {
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
            } elseif ($setterId) {
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
                        // $closerStateRedline = $closerLocation->redline_standard;
                    }
                    // closer_redline
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

                    // closer_redline
                    $redline = $saleStandardRedline + ($closer2_redline - $closerStateRedline);
                    $data['closer2_redline'] = $redline;
                }
            } elseif ($closerId) {
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
                    // closer_redline
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

    // NOT IN USE
    public function projectedsalesSummary(Request $request): JsonResponse
    {
        if ($request->pid) {
            $pid = $request->pid;
        }
        $result = [];

        $filter = $request->filter ?? 'position';

        if ($filter == 'type') {
            // projected commisions
            $result['commission']['data'] = [];
            $result['commission']['subtotal'] = 0;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $request->pid)->first();
            if (! empty($saleData)) {
                $closerId = $saleData->salesMasterProcess->closer1_id;
                $closer2Id = $saleData->salesMasterProcess->closer2_id;
                $setterId = $saleData->salesMasterProcess->setter1_id;
                $setter2Id = $saleData->salesMasterProcess->setter2_id;
                $m1date = $saleData->m1_date;
                $m2date = $saleData->m2_date;
                $customer_signoff = $saleData->customer_signoff;
                $kw = $saleData->kw;
                $pid = $saleData->pid;
                $net_epc = $saleData->net_epc;
                $location_code = $saleData->location_code;
                $customer_state = $saleData->customer_state;
                $position = '';

                $total_comission = 0;

                if (empty($data->m1_date)) {
                    $sales_m1_projection = $this->salesProjectionM1(['closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled]);

                    if (! empty($sales_m1_projection)) {
                        foreach ($sales_m1_projection as $key => $value) {
                            array_push($result['commission']['data'], $value);
                            $total_comission += $value['amount'] ?? 0;
                        }
                    }
                }

                if (empty($data->m2_date)) {
                    $sales_m2_projection = $this->salesProjectionM2(['closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled]);

                    if (! empty($sales_m2_projection)) {
                        foreach ($sales_m2_projection as $key => $value) {
                            array_push($result['commission']['data'], $value);
                            $total_comission += $value['amount'] ?? 0;
                        }
                    }
                }

                $result['commission']['subtotal'] = $total_comission;
            }

            // projected overrides
            $result['override']['data'] = [];
            $result['override']['subtotal'] = 0;
            $sales = ProjectionUserOverrides::where('pid', $pid)->get();
            if (count($sales) > 0) {
                $total_override = 0;
                foreach ($sales as $key => $sale) {
                    $override_over_user = User::where('id', $sale->sale_user_id)->select('id', 'first_name', 'last_name', 'position_id')->first();
                    // $userOrganizationHistory = UserOrganizationHistory::where('user_id', $sale->sale_user_id)->where('effective_date', '>=', $sale->date)->orderBy('effective_date', 'DESC')->first();
                    $positionData = Positions::select('position_name')->where('id', '=', @$override_over_user->position_id)->first();
                    $dataOvr = [
                        // 'pid'               => $sale->pid,
                        'user_id' => $sale->sale_user_id,
                        'user_name' => $override_over_user->first_name.' '.$override_over_user->last_name,
                        // 'override_over'     => $override_over_user->first_name.' '.$override_over_user->last_name,
                        'position_id' => isset($override_over_user->position_id) ? $override_over_user->position_id : '',
                        'position_name' => @$positionData->position_name,
                        // 'type'              => $sale->type,
                        // 'kw_installed'      => $sale->kw,
                        // 'override'          => $sale->overrides_amount.' '.$sale->overrides_type,
                        'amount' => $sale->total_override,
                        // 'date'              => $sale->date,
                        'description' => $sale->customer_name.' | '.$sale->type,
                    ];
                    array_push($result['override']['data'], $dataOvr);
                    $total_override += $sale->total_override;
                }
                $result['override']['subtotal'] = $total_override;
            }

        } else {
            $comissionData = [];
            // projected commisions
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $request->pid)->first();
            if (! empty($saleData)) {
                $closerId = $saleData->salesMasterProcess->closer1_id;
                $closer2Id = $saleData->salesMasterProcess->closer2_id;
                $setterId = $saleData->salesMasterProcess->setter1_id;
                $setter2Id = $saleData->salesMasterProcess->setter2_id;
                $m1date = $saleData->m1_date;
                $m2date = $saleData->m2_date;
                $customer_signoff = $saleData->customer_signoff;
                $kw = $saleData->kw;
                $pid = $saleData->pid;
                $net_epc = $saleData->net_epc;
                $location_code = $saleData->location_code;
                $customer_state = $saleData->customer_state;
                $position = '';

                $total_comission = 0;

                if (empty($data->m1_date)) {
                    $sales_m1_projection = $this->salesProjectionM1(['closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled]);
                    if (! empty($sales_m1_projection)) {
                        foreach ($sales_m1_projection as $key => $value) {
                            $userData = [
                                'user_id' => $value['user_id'],
                                'image' => $value['image'],
                                'user_name' => $value['user_name'],
                                'position_id' => $value['position_id'],
                                'position_name' => $value['position_name'],
                            ];

                            $amountdata = [
                                'type' => 'comission',
                                'amount' => $value['amount'],
                                'amount_type' => $value['amount_type'],
                            ];
                            $position_name = strtolower($value['position_name']);
                            $comissionData[$position_name]['user_details'] = $userData;
                            $comissionData[$position_name][$value['user_id']][] = $amountdata;
                        }
                    }
                }
                // print_r($comissionData);
                if (empty($data->m2_date)) {
                    $sales_m2_projection = $this->salesProjectionM2(['closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                        'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled]);
                    if (! empty($sales_m2_projection)) {
                        foreach ($sales_m2_projection as $key => $value) {
                            $userData = [
                                'user_id' => $value['user_id'],
                                'image' => $value['image'],
                                'user_name' => $value['user_name'],
                                'position_id' => $value['position_id'],
                                'position_name' => $value['position_name'],
                            ];

                            $amountdata = [
                                'type' => 'comission',
                                'amount' => $value['amount'],
                                'amount_type' => $value['amount_type'],
                            ];

                            $position_name = strtolower($value['position_name']);
                            $comissionData[$position_name]['user_details'] = $userData;
                            $comissionData[$position_name][$value['user_id']][] = $amountdata;
                        }
                    }
                }
                // print_r($comissionData);
            }

            // projected overrides
            $overrideData = [];
            $sales = ProjectionUserOverrides::where('pid', $pid)->get();
            if (count($sales) > 0) {
                $total_override = 0;
                foreach ($sales as $key => $sale) {
                    $override_over_user = User::where('id', $sale->sale_user_id)->select('id', 'first_name', 'last_name', 'position_id', 'image')->first();
                    // $userOrganizationHistory = UserOrganizationHistory::where('user_id', $sale->sale_user_id)->where('effective_date', '>=', $sale->date)->orderBy('effective_date', 'DESC')->first();
                    $positionData = Positions::select('position_name')->where('id', '=', @$override_over_user->position_id)->first();
                    $userData = [
                        'user_id' => $sale->sale_user_id,
                        'image' => $override_over_user->image,
                        'user_name' => $override_over_user->first_name.' '.$override_over_user->last_name,
                        'position_id' => isset($override_over_user->position_id) ? $override_over_user->position_id : '',
                        'position_name' => @$positionData->position_name,
                    ];
                    $amountdata = [
                        'type' => 'override',
                        'amount' => $sale->total_override,
                        'description' => $sale->customer_name.' | '.$sale->type,
                    ];

                    $position_name = strtolower($positionData->position_name);
                    $overrideData[$position_name]['user_details'] = $userData;
                    $overrideData[$position_name][$sale->sale_user_id][] = $amountdata;
                }

            }

            $mergedArray = [];
            $mergedArray = $this->mergeArrays($comissionData, $overrideData);

            $i = 0;
            if (! empty($mergedArray)) {
                foreach ($mergedArray as $key => $value) {
                    $mergedArray[$key]['data'] = $mergedArray[$key][$value['user_details']['user_id']];
                    $subtotal = 0;
                    foreach ($value[$value['user_details']['user_id']] as $data) {
                        $subtotal += $data['amount'];
                    }
                    $mergedArray[$key]['subtotal'] = $subtotal;
                    unset($mergedArray[$key][$value['user_details']['user_id']]);
                }
            }

            $result = $mergedArray;
        }

        return response()->json([
            'ApiName' => 'projected_sales_summary',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);

    }

    public function account_graph(Request $request): JsonResponse
    {
        $date = date('Y-m-d');
        $user_id = auth()->user()->id;
        $clawbackPid = DB::table('clawback_settlements')->where('user_id', $user_id)->distinct()->pluck('pid')->toArray();
        $totalAmounts = [];
        $pid = DB::table('sale_master_process')->where('closer1_id', $user_id)->orWhere('closer2_id', $user_id)->orWhere('setter1_id', $user_id)->orWhere('setter2_id', $user_id)->pluck('pid');
        $mdates = getdates();
        $companyProfile = CompanyProfile::first();
        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
            $currentDate->dayOfWeek;
            for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {
                $now = Carbon::now();
                $newDateTime = Carbon::now()->subDays($i);
                $weekDate = date('m-d-Y', strtotime($newDateTime));
                $date = date('Y-m-d', strtotime($newDateTime));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => date('m/d/Y', strtotime($newDateTime)),
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => date('m/d/Y', strtotime($newDateTime)),
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                }
            }
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < 7; $i++) {
                $currentDate = \Carbon\Carbon::now();
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                $weekDate = date('m-d-Y', strtotime($startOfLastWeek));
                $date = date('Y-m-d', strtotime($startOfLastWeek));

                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => date('m/d/Y', strtotime($startOfLastWeek)),
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => date('m/d/Y', strtotime($startOfLastWeek)),
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                }
            }
        } elseif ($filterDataDateWise == 'this_month') {
            $endOfLastWeek = Carbon::now();
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;

            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            for ($i = 0; $i <= $dateDays; $i++) {
                $weekDate = date('m/d/Y', strtotime(Carbon::now()->startOfMonth()->addDays($i)));
                $date = date('Y-m-d', strtotime(Carbon::now()->startOfMonth()->addDays($i)));
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => $weekDate,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                }
            }
        } elseif ($filterDataDateWise == 'last_month') {
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            $startDate = Carbon::now()->startOfMonth()->subMonth()->toDateString();
            $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < $month; $i++) {
                $weekDate = date('m/d/y', strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i)));
                $date = date('Y-m-d', strtotime(strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i))));
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereDate('updated_at', $date)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $amount[] = [
                    'date' => $weekDate,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $date)
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
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

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/y';
            for ($i = 0; $i < $weeks; $i++) {
                $currentDate = \Carbon\Carbon::now();
                $startDate = $date->copy();
                // loop to end of the week while not crossing the last date of month
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
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid');

                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;
                $time = strtotime($sDate);
                $weekDate = $dates['w'.$i];
                $amount[] = [
                    'date' => $weekDate,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
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

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
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
                $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $time = strtotime($sDate);
                $month = date('M', $time);
                $weekDate = $dates['w'.$i];
                $amount[] = [
                    'date' => $weekDate,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $weekDate,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                }
            }
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
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

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $time = strtotime($sDate);
                $month = date('M', $time);
                $amount[] = [
                    'date' => $month,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $month,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                }
            }
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

            $data = [];
            $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
            } else {
                $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
            }
            $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
            $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

            $totalM1Amount = 0;
            $totalM2Amount = 0;
            $totalProjectedM1Amount = 0;
            $totalProjectedM2Amount = 0;
            $currentDateTime = Carbon::now();
            $m1Amount = [];
            $m2Amount = [];
            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                $pids = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                $amountM1 = 0;
                $amountM2 = 0;

                $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                $amountM1 = $salesm1Amount;

                $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                $amountM2 = $salesm2Amount;

                $time = strtotime($sDate);
                $month = date('M', $time);
                $amount[] = [
                    'date' => $month,
                    // 'm1_amount' => $amountM1,
                    // 'm2_amount' => $amountM2
                ];
                foreach ($mdates as $date) {
                    $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                }
                $totalM1Amount += $amountM1;
                $totalM2Amount += $amountM2;

                // Projection
                $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                    ->where(function ($q) {
                        $q->whereNull('m1_date')->orWhereNull('m2_date');
                    })->get();
                $projectedAmountM1 = 0;
                $projectedAmountM2 = 0;
                foreach ($projectedSales as $projectedSale) {
                    $pidProjectedAmountM1 = 0;
                    $pidProjectedAmountM2 = 0;
                    if (empty($projectedSale->m1_date)) {
                        $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m2_date)) {
                        $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                    }

                    if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                        $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                    } elseif (empty($projectedSale->m2_date)) {
                        $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                        $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                    }
                    $projectedAmountM1 += $pidProjectedAmountM1;
                    $projectedAmountM2 += $pidProjectedAmountM2;
                }

                $projectedamount[] = [
                    'date' => $month,
                    'm1_amount' => $projectedAmountM1,
                    'm2_amount' => $projectedAmountM2,
                ];
                $totalProjectedM1Amount += $projectedAmountM1;
                $totalProjectedM2Amount += $projectedAmountM2;
            }
            $totalAmounts = [
                // 'm1_amount' => $totalM1Amount,
                // 'm1_projected_amount' => $totalProjectedM1Amount,
                // 'm2_amount' => $totalM2Amount,
                // 'm2_projected_amount' => $totalProjectedM2Amount
            ];
            foreach ($mdates as $date) {
                if (empty($totalAmounts)) {
                    $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                } else {
                    $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
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
                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereNotIn('pid', $clawbackPid)->whereIn('pid', $pid)->count();

                $totalM1Amount = 0;
                $totalM2Amount = 0;
                $totalProjectedM1Amount = 0;
                $totalProjectedM2Amount = 0;

                $currentDateTime = Carbon::now();
                $m1Amount = [];
                $m2Amount = [];

                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));

                        $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                        $amountM1 = $salesm1Amount;

                        // $salesm2Amount = UserCommission::whereDate('updated_at',$weekDate)->where('user_id',$user_id)->where('status',5)->where('amount_type','m2')->sum('amount');
                        $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                        $amountM2 = $salesm2Amount;
                        // }
                        $weekDates = date('m-d-y', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                        $amount[] = [
                            'date' => $weekDates,
                            // 'm1_amount' => $amountM1,
                            // 'm2_amount' => $amountM2
                        ];
                        foreach ($mdates as $date) {
                            $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                        }
                        $totalM1Amount += $amountM1;
                        $totalM2Amount += $amountM2;

                        // Projection
                        $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)
                            ->where(function ($q) {
                                $q->whereNull('m1_date')->orWhereNull('m2_date');
                            })->get();
                        $projectedAmountM1 = 0;
                        $projectedAmountM2 = 0;
                        foreach ($projectedSales as $projectedSale) {
                            $pidProjectedAmountM1 = 0;
                            $pidProjectedAmountM2 = 0;
                            if (empty($projectedSale->m1_date)) {
                                $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m2_date)) {
                                $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                                $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                            } elseif (empty($projectedSale->m2_date)) {
                                $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                            }
                            $projectedAmountM1 += $pidProjectedAmountM1;
                            $projectedAmountM2 += $pidProjectedAmountM2;
                        }

                        $projectedamount[] = [
                            'date' => $weekDates,
                            'm1_amount' => $projectedAmountM1,
                            'm2_amount' => $projectedAmountM2,
                        ];
                        $totalProjectedM1Amount += $projectedAmountM1;
                        $totalProjectedM2Amount += $projectedAmountM2;
                    }
                    $totalAmounts = [
                        // 'm1_amount' => $totalM1Amount,
                        // 'm1_projected_amount' => $totalProjectedM1Amount,
                        // 'm2_amount' => $totalM2Amount,
                        // 'm2_projected_amount' => $totalProjectedM2Amount
                    ];
                    foreach ($mdates as $date) {
                        if (empty($totalAmounts)) {
                            $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                        } else {
                            $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                        }
                    }
                } else {

                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;
                    $totalM1Amount = 0;
                    $totalM2Amount = 0;
                    $totalProjectedM1Amount = 0;
                    $totalProjectedM2Amount = 0;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

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

                        //     $sDateg = date('m/d/y', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                        //     $eDateg = date('m/d/y', strtotime($endsDate . ' - ' . 0 . ' days'));
                        // } else {

                        //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));

                        //     $sDateg = date('m/d/y', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDateg = date('m/d/y', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                        // }
                        // if ($i == $weekCount - 1) {
                        //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDate = $endDate;

                        //     $sDateg = date('m/d/y', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //     $eDateg = date('m/d/y', strtotime($endDate . ' + ' . $dayWeek . ' days'));
                        // }

                        $pids = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        if (count($pids) > 0) {
                            $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                            $amountM1 = $salesm1Amount;

                            $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                            $amountM2 = $salesm2Amount;
                        }

                        $amount[] = [
                            'date' => $month, // $sDateg . ' to ' . $eDateg,
                            // 'm1_amount' => $amountM1,
                            // 'm2_amount' => $amountM2
                        ];
                        foreach ($mdates as $date) {
                            $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                        }
                        $totalM1Amount += $amountM1;
                        $totalM2Amount += $amountM2;

                        // Projection
                        $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                            ->where(function ($q) {
                                $q->whereNull('m1_date')->orWhereNull('m2_date');
                            })->get();
                        $projectedAmountM1 = 0;
                        $projectedAmountM2 = 0;
                        foreach ($projectedSales as $projectedSale) {
                            $pidProjectedAmountM1 = 0;
                            $pidProjectedAmountM2 = 0;
                            if (empty($projectedSale->m1_date)) {
                                $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m2_date)) {
                                $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                                $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                            } elseif (empty($projectedSale->m2_date)) {
                                $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                            }
                            $projectedAmountM1 += $pidProjectedAmountM1;
                            $projectedAmountM2 += $pidProjectedAmountM2;
                        }
                        $projectedamount[] = [
                            'date' => $month, // $sDateg . ' to ' . $eDateg,
                            'm1_amount' => $projectedAmountM1,
                            'm2_amount' => $projectedAmountM2,
                        ];
                        $totalProjectedM1Amount += $projectedAmountM1;
                        $totalProjectedM2Amount += $projectedAmountM2;
                    }
                    $totalAmounts = [
                        // 'm1_amount' => $totalM1Amount,
                        // 'm1_projected_amount' => $totalProjectedM1Amount,
                        // 'm2_amount' => $totalM2Amount,
                        // 'm2_projected_amount' => $totalProjectedM2Amount
                    ];
                    foreach ($mdates as $date) {
                        if (empty($totalAmounts)) {
                            $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                        } else {
                            $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                        }
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
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('m2_date')->whereIn('pid', $pid)->whereNull('date_cancelled')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->whereNotIn('pid', $clawbackPid)->count();

                $totalM1Amount = 0;
                $totalM2Amount = 0;
                $totalProjectedM1Amount = 0;
                $totalProjectedM2Amount = 0;

                $currentDateTime = Carbon::now();
                $m1Amount = [];
                $m2Amount = [];
                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                        $pids = SalesMaster::whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                        $amountM1 = 0;
                        $amountM2 = 0;
                        $salesm1Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                        $amountM1 = $salesm1Amount;
                        $salesm2Amount = UserCommission::whereDate('updated_at', $weekDate)->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                        $amountM2 = $salesm2Amount;
                        $weekDates = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                        $amount[] = [
                            'date' => $weekDates,
                            // 'm1_amount' => $amountM1,
                            // 'm2_amount' => $amountM2
                        ];
                        foreach ($mdates as $date) {
                            $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                        }
                        $totalM1Amount += $amountM1;
                        $totalM2Amount += $amountM2;

                        // Projection
                        $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->where('customer_signoff', $weekDate)
                            ->where(function ($q) {
                                $q->whereNull('m1_date')->orWhereNull('m2_date');
                            })->get();
                        $projectedAmountM1 = 0;
                        $projectedAmountM2 = 0;
                        foreach ($projectedSales as $projectedSale) {
                            $pidProjectedAmountM1 = 0;
                            $pidProjectedAmountM2 = 0;
                            if (empty($projectedSale->m1_date)) {
                                $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m2_date)) {
                                $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                                $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                            } elseif (empty($projectedSale->m2_date)) {
                                $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                            }
                            $projectedAmountM1 += $pidProjectedAmountM1;
                            $projectedAmountM2 += $pidProjectedAmountM2;
                        }

                        $projectedamount[] = [
                            'date' => $weekDates,
                            'm1_amount' => $projectedAmountM1,
                            'm2_amount' => $projectedAmountM2,
                        ];

                        $totalProjectedM1Amount += $projectedAmountM1;
                        $totalProjectedM2Amount += $projectedAmountM2;
                    }

                    $totalAmounts = [
                        // 'm1_amount' => $totalM1Amount,
                        // 'm1_projected_amount' => $totalProjectedM1Amount,
                        // 'm2_amount' => $totalM2Amount,
                        // 'm2_projected_amount' => $totalProjectedM2Amount
                    ];
                    foreach ($mdates as $date) {
                        if (empty($totalAmounts)) {
                            $totalAmounts[0] = [$date => 10, $date.'_projected_amount' => ''];
                        } else {
                            $totalAmounts[0] = array_merge($totalAmounts[0], [$date => 10, $date.'_projected_amount' => '']);
                        }
                    }
                } else {

                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;
                    $totalM1Amount = 0;
                    $totalM2Amount = 0;
                    $totalProjectedM1Amount = 0;
                    $totalProjectedM2Amount = 0;

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
                            $salesm1Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm1')->sum('amount');
                            $amountM1 = $salesm1Amount;

                            $salesm2Amount = UserCommission::whereBetween('updated_at', [$sDate, $eDate])->where('user_id', $user_id)->where('status', 3)->where('amount_type', 'm2')->sum('amount');
                            $amountM2 = $salesm2Amount;
                        }

                        $amount[] = [
                            'date' => $sDateg.' to '.$eDateg,
                            // 'm1_amount' => $amountM1,
                            // 'm2_amount' => $amountM2
                        ];
                        foreach ($mdates as $date) {
                            $amount[count($amount) - 1] = array_merge($amount[count($amount) - 1], [$date => 10]);
                        }

                        $totalM1Amount += $amountM1;
                        $totalM2Amount += $amountM2;

                        // Projection
                        $projectedSales = SalesMaster::with('salesMasterProcess')->whereIn('pid', $pid)->where('date_cancelled', null)->whereBetween('customer_signoff', [$sDate, $eDate])
                            ->where(function ($q) {
                                $q->whereNull('m1_date')->orWhereNull('m2_date');
                            })->get();
                        $projectedAmountM1 = 0;
                        $projectedAmountM2 = 0;
                        foreach ($projectedSales as $projectedSale) {
                            $pidProjectedAmountM1 = 0;
                            $pidProjectedAmountM2 = 0;
                            if (empty($projectedSale->m1_date)) {
                                $m1AmountProjected = $this->calculateProjectedM1($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m2_date)) {
                                $m2AmountProjected = $this->calculateProjectedM2($projectedSale, $user_id);
                            }

                            if (empty($projectedSale->m1_date) && empty($projectedSale->m2_date)) {
                                $pidProjectedAmountM1 = $m1AmountProjected ? $m1AmountProjected : 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $pidProjectedAmountM1 : 0;
                            } elseif (empty($projectedSale->m2_date)) {
                                $userM1 = UserCommission::where(['pid' => $projectedSale->pid, 'user_id' => $user_id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $pidProjectedAmountM2 = $m2AmountProjected ? $m2AmountProjected - $userM1 : 0;
                            }
                            $projectedAmountM1 += $pidProjectedAmountM1;
                            $projectedAmountM2 += $pidProjectedAmountM2;
                        }

                        $projectedamount[] = [
                            'date' => $sDateg.' to '.$eDateg,
                            'm1_amount' => $projectedAmountM1,
                            'm2_amount' => $projectedAmountM2,
                        ];

                        $totalProjectedM1Amount += $projectedAmountM1;
                        $totalProjectedM2Amount += $projectedAmountM2;
                    }

                    $totalAmounts = [
                        // 'm1_amount' => $totalM1Amount,
                        // 'm1_projected_amount' => $totalProjectedM1Amount,
                        // 'm2_amount' => $totalM2Amount,
                        // 'm2_projected_amount' => $totalProjectedM2Amount
                    ];
                    foreach ($mdates as $date) {
                        $totalAmounts[] = [
                            $date => 10,
                            $date.'_projected_amount' => 10,
                        ];
                    }
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Custom Start Date and End Date id Required.',
                ]);
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
            $inatll = round((($m2Complete / count($totalSales)) * 100), 5);
            $data['install_ratio'] = [
                'install' => $inatll.'%',
                'uninstall' => round(100 - $inatll, 5).'%',
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '100%',
            ];
        }

        $data['graph_m1_m2_amount'] = [
            'graph_amount' => $amount,
            // 'total_amount'  => $totalAmounts
        ];

        return response()->json([
            'ApiName' => 'filter_customer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function calculateProjectedM1($data, $uid)
    {
        $m1ProjectedAmount = 0;
        $companyProfile = CompanyProfile::first();
        $closerId = $data->salesMasterProcess->closer1_id;
        $closer2Id = $data->salesMasterProcess->closer2_id;
        $setterId = $data->salesMasterProcess->setter1_id;
        $setter2Id = $data->salesMasterProcess->setter2_id;
        $m1date = $data->m1_date;
        $customer_signoff = $data->customer_signoff;
        $kw = $data->kw;
        $pid = $data->pid;
        $position = '';

        $sales_projection_m1_amount = '';
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $sales_projection_m1_amount = $this->pestSalesProjectionM1([
                'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'uid' => $uid,
            ]);
        } else {
            $sales_projection_m1_amount = $this->salesProjectionM1([
                'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'uid' => $uid,
            ]);
        }

        if (! empty($sales_projection_m1_amount)) {
            $m1ProjectedAmount += $sales_projection_m1_amount['amount'];
        }

        return $m1ProjectedAmount;
    }

    public function calculateProjectedM2($data, $uid)
    {
        $m2ProjectedAmount = 0;
        $companyProfile = CompanyProfile::first();
        $closerId = $data->salesMasterProcess->closer1_id;
        $closer2Id = $data->salesMasterProcess->closer2_id;
        $setterId = $data->salesMasterProcess->setter1_id;
        $setter2Id = $data->salesMasterProcess->setter2_id;
        $m1date = $data->m1_date;
        $m2date = $data->m2_date;
        $grossAmountValue = $data->gross_account_value;
        $customer_signoff = $data->customer_signoff;
        $kw = $data->kw;
        $pid = $data->pid;
        $net_epc = $data->net_epc;
        $location_code = $data->location_code;
        $customer_state = $data->customer_state;
        $position = '';

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $sales_projection_m2_amount = $this->pestSalesProjectionM2([
                'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'gross_account_value' => $grossAmountValue, 'uid' => $uid,
            ]);
        } else {
            $sales_projection_m2_amount = $this->salesProjectionM2([
                'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'date_cancelled' => $data->date_cancelled, 'gross_account_value' => $grossAmountValue, 'uid' => $uid,
            ]);
        }

        if (! empty($sales_projection_m2_amount)) {
            $m2ProjectedAmount += $sales_projection_m2_amount['commission'];
        }

        return $m2ProjectedAmount;
    }

    /**
     *  @method userProjectionSummary
     * It is used to show projected values on user account summary */
    public function userProjectionSummary(Request $request): JsonResponse
    {
        $pid = '';
        if ($request->pid) {
            $pid = $request->pid;
        }
        $result = [];

        $this->saleprojectionsummary($pid);
        $filter = $request->filter ?? 'position';
        $companyProfile = CompanyProfile::first();
        if ($filter == 'type') {
            // projected commisions
            $result['commission']['data'] = [];
            $result['commission']['subtotal'] = 0;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $request->pid)->first();
            if (! empty($saleData)) {
                $closerId = $saleData->salesMasterProcess->closer1_id;
                $closer2Id = $saleData->salesMasterProcess->closer2_id;
                $setterId = $saleData->salesMasterProcess->setter1_id;
                $setter2Id = $saleData->salesMasterProcess->setter2_id;
                $m1date = $saleData->m1_date;
                $m2date = $saleData->m2_date;
                $grossAmountValue = $saleData->gross_account_value;
                $customer_signoff = $saleData->customer_signoff;
                $kw = $saleData->kw;
                $pid = $saleData->pid;
                $net_epc = $saleData->net_epc;
                $location_code = $saleData->location_code;
                $customer_state = $saleData->customer_state;
                $position = '';

                $total_comission = 0;
                if (empty($saleData->m1_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m1_projection = $this->pestSalesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled,
                        ]);
                    } else {
                        $sales_m1_projection = $this->salesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled,
                        ]);
                    }
                }

                if (empty($saleData->m2_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m2_projection = $this->pestSalesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    } else {
                        $sales_m2_projection = $this->salesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    }
                }

                if (empty($saleData->m1_date) && empty($saleData->m2_date)) {
                    if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                        if (@$sales_m2_projection['closer']) {
                            if (@$sales_m2_projection['closer']['closer1']) {
                                $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - (@$sales_m1_projection['closer']['closer1']['amount'] ? $sales_m1_projection['closer']['closer1']['amount'] : 0);
                            }
                            if (@$sales_m2_projection['closer']['closer2']) {
                                $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - (@$sales_m1_projection['closer']['closer2']['amount'] ? $sales_m1_projection['closer']['closer2']['amount'] : 0);
                            }
                        }
                        if (@$sales_m2_projection['setter']) {
                            if (@$sales_m2_projection['setter']['setter1']) {
                                $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - (@$sales_m1_projection['setter']['setter1']['amount'] ? $sales_m1_projection['setter']['setter1']['amount'] : 0);
                            }
                            if (@$sales_m2_projection['setter']['setter2']) {
                                $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - (@$sales_m1_projection['setter']['setter2']['amount'] ? $sales_m1_projection['setter']['setter2']['amount'] : 0);
                            }
                        }
                    }
                } elseif (empty($saleData->m2_date)) {
                    if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                        if (@$sales_m2_projection['closer']) {
                            if (@$sales_m2_projection['closer']['closer1']) {
                                $closer1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - $closer1M1;
                            }
                            if (@$sales_m2_projection['closer']['closer2']) {
                                $closer2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - $closer2M1;
                            }
                        }

                        if (@$sales_m2_projection['setter']) {
                            if (@$sales_m2_projection['setter']['setter1']) {
                                $setter1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - $setter1M1;
                            }
                            if (@$sales_m2_projection['setter']['setter2']) {
                                $setter2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - $setter2M1;
                            }
                        }
                    }
                }

                if (isset($sales_m2_projection) && ! empty($sales_m1_projection)) {
                    foreach ($sales_m1_projection as $m1_projection) {
                        if (! empty($m1_projection)) {
                            foreach ($m1_projection as $key => $value) {
                                array_push($result['commission']['data'], $value);
                                $total_comission += $value['amount'] ?? 0;
                            }
                        }
                    }
                }

                if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                    foreach ($sales_m2_projection as $m2_projection) {
                        if (! empty($m2_projection)) {
                            foreach ($m2_projection as $key => $value) {
                                array_push($result['commission']['data'], $value);
                                $total_comission += $value['amount'] ?? 0;
                            }
                        }
                    }
                }

                $result['commission']['subtotal'] = $total_comission;
            }

            // projected overrides
            $result['override']['data'] = [];
            $result['override']['subtotal'] = 0;
            $sales = ProjectionUserOverrides::where('pid', $pid)->get();
            if (count($sales) > 0) {
                $total_override = 0;
                foreach ($sales as $key => $sale) {
                    $override_over_user = User::where('id', $sale->sale_user_id)->select('id', 'first_name', 'last_name', 'position_id')->first();
                    $user_data = User::where('id', $sale->user_id)->select('id', 'first_name', 'last_name', 'position_id')->first();
                    $positionData = Positions::select('position_name')->where('id', '=', @$override_over_user->position_id)->first();
                    $dataOvr = [
                        'user_id' => $override_over_user->id,
                        'user_name' => $override_over_user->first_name.' '.$override_over_user->last_name,
                        'position_id' => isset($override_over_user->position_id) ? $override_over_user->position_id : '',
                        'position_name' => @$positionData->position_name,
                        'amount' => $sale->total_override,
                        'description' => $user_data->first_name.' '.$user_data->last_name.' | '.$sale->type,
                    ];
                    array_push($result['override']['data'], $dataOvr);
                    $total_override += $sale->total_override;
                }
                $result['override']['subtotal'] = $total_override;
            }
        } else {
            $comissionData = [];
            // projected commisions
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $request->pid)->first();
            if (! empty($saleData)) {
                $closerId = $saleData->salesMasterProcess->closer1_id;
                $closer2Id = $saleData->salesMasterProcess->closer2_id;
                $setterId = $saleData->salesMasterProcess->setter1_id;
                $setter2Id = $saleData->salesMasterProcess->setter2_id;
                $m1date = $saleData->m1_date;
                $m2date = $saleData->m2_date;
                $grossAmountValue = $saleData->gross_account_value;
                $customer_signoff = $saleData->customer_signoff;
                $kw = $saleData->kw;
                $pid = $saleData->pid;
                $net_epc = $saleData->net_epc;
                $location_code = $saleData->location_code;
                $customer_state = $saleData->customer_state;
                $position = '';

                $total_comission = 0;

                if (empty($saleData->m1_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m1_projection = $this->pestSalesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled,
                        ]);
                    } else {
                        $sales_m1_projection = $this->salesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled,
                        ]);
                    }

                    if (! empty($sales_m1_projection)) {
                        foreach ($sales_m1_projection as $m1_projection) {
                            if (! empty($m1_projection)) {
                                foreach ($m1_projection as $key => $value) {
                                    $userData = [
                                        'user_id' => $value['user_id'],
                                        'image' => $value['image'],
                                        'user_name' => $value['user_name'],
                                        'position_id' => $value['position_id'],
                                        'position_name' => $value['position_name'],
                                    ];

                                    $amountdata = [
                                        'type' => 'comission',
                                        'amount' => $value['amount'],
                                        'amount_type' => $value['amount_type'],
                                    ];
                                    $position_name = strtolower($value['position_name']);
                                    $comissionData[$position_name]['user_details'] = $userData;
                                    $comissionData[$position_name][$value['user_id']][] = $amountdata;
                                }
                            }
                        }
                    }
                }

                if (empty($saleData->m2_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m2_projection = $this->pestSalesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    } else {
                        $sales_m2_projection = $this->salesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $saleData->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    }

                    if (empty($saleData->m1_date) && empty($saleData->m2_date)) {
                        if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                            if (@$sales_m2_projection['closer']) {
                                if (@$sales_m2_projection['closer']['closer1']) {
                                    $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - (@$sales_m1_projection['closer']['closer1']['amount'] ? $sales_m1_projection['closer']['closer1']['amount'] : 0);
                                }
                                if (@$sales_m2_projection['closer']['closer2']) {
                                    $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - (@$sales_m1_projection['closer']['closer2']['amount'] ? $sales_m1_projection['closer']['closer2']['amount'] : 0);
                                }
                            }
                            if (@$sales_m2_projection['setter']) {
                                if (@$sales_m2_projection['setter']['setter1']) {
                                    $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - (@$sales_m1_projection['setter']['setter1']['amount'] ? $sales_m1_projection['setter']['setter1']['amount'] : 0);
                                }
                                if (@$sales_m2_projection['setter']['setter2']) {
                                    $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - (@$sales_m1_projection['setter']['setter2']['amount'] ? $sales_m1_projection['setter']['setter2']['amount'] : 0);
                                }
                            }
                        }
                    } elseif (empty($saleData->m2_date)) {
                        if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                            if (@$sales_m2_projection['closer']) {
                                if (@$sales_m2_projection['closer']['closer1']) {
                                    $closer1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                    $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - $closer1M1;
                                }
                                if (@$sales_m2_projection['closer']['closer2']) {
                                    $closer2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                    $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - $closer2M1;
                                }
                            }

                            if (@$sales_m2_projection['setter']) {
                                if (@$sales_m2_projection['setter']['setter1']) {
                                    $setter1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                    $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - $setter1M1;
                                }
                                if (@$sales_m2_projection['setter']['setter2']) {
                                    $setter2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                    $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - $setter2M1;
                                }
                            }
                        }
                    }

                    if (! empty($sales_m2_projection)) {
                        foreach ($sales_m2_projection as $m2_projection) {
                            if (! empty($m2_projection)) {
                                foreach ($m2_projection as $key => $value) {
                                    $userData = [
                                        'user_id' => $value['user_id'],
                                        'image' => $value['image'],
                                        'user_name' => $value['user_name'],
                                        'position_id' => $value['position_id'],
                                        'position_name' => $value['position_name'],
                                    ];

                                    $amountdata = [
                                        'type' => 'comission',
                                        'amount' => $value['amount'],
                                        'amount_type' => $value['amount_type'],
                                    ];

                                    $position_name = strtolower($value['position_name']);
                                    $comissionData[$position_name]['user_details'] = $userData;
                                    $comissionData[$position_name][$value['user_id']][] = $amountdata;
                                }
                            }
                        }
                    }
                }
            }

            // projected overrides
            $overrideData = [];
            $sales = ProjectionUserOverrides::where('pid', $pid)->get();
            if (count($sales) > 0) {
                $total_override = 0;
                foreach ($sales as $key => $sale) {
                    $override_over_user = User::where('id', $sale->sale_user_id)->select('id', 'first_name', 'last_name', 'position_id', 'image')->first();
                    $positionData = Positions::select('position_name')->where('id', '=', @$override_over_user->position_id)->first();
                    $user_data = User::where('id', $sale->user_id)->select('id', 'first_name', 'last_name', 'position_id')->first();

                    $userData = [
                        'user_id' => $override_over_user->id,
                        'image' => $override_over_user->image,
                        'user_name' => $override_over_user->first_name.' '.$override_over_user->last_name,
                        'position_id' => isset($override_over_user->position_id) ? $override_over_user->position_id : '',
                        'position_name' => @$positionData->position_name,
                    ];
                    $amountdata = [
                        'type' => 'override',
                        'amount' => $sale->total_override,
                        'description' => $user_data->first_name.' '.$user_data->last_name.' | '.$sale->type,
                    ];

                    $position_name = strtolower($positionData->position_name);
                    $overrideData[$position_name]['user_details'] = $userData;
                    $overrideData[$position_name][$override_over_user->id][] = $amountdata;
                }
            }

            $mergedArray = [];
            $mergedArray = $this->mergeComissionOverrideArrays($comissionData, $overrideData);
            if (! empty($mergedArray)) {
                foreach ($mergedArray as $key => $value) {
                    if (isset($mergedArray[$key][$value['user_details']['user_id']])) {
                        $mergedArray[$key]['data'] = $mergedArray[$key][$value['user_details']['user_id']];
                        $subtotal = 0;
                        foreach ($value[$value['user_details']['user_id']] as $data) {
                            $subtotal += $data['amount'];
                        }
                        $mergedArray[$key]['subtotal'] = $subtotal;
                        unset($mergedArray[$key][$value['user_details']['user_id']]);
                    }
                }
            }
            $result = $mergedArray;
        }

        return response()->json([
            'ApiName' => 'projected_sales_summary',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ]);
    }

    /**
     * @method mergeComissionOverrideArrays
     * it is used to merge two arrays
     */
    public function mergeComissionOverrideArrays($array1, $array2)
    {
        if (! empty($array2)) {
            foreach ($array2 as $key => $value) {
                if (isset($value['user_details'])) {
                    unset($value['user_details']);
                }
                if (! empty($value)) {
                    foreach ($value as $key1 => $val) {
                        if (isset($array1[$key]) && isset($array1[$key][$key1])) {
                            $array1[$key][$key1] = array_merge($array1[$key][$key1], $val);
                        } else {
                            $array1[$key][$key1] = $val;
                        }
                    }
                }
            }
        }

        return $array1;
    }

    public function salesAccountSummaryProjectionM1($val)
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
        $accountSummary = @$val['from'];
        $closerData = [];
        $setterData = [];
        $total = 0;

        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $closerUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();
            $upfrontAmount = '';
            $upfrontType = '';
            if ($closerUpfront) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
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

            $closer2 = User::where('id', $closer2Id)->first();
            $user_name2 = $closer2->first_name.' '.$closer2->last_name;
            $user_image2 = $closer->image;
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

            if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
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

                if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closerId,
                    'position_id' => $closer->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name;
                    $data['image'] = $user_image;
                    $closerData['closer1'] = $data;
                } else {
                    $total += $amount;
                }
            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
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

                if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer2Id,
                    'position_id' => $closer2->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name2;
                    $data['image'] = $user_image2;
                    $closerData['closer2'] = $data;
                } else {
                    $total += $amount2;
                }
            }
        } elseif ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            // IN THE CASE OF SELF-GEN THERE SHOULD BE RESTRICTION ONLY ON PRIMARY POSITION
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

                $data = [
                    'user_id' => $closerId,
                    'position_id' => $closer->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name;
                    $data['image'] = $user_image;
                    $closerData = ['closer1' => $data];
                } else {
                    $total += $amount;
                }
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

                        if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        }

                        $data = [
                            'user_id' => $closerId,
                            'position_id' => $closer->position_id,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                        ];

                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                            $data['position_name'] = @$positionData->position_name;
                            $data['user_name'] = $user_name;
                            $data['image'] = $user_image;
                            $closerData = ['closer1' => $data];
                        } else {
                            $total += $amount;
                        }
                    }
                }
            }
        }

        if ($setterId != null && $setter2Id != null) {
            $setter = User::where('id', $setterId)->first();
            $user_name = $setter->first_name.' '.$setter->last_name;
            $user_image = $setter->image;
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

            $setter2 = User::where('id', $setter2Id)->first();
            $user_name2 = $setter2->first_name.' '.$setter2->last_name;
            $user_image2 = $setter2->image;
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

            if (! empty($setterUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
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

                if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                    $amount = $setterUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $setterId,
                    'position_id' => $setter->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name;
                    $data['image'] = $user_image;
                    $setterData['setter1'] = $data;
                } else {
                    $total += $amount;
                }
            }

            if (! empty($setter2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
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

                if (! empty($setter2Upfront->upfront_limit) && $amount2 > $setter2Upfront->upfront_limit) {
                    $amount2 = $setter2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $setter2Id,
                    'position_id' => $setter2->position_id,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name2;
                    $data['image'] = $user_image2;
                    $setterData['setter2'] = $data;
                } else {
                    $total += $amount2;
                }
            }
        } elseif ($setterId) {
            $setter = User::where('id', $setterId)->first();
            $user_name = $setter->first_name.' '.$setter->last_name;
            $user_image = $setter->image;

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

                    if ($upfrontAmount && $upfrontType) {
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        } else {
                            $amount = ($upfrontAmount * $kw);
                        }

                        if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                            $amount = $setterUpfront->upfront_limit;
                        }

                        $data = [
                            'user_id' => $setterId,
                            'position_id' => $setter->position_id,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                        ];

                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                            $data['position_name'] = @$positionData->position_name;
                            $data['user_name'] = $user_name;
                            $data['image'] = $user_image;
                            $setterData['setter1'] = $data;
                        } else {
                            $total += $amount;
                        }
                    }
                }
            }
        }
        if (! empty($accountSummary)) {
            return [
                'closer' => $closerData,
                'setter' => $setterData,
            ];
        } else {
            return $data = [
                'user_id' => '',
                'position_id' => '',
                'amount_type' => 'm1',
                'amount' => $total,
            ];
        }
    }

    public function pestSalesAccountSummaryProjectionM1($val)
    {
        if ($val['date_cancelled']) {
            return 0;
        }
        $closer1 = $val['closer1_id'];
        $closer2 = $val['closer2_id'];

        $setterId = $val['setter1_id'];
        $setter2Id = $val['setter2_id'];

        $m1date = $val['m1_date'];
        $customerSignOff = $val['customer_signoff'];
        $kw = $val['kw'];
        $pid = $val['pid'];
        $position = $val['position'];
        $accountSummary = @$val['from'];
        $closerData = [];
        $setterData = [];
        $total = 0;

        if ($closer1 != null && $closer2 != null) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;
            $positionId = $closer->sub_position_id;

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
            $user_name2 = $closer2User->first_name.' '.$closer2User->last_name;
            $user_image2 = $closer2User->image;
            $position2Id = $closer2User->sub_position_id;

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

            if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
                if ($closer2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    }
                }

                if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer1,
                    'position_id' => $closer->position_id,
                    'amount_type' => 'upfront',
                    'amount' => $amount,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name;
                    $data['image'] = $user_image;
                    $closerData['closer1'] = $data;
                } else {
                    $total += $amount;
                }

            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
                if ($closerUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    }
                }

                if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }

                $data = [
                    'user_id' => $closer2,
                    'position_id' => $closer2User->position_id,
                    'amount_type' => 'upfront',
                    'amount' => $amount2,
                ];

                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer2User->position_id)->first();
                    $data['position_name'] = @$positionData->position_name;
                    $data['user_name'] = $user_name2;
                    $data['image'] = $user_image2;
                    $closerData['closer2'] = $data;
                } else {
                    $total += $amount2;
                }

            }
        } elseif ($closer1) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

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

                    if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    }

                    $data = [
                        'user_id' => $closer1,
                        'position_id' => $closer->position_id,
                        'amount_type' => 'upfront',
                        'amount' => $amount,
                    ];

                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $data['position_name'] = @$positionData->position_name;
                        $data['user_name'] = $user_name;
                        $data['image'] = $user_image;
                        $closerData = ['closer1' => $data];
                    } else {
                        $total += $amount;
                    }

                }
            }
        }

        if (! empty($accountSummary)) {
            return [
                'closer' => $closerData,
                'setter' => $setterData,
            ];
        } else {
            return $data = [
                'user_id' => '',
                'position_id' => '',
                'amount_type' => 'm1',
                'amount' => $total,
            ];
        }
    }

    public function salesAccountSummaryProjectionM2($checked)
    {
        if ($checked['date_cancelled']) {
            return 0;
        }
        $closerId = $checked['closer1_id'];
        $closer2Id = $checked['closer2_id'];
        $setterId = $checked['setter1_id'];
        $setter2Id = $checked['setter2_id'];
        $kw = $checked['kw'];
        $netEpc = $checked['net_epc'];
        $approvedDate = $checked['customer_signoff'];
        $position = $checked['position'];
        $accountSummary = @$checked['from'];
        $setterData = [];
        $closerData = [];

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

            $setter_commission = ($setter1_commission + $setter2_commission);
            if (! empty($accountSummary)) {
                $positionId = ($setter->position_id == 2) ? '3' : $setter->position_id;
                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                $setter1Result = [
                    'user_id' => $setterId,
                    'user_name' => $user_name,
                    'image' => $user_image,
                    'position_id' => $setter->position_id,
                    'position_name' => @$positionData->position_name,
                    'amount_type' => 'm2',
                    'amount' => $setter1_commission,
                ];

                $positionId2 = ($setter2->position_id == 2) ? '3' : $setter2->position_id;
                $positionData2 = Positions::select('position_name')->where('id', '=', $positionId2)->first();
                $setter2Result = [
                    'user_id' => $setter2Id,
                    'user_name' => $user_name2,
                    'image' => $user_image2,
                    'position_id' => $setter2->position_id,
                    'position_name' => @$positionData2->position_name,
                    'amount_type' => 'm2',
                    'amount' => $setter2_commission,
                ];

                $setterData = [
                    'setter1' => $setter1Result,
                    'setter2' => $setter2Result,
                ];
            }
        } elseif ($setterId) {
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
                    $commission_percentage = 0; // percenge
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

                if (! empty($accountSummary)) {
                    $positionId = ($setter->position_id == 2) ? '3' : $setter->position_id;
                    $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                    $setter1Result = [
                        'user_id' => $setterId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $setter->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $setter_commission,
                    ];
                    $setterData = [
                        'setter1' => $setter1Result,
                    ];
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
                $commission_percentage = 0; // percenge
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
                $commission_percentage2 = 0; // percenge
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

            $closer_commission = ($closer1_commission + $closer2_commission);
            if (! empty($accountSummary)) {
                $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                $closer1Result = [
                    'user_id' => $closerId,
                    'user_name' => $user_name,
                    'image' => $user_image,
                    'position_id' => $closer->position_id,
                    'position_name' => @$positionData->position_name,
                    'amount_type' => 'm2',
                    'amount' => $closer1_commission,
                ];

                $positionId2 = ($closer2->position_id == 3) ? '2' : $closer2->position_id;
                $positionData2 = Positions::select('position_name')->where('id', '=', $positionId2)->first();
                $closer2Result = [
                    'user_id' => $closer2Id,
                    'user_name' => $user_name2,
                    'image' => $user_image2,
                    'position_id' => $closer2->position_id,
                    'position_name' => @$positionData2->position_name,
                    'amount_type' => 'm2',
                    'amount' => $closer2_commission,
                ];

                $closerData = [
                    'closer1' => $closer1Result,
                    'closer2' => $closer2Result,
                ];
            }
        } elseif ($closerId) {
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
                } else {
                    $commission_percentage = 0; // percenge
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
                        $x = isset($x) && ! empty($x) ? $x : 1;
                        $closer_commission = ($kw * $selfgen_percentage * $x);
                    } else {
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            if (! empty($accountSummary)) {
                $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                $closer1Result = [
                    'user_id' => $closerId,
                    'user_name' => $user_name,
                    'image' => $user_image,
                    'position_id' => $closer->position_id,
                    'position_name' => @$positionData->position_name,
                    'amount_type' => 'm2',
                    'amount' => $closer_commission,
                ];
                $closerData = [
                    'closer1' => $closer1Result,
                ];
            }
        }

        if ($position == 2) {
            $commissiondata['commission'] = $closer_commission;
        } else {
            $commissiondata['commission'] = $setter_commission;
        }

        if (! empty($accountSummary)) {
            return [
                'closer' => $closerData,
                'setter' => $setterData,
            ];
        } else {
            return $commissiondata;
        }
    }

    public function pestSalesAccountSummaryProjectionM2($checked)
    {
        if ($checked['date_cancelled']) {
            return 0;
        }
        $closer1 = $checked['closer1_id'];
        $closer2 = $checked['closer2_id'];
        $grossAmountValue = $checked['gross_account_value'];
        $approvedDate = $checked['customer_signoff'];
        $accountSummary = @$checked['from'];
        $setterData = [];
        $closerData = [];

        $companyMargin = CompanyProfile::where('id', 1)->first();
        // Calculate setter & closer commission
        $closerCommission = 0;
        if ($closer1 != null && $closer2 != null) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

            $closer2data = User::where('id', $closer2)->first();
            $user_name2 = $closer2data->first_name.' '.$closer2data->last_name;
            $user_image2 = $closer2data->image;

            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            $commissionPercentage2 = 0;
            $commission2History = UserCommissionHistory::where('user_id', $closer2)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                }
            } else {
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                }
            }

            $closer1Commission = ($closer1Commission);
            $closer2Commission = ($closer2Commission);
            $closerCommission = ($closer1Commission + $closer2Commission);
            if (! empty($accountSummary)) {
                $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                $closer1Result = [
                    'user_id' => $closer1,
                    'user_name' => $user_name,
                    'image' => $user_image,
                    'position_id' => $closer->position_id,
                    'position_name' => @$positionData->position_name,
                    'amount_type' => 'commission',
                    'amount' => $closer1Commission,
                ];

                $closer2Result = [
                    'user_id' => $closer2,
                    'user_name' => $user_name2,
                    'image' => $user_image2,
                    'position_id' => $closer2data->position_id,
                    'position_name' => 'Closer2',
                    'amount_type' => 'commission',
                    'amount' => $closer2Commission,
                ];

                $closerData = [
                    'closer1' => $closer1Result,
                    'closer2' => $closer2Result,
                ];
            }
        } elseif ($closer1) {
            $closer = User::where('id', $closer1)->first();
            $user_name = $closer->first_name.' '.$closer->last_name;
            $user_image = $closer->image;

            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
            } else {
                $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
            }

            $closerCommission = ($closerCommission);
            if (! empty($accountSummary)) {
                $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                $positionData = Positions::select('position_name')->where('id', '=', $positionId)->first();
                $closer1Result = [
                    'user_id' => $closer1,
                    'user_name' => $user_name,
                    'image' => $user_image,
                    'position_id' => $closer->position_id,
                    'position_name' => @$positionData->position_name,
                    'amount_type' => 'commission',
                    'amount' => $closerCommission,
                ];
                $closerData = [
                    'closer1' => $closer1Result,
                ];
            }
        }

        $commissiondata['commission'] = $closerCommission;
        if (! empty($accountSummary)) {
            return [
                'closer' => $closerData,
                'setter' => $setterData,
            ];
        } else {
            return $commissiondata;
        }
    }

    public function saleprojectionsummary($pid)
    {
        ProjectionUserOverrides::where('pid', $pid)->delete();
        $sales = SalesMaster::with('salesMasterProcess:sale_master_id,closer1_id,closer2_id,setter1_id,setter2_id')
            ->whereNotNull('customer_signoff')
            ->whereNull('m2_date')
            ->whereNull('date_cancelled')
            ->where('pid', $pid)
            ->orderBy('customer_signoff', 'ASC')
            ->get();
        $calculator = new CalculateOverrideProjections;
        if (! empty($sales)) {
            $companyProfile = CompanyProfile::first();
            foreach ($sales as $sale) {
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $calculator->pestCalculateoverride($sale);
                } else {
                    $calculator->calculateoverride($sale);
                }
            }
        }
    }

    public function subroutineEight($checked)
    {
        $companyProfile = CompanyProfile::where('id', 1)->first();
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
            $commission11 = $this->subroutineEightForSolar($checked);
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $commission11 = $this->subroutineEightForFlex($checked);
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            $commission11 = $this->subroutineEightForTurf($checked);
        }

        return $commission11;
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
        } else {
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
                $setter1_commission = (($netEpc - $redline['setter1_redline']) * $x * $kw * 1000 * $commission_percentage / 100) * 0.5;
            }

            if ($commission_type2 == 'per kw') {
                $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
            } else {
                $setter2_commission = (($netEpc - $redline['setter2_redline']) * $x * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
            }

            if (isset($checked['uid'])) {
                if ($setterId == $checked['uid']) {
                    $commissiondata['commission'] = $setter1_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter1_commission;

                    return $commissiondata;
                } else {
                    $commissiondata['commission'] = $setter2_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter2_commission;

                    return $commissiondata;
                }
            } elseif (! isset($checked['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                    $setter1Result = [
                        'user_id' => $setterId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $setter->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $setter1_commission,
                    ];

                    $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                    $setter2Result = [
                        'user_id' => $setter2Id,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $setter2->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $setter2_commission,
                    ];

                    return [
                        'setter1' => $setter1Result,
                        'setter2' => $setter2Result,
                    ];
                }
            }
        } elseif ($setterId) {
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
                    $commission_percentage = 0; // percenge
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
                    $setter_commission = (($netEpc - $redline['setter1_redline']) * $x * $kw * 1000 * $commission_percentage / 100);
                }

                if (isset($checked['uid']) && $setterId == $checked['uid']) {
                    $commissiondata['commission'] = $setter_commission;
                    $commissiondata['closer_commission'] = 0;
                    $commissiondata['setter_commission'] = $setter_commission;

                    return $commissiondata;
                } elseif (! isset($val['amount_data'])) {

                    // $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage/100);

                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = [
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter_commission,
                        ];

                        return [
                            'setter1' => $setter1Result,
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
                $commission_percentage = 0; // percenge
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
                $commission_percentage2 = 0; // percenge
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
                $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $x * $kw * 1000) - ($setter_commission / 2)) * 0.5;
            }

            if ($commission_type2 == 'per kw') {
                $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
            } else {
                $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $x * $kw * 1000) - ($setter_commission / 2)) * 0.5;
            }

            if (isset($checked['uid'])) {
                if ($closerId == $checked['uid']) {
                    $commissiondata['commission'] = $closer1_commission;
                    $commissiondata['closer_commission'] = $closer1_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                } else {
                    $commissiondata['commission'] = $closer2_commission;
                    $commissiondata['closer_commission'] = $closer2_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                }
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = [
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer1_commission,
                    ];

                    $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                    $closer2Result = [
                        'user_id' => $closer2Id,
                        'user_name' => $user_name2,
                        'image' => $user_image2,
                        'position_id' => $closer2->position_id,
                        'position_name' => @$positionData2->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer2_commission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                        'closer2' => $closer2Result,
                    ];
                }
            }
        } elseif ($closerId) {
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
                } else {
                    $commission_percentage = 0; // percenge
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
                        $x = isset($x) && ! empty($x) ? $x : 1;
                        $closer_commission = ($kw * $selfgen_percentage * $x);
                    } else {
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            if (isset($checked['uid']) && $closerId == $checked['uid']) {
                $commissiondata['commission'] = $closer_commission;
                $commissiondata['closer_commission'] = $closer_commission;
                $commissiondata['setter_commission'] = 0;

                return $commissiondata;
            } elseif (! isset($val['amount_data'])) {
                if (! empty($accountSummary)) {
                    $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                    $closer1Result = [
                        'user_id' => $closerId,
                        'user_name' => $user_name,
                        'image' => $user_image,
                        'position_id' => $closer->position_id,
                        'position_name' => @$positionData->position_name,
                        'amount_type' => 'm2',
                        'amount' => $closer_commission,
                    ];

                    return [
                        'closer1' => $closer1Result,
                    ];
                }
            }
        }

        $commissiondata['commission'] = $closer_commission + $setter_commission;
        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

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

                if (isset($checked['uid'])) {
                    if ($setterId == $checked['uid']) {
                        $commissiondata['commission'] = $setter1_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter1_commission;

                        return $commissiondata;
                    } else {
                        $commissiondata['commission'] = $setter2_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter2_commission;

                        return $commissiondata;
                    }
                } elseif (! isset($checked['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = [
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter1_commission,
                        ];

                        $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                        $setter2Result = [
                            'user_id' => $setter2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $setter2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter2_commission,
                        ];

                        return [
                            'setter1' => $setter1Result,
                            'setter2' => $setter2Result,
                        ];
                    }
                }
                $setter_commission = ($setter1_commission + $setter2_commission);
            } elseif ($setterId) {
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
                        $commission_percentage = 0; // percenge
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
                            $setter_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                        }
                    } else {
                        if ($commission_type == 'per kw') {
                            $setter_commission = ($kw * $commission_percentage);
                        } else {
                            $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);
                        }
                    }
                    if (isset($checked['uid']) && $setterId == $checked['uid']) {
                        $commissiondata['commission'] = $setter_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter_commission;

                        return $commissiondata;
                    } elseif (! isset($val['amount_data'])) {

                        // $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage/100);

                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                            $setter1Result = [
                                'user_id' => $setterId,
                                'user_name' => $user_name,
                                'image' => $user_image,
                                'position_id' => $setter->position_id,
                                'position_name' => @$positionData->position_name,
                                'amount_type' => 'm2',
                                'amount' => $setter_commission,
                            ];

                            return [
                                'setter1' => $setter1Result,
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
                    $commission_percentage = 0; // percenge
                    $commission_type = null;
                    // $positionId = $closer->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                $closer2 = User::where('id', $closer2Id)->first();
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
                    $commission_percentage2 = 0; // percenge
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

                if (isset($checked['uid'])) {
                    if ($closerId == $checked['uid']) {
                        $commissiondata['commission'] = $closer1_commission;
                        $commissiondata['closer_commission'] = $closer1_commission;
                        $commissiondata['setter_commission'] = 0;

                        return $commissiondata;
                    } else {
                        $commissiondata['commission'] = $closer2_commission;
                        $commissiondata['closer_commission'] = $closer2_commission;
                        $commissiondata['setter_commission'] = 0;

                        return $commissiondata;
                    }
                } elseif (! isset($val['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = [
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer1_commission,
                        ];

                        $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                        $closer2Result = [
                            'user_id' => $closer2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $closer2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer2_commission,
                        ];

                        return [
                            'closer1' => $closer1Result,
                            'closer2' => $closer2Result,
                        ];
                    }
                }
                $closer_commission = ($closer1_commission + $closer2_commission);
            } elseif ($closerId) {
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
                    } else {
                        $commission_percentage = 0; // percenge
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
                        $closer_commission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $closer_commission = ($kw * $commission_percentage);
                    } else {
                        $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);
                    }
                }

                // $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage/100);
                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                    $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                        $selfgen_percentage = $commissionSelfgen->commission;
                        if ($commissionSelfgen->commission_type == 'per kw') {
                            $x = isset($x) && ! empty($x) ? $x : 1;
                            $closer_commission = ($kw * $selfgen_percentage * $x);
                        } else {
                            $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                        }
                    }
                }

                if (isset($checked['uid']) && $closerId == $checked['uid']) {
                    $commissiondata['commission'] = $closer_commission;
                    $commissiondata['closer_commission'] = $closer_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                } elseif (! isset($val['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = [
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer_commission,
                        ];

                        return [
                            'closer1' => $closer1Result,
                        ];
                    }
                }
            }

            $commissiondata['commission'] = $closer_commission + $setter_commission;
            $commissiondata['closer_commission'] = $closer_commission;
            $commissiondata['setter_commission'] = $setter_commission;

            return $commissiondata;
        }
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

                if (isset($checked['uid'])) {
                    if ($setterId == $checked['uid']) {
                        $commissiondata['commission'] = $setter1_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter1_commission;

                        return $commissiondata;
                    } else {
                        $commissiondata['commission'] = $setter2_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter2_commission;

                        return $commissiondata;
                    }
                } elseif (! isset($checked['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                        $setter1Result = [
                            'user_id' => $setterId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $setter->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter1_commission,
                        ];

                        $positionData2 = Positions::select('position_name')->where('id', '=', $setter2->position_id)->first();
                        $setter2Result = [
                            'user_id' => $setter2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $setter2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $setter2_commission,
                        ];

                        return [
                            'setter1' => $setter1Result,
                            'setter2' => $setter2Result,
                        ];
                    }
                }
                $setter_commission = ($setter1_commission + $setter2_commission);
            } elseif ($setterId) {
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
                        $commission_percentage = 0; // percenge
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
                    if (isset($checked['uid']) && $setterId == $checked['uid']) {
                        $commissiondata['commission'] = $setter_commission;
                        $commissiondata['closer_commission'] = 0;
                        $commissiondata['setter_commission'] = $setter_commission;

                        return $commissiondata;
                    } elseif (! isset($val['amount_data'])) {

                        // $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage/100);

                        if (! empty($accountSummary)) {
                            $positionData = Positions::select('position_name')->where('id', '=', $setter->position_id)->first();
                            $setter1Result = [
                                'user_id' => $setterId,
                                'user_name' => $user_name,
                                'image' => $user_image,
                                'position_id' => $setter->position_id,
                                'position_name' => @$positionData->position_name,
                                'amount_type' => 'm2',
                                'amount' => $setter_commission,
                            ];

                            return [
                                'setter1' => $setter1Result,
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
                    $commission_percentage = 0; // percenge
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
                    $commission_percentage2 = 0; // percenge
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

                if (isset($checked['uid'])) {
                    if ($closerId == $checked['uid']) {
                        $commissiondata['commission'] = $closer1_commission;
                        $commissiondata['closer_commission'] = $closer1_commission;
                        $commissiondata['setter_commission'] = 0;

                        return $commissiondata;
                    } else {
                        $commissiondata['commission'] = $closer2_commission;
                        $commissiondata['closer_commission'] = $closer2_commission;
                        $commissiondata['setter_commission'] = 0;

                        return $commissiondata;
                    }
                } elseif (! isset($val['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = [
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer1_commission,
                        ];

                        $positionData2 = Positions::select('position_name')->where('id', '=', $closer2->position_id)->first();
                        $closer2Result = [
                            'user_id' => $closer2Id,
                            'user_name' => $user_name2,
                            'image' => $user_image2,
                            'position_id' => $closer2->position_id,
                            'position_name' => @$positionData2->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer2_commission,
                        ];

                        return [
                            'closer1' => $closer1Result,
                            'closer2' => $closer2Result,
                        ];
                    }
                }
            } elseif ($closerId) {
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
                    } else {
                        $commission_percentage = 0; // percenge
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
                            $x = isset($x) && ! empty($x) ? $x : 1;
                            $closer_commission = ($kw * $selfgen_percentage * $x);
                        } else {
                            $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                        }
                    }
                }

                if (isset($checked['uid']) && $closerId == $checked['uid']) {
                    $commissiondata['commission'] = $closer_commission;
                    $commissiondata['closer_commission'] = $closer_commission;
                    $commissiondata['setter_commission'] = 0;

                    return $commissiondata;
                } elseif (! isset($val['amount_data'])) {
                    if (! empty($accountSummary)) {
                        $positionData = Positions::select('position_name')->where('id', '=', $closer->position_id)->first();
                        $closer1Result = [
                            'user_id' => $closerId,
                            'user_name' => $user_name,
                            'image' => $user_image,
                            'position_id' => $closer->position_id,
                            'position_name' => @$positionData->position_name,
                            'amount_type' => 'm2',
                            'amount' => $closer_commission,
                        ];

                        return [
                            'closer1' => $closer1Result,
                        ];
                    }
                }
            }
        }

        $commissiondata['commission'] = $closer_commission + $setter_commission;
        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

        return $commissiondata;
    }

    // Getting Called From Command - projectedOverrideData:sync (Every 6 Hours)
    public function syncProjectedOverridesData($pid = '')
    {
        $sales = SalesMaster::select('pid')
            ->whereNotNull('customer_signoff')->whereNull('m2_date')->whereNull('date_cancelled')
            ->when(! empty($pid), function ($q) use ($pid) {
                $q->where('pid', $pid);
            })->orderBy('customer_signoff', 'ASC')->get();

        if (count($sales) == 0) {
            return ['success' => true, 'message' => 'Data Not Found!!'];
        }

        $errors = [];
        foreach ($sales as $sale) {
            try {
                $this->saleprojectionsummary($sale->pid);
            } catch (\Exception $e) {
                $errors[] = [
                    'pid' => $sale->pid,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
        }

        return ['success' => true, 'message' => 'Override Projection Command Success!!'];
    }

    // Getting Called From Command - projectionCommission:sync (Every 4 Hours)
    public function syncProjectedCommissionData($pid = '')
    {
        if ($pid) {
            ProjectionUserCommission::where('pid', $pid)->delete();
        } else {
            ProjectionUserCommission::truncate();
        }
        $sales = SalesMaster::with('salesMasterProcess')
            ->whereNotNull('customer_signoff')->whereNull('date_cancelled')->where(function ($q) {
                $q->whereNull('m1_Date')->orWhereNull('m2_date');
            })->when(! empty($pid), function ($q) use ($pid) {
                $q->where('pid', $pid);
            })->orderBy('customer_signoff', 'ASC')->get();

        if (count($sales) == 0) {
            return ['success' => true, 'message' => 'Data Not Found!!'];
        }

        $errors = [];
        $companyProfile = CompanyProfile::first();
        foreach ($sales as $sale) {
            try {
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
                $position = '';

                if (empty($sale->m1_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m1_projection = $this->pestSalesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $sale->date_cancelled,
                        ]);
                    } else {
                        $sales_m1_projection = $this->salesAccountSummaryProjectionM1([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $sale->date_cancelled,
                        ]);
                    }
                }

                if (empty($sale->m2_date)) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sales_m2_projection = $this->pestSalesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $sale->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    } else {
                        $sales_m2_projection = $this->salesAccountSummaryProjectionM2([
                            'closer1_id' => $closerId, 'closer2_id' => $closer2Id, 'setter1_id' => $setterId, 'setter2_id' => $setter2Id,
                            'm1_date' => $m1date, 'm2_date' => $m2date, 'customer_signoff' => $customer_signoff, 'kw' => $kw, 'pid' => $pid, 'net_epc' => $net_epc, 'location_code' => $location_code, 'customer_state' => $customer_state, 'position' => $position, 'from' => 'accountSummary', 'date_cancelled' => $sale->date_cancelled, 'gross_account_value' => $grossAmountValue,
                        ]);
                    }
                }

                $insert = [];
                if (empty($sale->m1_date) && empty($sale->m2_date)) {
                    if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                        if (@$sales_m2_projection['closer']) {
                            if (@$sales_m2_projection['closer']['closer1']) {
                                $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - (@$sales_m1_projection['closer']['closer1']['amount'] ? $sales_m1_projection['closer']['closer1']['amount'] : 0);
                            }
                            if (@$sales_m2_projection['closer']['closer2']) {
                                $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - (@$sales_m1_projection['closer']['closer2']['amount'] ? $sales_m1_projection['closer']['closer2']['amount'] : 0);
                            }
                        }
                        if (@$sales_m2_projection['setter']) {
                            if (@$sales_m2_projection['setter']['setter1']) {
                                $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - (@$sales_m1_projection['setter']['setter1']['amount'] ? $sales_m1_projection['setter']['setter1']['amount'] : 0);
                            }
                            if (@$sales_m2_projection['setter']['setter2']) {
                                $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - (@$sales_m1_projection['setter']['setter2']['amount'] ? $sales_m1_projection['setter']['setter2']['amount'] : 0);
                            }
                        }

                        if (isset($sales_m1_projection['setter']['setter1']['amount'])) {
                            $insert[] = [
                                'user_id' => $setterId,
                                'pid' => $pid,
                                'type' => 'M1',
                                'amount' => $sales_m1_projection['setter']['setter1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m1_projection['setter']['setter2']['amount'])) {
                            $insert[] = [
                                'user_id' => $setter2Id,
                                'pid' => $pid,
                                'type' => 'M1',
                                'amount' => $sales_m1_projection['setter']['setter2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m1_projection['closer']['closer1']['amount'])) {
                            $insert[] = [
                                'user_id' => $closerId,
                                'pid' => $pid,
                                'type' => 'M1',
                                'amount' => $sales_m1_projection['closer']['closer1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m1_projection['closer']['closer2']['amount'])) {
                            $insert[] = [
                                'user_id' => $closer2Id,
                                'pid' => $pid,
                                'type' => 'M1',
                                'amount' => $sales_m1_projection['closer']['closer2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['setter']['setter1']['amount'])) {
                            $insert[] = [
                                'user_id' => $setterId,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['setter']['setter1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['setter']['setter2']['amount'])) {
                            $insert[] = [
                                'user_id' => $setter2Id,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['setter']['setter2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['closer']['closer1']['amount'])) {
                            $insert[] = [
                                'user_id' => $closerId,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['closer']['closer1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['closer']['closer2']['amount'])) {
                            $insert[] = [
                                'user_id' => $closer2Id,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['closer']['closer2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                } elseif (empty($sale->m2_date)) {
                    if (isset($sales_m2_projection) && ! empty($sales_m2_projection)) {
                        if (@$sales_m2_projection['closer']) {
                            if (@$sales_m2_projection['closer']['closer1']) {
                                $closer1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['closer']['closer1']['amount'] = $sales_m2_projection['closer']['closer1']['amount'] - $closer1M1;
                            }
                            if (@$sales_m2_projection['closer']['closer2']) {
                                $closer2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['closer']['closer2']['amount'] = $sales_m2_projection['closer']['closer2']['amount'] - $closer2M1;
                            }
                        }

                        if (@$sales_m2_projection['setter']) {
                            if (@$sales_m2_projection['setter']['setter1']) {
                                $setter1M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['setter']['setter1']['amount'] = $sales_m2_projection['setter']['setter1']['amount'] - $setter1M1;
                            }
                            if (@$sales_m2_projection['setter']['setter2']) {
                                $setter2M1 = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'amount_type' => 'm1'])->sum('amount') ?? 0;
                                $sales_m2_projection['setter']['setter2']['amount'] = $sales_m2_projection['setter']['setter2']['amount'] - $setter2M1;
                            }
                        }

                        if (isset($sales_m2_projection['setter']['setter1']['amount'])) {
                            $insert[] = [
                                'user_id' => $setterId,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['setter']['setter1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['setter']['setter2']['amount'])) {
                            $insert[] = [
                                'user_id' => $setter2Id,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['setter']['setter2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['closer']['closer1']['amount'])) {
                            $insert[] = [
                                'user_id' => $closerId,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['closer']['closer1']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        if (isset($sales_m2_projection['closer']['closer2']['amount'])) {
                            $insert[] = [
                                'user_id' => $closer2Id,
                                'pid' => $pid,
                                'type' => 'M2',
                                'amount' => $sales_m2_projection['closer']['closer2']['amount'],
                                'customer_signoff' => $customer_signoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }

                ProjectionUserCommission::insert($insert);
            } catch (\Exception $e) {
                $errors[] = [
                    'pid' => $sale->pid,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }
        }

        return ['success' => true, 'message' => 'Commission Projection Command Success!!'];
    }
}
