<?php

namespace App\Http\Controllers\API\Sales;

use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CustomField;
use App\Models\DailyPayFrequency;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollCommon;
use App\Models\PayrollDeductions;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertime;
use App\Models\PositionPayFrequency;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\WeeklyPayFrequency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesPendingPayController extends Controller
{
    use PayFrequencyTrait;

    public function __construct(Request $request) {}

    /**
     * Display a listing of the resource.
     */
    public function getPendingPay(Request $request): JsonResponse
    {
        $data = [];
        $userId = isset($request->user_id) ? $request->user_id : null;
        if ($userId) {
            $user_id = $userId;
        } else {
            $user_id = auth()->user()->id;
        }

        $commission = 0;
        $override = 0;
        $adjustment = 0;
        $reimbursement = 0;
        $deduction = 0;
        $reconciliation = 0;
        $custom_payment = 0;
        $wages = 0;
        $anticipated_pay = 0;

        $user = User::find($user_id);
        // $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => auth()->user()->sub_position_id])->first();
        $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
        if (! $positionPayFrequency) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => 'Position not set for this user!!',
                'data' => [],
            ], 400);
        }
        if ($positionPayFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
            $class = WeeklyPayFrequency::class;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
            $class = MonthlyPayFrequency::class;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
            $class = DailyPayFrequency::class;
        }

        if (! isset($class)) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => "User's position have no pay frequency!!",
                'data' => [],
            ], 400);
        }

        $payFrequency = $class::query();
        if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID || $positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
            $payFrequency = $payFrequency->where(['type' => $type, 'closed_status' => '0'])->orderBy('pay_period_from')->first();
        } elseif ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
            $payFrequency = $this->daily_pay_period($class);
        } else {
            $payFrequency = $payFrequency->where('closed_status', '0')->orderBy('pay_period_from')->first();
        }
        $start_date = $payFrequency->pay_period_from ?? null;
        $end_date = $payFrequency->pay_period_to ?? null;

        $payRoll = PayRoll::where(['user_id' => $user_id, 'finalize_status' => 0, 'status' => 1])->orderBy('pay_period_to', 'asc')->first();
        if ($payRoll) {
            $data_query = Payroll::selectRaw('sum(commission) as commission, sum(override) as override, sum(adjustment) as adjustment, sum(reimbursement) as reimbursement, sum(deduction) as deduction,
             sum(reconciliation) as reconciliation, sum(custom_payment) as custom_payment, sum(hourly_salary) as hourly_salary, sum(overtime) as overtime, id, user_id')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $user_id])->first();

            if ($positionPayFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                $adjustment_total = PayrollAdjustmentDetail::where(['payroll_id' => $data_query->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->sum('amount');

                $usercommissions = UserCommission::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                $clawbackSum = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'clawback_type' => 'next payroll', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', '!=', 'overrides')->sum('clawback_amount');

                $overrides = UserOverrides::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'overrides_settlement_type' => 'during_m2', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                $clawbackSumChange = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'clawback_type' => 'next payroll', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', 'overrides')->sum('clawback_amount');

                $reimbursement = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'adjustment_type_id' => '2', 'is_next_payroll' => 0, 'is_mark_paid' => 0])->sum('amount');

                $adjustmentToAdd = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');

                $adjustmentToNigative = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->whereIn('adjustment_type_id', [5])->sum('amount');

                $customfieldSum = CustomField::where(['payroll_id' => $data_query->id, 'is_next_payroll' => 0, 'user_id' => $user_id, 'is_mark_paid' => 0])->sum('value');

                $hourlySalarySum = PayrollHourlySalary::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');

                $overtimeSum = PayrollOvertime::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data_query->id, 'user_id' => $user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');

                /* recon amount total display */
                $reconAdjustmentSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'payroll_id' => $data_query->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('adjustments');
                $reconDeductionSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'payroll_id' => $data_query->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('deductions');
                $reconClawbackSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'payroll_id' => $data_query->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('clawback');
                $reconCommissionSum = ReconCommissionHistory::where(['user_id' => $user_id, 'payroll_id' => $data_query->id, 'is_ineligible' => '0'])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('paid_amount');
                $reconOverrideSum = ReconOverrideHistory::where(['user_id' => $user_id, 'payroll_id' => $data_query->id, 'is_ineligible' => '0'])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('paid');
                $reconFinalizeSum = $reconCommissionSum + $reconOverrideSum + $reconAdjustmentSum + $reconDeductionSum - $reconClawbackSum;
            } else {
                $adjustment_total = PayrollAdjustmentDetail::where(['payroll_id' => $data_query->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->sum('amount');

                $usercommissions = UserCommission::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                $clawbackSum = ClawbackSettlement::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'clawback_type' => 'next payroll', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', '!=', 'overrides')->sum('clawback_amount');

                $overrides = UserOverrides::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'overrides_settlement_type' => 'during_m2', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                $clawbackSumChange = ClawbackSettlement::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'clawback_type' => 'next payroll', 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', 'overrides')->sum('clawback_amount');

                $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'adjustment_type_id' => '2'])->sum('amount');

                $adjustmentToAdd = ApprovalsAndRequest::where('status', 'Accept')->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');

                $adjustmentToNigative = ApprovalsAndRequest::where('status', 'Accept')->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->whereIn('adjustment_type_id', [5])->sum('amount');

                // $customfieldSum = CustomField::where(['payroll_id' => $data_query->id, 'user_id' =>  $user_id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])->sum('value');

                $hourlySalarySum = PayrollHourlySalary::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('total');

                $overtimeSum = PayrollOvertime::whereIn('status', [1, 6])->where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('total');

                /* recon amount total display */
                $reconAdjustmentSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->sum('adjustments');
                $reconDeductionSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->sum('deductions');
                $reconClawbackSum = ReconciliationFinalizeHistory::where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->sum('clawback');
                $reconCommissionSum = ReconCommissionHistory::where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_ineligible' => '0'])->sum('paid_amount');
                $reconOverrideSum = ReconOverrideHistory::where(['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_ineligible' => '0'])->sum('paid');
                $reconFinalizeSum = $reconCommissionSum + $reconOverrideSum + $reconAdjustmentSum - $reconDeductionSum - $reconClawbackSum;
            }

            $commissionTotal = ($usercommissions - $clawbackSum);
            $overridesTotal = ($overrides - $clawbackSumChange);
            $adjustment = ($adjustmentToAdd - $adjustmentToNigative) + ($adjustment_total);

            // Sum the payroll components
            $commission = $commissionTotal ?? 0;
            $override = $overridesTotal ?? 0;
            $adjustment = $adjustment ?? 0;
            $reimbursement = $reimbursement ?? 0;
            $reconciliation = $reconFinalizeSum ?? 0;
            $deduction = $data_query->deduction ?? 0;
            $custom_payment = $data_query->custom_payment ?? 0;
            $hourlySalary = $hourlySalarySum ?? 0;
            $overtime = $overtimeSum ?? 0;
            $wages = ($hourlySalary + $overtime);

            // Calculate anticipated pay correctly
            $anticipated_pay = $commission + $override + $adjustment + $reimbursement + $reconciliation + $custom_payment + $wages - $deduction;
        }

        // Prepare the data for the response
        $data = [
            'commission' => $commission,
            'override' => $override,
            'adjustment' => $adjustment,
            'reimbursement' => $reimbursement,
            'deduction' => $deduction,
            'reconciliation' => $reconciliation,
            'custom_field' => $custom_payment,
            'wages' => $wages,
            'anticipated_pay' => $anticipated_pay,
        ];

        return response()->json([
            'ApiName' => 'get_payroll_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'from_date' => $start_date,
            'to_date' => $end_date,
        ]);
    }

    public function commissionDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $userCommission = UserCommission::with([
            'saledata',
            'payrollcommon',
            'milestoneSchemaTrigger' => function ($query) {
                $query->select('id', 'name');
            },
        ])->whereIn('status', [1, 2, 6]);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $userCommission->where(function ($query) use ($search) {
                // Grouped conditions for searching in userInfo and salesDetail
                $query->whereHas('saledata', function ($sales_qry) use ($search) {
                    $sales_qry->where('customer_name', 'like', '%'.trim($search).'%');
                });
            });
        }

        $userCommission = $userCommission->where([
            'user_id' => $user_id,
            'pay_period_from' => $pay_period_from,
            'pay_period_to' => $pay_period_to,
        ]);

        if ($request->has('sort') && $request->input('sort') != '') {
            $userCommission->orderBy('sale_masters.'.$request->input('sort'), $request->input('sort_val'));
        }

        $userCommission = $userCommission->get();
        $companyProfile = CompanyProfile::first();
        foreach ($userCommission as $value) {
            $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
            $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
            $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
            $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

            $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type, 'adjustment_type' => $value->amount_type])->first();
            if ($value->amount_type == 'm1') {
                $date = isset($value->saledata->m1_date) ? $value->saledata->m1_date : '';
            } else {
                $date = isset($value->saledata->m2_date) ? $value->saledata->m2_date : '';
            }

            $location_data = Locations::with('State')->where('general_code', '=', $value->saledata->customer_state)->first();
            if ($location_data) {
                $state_code = $location_data->state->state_code;
            } else {
                $state_code = null;
            }
            $type = isset($value->amount_type) ? $value->amount_type : null;
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($value->amount_type == 'm1') {
                    $type = 'Upfront';
                } elseif ($value->amount_type == 'm2') {
                    $type = 'Commission';
                } elseif ($value->amount_type == 'm2 update') {
                    $type = 'Commission Update';
                }
            }

            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                // Use schema_name if available, otherwise use relationship to get milestone name
                if (! empty($value->schema_name)) {
                    $type = $value->schema_name;
                } elseif (! empty($value->milestoneSchemaTrigger) && ! empty($value->milestoneSchemaTrigger->name)) {
                    $type = $value->milestoneSchemaTrigger->name;
                } else {
                    // Fallback mapping for common cases
                    $milestoneNameMap = [
                        'm1' => 'Closing Date',
                        'm2' => 'Funding Date',
                        'm1 update' => 'Closing Date Update',
                        'm2 update' => 'Funding Date Update',
                        'reconciliation' => 'Reconciliation',
                        'reconciliation update' => 'Reconciliation Update',
                    ];
                    $type = $milestoneNameMap[$value->amount_type] ?? ucfirst(str_replace('_', ' ', $value->amount_type));
                }
            }

            $compRate = 0;

            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->commission_type !== 'per sale') {
                $compRate = number_format($value->comp_rate, 4, '.', '');
            }
            $data[$payroll_status][$period][] = [
                'id' => $value->id,
                'pid' => $value->pid,
                'payroll_id' => $value->payroll_id,
                'state_id' => $state_code,
                'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
                'rep_redline' => isset($value->redline) ? $value->redline : null,
                'comp_rate' => $compRate,
                'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                'amount' => isset($value->amount) ? $value->amount * 1 : null,
                'date' => isset($date) ? $date : null,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                'amount_type' => $type,
                'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                'position_id' => $value->position_id,
                'is_mark_paid' => $value->is_mark_paid,
                'is_next_payroll' => $value->is_next_payroll,
                'is_move_to_recon' => $value->is_move_to_recon,
                'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                'gross_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                'product' => @$value->saledata->product ? $value->saledata->product : null,
                'service_schedule' => @$value->saledata->service_schedule ? $value->saledata->service_schedule : null,
                'commission_amount' => @$value->commission_amount ?? null,
                'commission_type' => @$value->commission_type ?? null,
            ];
            $subtotal['commission'][$payroll_calculate][] = $value->amount * 1;
        }

        $clawBackSettlement = ClawbackSettlement::with('users', 'salesDetail', 'payrollcommon');
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $clawBackSettlement->where(function ($query) use ($search) {
                // Grouped conditions for searching in userInfo and salesDetail
                $query->whereHas('salesDetail', function ($sales_qry) use ($search) {
                    $sales_qry->where('customer_name', 'like', '%'.trim($search).'%');
                });
            });
        }
        $clawBackSettlement = $clawBackSettlement->where('type', '!=', 'overrides')->where([
            'user_id' => $user_id,
            'clawback_type' => 'next payroll',
            'pay_period_from' => $pay_period_from,
            'pay_period_to' => $pay_period_to,
        ])->get();

        foreach ($clawBackSettlement as $value) {
            $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
            $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
            $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
            $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

            $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $value->adders_type])->first();
            $location_data = Locations::with('State')->where('general_code', '=', $value->salesDetail->customer_state)->first();
            if ($location_data) {
                $state_code = $location_data->state->state_code;
            } else {
                $state_code = null;
            }
            $returnSalesDate = isset($value->salesDetail->return_sales_date) ? $value->salesDetail->return_sales_date : null;

            $compRate = 0;

            $data[$payroll_status][$period][] = [
                'id' => $value->id,
                'pid' => $value->pid,
                'payroll_id' => $value->payroll_id,
                'state_id' => $state_code,
                'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                'customer_state' => isset($value->salesDetail->customer_state) ? $value->salesDetail->customer_state : null,
                'rep_redline' => isset($value->users->redline) ? $value->users->redline : null,
                'kw' => isset($value->salesDetail->kw) ? $value->salesDetail->kw : null,
                'net_epc' => isset($value->salesDetail->net_epc) ? $value->salesDetail->net_epc : null,
                'amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                'date' => isset($value->salesDetail->date_cancelled) ? $value->salesDetail->date_cancelled : $returnSalesDate,
                'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                // this is clawback adjustment
                'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                'amount_type' => 'clawback',
                'adders' => isset($value->salesDetail->adders) ? $value->salesDetail->adders : null,
                'is_mark_paid' => $value->is_mark_paid,
                'is_next_payroll' => $value->is_next_payroll,
                'is_move_to_recon' => $value->is_move_to_recon,
                'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
                'product' => @$value->salesDetail->product ? $value->salesDetail->product : null,
                'service_schedule' => @$value->salesDetail->service_schedule ? $value->salesDetail->service_schedule : null,
                'commission_amount' => @$value->clawback_cal_amount ?? null,
                'commission_type' => @$value->clawback_cal_type ?? null,
                // 'comp_rate' => $compRate,
            ];
            $subtotal['commission'][$payroll_calculate][] = isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
        }

        $data['subtotal'] = $subtotal;

        return response()->json([
            'ApiName' => 'commission_details',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function overrideDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $userOverrides = UserOverrides::with('userInfo', 'salesDetail', 'payrollcommon')->whereIn('status', [1, 2, 6]);
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $userOverrides->where(function ($query) use ($search) {
                // Grouped conditions for searching in userInfo and salesDetail
                $query->whereHas('userInfo', function ($user_qry) use ($search) {
                    $searchTermLike = str_replace(' ', '%', $search);
                    $user_qry->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTermLike}%"]);
                })->orWhereHas('salesDetail', function ($sales_qry) use ($search) {
                    $sales_qry->where('customer_name', 'like', '%'.trim($search).'%');
                })->orWhere('type', 'LIKE', '%'.$search.'%');
            });
        }
        $userOverrides->where([
            'user_id' => $user_id,
            'overrides_settlement_type' => 'during_m2',
            'pay_period_from' => $pay_period_from,
            'pay_period_to' => $pay_period_to,
        ]);
        $userOverrides = $userOverrides->get();

        foreach ($userOverrides as $value) {
            $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
            $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
            $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
            $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

            $redLineType = $value->calculated_redline_type;
            if (in_array($value->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                $redLineType = 'percent';
            }

            $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type, 'adjustment_type' => $value->type, 'sale_user_id' => $value->sale_user_id])->first();
            $data[$payroll_status][$period][] = [
                'id' => $value->id,
                'pid' => $value->pid,
                'payroll_id' => $value->payroll_id,
                'first_name' => isset($value->userInfo->first_name) ? $value->userInfo->first_name : null,
                'last_name' => isset($value->userInfo->last_name) ? $value->userInfo->last_name : null,
                'position_id' => isset($value->userInfo->position_id) ? $value->userInfo->position_id : null,
                'sub_position_id' => isset($value->userInfo->sub_position_id) ? $value->userInfo->sub_position_id : null,
                'is_super_admin' => isset($value->userInfo->is_super_admin) ? $value->userInfo->is_super_admin : null,
                'is_manager' => isset($value->userInfo->is_manager) ? $value->userInfo->is_manager : null,
                'image' => isset($value->userInfo->image) ? $value->userInfo->image : null,
                'type' => isset($value->type) ? $value->type : null,
                'accounts' => 1,
                'kw_installed' => $value->kw,
                'total_amount' => isset($value->amount) ? $value->amount * 1 : 0,
                'override_type' => $value->overrides_type,
                'override_amount' => isset($value->overrides_amount) ? $value->overrides_amount * 1 : 0,
                'calculated_redline' => $value->calculated_redline,
                'state' => isset($value->userInfo->state) ? $value->userInfo->state->state_code : null,
                'm2_date' => isset($value->salesDetail->m2_date) ? $value->salesDetail->m2_date : null,
                'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                'is_mark_paid' => $value->is_mark_paid,
                'is_next_payroll' => $value->is_next_payroll,
                'is_move_to_recon' => $value->is_move_to_recon,
                'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
                'calculated_redline_type' => $redLineType,
            ];

            $subtotal['override'][$payroll_calculate][] = isset($value->amount) ? $value->amount * 1 : 0;
        }

        $clawbackSettlements = ClawbackSettlement::with(['payrollcommon', 'salesDetail', 'saleUserInfo.state'])->where([
            'type' => 'overrides',
            'user_id' => $user_id,
            'clawback_type' => 'next payroll',
            'pay_period_from' => $pay_period_from,
            'pay_period_to' => $pay_period_to,
        ])->get();

        foreach ($clawbackSettlements as $value) {
            $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
            $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
            $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
            $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

            $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => 'clawback', 'adjustment_type' => $value->adders_type, 'sale_user_id' => $value->sale_user_id])->first();
            $data[$payroll_status][$period][] = [
                'id' => $value->id,
                'pid' => $value->pid,
                'payroll_id' => $value->payroll_id,
                'first_name' => isset($value->saleUserInfo->first_name) ? $value->saleUserInfo->first_name : null,
                'last_name' => isset($value->saleUserInfo->last_name) ? $value->saleUserInfo->last_name : null,
                'position_id' => isset($value->saleUserInfo->position_id) ? $value->saleUserInfo->position_id : null,
                'sub_position_id' => isset($value->saleUserInfo->sub_position_id) ? $value->saleUserInfo->sub_position_id : null,
                'is_super_admin' => isset($value->saleUserInfo->is_super_admin) ? $value->saleUserInfo->is_super_admin : null,
                'is_manager' => isset($value->saleUserInfo->is_manager) ? $value->saleUserInfo->is_manager : null,
                'image' => isset($value->saleUserInfo->image) ? $value->saleUserInfo->image : null,
                'type' => 'clawback',
                'accounts' => 1,
                'kw_installed' => isset($value->salesDetail->kw) ? $value->salesDetail->kw : null,
                'total_amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                'override_type' => 'clawback', // Not On Table
                'override_amount' => null,
                'calculated_redline' => '',
                'state' => isset($value->saleUserInfo->state) ? $value->saleUserInfo->state->state_code : null,
                'm2_date' => isset($value->salesDetail->m2_date) ? $value->salesDetail->m2_date : null,
                'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                'is_mark_paid' => $value->is_mark_paid,
                'is_next_payroll' => $value->is_next_payroll,
                'is_move_to_recon' => $value->is_move_to_recon,
                'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
            ];
            $subtotal['override'][$payroll_calculate][] = isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
        }

        $data['subtotal'] = $subtotal;

        return response()->json([
            'ApiName' => 'override_details',
            'status' => true,
            'message' => 'Successfully.',
            'payroll_status' => 1,
            'data' => $data,
        ]);
    }

    public function overrideDetails_old(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payroll_id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = Payroll::where(['user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->get()->toArray();

        $sub_total = 0;

        if (! empty($Payroll)) {
            $payroll_id = implode(',', array_column($Payroll, 'id'));
            $user_ids = implode(',', array_column($Payroll, 'user_id'));
            // $userdata = UserOverrides::with('userInfo', 'salesDetail', 'payrollcommon')->whereIn('status', [1, 2, 6])
            $userdata = DB::table('user_overrides')->select('*', 'user_overrides.id as lid', 'payroll_common.id as pcid', 'user_overrides.type as ctype', 'users.first_name as first_name', 'users.last_name as last_name')
                ->leftJoin('sale_masters', 'user_overrides.pid', '=', 'sale_masters.pid')
                ->leftJoin('users', 'user_overrides.user_id', '=', 'users.id')
                ->leftJoin('payroll_common', 'user_overrides.ref_id', '=', 'payroll_common.id')
                ->where('user_id', $user_id)
                ->where([
                    'overrides_settlement_type' => 'during_m2',
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                ]);

            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');

                $userdata->where('customer_name', 'like', '%'.$search.'%');
                // ->orWhere('user_overrides.type', 'LIKE', '%' .$search.'%')
                // ->orWhere('first_name' , 'like', '%' . $search . '%')
                // ->orWhere('last_name', 'LIKE', '%' .$search.'%')
                // ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $search . '%']);

            }

            if ($request->has('sort') && $request->input('sort') != '') {
                $sort = $request->input('sort');
                $userdata->orderBy('user_overrides.'.$request->input('sort'), $request->input('sort_val'));
            }
            $userdata = $userdata->get();
            foreach ($userdata as $key => $value) {

                $payroll_status = (empty($value->pcid)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->pcid)) ? 'current' : (date('m/d/Y', strtotime($value->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->orig_payto)));
                $payroll_modified_date = isset($value->payroll_modified_date) ? date('m/d/Y', strtotime($value->payroll_modified_date)) : '';

                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['user_id' => $user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                // $sub_total = ($sub_total + $value->amount);

                $data[$payroll_status][$period][] = [
                    'id' => $value->lid,
                    'pid' => $value->pid,
                    'payroll_id' => $value->payroll_id,
                    'first_name' => isset($value->first_name) ? $value->first_name : null,
                    'last_name' => isset($value->last_name) ? $value->last_name : null,
                    'position_id' => isset($value->position_id) ? $value->position_id : null,
                    'sub_position_id' => isset($value->sub_position_id) ? $value->sub_position_id : null,
                    'is_super_admin' => isset($value->is_super_admin) ? $value->is_super_admin : null,
                    'is_manager' => isset($value->is_manager) ? $value->is_manager : null,
                    'image' => isset($value->image) ? $value->image : null,
                    'type' => isset($value->ctype) ? $value->ctype : null,
                    'accounts' => 1,
                    'kw_installed' => $value->kw,
                    'total_amount' => isset($value->amount) ? $value->amount * 1 : 0,
                    'override_type' => $value->overrides_type,
                    'override_amount' => isset($value->overrides_amount) ? $value->overrides_amount * 1 : 0,
                    'calculated_redline' => $value->calculated_redline,
                    'state' => isset($value->state) ? $value->state->state_code : null,
                    'm2_date' => isset($value->m2_date) ? $value->m2_date : null,
                    'customer_name' => isset($value->customer_name) ? $value->customer_name : null,
                    'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];

                $subtotal['override'][$payroll_calculate][] = isset($value->amount) ? $value->amount * 1 : 0;
                unset($adjustmentAmount);
                unset($value);
            }
            $clawbackSettlements = ClawbackSettlement::with('adjustment.commented_by')->with(['payrollcommon', 'salesDetail', 'saleUserInfo.state', 'adjustment' => function ($q) use ($user_id) {
                $q->where(['user_id' => $user_id, 'payroll_type' => 'overrides', 'type' => 'clawback']);
            }])->where([
                'type' => 'overrides',
                'user_id' => $user_id,
                'clawback_type' => 'next payroll',
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ])
                ->whereIn('payroll_id', [$payroll_id])
                ->get();

            foreach ($clawbackSettlements as $value) {

                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'pid' => $value->pid,
                    'payroll_id' => $value->payroll_id,
                    'first_name' => isset($value->saleUserInfo->first_name) ? $value->saleUserInfo->first_name : null,
                    'last_name' => isset($value->saleUserInfo->last_name) ? $value->saleUserInfo->last_name : null,
                    'position_id' => isset($value->saleUserInfo->position_id) ? $value->saleUserInfo->position_id : null,
                    'sub_position_id' => isset($value->saleUserInfo->sub_position_id) ? $value->saleUserInfo->sub_position_id : null,
                    'is_super_admin' => isset($value->saleUserInfo->is_super_admin) ? $value->saleUserInfo->is_super_admin : null,
                    'is_manager' => isset($value->saleUserInfo->is_manager) ? $value->saleUserInfo->is_manager : null,
                    'image' => isset($value->saleUserInfo->image) ? $value->saleUserInfo->image : null,
                    'type' => 'clawback',
                    'accounts' => 1,
                    'kw_installed' => isset($value->salesDetail->kw) ? $value->salesDetail->kw : null,
                    'total_amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                    'override_type' => 'clawback', // Not On Table
                    'override_amount' => null,
                    'calculated_redline' => '',
                    'state' => isset($value->saleUserInfo->state) ? $value->saleUserInfo->state->state_code : null,
                    'm2_date' => isset($value->salesDetail->m2_date) ? $value->salesDetail->m2_date : null,
                    'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                    'override_adjustment' => isset($value->adjustment->amount) ? $value->adjustment->amount * 1 : 0,
                    'adjustment_by' => isset($value->adjustment->commented_by->first_name) ? $value->adjustment->commented_by->first_name.' '.$value->adjustment->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($value->adjustment->comment) ? $value->adjustment->comment : null,
                    'adjustment_id' => isset($value->adjustment->id) ? $value->adjustment->id : null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
                $subtotal['override'][$payroll_calculate][] = isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
                unset($value);
            }

            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
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

    public function adjustmentDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($payroll)) {
            $payroll_id = $payroll->id;
            $details = PayrollAdjustmentDetail::with('commented_by', 'payrollcommon', 'userData');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');
                $details->whereHas('userData', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
                $details->orWhere('payroll_type', 'like', '%'.$search.'%');
            }
            $details = $details->where(['payroll_id' => $payroll_id, 'user_id' => $user_id])->get();
            $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
            foreach ($details as $value) {
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'pid' => $value->pid,
                    'payroll_id' => $value->payroll_id,
                    'user_details' => $value->userData,
                    'first_name' => $value?->commented_by?->first_name,
                    'last_name' => $value?->commented_by?->first_name,
                    'is_super_admin' => isset($value->commented_by->is_super_admin) ? $value->commented_by->is_super_admin : null,
                    'is_manager' => isset($value->commented_by->is_manager) ? $value->commented_by->is_manager : null,
                    'position_id' => isset($value->commented_by->position_id) ? $value->commented_by->position_id : null,
                    'sub_position_id' => isset($value->commented_by->sub_position_id) ? $value->commented_by->sub_position_id : null,
                    'image' => $image_s3,
                    'date' => isset($value['updated_at']) ? date('Y-m-d', strtotime($value['updated_at'])) : null,
                    'amount' => $value['amount'] * 1,
                    'payroll_type' => $value['payroll_type'],
                    'type' => $value['type'],
                    'description' => isset($value['comment']) ? $value['comment'] : null,
                    'adjustment_type' => 'payroll',
                    'adjustment_id' => $value->id,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
                $subtotal['adjustment'][$payroll_calculate][] = isset($value['amount']) ? $value['amount'] * 1 : 0;
            }

            $dataApprovalAndRequest = ApprovalsAndRequest::with('adjustment', 'approvedBy', 'payrollcommon', 'userData', 'comments');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');
                $dataApprovalAndRequest->whereHas('userData', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
                $dataApprovalAndRequest->orWhereHas('adjustment', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%');
                });
            }
            $dataApprovalAndRequest = $dataApprovalAndRequest->where('adjustment_type_id', '!=', 2)
                ->where(['user_id' => $user_id])->where('payroll_id', $payroll_id)->get();

            foreach ($dataApprovalAndRequest as $value) {
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value['id'],
                    'pid' => $value->req_no,
                    'payroll_id' => $value->payroll_id,
                    'user_details' => $value->userData,
                    'is_super_admin' => $value->approvedBy->is_super_admin,
                    'is_manager' => $value->approvedBy->is_manager,
                    'position_id' => $value->approvedBy->position_id,
                    'sub_position_id' => $value->approvedBy->sub_position_id,
                    'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                    'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                    'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                    'date' => isset($value['created_at']) ? date('Y-m-d', strtotime($value['created_at'])) : null,
                    'amount' => ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1,
                    'payroll_type' => $value->adjustment->name,
                    'type' => 'Adjustment',
                    'description' => isset($value->description) ? $value->description : (isset($value->comments->comment) ? strip_tags($value->comments->comment) : null),
                    'adjustment_type' => 'payroll',
                    'adjustment_id' => null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
                $subtotal['adjustment'][$payroll_calculate][] = ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1;
            }
            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ]);
        } else {
            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ]);
        }
    }

    public function adjustmentDetails_old(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (! empty($payroll)) {
            // $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();

            // if (!empty($dataAdjustment)) {
            // $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
            $details = PayrollAdjustmentDetail::with('commented_by', 'payrollcommon', 'userData');

            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');
                $details->whereHas('userData', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
                $details->orWhere('payroll_type', 'like', '%'.$search.'%');
            }
            $details = $details->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();
            $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
            foreach ($details as $value) {

                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'pid' => $value->pid,
                    'payroll_id' => $value->payroll_id,
                    'user_details' => $value->userData,
                    'first_name' => isset($value->commented_by->first_name) ? $value->commented_by->first_name : 'Super',
                    'last_name' => isset($value->commented_by->first_name) ? $value->commented_by->last_name : 'Admin',
                    'is_super_admin' => isset($value->commented_by->is_super_admin) ? $value->commented_by->is_super_admin : null,
                    'is_manager' => isset($value->commented_by->is_manager) ? $value->commented_by->is_manager : null,
                    'position_id' => isset($value->commented_by->position_id) ? $value->commented_by->position_id : null,
                    'sub_position_id' => isset($value->commented_by->sub_position_id) ? $value->commented_by->sub_position_id : null,
                    'image' => $image_s3,
                    'date' => isset($value['updated_at']) ? date('Y-m-d', strtotime($value['updated_at'])) : null,
                    'amount' => $value['amount'] * 1,
                    'payroll_type' => $value['payroll_type'],
                    'type' => $value['type'],
                    'description' => isset($value['comment']) ? $value['comment'] : null,
                    'adjustment_type' => 'payroll',
                    'adjustment_id' => $value->id,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,

                ];
                $subtotal['adjustment'][$payroll_calculate][] = isset($value['amount']) ? $value['amount'] * 1 : 0;
                unset($value);
            }
            // }

            $dataApprovalAndRequest = ApprovalsAndRequest::with('adjustment', 'approvedBy', 'payrollcommon', 'userData', 'comments');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');
                $dataApprovalAndRequest->whereHas('approvedBy', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
                $dataApprovalAndRequest->orWhere('type', 'like', '%'.$search.'%');
            }
            $dataApprovalAndRequest = $dataApprovalAndRequest->where('adjustment_type_id', '!=', 2)
                ->where(['payroll_id' => $payroll->id])
                ->where(['user_id' => $payroll->user_id])
                ->get();

            foreach ($dataApprovalAndRequest as $key => $value) {
                // $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value['id'],
                    'pid' => $value->req_no,
                    'payroll_id' => $value->payroll_id,
                    'user_details' => $value->userData,
                    'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                    'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                    'position_id' => isset($value->approvedBy->position_id) ? $value->approvedBy->position_id : null,
                    'sub_position_id' => isset($value->approvedBy->sub_position_id) ? $value->approvedBy->sub_position_id : null,
                    'is_super_admin' => isset($value->approvedBy->is_super_admin) ? $value->approvedBy->is_super_admin : null,
                    'is_manager' => isset($value->approvedBy->is_manager) ? $value->approvedBy->is_manager : null,
                    'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                    'date' => isset($value['created_at']) ? date('Y-m-d', strtotime($value['created_at'])) : null,
                    'amount' => ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1,
                    'payroll_type' => $value->adjustment->name,
                    'type' => 'Adjustment',
                    'description' => isset($value->description) ? $value->description : (isset($value->comments->comment) ? strip_tags($value->comments->comment) : null),
                    'adjustment_type' => 'payroll',
                    'adjustment_id' => null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
                $subtotal['adjustment'][$payroll_calculate][] = ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1;
                unset($value);
            }
            $data['subtotal'] = $subtotal;

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

    public function reimbursementDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->get()->toArray();
        $payroll_status = '';
        if (! empty($payroll)) {
            $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy', 'costcenter', 'userData')->where('status', 'Accept')->where(['user_id' => $user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to]);
            if ($request->has('search') && ! empty($request->input('search'))) {
                $search = $request->input('search');
                $reimbursement->whereHas('approvedBy', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
            }
            $reimbursement = $reimbursement->get();
            foreach ($reimbursement as $value) {
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'pid' => $value->req_no,
                    'payroll_id' => $value->payroll_id,
                    'cost_center' => isset($value->costcenter->name) ? $value->costcenter->name : null,
                    'user_details' => $value->userData,
                    'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                    'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                    'position_id' => isset($value->approvedBy->position_id) ? $value->approvedBy->position_id : null,
                    'sub_position_id' => isset($value->approvedBy->sub_position_id) ? $value->approvedBy->sub_position_id : null,
                    'is_super_admin' => isset($value->approvedBy->is_super_admin) ? $value->approvedBy->is_super_admin : null,
                    'is_manager' => isset($value->approvedBy->is_manager) ? $value->approvedBy->is_manager : null,
                    'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                    'date' => isset($value->cost_date) ? $value->cost_date : null,
                    'amount' => isset($value->amount) ? $value->amount * 1 : 0,
                    'description' => isset($value->description) ? $value->description : null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                ];
                $subtotal['reimbursement'][$payroll_calculate][] = isset($value->amount) ? $value->amount * 1 : 0;
            }

            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll_status,
                'data' => $data,
            ]);
        } else {
            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ]);
        }
    }

    public function PayrollReconciliation(Request $request)
    {
        $data = ReconciliationFinalizeHistory::where('user_id', $request->user_id)
            ->whereDate('pay_period_from', $request->pay_period_from)
            ->whereDate('pay_period_to', $request->pay_period_to)
            ->get();
        if ($data) {
            $response = $data->transform(function ($result) {
                $executedOnDate = Carbon::parse($result->executed_on)->format('m/d/Y');
                $updatedAtTime = Carbon::parse($result->updated_at)->format('H:i:s');
                $combinedDateTime = $executedOnDate.' '.$updatedAtTime;

                return [
                    'added_to_payroll_on' => $combinedDateTime,
                    'startDate_endDate' => Carbon::parse($result->start_date)->format('m/d/Y').' to '.Carbon::parse($result->end_date)->format('m/d/Y'),
                    'commission' => $result->paid_commission,
                    'override' => $result->paid_override,
                    'clawback' => -1 * $result->clawback,
                    'adjustment' => $result->adjustments - $result->deductions,
                    // 'total' => $result->gross_amount,
                    'total' => ($result->paid_commission + $result->paid_override + $result->adjustments - $result->deductions - $result->clawback),
                    'payout' => $result->payout,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'payroll in reconcitation api ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
            'subtotal' => 0.00,
        ], 200);
        /* $myArray = array();
        if(isset($data) && $datas!='[]')
        {
            foreach($datas as $data)
            {
                $commission = $data->commission;
                $payout = $data->payout;
                $override = $data->override;
                $totalCommission = ($commission*$payout)/100;
                $totalOverride = ($override*$payout)/100;
                $clawback = $data->clawback;
                $adjustments = $data->adjustments;
                $total = $data->net_amount;

                $myArray[] = [
                    'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y'),
                    'startDate_endDate' => Carbon::parse($data->start_date)->format('m/d/Y').' to '.Carbon::parse($data->end_date)->format('m/d/Y'),
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'clawback' => $clawback,
                    'adjustment' => $adjustments,
                    'total' => $total,
                    'payout' => $payout
                ];
            }
        }

        return response()->json([
            'ApiName' => 'payroll in reconcitation api ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $myArray,
            'subtotal'=>@$total
        ], 200); */

    }

    // public function payrollDeductions(Request $request): JsonResponse{
    //     $Validator = Validator::make(
    //         $request->all(),
    //         [
    //             'user_id' => 'required',
    //             'pay_period_from' => 'required',
    //             'pay_period_to' => 'required',
    //             //'user_id'    => 'required', // 11
    //         ]
    //     );
    //     if ($Validator->fails()) {
    //         return response()->json(['error' => $Validator->errors()], 400);
    //     }
    //     $id = $request->id;
    //     $user_id = $request->user_id;
    //     $pay_period_from = $request->pay_period_from;
    //     $pay_period_to = $request->pay_period_to;
    //     $payroll = GetPayrollData::where(['user_id' => $user_id,'pay_period_from' => $pay_period_from,'pay_period_to' => $pay_period_to])->get()->toArray();

    //     $paydata = [];
    //     $Payroll_status = '';
    //     if (!empty($payroll)) {
    //         $Payroll_status = $payroll->status;
    //         $paydata = PayrollDeductions::with('costcenter')
    //         ->leftjoin("payroll_adjustment_details",function($join){
    //             $join->on("payroll_adjustment_details.payroll_id","=","payroll_deductions.payroll_id")
    //                 ->on("payroll_adjustment_details.cost_center_id","=","payroll_deductions.cost_center_id");
    //         })
    //          ->where('payroll_deductions.user_id', $user_id)
    //          ->select('payroll_deductions.*','payroll_adjustment_details.amount as adjustment_amount')
    //          ->get();
    //     }

    //     $response_arr = [];
    //     $subtotal = 0;
    //     foreach($paydata as $d){
    //         $subtotal = $d->subtotal;
    //         $response_arr[] = [
    //             'Type' => $d->costcenter->name,
    //             'Amount' => $d->amount,
    //             'Limit' => $d->limit,
    //             'Total' => $d->total,
    //             'Outstanding' => $d->outstanding,
    //             'cost_center_id' => $d->cost_center_id,
    //             'adjustment_amount' => isset($d->adjustment_amount)?$d->adjustment_amount:0
    //         ];
    //     }

    //     $response = array('list'=>$response_arr , 'subtotal'=>$subtotal);

    //     return response()->json([
    //         'ApiName' => 'payroll_Deductions',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $response,
    //     ], 200);
    // }
    public function payrollDeductions(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        // Get the payroll data for the specified user and period
        $payroll = GetPayrollData::where([
            'user_id' => $user_id,
            'pay_period_from' => $pay_period_from,
            'pay_period_to' => $pay_period_to,
        ])->first(); // Changed to first() to get a single payroll record

        if (! $payroll) {
            return response()->json([
                'ApiName' => 'payroll_Deductions',
                'status' => false,
                'message' => 'No payroll data found for the specified period.',
                'data' => [],
            ], 404);
        }

        // Fetch payroll deductions with potential adjustments, avoiding duplicates
        $paydata = PayrollDeductions::with('costcenter')
            ->leftJoin('payroll_adjustment_details', function ($join) {
                $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_deductions.payroll_id')
                    ->on('payroll_adjustment_details.cost_center_id', '=', 'payroll_deductions.cost_center_id');
            })
            ->where('payroll_deductions.user_id', $user_id)
            ->where('payroll_deductions.pay_period_from', $pay_period_from)
            ->where('payroll_deductions.pay_period_to', $pay_period_to)
            ->select('payroll_deductions.*', 'payroll_adjustment_details.amount as adjustment_amount')
            ->distinct() // Ensure uniqueness in the retrieved data
            ->get();

        // Construct the response array and calculate the subtotal
        $response_arr = [];
        $subtotal = [];

        foreach ($paydata as $d) {
            $payroll_calculate = ($d->is_mark_paid == 1 || $d->is_next_payroll == 1 || $d->is_move_to_recon == 1) ? 'ignore' : 'count';
            $response_arr[] = [
                'Type' => $d->costcenter->name,
                'Amount' => $d->amount,
                'Limit' => $d->limit,
                'Total' => $d->total,
                'Outstanding' => $d->outstanding,
                'cost_center_id' => $d->cost_center_id,
                'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                'is_move_to_recon' => $d->is_move_to_recon,
            ];
            // $subtotal += $d->amount; // Accumulate the subtotal
            $amount = isset($d->amount) ? $d->amount * 1 : 0;

            // Accumulate the subtotal into the appropriate array based on $payroll_calculate
            $subtotal['deduction'][$payroll_calculate][] = $amount;
        }

        $response = [
            'list' => $response_arr,
            'subtotal' => $subtotal,
        ];

        return response()->json([
            'ApiName' => 'payroll_Deductions',
            'status' => true,
            'message' => 'Successfully retrieved payroll deductions.',
            'data' => $response,
        ], 200);
    }

    public function additionalValueDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;
        $custom_field_id = $request->custom_field_id ?? null;

        $payroll = GetPayrollData::where(['user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($payroll)) {
            if ($custom_field_id != null && $custom_field_id != 0) {
                $customeFields = \App\Models\CustomField::with(['getColumn', 'getApprovedBy'])->where('column_id', $custom_field_id)->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            } else {
                $customeFields = \App\Models\CustomField::with(['getColumn', 'getApprovedBy'])->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            }

            $data = [];
            foreach ($customeFields as $value) {
                $payroll_status = $value->is_next_payroll == 1 ? 'next_payroll' : 'current_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';

                $payrollData = Payroll::where('id', $value->payroll_id)->first();
                $period = 'current';
                $payroll_modified_date = '';
                if ($payrollData != null && isset($payrollData->ref_id)) {
                    $payrollCommon = PayrollCommon::where('id', $payrollData->ref_id)->first();
                    if ($payrollCommon != null && isset($payrollData->payroll_modified_date)) {
                        $payroll_modified_date = date('m/d/Y', strtotime($payrollData->payroll_modified_date));
                        $period = 'current';
                    }
                }

                $approved_by_detail = [];
                if ($value->getApprovedBy != null) {
                    $image = isset($value->getApprovedBy->image) && $value->getApprovedBy->image != null ? s3_getTempUrl(config('app.domain_name').'/'.$value->getApprovedBy->image) : null;

                    // Fetch additional details if they exist
                    $approved_by_detail = [
                        'first_name' => $value->getApprovedBy->first_name,
                        'middle_name' => $value->getApprovedBy->middle_name,
                        'last_name' => $value->getApprovedBy->last_name,
                        'position_id' => $value->getApprovedBy->position_id ?? null,
                        'sub_position_id' => $value->getApprovedBy->sub_position_id ?? null,
                        'is_super_admin' => $value->getApprovedBy->is_super_admin ?? 0,
                        'is_manager' => $value->getApprovedBy->is_manager ?? 0,
                        'image' => $image,
                        'date' => \Carbon\Carbon::parse($value->created_at)->format('Y-m-d H:i:s'),
                        'amount' => isset($value->value) ? ($value->value) : 0,
                        'description' => $value->description ?? null,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => $payroll_modified_date,
                    ];
                }

                $date = $value->updated_at != null ? \Carbon\Carbon::parse($value->updated_at)->format('m/d/Y') : \Carbon\Carbon::parse($value->created_at)->format('m/d/Y');
                $amount = isset($value->value) ? ($value->value) : 0;
                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'custom_field_id' => $value->column_id,
                    'amount' => $amount,
                    'type' => $value->getColumn->field_name ?? '',
                    'date' => $date,
                    'comment' => $value->comment,
                    'adjustment_by' => $value->approved_by,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'adjustment_by_detail' => $approved_by_detail,
                ];

                $subtotal['amount'][$payroll_calculate][] = $amount * 1;
            }
            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'additionalValueDetails',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ]);
        } else {
            return response()->json([
                'ApiName' => 'additionalValueDetails',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ]);
        }
    }

    public function wagesDetails(Request $request)
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        if (! empty($user_id)) {

            $paydata = PayrollHourlySalary::with('userdata')
                ->leftjoin('payroll_overtimes', function ($join) {
                    $join->on('payroll_overtimes.payroll_id', '=', 'payroll_hourly_salary.payroll_id')
                        ->on('payroll_overtimes.user_id', '=', 'payroll_hourly_salary.user_id')
                        ->on('payroll_overtimes.date', '=', 'payroll_hourly_salary.date');
                })
             // That is unwanted join which fetching  hourlyslary orvertime into multiplication each  adjument row
            // ->leftjoin("payroll_adjustment_details",function($join){
            //     $join->on("payroll_adjustment_details.payroll_id","=","payroll_hourly_salary.payroll_id")
            //         ->on("payroll_adjustment_details.user_id","=","payroll_hourly_salary.user_id");
            // })
                ->where('payroll_hourly_salary.user_id', $user_id)
                ->where('payroll_hourly_salary.is_next_payroll', 0)
                ->where('payroll_hourly_salary.pay_period_from', $pay_period_from)
                ->where('payroll_hourly_salary.pay_period_to', $pay_period_to)
                ->select('payroll_hourly_salary.*', 'payroll_overtimes.overtime', 'payroll_overtimes.total as overtime_total')
                ->get();

            // return $paydata;

            $response_arr = [];
            $total = 0;
            $subtotal = 0;
            $totalSeconds = 0;
            $totalHours = 0;
            $totalOvertime = 0;
            foreach ($paydata as $d) {
                // if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0) {
                //     $subtotal += $d->total;
                // }
                $total = ($d->total + $d->overtime_total);
                $subtotal += $total;

                if (! empty($d->regular_hours)) {
                    $timeA = Carbon::createFromFormat('H:i', $d->regular_hours);
                    $secondsA = $timeA->hour * 3600 + $timeA->minute * 60;
                    $totalSeconds = $totalSeconds + $secondsA;
                }

                if (! empty($d->overtime)) {
                    $totalOvertime = $totalOvertime + $d->overtime;
                }

                $response_arr[] = [
                    'id' => $d->id,
                    'payroll_id' => $d->payroll_id,
                    'is_mark_paid' => $d->is_mark_paid,
                    'is_next_payroll' => $d->is_next_payroll,
                    'date' => $d->date,
                    'hourly_rate' => $d->hourly_rate,
                    'overtime_rate' => $d->overtime_rate,
                    'salary' => $d->salary,
                    'regular_hours' => $d->regular_hours,
                    'overtime' => $d->overtime,
                    'total' => $total,
                    'adjustment_amount' => 0,
                ];
            }

            $totalHours = ($totalSeconds > 0) ? ($totalSeconds / 3600) : 0;

            $totalData = [
                'total_amount' => $subtotal,
                'total_regular_hour' => number_format($totalHours, 2),
                'total_overtime' => number_format($totalOvertime, 2),
            ];

            $response = ['list' => $response_arr, 'subtotal' => $totalData];

            return response()->json([
                'ApiName' => 'wages_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => 1,
                'data' => $response,
            ], 200);

        } else {

            return response()->json([
                'ApiName' => 'wages_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);

        }

    }
}
