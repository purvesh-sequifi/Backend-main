<?php

namespace App\Http\Controllers\API\Reports;

use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalApi\PayrollReportRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\CustomFieldHistory;
use App\Models\GetPayrollData;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\PayrollSsetup;
use App\Models\PositionCommission;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
use App\Models\W2PayrollTaxDeduction;
use App\Traits\PushNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReportsPayrollControllerV1 extends Controller
{
    use PayFrequencyTrait;
    use PushNotificationTrait;

    public function __construct(Request $request)
    {
        // $user = auth('api')->user();
    }

    public function commissionDetails(Request $request)
    {
        $compRate = 0;
        $companyProfile = CompanyProfile::first();

        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            /* $commissionPayrolls = UserCommissionLock::with('userdata.positionDetail', 'saledata')
            ->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'pid' => $request->pid, 'is_stop_payroll' => 0, 'status' => '3'])->get(); */
            $commissionPayrolls = UserCommissionLock::with('userdata.positionDetail', 'saledata')
                ->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'pid' => $request->pid, 'is_stop_payroll' => 0])->whereIn('status', [3, 6])->get();

            $data = [];
            $subtotal = 0;
            foreach ($commissionPayrolls as $commissionPayroll) {
                $adjustmentAmount = PayrollAdjustmentDetailLock::with('commented_by')->where(['pid' => $request->pid, 'user_id' => $commissionPayroll->user_id, 'pid' => $commissionPayroll->pid, 'payroll_type' => 'commission', 'type' => $commissionPayroll->schema_type, 'status' => '3'])->first();

                $s3_image = (isset($commissionPayroll->userdata->image) && $commissionPayroll->userdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$commissionPayroll->userdata->image) : null;

                $repRedline = null;
                if ($commissionPayroll->redline_type) {
                    if (in_array($commissionPayroll->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                        $repRedline = $commissionPayroll->redline.' Per Watt';
                    } else {
                        $repRedline = $commissionPayroll->redline.' '.ucwords($commissionPayroll->redline_type);
                    }
                }

                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $commissionPayroll->commission_type !== 'per sale') {
                    $salesData = SalesMaster::where('pid', $request->pid)->first();
                    $netEpc = isset($salesData->net_epc) ? $salesData->net_epc * 100 : 0;
                    $redline = isset($commissionPayroll->redline) ? $commissionPayroll->redline : 0;
                    // $compRate = $netEpc - $redline;
                    $compRate = isset($commissionPayroll->comp_rate) ? $commissionPayroll->comp_rate : 0;
                    if (empty($value->comp_rate)) {
                        $commission = PositionCommission::where(['position_id' => $commissionPayroll->position_id, 'product_id' => $commissionPayroll->product_id])->where('effective_date', '<=', $commissionPayroll->date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $commission) {
                            $commission = PositionCommission::where(['position_id' => $commissionPayroll->position_id, 'product_id' => $commissionPayroll->product_id])->whereNull('effective_date')->first();
                        }
                        if (! empty($commission->commission_limit)) {
                            $compRate = $commission->commission_limit;
                        }
                    }
                }

                $innerQuery = DB::table('sale_product_master')
                    ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                    ->where('pid', $commissionPayroll->pid)
                    ->groupBy('milestone_schema_id');

                // Outer query wrapping the inner one
                $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                    ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                    ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                    ->get();

                $data['current_payroll']['current'][] = [
                    'pid' => $commissionPayroll->pid,
                    'first_name' => $commissionPayroll->userdata->first_name,
                    'last_name' => $commissionPayroll->userdata->last_name,
                    'image_s3' => $s3_image,
                    'product' => $commissionPayroll?->saledata?->product_code,
                    'rep_redline' => $repRedline,
                    'comp_rate' => number_format($compRate, 4, '.', ''),
                    'amount' => isset($commissionPayroll->amount) ? $commissionPayroll->amount * 1 : null,
                    'amount_type' => isset($commissionPayroll->schema_name) ? $commissionPayroll->schema_name : null,
                    'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $commissionPayroll->is_mark_paid,
                    'position_name' => @$commissionPayroll->userdata->positionDetail->position_name,
                    'position_id' => @$commissionPayroll->userdata->position_id,
                    'sub_position_id' => @$commissionPayroll->userdata->sub_position_id,
                    'is_super_admin' => @$commissionPayroll->userdata->is_super_admin,
                    'gross_account_value' => isset($commissionPayroll->saledata->gross_account_value) ? $commissionPayroll->saledata->gross_account_value : null,
                    'is_manager' => @$commissionPayroll->userdata->is_manager,
                    'commission_amount' => @$commissionPayroll->commission_amount,
                    'commission_type' => @$commissionPayroll->commission_type,
                    'trigger_date' => @$result[0]->milestone_json,
                ];
                // $subtotal = ($subtotal + $commissionPayroll->amount);
                if ($commissionPayroll->is_move_to_recon != 1) {
                    $subtotal = ($subtotal + $commissionPayroll->amount);
                }
            }

            /* $clawbackSettlements = ClawbackSettlementLock::with('users.positionDetail', 'salesDetail')->where('type', '!=', 'overrides')
            ->where(['pid' => $request->pid, 'clawback_type' => 'next payroll', 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'is_stop_payroll' => 0, 'status' => '3'])->get(); */
            $clawbackSettlements = ClawbackSettlementLock::with('users.positionDetail', 'salesDetail')->where('type', '!=', 'overrides')
                ->where(['pid' => $request->pid, 'clawback_type' => 'next payroll', 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'is_stop_payroll' => 0])->whereIn('status', [3, 6])->get();

            if (count($clawbackSettlements) > 0) {
                foreach ($clawbackSettlements as $clawbackSettlement) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::with('commented_by')->where(['pid' => $request->pid, 'user_id' => $clawbackSettlement->user_id, 'payroll_type' => 'commission', 'type' => 'clawback', 'status' => '3'])->first();
                    $returnSalesDate = isset($clawbackSettlement->salesDetail->return_sales_date) ? $clawbackSettlement->salesDetail->return_sales_date : null;
                    $s3_image = (isset($clawbackSettlement->users->image) && $clawbackSettlement->users->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$clawbackSettlement->users->image) : null;

                    $repRedline = null;
                    if ($clawbackSettlement->redline_type) {
                        if (in_array($clawbackSettlement->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $repRedline = $clawbackSettlement->redline.' Per Watt';
                        } else {
                            $repRedline = $clawbackSettlement->redline.' '.ucwords($clawbackSettlement->redline_type);
                        }
                    }

                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $clawbackSettlement->clawback_cal_type !== 'per sale') {
                        $salesData = SalesMaster::where('pid', $request->pid)->first();
                        $netEpc = isset($salesData->net_epc) ? $salesData->net_epc * 100 : 0;
                        $redline = isset($clawbackSettlement->redline) ? $clawbackSettlement->redline : 0;
                        $compRate = $netEpc - $redline;
                    }
                    $innerQuery = DB::table('sale_product_master')
                        ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                        ->where('pid', $clawbackSettlement->pid)
                        ->groupBy('milestone_schema_id');

                    // Outer query wrapping the inner one
                    $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                        ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                        ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                        ->get();

                    $data['current_payroll']['current'][] = [
                        'pid' => $clawbackSettlement->pid,
                        'first_name' => $clawbackSettlement->users->first_name,
                        'last_name' => $clawbackSettlement->users->last_name,
                        'image_s3' => $s3_image,
                        'product' => $clawbackSettlement?->salesDetail?->product_code,
                        'rep_redline' => $repRedline,
                        'comp_rate' => 0, // round($compRate, 2),
                        'amount' => isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount * 1) : null,
                        'date' => isset($clawbackSettlement->salesDetail->date_cancelled) ? $clawbackSettlement->salesDetail->date_cancelled : $returnSalesDate,
                        'amount_type' => 'clawback',
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                        'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                        'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                        'is_mark_paid' => $clawbackSettlement->is_mark_paid,
                        'position_name' => @$clawbackSettlement->users->positionDetail->position_name,
                        'position_id' => @$clawbackSettlement->users->position_id,
                        'sub_position_id' => @$clawbackSettlement->users->sub_position_id,
                        'is_super_admin' => @$clawbackSettlement->users->is_super_admin,
                        'is_manager' => @$clawbackSettlement->users->is_manager,
                        'commission_amount' => @$clawbackSettlement->clawback_cal_amount,
                        'commission_type' => @$clawbackSettlement->clawback_cal_type,
                        'trigger_date' => @$result[0]->milestone_json,
                    ];
                    // $subtotal = ($subtotal - $clawbackSettlement->clawback_amount);
                    if ($clawbackSettlement->is_move_to_recon != 1) {
                        $subtotal = ($subtotal - $clawbackSettlement->clawback_amount);
                    }
                }
            }

            $data['subtotal'] = $subtotal;
            $commonData = SalesMaster::where('pid', $request->pid)->first();
            $data['common_data'] = [
                'location_code' => @$commonData->location_code,
                'kw' => @$commonData->kw,
                'net_epc' => @$commonData->net_epc,
            ];

            /**
             * Apply sorting if sort and sort_val parameters are provided and valid
             * This ensures the response always maintains a consistent structure
             */
            if ($request->has('sort') && $request->has('sort_val') &&
                ! empty($data['current_payroll']['current']) &&
                is_array($data['current_payroll']['current'])) {

                $sortKey = $request->input('sort');
                $sortDirection = strtolower($request->input('sort_val')) === 'desc' ? 'desc' : 'asc';

                // Convert the array to a collection for easier manipulation
                $collection = collect($data['current_payroll']['current']);

                // Custom sorting logic
                $sorted = $collection->sortBy(function ($item) use ($sortKey) {
                    // Special case for commission sorting (combines amount and type)
                    if ($sortKey === 'commission') {
                        $amount = $item['commission_amount'] ?? 0;
                        $type = $item['commission_type'] ?? '';

                        return [$amount, $type];
                    }
                    // Special case for full_name sorting (combines first_name and last_name)
                    elseif ($sortKey === 'full_name' || $sortKey === 'employee_name' || $sortKey === 'customer_name' || $sortKey === 'employee') {
                        $firstName = $item['first_name'] ?? '';
                        $lastName = $item['last_name'] ?? '';

                        return trim($firstName.' '.$lastName);
                    }

                    // Default sorting for other fields
                    // Handle potential missing or null values gracefully
                    return $item[$sortKey] ?? '';

                }, SORT_REGULAR, $sortDirection === 'desc');

                // Convert back to array with reindexed keys
                $data['current_payroll']['current'] = array_values($sorted->toArray());
            }

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => '3', // Paid
                'data' => $data,
            ]);
        } else {
            $data = [];
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required', // 15
                    'user_id' => 'required',
                    'pay_period_from' => 'required',
                    'pay_period_to' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $user_id = $request->user_id;
            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            $compRate = 0;
            $companyProfile = CompanyProfile::first();
            $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

            if (! empty($Payroll)) {
                $usercommission = UserCommissionLock::with('userdata.positionDetail', 'saledata')->whereIn('status', [3])->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
                // $clawbackSettlement = ClawbackSettlementLock::with('users.positionDetail', 'salesDetail')->where(['payroll_id'=>$Payroll->id,'user_id' =>  $Payroll->user_id,'status' =>  3, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
                $clawbackSettlement = ClawbackSettlementLock::with('users.positionDetail', 'salesDetail')->whereIn('status', [3, 6])->whereIn('type', ['commission', 'recon-commission'])->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();

                $subtotal = 0;
                if (count($usercommission) > 0) {
                    foreach ($usercommission as $key => $value) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->schema_type])->first();

                        $repRedline = null;
                        if ($value->redline_type) {
                            if (in_array($value->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                                $repRedline = $value->redline.' Per Watt';
                            } else {
                                $repRedline = $value->redline.' '.ucwords($value->redline_type);
                            }
                        }

                        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->commission_type !== 'per sale') {
                            $netEpc = isset($value->saledata->net_epc) ? $value->saledata->net_epc * 100 : 0;
                            $redline = isset($value->redline) ? $value->redline : 0;
                            // $compRate = $netEpc - $redline;
                            $compRate = isset($value->comp_rate) ? $value->comp_rate : 0;
                            if (empty($value->comp_rate)) {
                                $product_id = $value->product_id ? $value->product_id : 1;
                                $commission = PositionCommission::where(['position_id' => $value->position_id, 'product_id' => $product_id])->where('effective_date', '<=', $value->date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if (! $commission) {
                                    $commission = PositionCommission::where(['position_id' => $value->position_id, 'product_id' => $product_id])->whereNull('effective_date')->first();
                                }
                                if (! empty($commission->commission_limit) && $commission->commission_limit < $compRate) {
                                    $compRate = $commission->commission_limit;
                                }
                            }
                        }
                        $innerQuery = DB::table('sale_product_master')
                            ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                            ->where('pid', $value->pid)
                            ->groupBy('milestone_schema_id');

                        // Outer query wrapping the inner one
                        $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                            ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                            ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                            ->get();
                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->pid,
                            'position_id' => $value->position_id,
                            'product' => $value?->saledata?->product_code,
                            'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                            'customer_state' => isset($value->saledata->customer_state) ? strtoupper($value->saledata->customer_state) : null,
                            'rep_redline' => $repRedline,
                            'comp_rate' => number_format($compRate, 4, '.', ''),
                            'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                            'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                            'amount' => isset($value->amount) ? $value->amount : null,
                            'amount_type' => isset($value->schema_name) ? $value->schema_name : null,
                            'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                            'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'is_mark_paid' => $value->is_mark_paid,
                            'is_move_to_recon' => $value->is_move_to_recon,
                            'gross_account_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                            'position_name' => @$value->userdata->positionDetail->position_name,
                            'commission_amount' => $value->commission_amount,
                            'commission_type' => $value->commission_type,
                            'is_onetime_payment' => $value->is_onetime_payment,
                            'trigger_date' => @$result[0]->milestone_json,
                        ];
                        // Only include in subtotal if not moved to recon AND not a one-time payment
                        if ($value->is_move_to_recon != 1 && $value->is_onetime_payment != 1) {
                            $subtotal = ($subtotal + $value->amount);
                        }
                    }
                    $data['subtotal'] = $subtotal;
                }

                if (count($clawbackSettlement) > 0) {
                    foreach ($clawbackSettlement as $key1 => $val) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->first();
                        $repRedline = null;
                        if ($val->redline_type) {
                            if (in_array($val->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                                $repRedline = $val->redline.' Per Watt';
                            } else {
                                $repRedline = $val->redline.' '.ucwords($val->redline_type);
                            }
                        }

                        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                            $netEpc = isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc * 100 : 0;
                            $redline = isset($val->redline) ? $val->redline : 0;
                            $compRate = $netEpc - $redline;
                        }
                        $innerQuery = DB::table('sale_product_master')
                            ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                            ->where('pid', $val->pid)
                            ->groupBy('milestone_schema_id');

                        // Outer query wrapping the inner one
                        $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                            ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                            ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                            ->get();
                        $data['current_payroll']['current'][] = [
                            'id' => $val->id,
                            'pid' => $val->pid,
                            'product' => $val?->salesDetail?->product_code,
                            'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                            'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                            'rep_redline' => $repRedline,
                            'comp_rate' => 0, // round($compRate, 2),
                            'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                            'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                            'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                            'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                            'amount_type' => 'clawback',
                            'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                            'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'is_mark_paid' => $val->is_mark_paid,
                            'is_move_to_recon' => $val->is_move_to_recon,
                            'position_name' => @$val->users->positionDetail->position_name,
                            'clawback_cal_amount' => $val->clawback_cal_amount,
                            'clawback_cal_type' => $val->clawback_cal_type,
                            'is_onetime_payment' => $val->is_onetime_payment,
                            'trigger_date' => @$result[0]->milestone_json,
                        ];
                        // Only include in subtotal if not moved to recon AND not a one-time payment
                        if ($val->is_move_to_recon != 1 && $val->is_onetime_payment != 1) {
                            $subtotal = ($subtotal - $val->clawback_amount);
                        }
                    }
                    $data['subtotal'] = $subtotal;
                }

                // Apply sorting if sort and sort_val parameters are provided
                if ($request->has('sort') && $request->has('sort_val') && ! empty($data['current_payroll']['current'])) {
                    $sortKey = $request->input('sort');
                    $sortDirection = strtolower($request->input('sort_val')) === 'desc' ? 'desc' : 'asc';

                    $collection = collect($data['current_payroll']['current']);

                    // Custom sorting for commission (combining amount and type)
                    $sorted = $collection->sortBy(function ($item) use ($sortKey) {
                        if ($sortKey === 'commission') {
                            // For commission, sort by both amount and type
                            $amount = $item['commission_amount'] ?? 0;
                            $type = $item['commission_type'] ?? '';

                            return [$amount, $type];
                        }

                        return $item[$sortKey] ?? '';
                    }, SORT_REGULAR, $sortDirection === 'desc');

                    $data['current_payroll']['current'] = $sorted->values()->all();
                }

                return response()->json([
                    'ApiName' => 'commission_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $Payroll->status,
                    'data' => $data,
                ], 200);
            } else {

                return response()->json([
                    'ApiName' => 'commission_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ], 200);

            }
        }

    }

    public function commissionDetailsPest() {}

    // overrideDetails for post request
    public function overrideDetails(Request $request): JsonResponse
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $overridePayrolls = UserOverridesLock::with('userInfo', 'userdata', 'salesDetail')
                ->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'pid' => $request->pid, 'is_stop_payroll' => 0, 'overrides_settlement_type' => 'during_m2', 'status' => '3'])->get();

            $data = [];
            $subtotal = 0;
            foreach ($overridePayrolls as $overridePayroll) {
                $redLineType = $overridePayroll->calculated_redline_type;
                if (in_array($overridePayroll->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                    $redLineType = 'percent';
                }
                $adjustmentAmount = PayrollAdjustmentDetailLock::with('commented_by')
                    ->where(['user_id' => $overridePayroll->user_id, 'pid' => $overridePayroll->pid, 'payroll_type' => 'overrides', 'type' => $overridePayroll->type, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'status' => '3'])->first();
                $data['current_payroll']['current'][] = [
                    'pid' => $overridePayroll->pid,
                    'product' => $overridePayroll?->salesDetail?->product_code,
                    'first_name' => isset($overridePayroll->userInfo->first_name) ? $overridePayroll->userInfo->first_name : null,
                    'last_name' => isset($overridePayroll->userInfo->last_name) ? $overridePayroll->userInfo->last_name : null,
                    'position_id' => isset($overridePayroll->userInfo->position_id) ? $overridePayroll->userInfo->position_id : null,
                    'sub_position_id' => isset($overridePayroll->userInfo->sub_position_id) ? $overridePayroll->userInfo->sub_position_id : null,
                    'is_super_admin' => isset($overridePayroll->userInfo->is_super_admin) ? $overridePayroll->userInfo->is_super_admin : null,
                    'is_manager' => isset($overridePayroll->userInfo->is_manager) ? $overridePayroll->userInfo->is_manager : null,
                    'image' => isset($overridePayroll->userInfo->image) ? $overridePayroll->userInfo->image : null,
                    'user_first_name' => isset($overridePayroll->userdata->first_name) ? $overridePayroll->userdata->first_name : null,
                    'user_last_name' => isset($overridePayroll->userdata->last_name) ? $overridePayroll->userdata->last_name : null,
                    'user_image' => isset($overridePayroll->userdata->image) ? $overridePayroll->userdata->image : null,
                    'user_position_id' => @$overridePayroll->userdata->position_id,
                    'user_sub_position_id' => @$overridePayroll->userdata->sub_position_id,
                    'user_is_super_admin' => @$overridePayroll->userdata->is_super_admin,
                    'user_is_manager' => @$overridePayroll->userdata->is_manager,
                    'type' => isset($overridePayroll->type) ? $overridePayroll->type : null,
                    'total_amount' => isset($overridePayroll->amount) ? $overridePayroll->amount * 1 : 0,
                    'override_type' => $overridePayroll->overrides_type,
                    'override_amount' => isset($overridePayroll->overrides_amount) ? $overridePayroll->overrides_amount * 1 : 0,
                    'm2_date' => isset($overridePayroll->salesDetail->m2_date) ? $overridePayroll->salesDetail->m2_date : null,
                    'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => $adjustmentAmount?->commented_by?->first_name.' '.$adjustmentAmount?->commented_by?->last_name,
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $overridePayroll->is_mark_paid,
                    'is_move_to_recon' => $overridePayroll->is_move_to_recon,
                    'calculated_redline_type' => $redLineType,
                    'dismiss' => isset($overridePayroll->userInfo->id) && isUserDismisedOn($overridePayroll->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($overridePayroll->userInfo->id) && isUserTerminatedOn($overridePayroll->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($overridePayroll->userInfo->id) && isUserContractEnded($overridePayroll->userInfo->id) ? 1 : 0,
                ];
                // $subtotal = $subtotal + $overridePayroll->amount;
                if ($overridePayroll->is_move_to_recon != 1) {
                    $subtotal = $subtotal + $overridePayroll->amount;
                }
            }

            $clawbackSettlements = ClawbackSettlementLock::with(['salesDetail', 'saleUserInfo.state', 'users'])->where([
                'type' => 'overrides',
                'pid' => $request->pid,
                'clawback_type' => 'next payroll',
                'pay_period_from' => $request->pay_period_from,
                'pay_period_to' => $request->pay_period_to,
                'is_stop_payroll' => 0,
                'status' => '3',
            ])->get();

            foreach ($clawbackSettlements as $clawbackSettlement) {
                $adjustmentAmount = PayrollAdjustmentDetailLock::with('commented_by')
                    ->where(['user_id' => $clawbackSettlement->user_id, 'pid' => request()->input('pid'), 'payroll_type' => 'overrides', 'type' => 'clawback', 'pay_period_from' => request()->input('pay_period_from'), 'pay_period_to' => request()->input('pay_period_to'), 'status' => '3'])->first();
                $data['current_payroll']['current'][] = [
                    'pid' => $clawbackSettlement->pid,
                    'product' => $clawbackSettlement?->salesDetail?->product_code,
                    'first_name' => isset($clawbackSettlement->saleUserInfo->first_name) ? $clawbackSettlement->saleUserInfo->first_name : null,
                    'last_name' => isset($clawbackSettlement->saleUserInfo->last_name) ? $clawbackSettlement->saleUserInfo->last_name : null,
                    'position_id' => isset($clawbackSettlement->saleUserInfo->position_id) ? $clawbackSettlement->saleUserInfo->position_id : null,
                    'sub_position_id' => isset($clawbackSettlement->saleUserInfo->sub_position_id) ? $clawbackSettlement->saleUserInfo->sub_position_id : null,
                    'is_super_admin' => isset($clawbackSettlement->saleUserInfo->is_super_admin) ? $clawbackSettlement->saleUserInfo->is_super_admin : null,
                    'is_manager' => isset($clawbackSettlement->saleUserInfo->is_manager) ? $clawbackSettlement->saleUserInfo->is_manager : null,
                    'image' => isset($clawbackSettlement->saleUserInfo->image) ? $clawbackSettlement->saleUserInfo->image : null,
                    'user_first_name' => isset($clawbackSettlement->users->first_name) ? $clawbackSettlement->users->first_name : null,
                    'user_last_name' => isset($clawbackSettlement->users->last_name) ? $clawbackSettlement->users->last_name : null,
                    'user_image' => isset($clawbackSettlement->users->image) ? $clawbackSettlement->users->image : null,
                    'user_position_id' => @$clawbackSettlement->users->position_id,
                    'user_sub_position_id' => @$clawbackSettlement->users->sub_position_id,
                    'user_is_super_admin' => @$clawbackSettlement->users->is_super_admin,
                    'user_is_manager' => @$clawbackSettlement->users->is_manager,
                    'type' => 'clawback',
                    'total_amount' => isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount * 1) : null,
                    'override_type' => 'clawback',
                    'override_amount' => null,
                    'm2_date' => isset($clawbackSettlement->salesDetail->m2_date) ? $clawbackSettlement->salesDetail->m2_date : null,
                    'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => $adjustmentAmount?->commented_by?->first_name.' '.$adjustmentAmount?->commented_by?->last_name,
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $clawbackSettlement->is_mark_paid,
                    'is_move_to_recon' => $clawbackSettlement->is_move_to_recon,
                    'dismiss' => isset($clawbackSettlement->saleUserInfo->id) && isUserDismisedOn($clawbackSettlement->saleUserInfo->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($clawbackSettlement->saleUserInfo->id) && isUserTerminatedOn($clawbackSettlement->saleUserInfo->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($clawbackSettlement->saleUserInfo->id) && isUserContractEnded($clawbackSettlement->saleUserInfo->id) ? 1 : 0,
                ];
                // $subtotal = $subtotal - $clawbackSettlement->is_move_to_recon != 1 ? $clawbackSettlement->clawback_amount : 0;
                if ($clawbackSettlement->is_move_to_recon != 1) {
                    $subtotal = $subtotal - $clawbackSettlement->clawback_amount;
                }
            }

            $data['sub_total'] = round($subtotal, 2, PHP_ROUND_HALF_EVEN);
            $commonData = SalesMaster::where('pid', $request->pid)->first();
            $data['common_data'] = [
                'location_code' => @$commonData->location_code,
                'kw' => @$commonData->kw,
                'net_epc' => @$commonData->net_epc,
            ];

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 3, // Paid
                'data' => $data,
            ]);
        } else {
            $data = [];
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required', // 15
                    'user_id' => 'required',
                    'pay_period_from' => 'required',
                    'pay_period_to' => 'required',
                    // 'user_id'    => 'required', // 11
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $user_id = $request->user_id;
            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
            $sub_total = 0;
            if (! empty($Payroll)) {
                $userdata = UserOverridesLock::with('salesDetail')->whereIn('status', [3, 6])->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
                if (count($userdata) > 0) {
                    foreach ($userdata as $key => $value) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();
                        $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                        $sale = SalesMaster::where(['pid' => $value->pid])->first();
                        // $sub_total = ($sub_total + $value->is_move_to_recon? $value->amount : 0);
                        if ($value->is_move_to_recon != 1) {
                            $sub_total = ($sub_total + $value->amount);
                        }

                        $redLineType = $value->calculated_redline_type;
                        if (in_array($value->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $redLineType = 'percent';
                        }

                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->pid,
                            'product' => $value?->salesDetail?->product_code,
                            'override_over' => isset($user->first_name) ? $user->first_name.' '.$user->last_name : '',
                            'type' => isset($value->type) ? $value->type : null,
                            'accounts' => 1,
                            'kw_installed' => $value->kw,
                            'total_amount' => $value->amount,
                            'override_type' => $value->overrides_type,
                            'override_amount' => $value->overrides_amount,
                            'calculated_redline' => $value->calculated_redline,
                            'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                            'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'is_mark_paid' => $value->is_mark_paid,
                            'is_move_to_recon' => $value->is_move_to_recon,
                            'calculated_redline_type' => $redLineType,
                            'dismiss' => isset($user->id) && isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0,
                            'terminate' => isset($user->id) && isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isset($user->id) && isUserContractEnded($user->id) ? 1 : 0,
                        ];
                    }
                    $data['sub_total'] = round($sub_total, 2, PHP_ROUND_HALF_EVEN);
                }

                $clawbackSettlement = ClawbackSettlementLock::with('users.positionDetail', 'salesDetail')->whereIn('status', [3, 6])->whereIn('type', ['overrides', 'recon-override'])->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
                if (count($clawbackSettlement) > 0) {
                    foreach ($clawbackSettlement as $key => $value) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => 'clawback'])->first();

                        $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                        $sale = SalesMaster::where(['pid' => $value->pid])->first();
                        // $sub_total = ($sub_total + $value->is_move_to_recon? $value->amount : 0);
                        if ($value->is_move_to_recon != 1) {
                            $sub_total = ($sub_total - $value->clawback_amount);
                        }
                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->pid,
                            'product' => $value?->salesDetail?->product_code,
                            'override_over' => isset($user->first_name) ? $user->first_name.' '.$user->last_name : '',
                            'type' => 'clawback',
                            'accounts' => 1,
                            'kw_installed' => isset($sale->kw) ? $sale->kw : null,
                            'total_amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                            'override_type' => 'clawback',
                            'override_amount' => null,
                            'calculated_redline' => '',
                            'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                            'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                            'is_mark_paid' => $value->is_mark_paid,
                            'is_move_to_recon' => $value->is_move_to_recon,
                            'dismiss' => isset($user->id) && isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0,
                            'terminate' => isset($user->id) && isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isset($user->id) && isUserContractEnded($user->id) ? 1 : 0,
                        ];
                    }
                    $data['sub_total'] = round($sub_total, 2, PHP_ROUND_HALF_EVEN);
                }

                return response()->json([
                    'ApiName' => 'override_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $Payroll->status,
                    'data' => $data,
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'override_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ], 200);
            }
        }
    }

    public function adjustmentDetails(Request $request): JsonResponse
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $adjustmentPayrolls = PayrollAdjustmentDetailLock::with('commented_by', 'userDetail:id,first_name,last_name')->where(['pid' => $request->pid, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'status' => '3'])->get();
            if (count($adjustmentPayrolls) == 0) {
                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ]);
            }

            foreach ($adjustmentPayrolls as $adjustmentPayroll) {

                $commentBy = $adjustmentPayroll->commented_by;
                $userDetails = [
                    'id' => $adjustmentPayroll->userDetail?->id ?? null,
                    'first_name' => $adjustmentPayroll->userDetail?->first_name,
                    'last_name' => $adjustmentPayroll->userDetail?->last_name,
                    'dismiss' => isset($adjustmentPayroll->userDetail->id) && isUserDismisedOn($adjustmentPayroll->userDetail->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($adjustmentPayroll->userDetail->id) && isUserTerminatedOn($adjustmentPayroll->userDetail->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isset($adjustmentPayroll->userDetail->id) && isUserContractEnded($adjustmentPayroll->userDetail->id) ? 1 : 0,
                ];
                $data['current_payroll']['current'][] = [
                    'pid' => $adjustmentPayroll->pid,
                    'adjustment_by' => $commentBy?->first_name.' '.$commentBy?->last_name,
                    'user_details' => $userDetails,
                    'date' => isset($adjustmentPayroll->updated_at) ? date('Y-m-d', strtotime($adjustmentPayroll->updated_at)) : null,
                    'amount' => isset($adjustmentPayroll->amount) ? $adjustmentPayroll->amount : null,
                    'type' => $adjustmentPayroll->payroll_type,
                    'description' => $adjustmentPayroll->comment,
                    'is_mark_paid' => $adjustmentPayroll->is_mark_paid,
                    'dismiss' => isset($overridePayroll->userInfo->id) && isUserDismisedOn($overridePayroll->userInfo->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isset($overridePayroll->userInfo->id) && isUserTerminatedOn($overridePayroll->userInfo->id, date('Y-m-d')) ? 1 : 0,
                ];
            }

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => '3', // Paid
                'data' => $data,
            ]);
        } else {
            $data = [];
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required', // 15
                    'user_id' => 'required',
                    'pay_period_from' => 'required',
                    'pay_period_to' => 'required',
                    // 'user_id'    => 'required', // 11
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $user_id = $request->user_id;
            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

            $payroll_status = '';
            $sub_total = 0;

            if (! empty($payroll)) {
                $adjustment = ApprovalsAndRequestLock::with('user:id,first_name,last_name', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
                $adjustmentNegative = ApprovalsAndRequestLock::with('user:id,first_name,last_name', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

                if (count($adjustment) > 0) {
                    foreach ($adjustment as $value) {
                        $userDetails = [
                            'id' => $value->user?->id ?? null,
                            'first_name' => $value->user?->first_name,
                            'last_name' => $value->user?->last_name,
                            'dismiss' => isset($value->user->id) && isUserDismisedOn($value->user->id, date('Y-m-d')) ? 1 : 0,
                            'terminate' => isset($value->user->id) && isUserTerminatedOn($value->user->id, date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isset($value->user->id) && isUserContractEnded($value->user->id) ? 1 : 0,
                        ];
                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->req_no,
                            'adjustment_by' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name.' '.$value->approvedBy->last_name : null,
                            'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                            'user_details' => $userDetails,
                            'amount' => isset($value->amount) ? $value->amount : null,
                            'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                            'description' => isset($value->description) ? $value->description : null,
                            'is_mark_paid' => $value->is_mark_paid,
                        ];
                    }
                }

                if (count($adjustmentNegative) > 0) {
                    foreach ($adjustmentNegative as $value) {
                        $userDetails = [
                            'id' => $value->user?->id ?? null,
                            'first_name' => $value->user?->first_name,
                            'last_name' => $value->user?->last_name,
                            'dismiss' => isset($value->user->id) && isUserDismisedOn($value->user->id, date('Y-m-d')) ? 1 : 0,
                            'terminate' => isset($value->user->id) && isUserTerminatedOn($value->user->id, date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isset($value->user->id) && isUserContractEnded($value->user->id) ? 1 : 0,
                        ];
                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->req_no,
                            'user_details' => $userDetails,
                            'adjustment_by' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name.' '.$value->approvedBy->last_name : null,
                            'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                            'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                            'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                            'description' => isset($value->description) ? $value->description : null,
                            'is_mark_paid' => $value->is_mark_paid,
                        ];
                    }
                }
                $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::with('userDetail:id,first_name,last_name')->where('payroll_id', $payroll->id)->where(['user_id' => $payroll->user_id])->get();
                if (count($PayrollAdjustmentDetail) > 0) {
                    foreach ($PayrollAdjustmentDetail as $value) {
                        $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                        $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                        $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                        if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                            $is_mark_paid = 1;
                        } else {
                            $is_mark_paid = 0;
                        }

                        $commentBy = $value->commented_by;
                        $userDetails = [
                            'id' => $value->userDetail?->id ?? null,
                            'first_name' => $value->userDetail?->first_name,
                            'last_name' => $value->userDetail?->last_name,
                            'dismiss' => isset($value->userDetail->id) && isUserDismisedOn($value->userDetail->id, date('Y-m-d')) ? 1 : 0,
                            'terminate' => isset($value->userDetail->id) && isUserTerminatedOn($value->userDetail->id, date('Y-m-d')) ? 1 : 0,
                            'contract_ended' => isset($value->userDetail->id) && isUserContractEnded($value->userDetail->id) ? 1 : 0,
                        ];
                        $data['current_payroll']['current'][] = [
                            'id' => $value->id,
                            'pid' => $value->pid,
                            'adjustment_by' => $commentBy?->first_name.' '.$commentBy?->last_name,
                            'user_details' => $userDetails,
                            'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                            'amount' => isset($value->amount) ? $value->amount : null,
                            'type' => $value->payroll_type,
                            'description' => $value->comment,
                            'is_mark_paid' => $is_mark_paid,
                        ];
                    }
                }

                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $payroll->status,
                    'data' => $data,

                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ], 200);
            }
        }
    }

    public function reimbursementDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequestLock::with('approvedBy', 'costcenter')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'status' => 'Paid', 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();

            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    $data['current_payroll']['current'][] = [
                        'id' => $value->id,
                        'request_id' => $value->req_no,
                        'approvedBy' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name.' '.$value->approvedBy->last_name : null,
                        // 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        // 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'costhead' => isset($value->costcenter->name) ? $value->costcenter->name : null,
                        // 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function payroll_report(Request $request)
    {
        $workerType = '1099';
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $type = $request->input('type');
        $search = $request->input('search');

        if ($type == 'pid') {
            $commissionPayrolls = UserCommissionLock::with('saledata', 'workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $overridePayrolls = UserOverridesLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $clawbackPayrolls = ClawbackSettlementLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll['data_type'] = 'commission';
                $data[$commissionPayroll['pid']][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll['data_type'] = 'override';
                $data[$overridePayroll['pid']][] = $overridePayroll;
            }
            foreach ($clawbackPayrolls as $clawbackPayroll) {
                $clawbackPayroll['data_type'] = 'clawback';
                $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $commissionColor = 0;
                $overrideColor = 0;
                $adjustmentColor = 0;
                $payrollIds = [];
                $isMarkPaid = 0;
                $isNextPayroll = 0;
                $total = 0;
                foreach ($data as $inner) {
                    if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $commissionColor) {
                                $commissionColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                $commissionNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $commissionNoPaid += $inner['amount'];
                            }
                        }
                        $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $overrideColor) {
                                $overrideColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                $overrideNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $overrideNoPaid += $inner['amount'];
                            }
                        }
                        $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'adjustment') {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $adjustmentColor) {
                                $adjustmentColor = 1;
                            }
                        } else {
                            $adjustmentNoPaid += $inner['amount'];
                        }
                        $adjustment += $inner['amount'];
                    }

                    $payrollIds[] = $inner['payroll_id'];
                    $total += 1;
                    if ($inner['is_mark_paid'] >= 1) {
                        $isMarkPaid += 1;
                    }
                    if ($inner['is_next_payroll'] >= 1) {
                        $isNextPayroll += 1;
                    }
                }

                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    $finalData[] = [
                        'pid' => $key,
                        'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                        'commission' => round($commissionNoPaid, 2),
                        'override' => round($overrideNoPaid, 2),
                        'adjustment' => round($adjustmentNoPaid, 2),
                        'net_pay' => round($netPayAmount, 2),
                        'commission_yellow_status' => $commissionColor,
                        'override_yellow_status' => $overrideColor,
                        'adjustment_yellow_status' => $adjustmentColor,
                    ];
                }
            }

            // Apply sorting if sort and sort_val parameters are provided
            if ($request->has('sort') && $request->has('sort_val') && ! empty($finalData)) {
                $sortKey = $request->input('sort');
                $isDescending = strtolower($request->input('sort_val')) === 'desc';

                // Define which fields should be treated as numeric for sorting
                $numericFields = ['commission', 'override', 'adjustment', 'net_pay'];
                $isNumericField = in_array($sortKey, $numericFields);

                // Define name fields that should map to 'customer_name'
                $nameFields = ['customer_name', 'first_name', 'last_name', 'full_name', 'employee_name', 'employee'];
                $isNameField = in_array(strtolower($sortKey), array_map('strtolower', $nameFields));

                // For name fields, always sort by 'customer_name'
                $effectiveSortKey = $isNameField ? 'customer_name' : $sortKey;

                // Handle name fields (all mapped to 'customer_name')
                if ($isNameField) {
                    usort($finalData, function ($a, $b) use ($effectiveSortKey, $isDescending) {
                        $aValue = strtolower($a[$effectiveSortKey] ?? '');
                        $bValue = strtolower($b[$effectiveSortKey] ?? '');

                        if ($aValue === $bValue) {
                            return 0;
                        }
                        $result = $aValue <=> $bValue;

                        return $isDescending ? -$result : $result;
                    });
                }
                // Handle numeric fields
                elseif ($isNumericField) {
                    usort($finalData, function ($a, $b) use ($effectiveSortKey, $isDescending) {
                        $aValue = (float) ($a[$effectiveSortKey] ?? 0);
                        $bValue = (float) ($b[$effectiveSortKey] ?? 0);

                        if ($aValue === $bValue) {
                            return 0;
                        }
                        $result = $aValue <=> $bValue;

                        return $isDescending ? -$result : $result;
                    });
                }
                // Default sorting for other fields
                else {
                    $sortArray = array_column($finalData, $effectiveSortKey);
                    if (! empty($sortArray)) {
                        array_multisort(
                            $sortArray,
                            $isDescending ? SORT_DESC : SORT_ASC,
                            SORT_STRING,
                            $finalData
                        );
                    }
                }
            }

            $finalData = paginate($finalData, $perpage);

            return response()->json([
                'ApiName' => 'payroll_report_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $finalData,
            ]);
        } else {
            $payrollHistory = PayrollHistory::with('workertype', 'usersdata.office.State')->where('payroll_history.payroll_id', '!=', 0)
                ->selectRaw('payroll_history.*, payroll_history.created_at as get_date')
                ->with('usersdata.positionDetail', 'positionDetail', 'payroll')
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search, function ($q) {
                    $q->whereHas('usersdata', function ($q) {
                        $q->where('first_name', 'Like', '%'.request()->input('search').'%')->orwhere('last_name', 'Like', '%'.request()->input('search').'%');
                    });
                })->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->orderBy(
                    User::withoutGlobalScopes()->select('first_name')
                        ->whereColumn('id', 'payroll_history.user_id')
                        ->orderBy('first_name', 'asc')
                        ->limit(1),
                    'ASC'
                )->paginate($perpage);

            // Transform the collection first
            $transformedCollection = $payrollHistory->getCollection()->map(function ($data) {
                if (isset($data->usersdata->image) && $data->usersdata->image != null) {
                    $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
                } else {
                    $s3_request_url = null;
                }

                if ($data->everee_payment_status == 3) {
                    $everee_webhook_message = 'Payment Success';
                } elseif ($data->everee_payment_status == 2 && isset($data->everee_status) && $data->everee_status == 2 && ($data->everee_webhook_json == null || $data->everee_webhook_json == '')) {
                    // Differentiate between profile completion and self-onboarding completion
                    $user = $data->usersdata ?? null;
                    if (!$user || !$user->onboardProcess) {
                        // Self-onboarding completion - user hasn't completed Everee self-onboarding
                        $everee_webhook_message = 'Payment will be processed once the user has logged in and completed the self-onboarding steps, confirming all required details.';
                    } else {
                        // Default fallback message
                        $everee_webhook_message = 'Payment will be processed once the user profile is fully completed.';
                    }
                } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                    $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                    if (isset($everee_webhook_data['paymentStatus']) && $everee_webhook_data['paymentStatus'] == 'ERROR') {
                        $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'] ?? null;
                    } else {
                        $everee_webhook_message = $data->everee_webhook_json;
                    }
                } elseif ($data->everee_payment_status == 1) {
                    $everee_webhook_message = 'Waiting for payment status to be updated.';
                } elseif ($data->everee_payment_status == 0) {
                    $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                }

                $userIds = $data->user_id;
                $userCommissionPayrollIDs = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $commissionIds = array_merge($userCommissionPayrollIDs, $ClawbackSettlementPayRollIDS);
                $commission = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $commissionIds)->sum('commission');

                $overridePayrollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $override = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overridePayrollIDs)->sum('override');
                $reconciliation = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])/* ->whereIn('payroll_id', $userCommissionPayrollIDs) */ ->sum('reconciliation');

                // hourlysalary
                $hourlysalaryPayrollIDs = PayrollHourlySalaryLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $hourlysalary = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $hourlysalaryPayrollIDs)->sum('hourly_salary');
                // overtime
                $overtimePayrollIDs = PayrollOvertimeLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $overtime = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overtimePayrollIDs)->sum('overtime');

                $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

                $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::where('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();
                $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);
                $miscellaneous = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');
                $netPayIDS = array_merge($commissionIds, $overridePayrollIDs, $approvalsAndRequestPayrollIDs, $adjustmentIds, $hourlysalaryPayrollIDs, $overtimePayrollIDs);
                if (! empty($netPayIDS)) {
                    $net_pay = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $netPayIDS)->sum('net_pay');
                } else {
                    $net_pay = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->sum('net_pay');
                }
                $reimbursement = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $approvalsAndRequestPayrollIDs)->sum('reimbursement');

                $custom_filed = [];
                $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                $total_custom = 0;
                if ($setting) {
                    foreach ($setting as $value) {
                        $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $data->payroll_id])->first();
                        if ($payroll_data) {
                            $total_custom += $payroll_data->value;
                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => $value['field_name'],
                                'value' => $payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        } else {
                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => $value['field_name'],
                                'value' => 0,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }

                    }
                }

                $commission_count = UserCommissionLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $override_count = UserOverridesLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->whereIn('status', [3, 6])->where('is_mark_paid', '=', '1')->count('id');
                $commission_clawback_count = ClawbackSettlementLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->whereIn('type', ['recon-commission', 'commission'])->count('id');
                $override_clawback_count = ClawbackSettlementLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->whereIn('type', ['recon-override', 'overrides'])->count('id');
                $approve_request_count = ApprovalsAndRequestLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '=', '1')->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->count('id');
                $payroll_adjustment_details_count = PayrollAdjustmentDetailLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $reimbursement_count = ApprovalsAndRequestLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->whereIn('adjustment_type_id', [2])->count('id');
                $hourlysalary_count = PayrollHourlySalaryLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $overtime_count = PayrollHourlySalaryLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');

                $deductionCount = PayrollDeductionLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                /* recon yellow color color code */
                $recon_commission_count = UserCommissionLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3', 'is_move_to_recon' => 1]);
                $recon_override_count = UserOverridesLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3', 'is_move_to_recon' => 1])->count('id');
                $recon_deduction_count = PayrollDeductionLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3', 'is_move_to_recon' => 1])->count('id');
                $recon_commission_clawback_count = ClawbackSettlementLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 1])->whereIn('status', [3])->whereIn('type', ['recon-commission', 'commission'])->count('id');
                $recon_override_clawback_count = ClawbackSettlementLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 1])->whereIn('status', [3])->whereIn('type', ['recon-override', 'overrides'])->count('id');

                // Return transformed data
                return [
                    'id' => $data->usersdata->id,
                    'payroll_id' => $data->payroll_id ?? null,
                    'employee' => $data->usersdata->first_name.' '.$data->usersdata->last_name,
                    'employee_image' => $data->usersdata->image,
                    'employee_image_s3' => $s3_request_url,
                    'position' => isset($data->usersdata->positionDetail->position_name) ? $data->usersdata->positionDetail->position_name : null,
                    'position_id' => isset($data->usersdata->position_id) ? $data->usersdata->position_id : null,
                    'sub_position_id' => isset($data->usersdata->sub_position_id) ? $data->usersdata->sub_position_id : null,
                    'is_super_admin' => isset($data->usersdata->is_super_admin) ? $data->usersdata->is_super_admin : null,
                    'is_manager' => isset($data->usersdata->is_manager) ? $data->usersdata->is_manager : null,
                    'commission' => $commission,
                    'override' => $override,
                    'hourlysalary' => $hourlysalary,
                    'overtime' => $overtime,
                    'adjustment' => $miscellaneous,
                    'reimbursement' => $reimbursement,
                    'clawback' => $data->clawback ?? 0,
                    'deduction' => $data->deduction ?? 0,
                    'reconciliation' => $reconciliation,
                    'net_pay' => $net_pay,
                    'everee_payment_status' => $data->everee_payment_status,
                    'everee_webhook_json' => isset($everee_webhook_message) ? $everee_webhook_message : $data->everee_webhook_json,
                    'everee_response' => isset($everee_webhook_message) ? $everee_webhook_message : $data->everee_webhook_json,
                    'commission_yellow_status' => ($commission_count >= 1 || $commission_clawback_count >= 1 || $recon_commission_count->count('id') >= 1) || $recon_commission_clawback_count >= 1 ? 1 : 0,
                    'override_yellow_status' => ($override_count >= 1 || $recon_override_count >= 1 || $recon_override_clawback_count >= 1 || $override_clawback_count >= 1) ? 1 : 0,
                    'hourlysalary_yellow_status' => ($hourlysalary_count >= 1) ? 1 : 0,
                    'overtime_yellow_status' => ($overtime_count >= 1) ? 1 : 0,
                    'deduction_yellow_status' => ($recon_deduction_count >= 1 || $deductionCount >= 1) ? 1 : 0,
                    'approve_request_yellow_status' => ($approve_request_count >= 1 || $payroll_adjustment_details_count >= 1) ? 1 : 0,
                    'reimbursement_yellow_status' => ($reimbursement_count >= 1) ? 1 : 0,
                    'custom_filed' => $custom_filed,
                    'total_custom' => $total_custom,
                    'dismiss' => isUserDismisedOn($data->usersdata->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($data->usersdata->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($data->usersdata->id) ? 1 : 0,
                    'office_name' => $data?->usersdata?->office?->office_name, // Returns null if usersdata or office is null
                    'state_name' => $data?->usersdata?->office?->State?->name, // Returns null if usersdata, office, or State is null
                    'state_code' => $data?->usersdata?->office?->State?->state_code, // Returns null if usersdata, office, or State is null
                ];
            })->filter(); // This removes any null/empty items

            // Ensure we have a valid collection
            if (! $transformedCollection) {
                $transformedCollection = collect(); // Initialize empty collection if null
            }

            // If sort parameters exist, apply sorting
            if ($request->has('sort') && $request->has('sort_val') && $transformedCollection->isNotEmpty()) {
                $sortKey = strtolower($request->input('sort'));
                $sortDirection = strtolower($request->input('sort_val')) === 'desc' ? 'desc' : 'asc';

                // List of name fields that should map to 'employee'
                $nameFields = ['customer_name', 'first_name', 'last_name', 'full_name', 'employee', 'employee_name'];

                // If it's a name field, sort by 'employee' case-insensitively
                if (in_array($sortKey, array_map('strtolower', $nameFields))) {
                    $sortedCollection = $transformedCollection->sortBy(function ($item) {
                        return strtolower($item['employee'] ?? '');
                    }, SORT_STRING, $sortDirection === 'desc');
                }
                // For other fields, use regular sort
                else {
                    $sortedCollection = $transformedCollection->sortBy($sortKey, SORT_REGULAR, $sortDirection === 'desc');
                }

                $transformedCollection = $sortedCollection->values();
            }

            // Set the collection back to paginator
            $payrollHistory->setCollection($transformedCollection);

            return response()->json([
                'ApiName' => 'Payroll_Report_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $payrollHistory->toArray(),
            ]);
        }
    }

    public function custom_payroll_report(PayrollReportRequest $request)
    {
        try {

            $workerType = '1099';
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            $search = $request->input('search');
            $perpage = $request->input('perpage', 100);
            $page = $request->input('page', 1);

            $payrollHistory = PayrollHistory::select(
                'payroll_history.id', 'payroll_history.user_id', 'payroll_history.everee_payment_status',
                'payroll_history.everee_webhook_json', 'payroll_history.pay_period_from', 'payroll_history.pay_period_to',
                'payroll_history.payroll_id', 'payroll_history.clawback', 'payroll_history.deduction', 'payroll_history.commission',
                'payroll_history.override', 'payroll_history.net_pay', 'payroll_history.created_at as get_date', 'u.first_name'
            )
                ->join('users as u', 'u.id', '=', 'payroll_history.user_id')
                ->with([
                    'usersdata' => function ($q) {
                        $q->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_super_admin', 'is_manager'); // include 'id' for foreign key relation
                    },
                    'usersdata.positionDetail' => function ($q) {
                        $q->select('id', 'position_name');
                    },
                    'positionDetail' => function ($q) {
                        $q->select('id', 'position_name');
                    },
                ])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search, function ($q) use ($search) {
                    $q->whereHas('usersdata', function ($q) use ($search) {
                        $q->where('first_name', 'Like', '%'.$search.'%')
                            ->orwhere('last_name', 'Like', '%'.$search.'%');
                    });
                })
                ->where('payroll_history.payroll_id', '!=', 0)
                ->where(function ($q) use ($start_date, $end_date) {
                    $q->where('pay_period_from', '>=', $start_date)
                        ->where('pay_period_to', '<=', $end_date);
                })
                ->orderBy(
                    'u.first_name', 'asc'
                )
                ->whereIn('payroll_history.everee_payment_status', [0, 3])
                ->limit($perpage)
                ->offset(($page - 1) * $perpage)
                ->get();

            $customFields = PayrollSsetup::orderBy('id')->get();

            $payrollHistory->transform(function ($data) use ($customFields) {

                if (isset($data->usersdata->image) && $data->usersdata->image != null) {
                    $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
                } else {
                    $s3_request_url = null;
                }

                if ($data->everee_payment_status == 3) {
                    $everee_webhook_message = 'Payment Success';
                } elseif ($data->everee_payment_status == 0) {
                    $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                }

                $custom_filed = [];
                $total_custom = 0;

                $payrollId = data_get($data, 'payroll_id');

                foreach ($customFields as $field) {
                    $fieldValue = CustomFieldHistory::where('column_id', $field->id)
                        ->where('payroll_id', $payrollId)
                        ->value('value') ?? 0;

                    $total_custom += $fieldValue;

                    $custom_filed[] = [
                        'id' => $field->id,
                        'field_name' => $field->field_name,
                        'value' => $fieldValue,
                        'worker_type' => $field->worked_type,
                    ];
                }

                // Return transformed data
                return [
                    // 'id' => data_get($data, 'usersdata.id'),
                    // 'payroll_id' => data_get($data, 'payroll_id'),
                    'employee' => data_get($data, 'usersdata.first_name').' '.data_get($data, 'usersdata.last_name'),
                    'employee_image' => data_get($data, 'usersdata.image'),
                    'employee_image_s3' => $s3_request_url,
                    'position' => data_get($data, 'usersdata.positionDetail.position_name'),
                    'position_id' => data_get($data, 'usersdata.position_id'),
                    'sub_position_id' => data_get($data, 'usersdata.sub_position_id'),
                    'is_super_admin' => data_get($data, 'usersdata.is_super_admin'),
                    'is_manager' => data_get($data, 'usersdata.is_manager'),
                    'commission' => data_get($data, 'commission', 0),
                    'override' => data_get($data, 'override', 0),
                    'hourlysalary' => data_get($data, 'hourly_salary', 0),
                    'overtime' => data_get($data, 'overtime', 0),
                    'adjustment' => data_get($data, 'adjustment', 0),
                    'reimbursement' => data_get($data, 'reimbursement', 0),
                    'clawback' => data_get($data, 'clawback', 0),
                    'deduction' => data_get($data, 'deduction', 0),
                    'reconciliation' => data_get($data, 'reconciliation', 0),
                    'net_pay' => data_get($data, 'net_pay', 0),
                    'everee_payment_status' => data_get($data, 'everee_payment_status'),
                    'everee_webhook_json' => $everee_webhook_message ?? data_get($data, 'everee_webhook_json'),
                    'custom_filed' => $custom_filed,
                    'total_custom' => $total_custom,
                    'dismiss' => isUserDismisedOn($data->usersdata->id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($data->usersdata->id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($data->usersdata->id) ? 1 : 0,
                ];
            });

            return response()->json([
                'ApiName' => 'Payroll_Report_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $payrollHistory,
            ]);

        } catch (\Exception $e) {
            $traceCode = Str::random(10);
            Log::error('Payroll Report Error', [
                'traceCode' => $traceCode,
                'exceptionMessage' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'public/v2/payroll_report',
                'status' => false,
                'message' => 'Something went wrong.',
                'data' => [
                    'traceCode' => $traceCode,
                ],
            ]);
        }
    }

    public function payroll_report_employees(Request $request)
    {
        $workerType = 'w2';
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $type = $request->input('type');
        $search = $request->input('search');

        if ($type == 'pid') {

            $commissionPayrolls = UserCommissionLock::with('saledata', 'workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $overridePayrolls = UserOverridesLock::with('saledata', 'workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $clawbackPayrolls = ClawbackSettlementLock::with('saledata', 'workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with('saledata', 'workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'status' => '3'])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search && ! empty($search), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll['data_type'] = 'commission';
                $data[$commissionPayroll['pid']][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll['data_type'] = 'override';
                $data[$overridePayroll['pid']][] = $overridePayroll;
            }
            foreach ($clawbackPayrolls as $clawbackPayroll) {
                $clawbackPayroll['data_type'] = 'clawback';
                $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $commissionColor = 0;
                $overrideColor = 0;
                $adjustmentColor = 0;
                $payrollIds = [];
                $isMarkPaid = 0;
                $isNextPayroll = 0;
                $total = 0;
                foreach ($data as $inner) {
                    if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $commissionColor) {
                                $commissionColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                $commissionNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $commissionNoPaid += $inner['amount'];
                            }
                        }
                        $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $overrideColor) {
                                $overrideColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                $overrideNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $overrideNoPaid += $inner['amount'];
                            }
                        }
                        $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'adjustment') {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $adjustmentColor) {
                                $adjustmentColor = 1;
                            }
                        } else {
                            $adjustmentNoPaid += $inner['amount'];
                        }
                        $adjustment += $inner['amount'];
                    }

                    $payrollIds[] = $inner['payroll_id'];
                    $total += 1;
                    if ($inner['is_mark_paid'] >= 1) {
                        $isMarkPaid += 1;
                    }
                    if ($inner['is_next_payroll'] >= 1) {
                        $isNextPayroll += 1;
                    }
                }

                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    $finalData[] = [
                        'pid' => $key,
                        'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                        'commission' => round($commissionNoPaid, 2),
                        'override' => round($overrideNoPaid, 2),
                        'adjustment' => round($adjustmentNoPaid, 2),
                        'net_pay' => round($netPayAmount, 2),
                        'commission_yellow_status' => $commissionColor,
                        'override_yellow_status' => $overrideColor,
                        'adjustment_yellow_status' => $adjustmentColor,
                    ];
                }
            }

            // Apply sorting if sort and sort_val parameters are provided
            if ($request->has('sort') && $request->has('sort_val') && ! empty($finalData)) {
                $sortKey = $request->input('sort');
                $sortDirection = $request->input('sort_val');

                // Define name fields that should map to 'customer_name'
                $nameFields = ['customer_name', 'first_name', 'last_name', 'full_name', 'employee_name', 'employee'];

                // If the sort key is a name field, map it to 'customer_name'
                if (in_array(strtolower($sortKey), array_map('strtolower', $nameFields))) {
                    $sortKey = 'customer_name'; // Map to customer_name for sorting
                }

                \applyPayrollSorting($finalData, $sortKey, $sortDirection);
            }

            $finalData = paginate($finalData, $perpage);

            return response()->json([
                'ApiName' => 'payroll_report_employees',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $finalData,
            ]);
        } else {
            $payrollHistory = PayrollHistory::with('workertype')->where('payroll_history.payroll_id', '!=', 0)
                ->selectRaw('payroll_history.*, payroll_history.created_at as get_date')
                ->with('usersdata.positionDetail', 'positionDetail', 'payroll')
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($search, function ($q) {
                    $q->whereHas('usersdata', function ($q) {
                        $q->where('first_name', 'Like', '%'.request()->input('search').'%')->orwhere('last_name', 'Like', '%'.request()->input('search').'%');
                    });
                })->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->orderBy(
                    User::select('first_name')
                        ->whereColumn('id', 'payroll_history.user_id')
                        ->orderBy('first_name', 'asc')
                        ->limit(1),
                    'ASC'
                )->whereIn('payroll_history.everee_payment_status', [0, 3])->paginate($perpage);

            $payrollHistory->getCollection()->transform(function ($data) {
                if (isset($data->usersdata->image) && $data->usersdata->image != null) {
                    $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
                } else {
                    $s3_request_url = null;
                }

                if ($data->everee_payment_status == 3) {
                    $everee_webhook_message = 'Payment Success';
                } elseif ($data->everee_payment_status == 2 && isset($data->everee_status) && $data->everee_status == 2 && ($data->everee_webhook_json == null || $data->everee_webhook_json == '')) {
                    // Differentiate between profile completion and self-onboarding completion
                    $user = $data->usersdata ?? null;
                    if (!$user || !$user->onboardProcess) {
                        // Self-onboarding completion - user hasn't completed Everee self-onboarding
                        $everee_webhook_message = 'Payment will be processed once the user has logged in and completed the self-onboarding steps, confirming all required details.';
                    } else {
                        // Default fallback message
                        $everee_webhook_message = 'Payment will be processed once the user profile is fully completed.';
                    }
                } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                    $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                    if (isset($everee_webhook_data['paymentStatus']) && $everee_webhook_data['paymentStatus'] == 'ERROR') {
                        $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'] ?? null;
                    } else {
                        $everee_webhook_message = $data->everee_webhook_json;
                    }
                } elseif ($data->everee_payment_status == 1) {
                    $everee_webhook_message = 'Waiting for payment status to be updated.';
                } elseif ($data->everee_payment_status == 0) {
                    $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                }

                $userIds = $data->user_id;
                $userCommissionPayrollIDs = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $commissionIds = array_merge($userCommissionPayrollIDs, $ClawbackSettlementPayRollIDS);
                $commission = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $commissionIds)->sum('commission');

                $overridePayrollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $override = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overridePayrollIDs)->sum('override');
                $reconciliation = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $userCommissionPayrollIDs)->sum('reconciliation');

                // hourlysalary
                $hourlysalaryPayrollIDs = PayrollHourlySalaryLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $hourlysalary = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $hourlysalaryPayrollIDs)->sum('hourly_salary');

                // overtime
                $overtimePayrollIDs = PayrollOvertimeLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $overtime = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overtimePayrollIDs)->sum('overtime');

                $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

                $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::where('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();
                $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);
                $miscellaneous = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');
                $netPayIDS = array_merge($commissionIds, $overridePayrollIDs, $approvalsAndRequestPayrollIDs, $adjustmentIds, $hourlysalaryPayrollIDs, $overtimePayrollIDs);
                if (! empty($netPayIDS)) {
                    $net_pay = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $netPayIDS)->sum('net_pay');
                } else {
                    $net_pay = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->sum('net_pay');
                }
                $reimbursement = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $approvalsAndRequestPayrollIDs)->sum('reimbursement');

                $custom_filed = [];
                $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                $total_custom = 0;
                if ($setting) {
                    foreach ($setting as $value) {
                        $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $data->payroll_id])->first();
                        if ($payroll_data) {
                            $total_custom += $payroll_data->value;
                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => $value['field_name'],
                                'value' => $payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        } else {
                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => $value['field_name'],
                                'value' => 0,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }

                    }
                }

                $commission_count = UserCommissionLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $override_count = UserOverridesLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $clawback_count = ClawbackSettlementLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $approve_request_count = ApprovalsAndRequestLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '=', '1')->whereIn('adjustment_type_id', [1, 3, 4, 5, 6])->count('id');
                $payroll_adjustment_details_count = PayrollAdjustmentDetailLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $reimbursement_count = ApprovalsAndRequestLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->whereIn('adjustment_type_id', [2])->count('id');
                $hourlysalary_count = PayrollHourlySalaryLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');
                $overtime_count = PayrollHourlySalaryLock::where('user_id', $data->usersdata->id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '=', '1')->count('id');

                // added to show taxes on payroll as in everee and to match the net pay amount as everee

                $w2taxDetails = W2PayrollTaxDeduction::select(DB::raw('SUM(state_income_tax) as state_income_tax, SUM(federal_income_tax) as federal_income_tax, SUM(medicare_tax) as medicare_tax, SUM(social_security_tax) as social_security_tax, SUM(additional_medicare_tax) as additional_medicare_tax,(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes'))->where(['user_id' => $data->usersdata->id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->first();

                // Done this change to show all tax details in popup inside payroll report.
                $taxNames = [
                    'state_income_tax' => 'State Income Tax',
                    'federal_income_tax' => 'Federal Income Tax',
                    'medicare_tax' => 'Medicare Tax',
                    'social_security_tax' => 'Social Security Tax',
                    'additional_medicare_tax' => 'Additional Medicare Tax',
                ];

                $taxDetails = [];
                if ($w2taxDetails) {
                    foreach ($taxNames as $column => $name) {
                        if (isset($w2taxDetails->$column)) {
                            $taxDetails[] = [
                                'name' => $name,
                                'amount' => $w2taxDetails->$column,
                            ];
                        }
                    }
                }

                if (! empty($w2taxDetails->total_taxes)) {
                    $net_pay = $net_pay - $w2taxDetails->total_taxes;
                }

                // Return transformed data
                return [
                    'id' => $data->usersdata->id,
                    'payroll_id' => $data->payroll_id ?? null,
                    'employee' => $data->usersdata->first_name.' '.$data->usersdata->last_name,
                    'employee_image' => $data->usersdata->image,
                    'employee_image_s3' => $s3_request_url,
                    'position' => isset($data->usersdata->positionDetail->position_name) ? $data->usersdata->positionDetail->position_name : null,
                    'position_id' => isset($data->usersdata->position_id) ? $data->usersdata->position_id : null,
                    'sub_position_id' => isset($data->usersdata->sub_position_id) ? $data->usersdata->sub_position_id : null,
                    'is_super_admin' => isset($data->usersdata->is_super_admin) ? $data->usersdata->is_super_admin : null,
                    'is_manager' => isset($data->usersdata->is_manager) ? $data->usersdata->is_manager : null,
                    'commission' => $commission,
                    'override' => $override,
                    'hourlysalary' => $hourlysalary,
                    'overtime' => $overtime,
                    'adjustment' => $miscellaneous,
                    'reimbursement' => $reimbursement,
                    'clawback' => $data->clawback ?? 0,
                    'deduction' => $data->deduction ?? 0,
                    'reconciliation' => $reconciliation,
                    'net_pay' => $net_pay,
                    'taxes' => $w2taxDetails->total_taxes ?? 0,
                    'tax_details' => $taxDetails,
                    'everee_payment_status' => $data->everee_payment_status,
                    'everee_webhook_json' => isset($everee_webhook_message) ? $everee_webhook_message : $data->everee_webhook_json,
                    'commission_yellow_status' => ($commission_count >= 1 || $clawback_count >= 1) ? 1 : 0,
                    'override_yellow_status' => ($override_count >= 1) ? 1 : 0,
                    'hourlysalary_yellow_status' => ($hourlysalary_count >= 1) ? 1 : 0,
                    'overtime_yellow_status' => ($overtime_count >= 1) ? 1 : 0,
                    'approve_request_yellow_status' => ($approve_request_count >= 1 || $payroll_adjustment_details_count >= 1) ? 1 : 0,
                    'reimbursement_yellow_status' => ($reimbursement_count >= 1) ? 1 : 0,
                    'custom_filed' => $custom_filed,
                    'total_custom' => $total_custom,
                ];
            });

            // Apply sorting if sort and sort_val parameters are provided and collection is not empty
            if ($request->has('sort') && $request->has('sort_val')) {
                $sortKey = $request->input('sort');
                $sortDirection = $request->input('sort_val');

                // Define name fields that should map to 'employee'
                $nameFields = ['customer_name', 'first_name', 'last_name', 'full_name', 'employee_name', 'employee'];

                // Get the transformed collection
                $transformedCollection = $payrollHistory->getCollection();

                // Only sort if the collection is not empty
                if ($transformedCollection->isNotEmpty()) {
                    // Check if the sort key is in our name fields
                    if (in_array(strtolower($sortKey), array_map('strtolower', $nameFields))) {
                        // Sort by 'employee' field for name-related fields
                        $sortedCollection = $transformedCollection->sortBy('employee', SORT_STRING, strtolower($sortDirection) === 'desc');
                    } else {
                        // Regular sort for other fields
                        $sortedCollection = $transformedCollection->sortBy(
                            $sortKey,
                            SORT_REGULAR,
                            strtolower($sortDirection) === 'desc'
                        );
                    }

                    // Set the sorted collection back to the paginator
                    $payrollHistory->setCollection($sortedCollection->values());
                }
            }

            return response()->json([
                'ApiName' => 'payroll_report_employees',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $payrollHistory,
            ]);
        }
    }

    public function additionalValueDetails(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required',
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;
        $custom_field_id = $request->custom_field_id ?? null;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {
            if ($custom_field_id != null && $custom_field_id != 0) {
                $customeFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->where('column_id', $custom_field_id)->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            } else {
                $customeFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            }
            $customeFields->transform(function ($customeFields) {
                $date = $customeFields->updated_at != null ? \Carbon\Carbon::parse($customeFields->updated_at)->format('m/d/Y') : \Carbon\Carbon::parse($customeFields->created_at)->format('m/d/Y');

                $approved_by_detail = [];
                if ($customeFields->getApprovedBy != null) {
                    if (isset($customeFields->getApprovedBy->image) && $customeFields->getApprovedBy->image != null) {
                        $image = s3_getTempUrl(config('app.domain_name').'/'.$customeFields->getApprovedBy->image);
                    } else {
                        $image = null;
                    }
                    $approved_by_detail = [
                        'first_name' => $customeFields->getApprovedBy->first_name,
                        'middle_name' => $customeFields->getApprovedBy->middle_name,
                        'last_name' => $customeFields->getApprovedBy->last_name,
                        'image' => $image,
                        'dismiss' => isset($customeFields->getApprovedBy->id) && isUserDismisedOn($customeFields->getApprovedBy->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($customeFields->getApprovedBy->id) && isUserTerminatedOn($customeFields->getApprovedBy->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($customeFields->getApprovedBy->id) && isUserContractEnded($customeFields->getApprovedBy->id) ? 1 : 0,
                    ];
                }

                return [
                    'id' => $customeFields->id,
                    'custom_field_id' => $customeFields->column_id,
                    'amount' => isset($customeFields->value) ? ($customeFields->value) : 0,
                    'type' => $customeFields->getColumn->field_name ?? '',
                    'date' => $date,
                    'comment' => $customeFields->comment,
                    'adjustment_by' => $customeFields->approved_by,
                    'adjustment_by_detail' => $approved_by_detail,
                ];
            });

            return response()->json([
                'ApiName' => 'additionalValueDetails',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $customeFields,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'additionalValueDetails',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function hourlySalaryDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;
        $companyProfile = CompanyProfile::first();
        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (! empty($Payroll)) {
            $userdata = PayrollHourlySalaryLock::where('status', 3)->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            if (count($userdata) > 0) {
                $sub_total = 0;
                $total = 0;
                $salary = 0;
                $adjustment = 0;
                $totalHours = 0;
                $totalMinutes = 0;
                $totalSeconds = 0;
                foreach ($userdata as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'hourlysalary', 'type' => $value->type])->first();
                    $sub_total += $value->amount;
                    $date = isset($value->date) ? $value->date : '';
                    $data['current_payroll']['current'][] = [
                        'id' => $value->id,
                        'date' => isset($date) ? $date : null,
                        'hourly_rate' => isset($value->hourly_rate) ? $value->hourly_rate * 1 : null,
                        'salary' => isset($value->salary) ? $value->salary * 1 : null,
                        'regular_hour' => isset($value->regular_hours) ? $value->regular_hours : null,
                        'total' => isset($value->total) ? $value->total * 1 : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'accounts' => 1,
                        'hourlysalary_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                    $adjustmentAmount = isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0;
                    $salary1 = isset($value->salary) ? $value->salary * 1 : 0;
                    $total1 = isset($value->total) ? $value->total * 1 : 0;
                    $regular_hours1 = isset($value->regular_hours) ? $value->regular_hours : '00:00';

                    if (! empty($value->regular_hours)) {
                        $timeA = Carbon::createFromFormat('H:i', $value->regular_hours);
                        $secondsA = $timeA->hour * 3600 + $timeA->minute * 60;
                        $totalSeconds = $totalSeconds + $secondsA;
                    }

                    $salary += $salary1;
                    $adjustment += $adjustmentAmount;
                    $total += $total1;
                }
                $totalHours += intdiv($totalMinutes, 60);
                $totalRemainingMinutes = $totalMinutes % 60;

                // Format the result ensuring it's within a 24-hour range
                $totalHours = ($totalSeconds > 0) ? floor($totalSeconds / 3600) : 00;
                $totalMinutes = ($totalSeconds > 0) ? floor($totalSeconds % 3600 / 60) : 00;

                $subtotal['regular_hours'] = sprintf('%02d:%02d', $totalHours, $totalMinutes);
                $subtotal['salary'] = $salary;
                $subtotal['adjustment'] = $adjustment;
                $subtotal['total'] = $total;
                $data['sub_total'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'hourly_salary_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'hourly_salary_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function overtimeDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $companyProfile = CompanyProfile::first();
        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        $sub_total = 0;
        if (! empty($Payroll)) {
            $userdata = PayrollOvertimeLock::where('status', 3)->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            if (count($userdata) > 0) {
                $overtime = 0;
                $adjustmentAmounttotal = 0;
                $total = 0;
                foreach ($userdata as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();
                    $date = isset($value->date) ? $value->date : '';
                    $data['current_payroll']['current'][] = [
                        'id' => $value->id,
                        'date' => isset($date) ? $date : null,
                        'overtime_rate' => isset($value->overtime_rate) ? $value->overtime_rate * 1 : null,
                        'overtime' => isset($value->overtime) ? $value->overtime * 1 : null,
                        'total' => isset($value->total) ? $value->total * 1 : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'accounts' => 1,
                        'overtime_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                    $adjustmentAmount1 = isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0;
                    $overtime1 = isset($value->overtime) ? $value->overtime * 1 : 0;
                    $total1 = isset($value->total) ? $value->total * 1 : 0;
                    $overtime += $overtime1;
                    $adjustmentAmounttotal += $adjustmentAmount1;
                    $total += $total1;
                }
                $subtotal['overtime'] = $overtime;
                $subtotal['adjustment'] = $adjustmentAmounttotal;
                $subtotal['total'] = $total;
                $data['sub_total'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'overtime_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'overtime_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }
}
