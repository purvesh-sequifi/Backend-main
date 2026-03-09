<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\User;
use App\Models\Payroll;
use App\Models\SalesMaster;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Models\FrequencyType;
use App\Models\PayrollSsetup;
use App\Models\UserOverrides;
use App\Models\PayrollHistory;
use App\Models\CompanyProfile;
use App\Models\UserCommission;
use App\Models\PayrollOvertime;
use App\Models\UserOverridesLock;
use App\Models\PayrollDeductions;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\UserCommissionLock;
use App\Models\PayrollOvertimeLock;
use App\Models\ApprovalsAndRequest;
use App\Models\PayrollHourlySalary;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PayrollDeductionLock;
use App\Models\ClawbackSettlementLock;
use App\Models\ApprovalsAndRequestLock;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollAdjustmentDetail;
use Illuminate\Support\Facades\Validator;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationFinalizeHistoryLock;
use App\Exports\ExportPayroll\PayrollCustomExport;

class PayrollBreakdownController extends Controller
{
    public function salaryBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "salary-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $salaryClass = PayrollHourlySalaryLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $salaryClass = PayrollHourlySalary::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $salaryDetails = $salaryClass::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id, "payroll_id" => $request->payroll_id
        ])
        ->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })
        ->get();

        $salaryAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
                "type" => "hourlysalary",
                "payroll_type" => "hourlysalary"
            ])
            ->where(function($q) {
                $q->whereNotNull('amount')->where('amount', '!=', 0);
            })
            ->get();

        $data = [];
        $hours = [];
        $subTotal = [
            "total" => 0,
            "adjustment" => 0
        ];
        foreach ($salaryDetails as $salaryDetail) {
            $adjustment = adjustmentColumn($salaryDetail, $salaryAdjustments, "hourlysalary");
            $payrollModifiedDate = isset($salaryDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($salaryDetail->payrollReference->payroll_modified_date)) : NULL;
            $key = empty($salaryDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($salaryDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($salaryDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $salaryDetail->id,
                "date" => $salaryDetail->date ? date("m/d/Y", strtotime($salaryDetail->date)) : NULL,
                "hourly_rate" => $salaryDetail->hourly_rate * 1,
                "salary" => $salaryDetail->salary * 1,
                "regular_hour" => $salaryDetail->regular_hours,
                "total" => $salaryDetail->total * 1,
                "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate,
                "adjustment" => $adjustment,
                "operation_type" => "salary", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $salaryDetail->is_mark_paid,
                "is_next_payroll" => $salaryDetail->is_next_payroll,
                "is_stop_payroll" => $salaryDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $salaryDetail->is_onetime_payment
            ];

            if (!$salaryDetail->is_mark_paid && !$salaryDetail->is_next_payroll && !$salaryDetail->is_onetime_payment) {
                $subTotal["total"] += $salaryDetail->total;
                $subTotal["adjustment"] += $adjustment["adjustment_amount"];

                $hours[] = $salaryDetail->regular_hours ?? "00:00";
            }
        }

        $subTotal["hours"] = getTotalHoursFromArray($hours);
        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "salary-breakdown",
            "data" => $response
        ]);
    }

    public function overtimeBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "overtime-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $overtimeClass = PayrollOvertimeLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $overtimeClass = PayrollOvertime::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $overtimeDetails = $overtimeClass::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id, "payroll_id" => $request->payroll_id
        ])
        ->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })
        ->get();

        $overtimeAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
                "type" => "overtime",
                "payroll_type" => "overtime"
            ])->get();

        $data = [];
        $hours = [];
        $subTotal = [
            "total" => 0,
            "adjustment" => 0
        ];
        foreach ($overtimeDetails as $overtimeDetail) {
            $adjustment = adjustmentColumn($overtimeDetail, $overtimeAdjustments, "overtime");
            $payrollModifiedDate = isset($overtimeDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($overtimeDetail->payrollReference->payroll_modified_date)) : NULL;
            $key = empty($overtimeDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($overtimeDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($overtimeDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $overtimeDetail->id,
                "date" => $overtimeDetail->date ? date("m/d/Y", strtotime($overtimeDetail->date)) : NULL,
                "overtime_rate" => $overtimeDetail->overtime_rate * 1,
                "overtime_hours" => $overtimeDetail->overtime_hours,
                "total" => $overtimeDetail->total * 1,
                "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate,
                "adjustment" => $adjustment,
                "operation_type" => "overtime", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $overtimeDetail->is_mark_paid,
                "is_next_payroll" => $overtimeDetail->is_next_payroll,
                "is_stop_payroll" => $overtimeDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $overtimeDetail->is_onetime_payment
            ];

            if (!$overtimeDetail->is_mark_paid && !$overtimeDetail->is_next_payroll && !$overtimeDetail->is_onetime_payment) {
                $subTotal["total"] += $overtimeDetail->total;
                $subTotal["adjustment"] += $adjustment["adjustment_amount"];

                $hours[] = $overtimeDetail->overtime_hours ?? "00:00";
            }
        }

        $subTotal["hours"] = getTotalHoursFromArray($hours);
        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "overtime-breakdown",
            "data" => $response
        ]);
    }

    public function commissionBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "payroll_id" => "required_if:type,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $type = $request->type;
        if ($type == "pid") {
            $validator = Validator::make($request->all(), [
                "pid" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "commission-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                "user_id" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "commission-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        }

        $companyProfile = CompanyProfile::first();
        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $commissionClass = UserCommissionLock::class;
            $clawBackClass = ClawbackSettlementLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $commissionClass = UserCommission::class;
            $clawBackClass = ClawbackSettlement::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }
        $userCommissions = $commissionClass::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "amount",
            "amount_type",
            "product_code",
            "schema_name",
            "schema_type",
            "redline",
            "redline_type",
            "comp_rate",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            "commission_amount",
            "commission_type",
            DB::raw("0 as is_claw_back"),
            "schema_type as payroll_type",
        )->with([
            "payrollSaleData:pid,customer_name,product_code,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->applyFrequencyFilter($param)->when($type == 'pid', function ($q) use ($request) {
            $q->where("pid", $request->pid);
        })->when($type == 'worker', function ($q) use ($request) {
            $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
        })
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        });

        $userClawBackCommissions = $clawBackClass::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "clawback_amount as amount",
            "adders_type as amount_type",
            "product_code",
            "schema_name",
            "schema_type",
            "redline",
            "redline_type",
            DB::raw("0 as comp_rate"),
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            "clawback_cal_amount as commission_amount",
            "clawback_cal_type as commission_type",
            DB::raw("1 as is_claw_back"),
            DB::raw('"clawback" as payroll_type'),
        )->with([
            "payrollSaleData:pid,customer_name,product_code,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->applyFrequencyFilter($param, [
            "type" => "commission",
            "clawback_type" => "next payroll"
        ])->when($type == 'pid', function ($q) use ($request) {
            $q->where("pid", $request->pid);
        })->when($type == 'worker', function ($q) use ($request) {
            $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
        })
        ->where(function($q) {
            $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
        });
        $userCommissions = $userCommissions->union($userClawBackCommissions)->get();

        $clawBackPid = [];
        $clawBackType = [];
        $commissionPid = [];
        $commissionType = [];
        foreach ($userCommissions as $userCommission) {
            if ($userCommission->is_claw_back) {
                $clawBackType[] = $userCommission->schema_type;
                $clawBackPid[] = $userCommission->pid;
            } else {
                $commissionType[] = $userCommission->schema_type;
                $commissionPid[] = $userCommission->pid;
            }
        }

        $commissionAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "payroll_type" => "commission"
            ])->when($type == 'pid', function ($q) use ($request) {
                $q->where("pid", $request->pid);
            })->when($type == 'worker', function ($q) use ($request) {
                $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
            })->whereIn("type", $commissionType)->whereIn("adjustment_type", $commissionType)->whereIn("pid", $commissionPid);

        $clawBackAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "payroll_type" => "commission",
                "type" => "clawback"
            ])->when($type == 'pid', function ($q) use ($request) {
                $q->where("pid", $request->pid);
            })->when($type == 'worker', function ($q) use ($request) {
                $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
            })->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $commissionAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        $subTotal = 0;
        foreach ($userCommissions as $userCommission) {
            $compRate = 0;
            $repRedline = formatRedline($userCommission->redline, $userCommission->redline_type);
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $userCommission->commission_type !== "per sale") {
                $compRate = number_format($userCommission->comp_rate, 4, ".", "");
            }
            $netEpc = $userCommission?->payrollSaleData?->net_epc;
            $feePercentage = null;
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && is_numeric($netEpc)) {
                $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '');
            }

            $image = null;
            if ($type == "pid" && $userCommission?->payrollUser && $userCommission?->payrollUser->image && $userCommission?->payrollUser->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $userCommission?->payrollUser->image);
            }

            if ($userCommission->is_claw_back) {
                $amount = (0 - $userCommission->amount);
                $amountType = 'clawback';
            } else {
                $amount = $userCommission->amount;
                $amountType = $userCommission->schema_name;
            }

            $adjustment = adjustmentColumn($userCommission, $adjustments, "commission");
            $key = empty($userCommission->payrollReference) ? "current" : (date("m/d/Y", strtotime($userCommission->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($userCommission->payrollReference->orig_payto)));
            $row = [
                "id" => $userCommission->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userCommission->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userCommission?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "first_name" => $userCommission?->payrollUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "last_name" => $userCommission?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "image_s3" => $image, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "position_id" => $userCommission?->payrollUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "sub_position_id" => $userCommission?->payrollUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_manager" => $userCommission?->payrollUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_super_admin" => $userCommission?->payrollUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "product" => $userCommission?->payrollSaleData?->product_code
                        ? strtoupper($userCommission->payrollSaleData->product_code)
                        : null, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "gross_account_value" => $userCommission?->payrollSaleData?->gross_account_value, // PEST, TURF, FIBER, MORTGAGE // WORKER
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount_type" => $amountType, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "trigger_date" => $userCommission?->payrollSaleData?->salesProductMaster, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "rep_redline" => $repRedline, // SOLAR, MORTGAGE // BOTH
                "comp_rate" => $compRate, // MORTGAGE // BOTH
                "operation_type" => $userCommission->is_claw_back ? "clawback" : "commission", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $userCommission->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $userCommission->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $userCommission?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $userCommission->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $userCommission->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "kw" => $userCommission?->payrollSaleData?->kw, // SOLAR // WORKER
                "net_epc" => $netEpc, // SOLAR, MORTGAGE // WORKER
                "adders" => $userCommission?->payrollSaleData?->adders, // SOLAR, TURF // WORKER
                "customer_state" => $userCommission->payrollSaleData->customer_state, // SOLAR, TURF, MORTGAGE // WORKER
                "commission_amount" => $userCommission->commission_amount, // PEST, FIBER // BOTH
                "commission_type" => $userCommission->commission_type, // PEST, FIBER // BOTH
            ];
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $row["fee_percentage"] = $feePercentage; // (net_epc * 100, 4dp)
            }
            $data[$key][] = $row;

            if (!$userCommission->is_mark_paid && !$userCommission->is_next_payroll && !$userCommission->is_onetime_payment && !$userCommission->is_move_to_recon) {
                if ($userCommission->is_claw_back) {
                    $subTotal -= $userCommission->amount;
                } else {
                    $subTotal += $userCommission->amount;
                }
            }
        }

        $user = NULL;
        $saleDetail = NULL;
        if ($type == "pid") {
            $sale = SalesMaster::where("pid", $request->pid)->first();
            $netEpc = $sale?->net_epc;
            $feePercentage = null;
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && is_numeric($netEpc)) {
                $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '');
            }
            $saleDetail = [
                "location_code" => $sale?->location_code, // SOLAR, MORTGAGE
                "kw" => $sale?->kw, // SOLAR
                "gross_account_value" => $sale?->gross_account_value, // MORTGAGE
                "net_epc" => $netEpc, // SOLAR, MORTGAGE
            ];
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $saleDetail["fee_percentage"] = $feePercentage; // (net_epc * 100, 4dp)
            }
        } else {
            $user = getUserData($request->user_id);
        }
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal,
            "common_data" => $saleDetail
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "commission-breakdown",
            "data" => $response
        ]);
    }

    public function overrideBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "payroll_id" => "required_if:type,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $type = $request->type;
        if ($type == "pid") {
            $validator = Validator::make($request->all(), [
                "pid" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "override-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                "user_id" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "override-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        }
        $companyProfile = CompanyProfile::first();
        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $overridesClass = UserOverridesLock::class;
            $clawBackClass = ClawbackSettlementLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $overridesClass = UserOverrides::class;
            $clawBackClass = ClawbackSettlement::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $userOverrides = $overridesClass::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "sale_user_id",
            "product_code",
            "type",
            "overrides_amount",
            "overrides_type",
            "amount",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            DB::raw("0 as is_claw_back"),
            "type as payroll_type",
        )->with([
            "payrollSaleData:pid,customer_name,product_code,kw,gross_account_value",
            "payrollReference",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->applyFrequencyFilter($param)->when($type == 'pid', function ($q) use ($request) {
            $q->where("pid", $request->pid);
        })->when($type == 'worker', function ($q) use ($request) {
            $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
        })
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        });

        $userClawBackOverrides = $clawBackClass::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "sale_user_id",
            "product_code",
            "adders_type as type",
            "clawback_cal_amount as overrides_amount",
            "clawback_cal_type as overrides_type",
            "clawback_amount as amount",
            "is_mark_paid",
            "is_next_payroll",
            "is_onetime_payment",
            "is_move_to_recon",
            DB::raw("1 as is_claw_back"),
            DB::raw('"clawback" as payroll_type')
        )->with([
            "payrollSaleData:pid,customer_name,product_code,kw,gross_account_value",
            "payrollReference",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->applyFrequencyFilter($param, [
            "type" => "overrides",
            "clawback_type" => "next payroll"
        ])->when($type == 'pid', function ($q) use ($request) {
            $q->where("pid", $request->pid);
        })->when($type == 'worker', function ($q) use ($request) {
            $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
        })
        ->where(function($q) {
            $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
        });
        $userOverrides = $userOverrides->union($userClawBackOverrides)->get();

        $clawBackPid = [];
        $clawBackType = [];
        $overridePid = [];
        $overrideType = [];
        foreach ($userOverrides as $userOverride) {
            if ($userOverride->is_claw_back) {
                $clawBackPid[] = $userOverride->pid;
                $clawBackType[] = $userOverride->type;
            } else {
                $overridePid[] = $userOverride->pid;
                $overrideType[] = $userOverride->type;
            }
        }

        $overrideAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "payroll_type" => "overrides"
            ])->when($type == 'pid', function ($q) use ($request) {
                $q->where("pid", $request->pid);
            })->when($type == 'worker', function ($q) use ($request) {
                $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
            })->whereIn("type", $overrideType)->whereIn("adjustment_type", $overrideType)->whereIn("pid", $overridePid);

        $clawBackAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "payroll_type" => "overrides",
                "type" => "clawback"
            ])->when($type == 'pid', function ($q) use ($request) {
                $q->where("pid", $request->pid);
            })->when($type == 'worker', function ($q) use ($request) {
                $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
            })->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $overrideAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        $subTotal = 0;
        foreach ($userOverrides as $userOverride) {
            $image = NULL;
            if ($type == "pid" && $userOverride?->payrollUser && $userOverride?->payrollUser->image && $userOverride?->payrollUser->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $userOverride?->payrollUser->image);
            }

            $overImage = NULL;
            if ($userOverride?->payrollOverUser && $userOverride?->payrollOverUser->image && $userOverride?->payrollOverUser->image != "Employee_profile/default-user.png") {
                $overImage = s3_getTempUrl(config("app.domain_name") . "/" . $userOverride?->payrollOverUser->image);
            }

            if ($userOverride->is_claw_back) {
                $amount = (0 - $userOverride->amount);
                $type = 'clawback';
            } else {
                $amount = $userOverride->amount;
                $type = $userOverride?->type;
            }

            $adjustment = adjustmentColumn($userOverride, $adjustments, "override");
            $key = empty($userOverride->payrollReference) ? "current" : (date("m/d/Y", strtotime($userOverride->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($userOverride->payrollReference->orig_payto)));
            
            $data[$key][] = [
                "id" => $userOverride->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userOverride->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userOverride?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "product" => $userOverride?->payrollSaleData?->product_code
                        ? strtoupper($userOverride->payrollSaleData->product_code)
                        : null, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                //"product" => $userOverride?->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "first_name" => $userOverride?->payrollUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "last_name" => $userOverride?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "position_id" => $userOverride?->payrollUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "sub_position_id" => $userOverride?->payrollUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_super_admin" => $userOverride?->payrollUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_manager" => $userOverride?->payrollUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "image" => $image, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "over_first_name" => $userOverride?->payrollOverUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_last_name" => $userOverride?->payrollOverUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_position_id" => $userOverride?->payrollOverUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_sub_position_id" => $userOverride?->payrollOverUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_super_admin" => $userOverride?->payrollOverUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_manager" => $userOverride?->payrollOverUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_image" => $overImage, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "type" => $type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "kw" => $userOverride?->payrollSaleData?->kw, // SOLAR, TURF // WORKER
                "override_amount" => $userOverride?->overrides_amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "override_type" => $userOverride?->overrides_type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "adjustment" => $adjustment, // BOTH
                "operation_type" => $userOverride->is_claw_back ? "clawback" : "override", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $userOverride?->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $userOverride?->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $userOverride?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $userOverride?->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $userOverride?->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "gross_account_value" => $userOverride?->payrollSaleData?->gross_account_value, // PEST, FIBER, MORTGAGE // WORKER
            ];

            if (!$userOverride?->is_mark_paid && !$userOverride?->is_next_payroll && !$userOverride?->is_onetime_payment && !$userOverride?->is_move_to_recon) {
                if ($userOverride->is_claw_back) {
                    $subTotal -= $userOverride->amount;
                } else {
                    $subTotal += $userOverride->amount;
                }
            }
        }

        $user = NULL;
        $saleDetail = NULL;
        if ($type == "pid") {
            $sale = SalesMaster::where("pid", $request->pid)->first();
            $netEpc = $sale?->net_epc;
            $feePercentage = null;
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && is_numeric($netEpc)) {
                $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '');
            }
            $saleDetail = [
                "location_code" => $sale?->location_code, // SOLAR, MORTGAGE
                "kw" => $sale?->kw, // SOLAR
                "gross_account_value" => $sale?->gross_account_value, // MORTGAGE
                "net_epc" => $netEpc, // SOLAR, MORTGAGE
            ];
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $saleDetail["fee_percentage"] = $feePercentage; // (net_epc * 100, 4dp)
            }
        } else {
            $user = getUserData($request->user_id);
        }
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal,
            "common_data" => $saleDetail
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "override-breakdown",
            "data" => $response
        ]);
    }

    public function adjustmentBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "payroll_id" => "required_if:type,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "adjustment-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $type = $request->type;
        if ($type == "pid") {
            $validator = Validator::make($request->all(), [
                "pid" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "adjustment-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                "user_id" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "adjustment-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $adjustmentDetails = $adjustmentClass::with([
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData:pid,customer_name"
        ])->applyFrequencyFilter($param)->when($type == 'pid', function ($q) use ($request) {
            $q->where("pid", $request->pid);
        })->when($type == 'worker', function ($q) use ($request) {
            $q->where(["user_id" => $request->user_id, "payroll_id" => $request->payroll_id]);
        })
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })
        ->get();

        $data = [];
        $subTotal = 0;
        foreach ($adjustmentDetails as $adjustmentDetail) {
            $image = NULL;
            if ($type == "pid" && $adjustmentDetail?->payrollUser && $adjustmentDetail?->payrollUser->image && $adjustmentDetail?->payrollUser->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $adjustmentDetail?->payrollUser->image);
            }

            $adjustment = adjustmentColumn($adjustmentDetail, $adjustmentDetail, "adjustment");
            $date = isset($adjustmentDetail->updated_at) ? date("m/d/Y", strtotime($adjustmentDetail->updated_at)) : NULL;
            $payrollModifiedDate = isset($adjustmentDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($adjustmentDetail->payrollReference->payroll_modified_date)) : NULL;
            $key = empty($adjustmentDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($adjustmentDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($adjustmentDetail->payrollReference->orig_payto)));
            $data[$key][] = [
                "id" => $adjustmentDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $adjustmentDetail->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "customer_name" => ($adjustmentDetail?->payrollSaleData?->customer_name) ? $adjustmentDetail?->payrollSaleData?->customer_name : ($adjustmentDetail?->payrollUser?->first_name . " " . $adjustmentDetail?->payrollUser?->last_name ?? NULL), // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "first_name" => $adjustmentDetail?->payrollUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "last_name" => $adjustmentDetail?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "position_id" => $adjustmentDetail?->payrollUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "sub_position_id" => $adjustmentDetail?->payrollUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_manager" => $adjustmentDetail?->payrollUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "is_super_admin" => $adjustmentDetail?->payrollUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "image" => $image, // SOLAR, PEST, TURF, FIBER, MORTGAGE // PID
                "payroll_type" => $adjustmentDetail->payroll_type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount" => $adjustmentDetail->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "description" => $adjustmentDetail->comment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "operation_type" => "adjustment", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL // BOTH
                "is_mark_paid" => $adjustmentDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_next_payroll" => $adjustmentDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_stop_payroll" => $adjustmentDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_onetime_payment" => $adjustmentDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "is_move_to_recon" => $adjustmentDetail->is_move_to_recon, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
            ];

            if (!$adjustmentDetail->is_mark_paid && !$adjustmentDetail->is_next_payroll && !$adjustmentDetail->is_onetime_payment && !$adjustmentDetail->is_move_to_recon) {
                $subTotal += $adjustmentDetail->amount;
            }
        }

        if ($type == 'worker') {
            if ($request->is_history) {
                $approvalAndRequestClass = ApprovalsAndRequestLock::class;
            } else {
                $approvalAndRequestClass = ApprovalsAndRequest::class;
            }

            $approvalAndRequestDetails = $approvalAndRequestClass::with([
                "payrollReference",
                "payrollUser:id,first_name,last_name,stop_payroll",
                "payrollAdjustment",
                "payrollComments",
                "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
            ])->applyFrequencyFilter($param, [
                "user_id" => $request->user_id, "payroll_id" => $request->payroll_id
            ])->where("adjustment_type_id", "!=", 2)->get();

            foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
                $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
                $date = isset($approvalAndRequestDetail->updated_at) ? date("m/d/Y", strtotime($approvalAndRequestDetail->updated_at)) : NULL;
                $payrollModifiedDate = isset($approvalAndRequestDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($approvalAndRequestDetail->payrollReference->payroll_modified_date)) : NULL;
                $key = empty($approvalAndRequestDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($approvalAndRequestDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($approvalAndRequestDetail->payrollReference->orig_payto)));
                $amount = ($approvalAndRequestDetail->adjustment_type_id == 5 && !empty($approvalAndRequestDetail["amount"])) ? -1 * $approvalAndRequestDetail["amount"] : 1 * $approvalAndRequestDetail["amount"];
                $data[$key][] = [
                    "id" => $approvalAndRequestDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "pid" => $approvalAndRequestDetail->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "customer_name" => $approvalAndRequestDetail?->payrollUser?->first_name . " " . $approvalAndRequestDetail?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "payroll_type" => $approvalAndRequestDetail?->payrollAdjustment?->name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "description" => isset($approvalAndRequestDetail->description)
                        ? $approvalAndRequestDetail->description
                        : (isset($approvalAndRequestDetail?->payrollComments?->comment) ? strip_tags($approvalAndRequestDetail?->payrollComments?->comment) : null), // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "operation_type" => "request_approval", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                    "is_mark_paid" => $approvalAndRequestDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_next_payroll" => $approvalAndRequestDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_stop_payroll" => $approvalAndRequestDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_onetime_payment" => $approvalAndRequestDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
                    "is_from_approval_and_request" => 1, // SOLAR, PEST, TURF, FIBER, MORTGAGE // TO DISPLAY UNDO BUTTON
                ];

                if (!$approvalAndRequestDetail->is_mark_paid && !$approvalAndRequestDetail->is_next_payroll && !$approvalAndRequestDetail->is_onetime_payment) {
                    $subTotal += $amount;
                }
            }
        }

        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "adjustment-breakdown",
            "data" => $response
        ]);
    }

    public function reimbursementBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "reimbursement-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $approvalAndRequestClass = ApprovalsAndRequestLock::class;
        } else {
            $approvalAndRequestClass = ApprovalsAndRequest::class;
        }

        $approvalAndRequestDetails = $approvalAndRequestClass::with([
            "payrollReference",
            "payrollCostCenter",
            "payrollUser:id,stop_payroll",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id,
            "payroll_id" => $request->payroll_id,
            "adjustment_type_id" => 2
        ])
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })
        ->get();

        $data = [];
        $subTotal = 0;
        foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
            $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
            $date = isset($approvalAndRequestDetail->cost_date) ? date("m/d/Y", strtotime($approvalAndRequestDetail->cost_date)) : NULL;
            $key = empty($approvalAndRequestDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($approvalAndRequestDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($approvalAndRequestDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $approvalAndRequestDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "req_no" => $approvalAndRequestDetail->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "cost_center" => $approvalAndRequestDetail?->payrollCostCenter?->name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "amount" => $approvalAndRequestDetail->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "description" => $approvalAndRequestDetail->description, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "operation_type" => "reimbursement", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $approvalAndRequestDetail->is_mark_paid, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_next_payroll" => $approvalAndRequestDetail->is_next_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_stop_payroll" => $approvalAndRequestDetail?->payrollUser?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_onetime_payment" => $approvalAndRequestDetail->is_onetime_payment, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
                "is_from_approval_and_request" => 1, // SOLAR, PEST, TURF, FIBER, MORTGAGE // TO DISPLAY UNDO BUTTON
            ];

            if (!$approvalAndRequestDetail->is_mark_paid && !$approvalAndRequestDetail->is_next_payroll && !$approvalAndRequestDetail->is_onetime_payment) {
                $subTotal += $approvalAndRequestDetail->amount;
            }
        }

        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "reimbursement-breakdown",
            "data" => $response
        ]);
    }

    public function deductionBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $deductionClass = PayrollDeductionLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $deductionClass = PayrollDeductions::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $deductionDetails = $deductionClass::with([
            "payrollUser:id,stop_payroll",
            "payrollCostCenter",
            "payrollReference"
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id,
            "payroll_id" => $request->payroll_id,
        ])
        ->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })
        ->get();

        $deductionAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
                "payroll_type" => "deduction"
            ])->get();

        $data = [];
        $subTotal = 0;
        foreach ($deductionDetails as $deductionDetail) {
            $adjustment = adjustmentColumn($deductionDetail, $deductionAdjustments, "deduction");
            $key = empty($deductionDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($deductionDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($deductionDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $deductionDetail->id,
                "type" => $deductionDetail->payrollCostCenter->name,
                "amount" => $deductionDetail->amount,
                "limit" => $deductionDetail->limit,
                "total" => $deductionDetail->total,
                "outstanding" => $deductionDetail->outstanding,
                "cost_center_id" => $deductionDetail->cost_center_id,
                "adjustment" => $adjustment,
                "operation_type" => "deduction", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $deductionDetail->is_mark_paid,
                "is_next_payroll" => $deductionDetail->is_next_payroll,
                "is_stop_payroll" => $deductionDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $deductionDetail->is_onetime_payment,
                "is_move_to_recon" => $deductionDetail->is_move_to_recon
            ];

            if (!$deductionDetail->is_mark_paid && !$deductionDetail->is_next_payroll && !$deductionDetail->is_onetime_payment && !$deductionDetail->is_move_to_recon) {
                $subTotal += $deductionDetail->total;
            }
        }

        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "deduction-breakdown",
            "data" => $response
        ]);
    }

    public function reconciliationBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "payroll_id" => "required_if:type,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "reconciliation-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        if ($request->type == "pid") {
            $validator = Validator::make($request->all(), [
                "pid" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "reconciliation-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                "user_id" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "reconciliation-breakdown",
                    "error" => $validator->errors()
                ], 400);
            }
        }

        if ($request->is_history) {
            $reconciliationClass = ReconciliationFinalizeHistoryLock::class;
        } else {
            $reconciliationClass = ReconciliationFinalizeHistory::class;
        }

        if ($request->type == "pid") {
        } else {
            $param = [
                "pay_frequency" => $request->pay_frequency,
                "worker_type" => $request->worker_type,
                "pay_period_from" => $request->pay_period_from,
                "pay_period_to" => $request->pay_period_to
            ];

            $reconciliationPayrollDetails = $reconciliationClass::with("payrollReference")->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
            ])->get();

            $data = [];
            $subTotal = 0;
            foreach ($reconciliationPayrollDetails as $reconciliationPayrollDetail) {
                $key = empty($reconciliationPayrollDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($reconciliationPayrollDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($reconciliationPayrollDetail->payrollReference->orig_payto)));
                $payrollModifiedDate = isset($reconciliationPayrollDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($reconciliationPayrollDetail->payrollReference->payroll_modified_date)) : NULL;
                $total = ($reconciliationPayrollDetail->paid_commission + $reconciliationPayrollDetail->paid_override + $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions - $reconciliationPayrollDetail->clawback);

                $data[$key][] = [
                    "payroll_added_date" => date("m-d-Y h:s:a", strtotime($reconciliationPayrollDetail->updated_at)),
                    "start_end" => date("m/d/Y", strtotime($reconciliationPayrollDetail->start_date)) . " to " . date("m/d/Y", strtotime($reconciliationPayrollDetail->end_date)),
                    "commission" => $reconciliationPayrollDetail->paid_commission,
                    "override" => $reconciliationPayrollDetail->paid_override,
                    "clawback" => (-1 * $reconciliationPayrollDetail->clawback),
                    "adjustment" => $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions,
                    "total" => $total,
                    "payout" => $reconciliationPayrollDetail->payout,
                    "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate,
                    "is_mark_paid" => $reconciliationPayrollDetail->is_mark_paid,
                    "is_next_payroll" => $reconciliationPayrollDetail->is_next_payroll,
                    "is_onetime_payment" => $reconciliationPayrollDetail->is_onetime_payment,
                ];

                if (!$reconciliationPayrollDetail->is_mark_paid && !$reconciliationPayrollDetail->is_next_payroll && !$reconciliationPayrollDetail->is_onetime_payment) {
                    $subTotal += $total;
                }
            }

            $user = getUserData($request->user_id);
            $response = [
                "user" => $user,
                "data" => $data,
                "subtotal" => $subTotal
            ];

            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "reconciliation-breakdown",
                "data" => $response
            ]);
        }
    }

    public function wagesBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "wages-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $salaryClass = PayrollHourlySalaryLock::class;
            $overtimeClass = PayrollOvertimeLock::class;
            $adjustmentClass = PayrollAdjustmentDetailLock::class;
        } else {
            $salaryClass = PayrollHourlySalary::class;
            $overtimeClass = PayrollOvertime::class;
            $adjustmentClass = PayrollAdjustmentDetail::class;
        }

        $salaryDetails = $salaryClass::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id,
            "payroll_id" => $request->payroll_id,
        ])->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })->get();

        $salaryAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
                "type" => "hourlysalary",
                "payroll_type" => "hourlysalary"
            ])->get();

        $data = [];
        $hours = [];
        $subTotal = [
            "hours" => 0,
            "total" => 0,
            "adjustment" => 0
        ];
        foreach ($salaryDetails as $salaryDetail) {
            $adjustment = adjustmentColumn($salaryDetail, $salaryAdjustments, "hourlysalary");
            $payrollModifiedDate = isset($salaryDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($salaryDetail->payrollReference->payroll_modified_date)) : NULL;
            $key = empty($salaryDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($salaryDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($salaryDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $salaryDetail->id,
                "date" => $salaryDetail->date ? date("m/d/Y", strtotime($salaryDetail->date)) : NULL,
                "hourly_rate" => $salaryDetail->hourly_rate * 1,
                "salary" => $salaryDetail->salary * 1,
                "regular_hour" => $salaryDetail->regular_hours,
                "total" => $salaryDetail->total * 1,
                "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate,
                "adjustment" => $adjustment,
                "operation_type" => "salary", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $salaryDetail->is_mark_paid,
                "is_next_payroll" => $salaryDetail->is_next_payroll,
                "is_stop_payroll" => $salaryDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $salaryDetail->is_onetime_payment
            ];

            if (!$salaryDetail->is_mark_paid && !$salaryDetail->is_next_payroll && !$salaryDetail->is_onetime_payment) {
                $subTotal["total"] += $salaryDetail->total;
                $subTotal["adjustment"] += $adjustment["adjustment_amount"];

                $hours[] = $salaryDetail->regular_hours ?? "00:00";
            }
        }

        $overtimeDetails = $overtimeClass::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->applyFrequencyFilter($param, [
            "user_id" => $request->user_id,
            "payroll_id" => $request->payroll_id,
        ])->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })->get();

        $overtimeAdjustments = $adjustmentClass::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->applyFrequencyFilter($param, [
                "user_id" => $request->user_id,
                "payroll_id" => $request->payroll_id,
                "type" => "overtime",
                "payroll_type" => "overtime"
            ])->get();

        foreach ($overtimeDetails as $overtimeDetail) {
            $adjustment = adjustmentColumn($overtimeDetail, $overtimeAdjustments, "overtime");
            $payrollModifiedDate = isset($overtimeDetail->payrollReference->payroll_modified_date) ? date("m/d/Y", strtotime($overtimeDetail->payrollReference->payroll_modified_date)) : NULL;
            $key = empty($overtimeDetail->payrollReference) ? "current" : (date("m/d/Y", strtotime($overtimeDetail->payrollReference->orig_payfrom)) . " - " . date("m/d/Y", strtotime($overtimeDetail->payrollReference->orig_payto)));

            $data[$key][] = [
                "id" => $overtimeDetail->id,
                "date" => $overtimeDetail->date ? date("m/d/Y", strtotime($overtimeDetail->date)) : NULL,
                "overtime_rate" => $overtimeDetail->overtime_rate * 1,
                "overtime_hours" => $overtimeDetail->overtime_hours,
                "total" => $overtimeDetail->total * 1,
                "payroll_modified_date" => ($key == "current") ? NULL : $payrollModifiedDate,
                "adjustment" => $adjustment,
                "operation_type" => "overtime", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => $overtimeDetail->is_mark_paid,
                "is_next_payroll" => $overtimeDetail->is_next_payroll,
                "is_stop_payroll" => $overtimeDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => $overtimeDetail->is_onetime_payment
            ];

            if (!$overtimeDetail->is_mark_paid && !$overtimeDetail->is_next_payroll && !$overtimeDetail->is_onetime_payment) {
                $subTotal["total"] += $overtimeDetail->total;
                $subTotal["adjustment"] += $adjustment["adjustment_amount"];

                $hours[] = $overtimeDetail->overtime_hours ?? "00:00";
            }
        }

        $subTotal["hours"] = getTotalHoursFromArray($hours);
        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "wages-breakdown",
            "data" => $response
        ]);
    }

    public function additionalFieldsBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "payroll_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "additional-fields-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $param = [
            "pay_frequency" => $request->pay_frequency,
            "worker_type" => $request->worker_type,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to
        ];

        if ($request->is_history) {
            $payrollClass = PayrollHistory::class;
        } else {
            $payrollClass = Payroll::class;
        }

        $payroll = $payrollClass::applyFrequencyFilter($param, [
            "user_id" => $request->user_id,
            "id" => $request->payroll_id
        ])->whereHas('payrollCustomFields', function ($q) {
            $q->whereNotNull('value')->where('value', '!=', 0);
        })->with(["payrollCustomFields.getColumn", "payrollCustomFields.getApprovedBy"])->first();

        $data = [];
        $subTotal = 0;
        $customFields = $payroll->payrollCustomFields ?? [];
        foreach ($customFields as $customField) {
            $image = null;
            $date = isset($customField->updated_at) ? date("m/d/Y", strtotime($customField->updated_at)) : NULL;
            if ($customField?->getApprovedBy?->image && $customField?->getApprovedBy?->image != "Employee_profile/default-user.png") {
                $image = s3_getTempUrl(config("app.domain_name") . "/" . $customField?->getApprovedBy?->image);
            }

            $data[] =  [
                "id" => $customField->id,
                "custom_field_id" => $customField->column_id,
                "custom_field_name" => $customField?->getColumn?->field_name,
                "amount" => isset($customField->value) ? ($customField->value) : 0,
                "type" => $customField?->getColumn?->field_name ?? "",
                "date" => $date,
                "comment" => $customField->comment,
                "adjustment_by" => $customField->approved_by,
                "adjustment" => [
                    "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_by" => $customField?->getApprovedBy?->first_name . " " . $customField?->getApprovedBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "position_id" => $customField?->getApprovedBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "sub_position_id" => $customField?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_manager" => $customField?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_super_admin" => $customField?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                ]
            ];

            if (!$customField->is_mark_paid && !$customField->is_next_payroll) {
                $subTotal += $customField->value;
            }
        }

        $user = getUserData($request->user_id);
        $response = [
            "user" => $user,
            "data" => $data,
            "subtotal" => $subTotal
        ];

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "additional-fields-breakdown",
            "data" => $response
        ]);
    }

    public function getCustomField()
    {
        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => PayrollSsetup::orderBy('id', 'Asc')->get()]);
    }

    public function updatePayrollCustomField(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payroll_id' => 'required',
            'column_id' => 'required',
            'value' => 'required',
            'comment' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-payroll-custom-field",
                "error" => $validator->errors()
            ], 400);
        }

        $payroll = Payroll::find($request->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-payroll-custom-field",
                "error" => "Payroll not found."
            ], 400);
        }

        CustomField::updateOrCreate(
            [
                'payroll_id' => $request->payroll_id,
                'column_id' => $request->column_id,
            ],
            [
                'user_id' => $payroll->user_id,
                'value' => $request->value,
                'comment' => $request->comment,
                'approved_by' => Auth::id(),
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency,
            ]
        );

        return response()->json([
            'ApiName' => 'update-payroll-custom-field',
            'status' => true,
            'message' => 'Successfully.'
        ]);
    }

    public function exportCustomField(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'pay_frequency' => 'required',
            'worker_type' => 'required|in:1099,w2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "export-custom-field",
                "error" => $validator->errors()
            ], 400);
        }

        $workerType = $request->worker_type;
        if ($request->pay_frequency == FrequencyType::DAILY_PAY_ID) {
            $param = [
                "pay_frequency" => $request->pay_frequency,
                "worker_type" => $workerType,
                "pay_period_from" => $request->end_date,
                "pay_period_to" => $request->end_date
            ];
        } else {
            $param = [
                "pay_frequency" => $request->pay_frequency,
                "worker_type" => $workerType,
                "pay_period_from" => $request->start_date,
                "pay_period_to" => $request->end_date
            ];
        }
        $payrolls = Payroll::select('id', 'user_id')->applyFrequencyFilter($param)->with('payrollCustomFields:id,column_id,payroll_id,value,comment')->get();
        $payrollUserIds = $payrolls->pluck('user_id')->toArray();
        $users = User::select('id', 'first_name', 'last_name')->where(function ($q) use ($payrollUserIds, $workerType) {
            $q->whereIn('id', $payrollUserIds)
                ->orWhere(function ($q2) use ($payrollUserIds, $workerType) {
                    $q2->whereNotIn('id', $payrollUserIds)
                        ->where(['worker_type' => $workerType, 'dismiss' => 0]);
                });
        })->orderBy('first_name', 'ASC')->get();

        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
        $settings = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->orderBy('id', 'Asc')->get()->toArray();
        $columns = array_column($settings, 'field_name');
        $column = ['Rep_name', 'Rep_id'];
        foreach ($columns as $value) {
            array_push($column, $value);
            array_push($column, 'comment');
        }

        $data = [];
        foreach ($users as $user) {
            $custom = [];
            $custom[] = ucfirst($user->first_name . ' ' . $user->last_name);
            $custom[] = $user->id;
            if (in_array($user->id, $payrollUserIds)) {
                $customPayroll = $payrolls->where('user_id', $user->id)->first();
                foreach ($settings as $setting) {
                    $value = '0';
                    $comment = null;
                    $customField = $customPayroll?->payrollCustomFields->where('column_id', $setting['id'])->first();
                    if ($customField) {
                        $value = $customField->value;
                        $comment = $customField->comment;
                    }
                    $custom[] = $value;
                    $custom[] = $comment;
                }
            } else {
                foreach ($settings as $setting) {
                    $custom[] = '0';
                    $custom[] = null;
                }
            }

            $data[] = $custom;
        }

        $title = 'Payroll Custom Field Records';
        $filename = "Payroll_Date_" . $request->start_date . "_to_" . $request->end_date . "_Records.xlsx";
        Excel::store(new PayrollCustomExport($column, $data, $title), 'exports/' . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);
        return response()->json(['url' => getStoragePath('exports/' . $filename)]);
    }

    public function importCustomField(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "file" => "required|mimes:xlsx,xls",
            "start_date" => "required|date_format:Y-m-d",
            "end_date" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "workerType" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "export-custom-field",
                "error" => $validator->errors()
            ], 400);
        }

        $file = $request->file('file');
        $title = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);

        $rows = Excel::toArray([], $file);
        $sheet = $rows[0];
        $validateImportPayroll = $this->validateImportPayrollData($sheet, $request, $title);
        if ($validateImportPayroll['error']) {
            return response()->json([
                'ApiName' => 'import-custom-field',
                'status'  => false,
                'message' => $validateImportPayroll['error_message']
            ], 400);
        }

        $saveOrUpdateCustomRecords = $this->saveOrUpdateCustomField($sheet, $request);
        if (!$saveOrUpdateCustomRecords['status']) {
            return response()->json($saveOrUpdateCustomRecords, 400);
        }

        return response()->json([
            'ApiName' => 'import-custom-field',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            'data' => $saveOrUpdateCustomRecords
        ]);
    }

    protected function validateImportPayrollData($sheet, Request $request, $fileName)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payFrequency = $request->pay_frequency;
        $workerType = $request->workerType ?? '1099';
        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';

        $getImportFileDate = $this->getImportFileDate($fileName);
        $fileStartDate = $getImportFileDate['file_start_date'] ?? null;
        $fileEndDate = $getImportFileDate['file_end_date'] ?? null;

        if (!$fileStartDate || !$fileEndDate) {
            return [
                'error' => true,
                'error_message' => 'The file name is invalid. Please download the sample file and try again.'
            ];
        } else if ($fileStartDate != $startDate || $fileEndDate != $endDate) {
            return [
                'error' => true,
                'error_message' => 'Payroll Start and End date not match!'
            ];
        }

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $fileStartDate,
            "pay_period_to" => $fileEndDate
        ];
        if (Payroll::applyFrequencyFilter($param)->whereIn('finalize_status', ['1', '2'])->first()) {
            return [
                'error' => true,
                'error_message' => 'This payroll is currently finalizing and executing the payroll therefor can not import custom fields.'
            ];
        }

        $sheet = $sheet[0];
        if (count($sheet) == 0) {
            return [
                'error' => true,
                'error_message' => 'The file is empty. Please download the sample file and try again.'
            ];
        }

        if (!in_array("Rep_name", $sheet)) {
            return [
                'error' => true,
                'error_message' => 'The first row is missing the "Rep_name" column header.'
            ];
        }
        if (!in_array("Rep_id", $sheet)) {
            return [
                'error' => true,
                'error_message' => 'The first row is missing the "Rep_id" column header.'
            ];
        }

        if ($sheet[0] != 'Rep_name') {
            return [
                'error' => true,
                'error_message' => 'The "Rep_name" must be in the first column.'
            ];
        }
        if ($sheet[1] != 'Rep_id') {
            return [
                'error' => true,
                'error_message' => 'The "Rep_id" must be in the second column.'
            ];
        }

        $result = array();
        for ($i = 2; $i < count($sheet); $i++) {
            if ($sheet[$i] != 'comment' && $sheet[$i] != 'Start date' && $sheet[$i] != 'End date') {
                if (is_string($sheet[$i])) {
                    $result[$i] = strtolower($sheet[$i]);
                }
            }
        }

        $settings = PayrollSsetup::selectRaw('id, LOWER(field_name) as field_name')->where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->orderBy('id', 'Asc')->get();
        foreach ($settings as $setting) {
            if (in_array($setting->field_name, $result)) {
                $key = array_search($setting->field_name, $result, true);
                $comment = $sheet[$key + 1];
                if ($comment != 'comment') {
                    return [
                        'error' => true,
                        'error_message' => 'The "comment" column is missing after ' . $setting->field_name
                    ];
                }
            }
        }
        return [
            'error' => false,
            'error_message' => null
        ];
    }

    protected function getImportFileDate($title)
    {
        $dates = [];
        $explodeTitle = explode("_", $title);
        if (isset($explodeTitle[2]) && !empty($explodeTitle[2])) {
            $dates['file_start_date'] = $explodeTitle[2];
        }

        if (isset($explodeTitle[4]) && !empty($explodeTitle[4])) {
            $dates['file_end_date'] = $explodeTitle[4];
        }
        return $dates;
    }

    protected function saveOrUpdateCustomField($sheet, $request)
    {
        try {
            DB::beginTransaction();
            $payFrequency = $request->pay_frequency;
            $startDate = $payFrequency == FrequencyType::DAILY_PAY_ID ? $request->end_date : $request->start_date;
            $endDate = $request->end_date;
            $workerType = $request->workerType ?? '1099';
            $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';

            $param = [
                "pay_frequency" => $payFrequency,
                "worker_type" => $workerType,
                "pay_period_from" => $startDate,
                "pay_period_to" => $endDate
            ];

            $columnIds = [];
            $userIdArray = [];
            foreach ($sheet as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    $result = array();
                    for ($i = 2; $i < count($row); $i++) {
                        if ($row[$i] != 'comment' && $row[$i] != 'Start date' && $row[$i] != 'End date') {
                            if (is_string($row[$i])) {
                                $result[$i] = strtolower($row[$i]);
                            }
                        }
                    }

                    $settings = PayrollSsetup::selectRaw('id, LOWER(field_name) as field_name')->where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->orderBy('id', 'Asc')->get();
                    foreach ($settings as $setting) {
                        if (in_array($setting->field_name, $result)) {
                            $key = array_search($setting->field_name, $result, true);
                            $columnIds[$key] = $setting->id;
                        }
                    }
                } else {
                    $columnRefId = $row[1] ?? 0;
                    $userData = User::select('id', 'first_name', 'last_name', 'email', 'sub_position_id')->where(['id' => $columnRefId])->first();
                    if (!$userData) {
                        $userIdArray[] = $columnRefId;
                        continue;
                    }

                    $customArray = [];
                    for ($i = 2; $i < count($row); $i += 2) {
                        if (isset($columnIds[$i]) && (isset($row[$i]) || isset($row[$i + 1]))) {
                            $customArray[] = [
                                'column_id' => $columnIds[$i],
                                'value' => isset($row[$i]) ? (float) $row[$i] : NULL,
                                'comment' => isset($row[$i + 1]) ? $row[$i + 1] : NULL
                            ];
                        }
                    }

                    if (sizeof($customArray) != 0) {
                        $payroll = Payroll::applyFrequencyFilter($param, ['user_id' => $columnRefId])->first();
                        if (!$payroll) {
                            $payroll = Payroll::create([
                                'user_id' => $columnRefId,
                                'pay_frequency' => $payFrequency,
                                'worker_type' => $workerType,
                                'position_id' => $userData->sub_position_id,
                                'pay_period_from' => $startDate,
                                'pay_period_to' => $endDate,
                                'status' => 1
                            ]);
                        }

                        foreach ($customArray as $custom) {
                            CustomField::updateOrCreate(['payroll_id' => $payroll->id, 'column_id' => $custom['column_id']], [
                                'user_id' => $payroll->user_id,
                                'column_id' => $custom['column_id'],
                                'value' => (float) $custom['value'],
                                'comment' => $custom['comment'],
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                'approved_by' => Auth::id(),
                            ]);
                        }
                    }
                }
            }

            if (count($userIdArray) > 0) {
                $string_data = implode(',', $userIdArray);
                $emailData = [];
                $emailData['email'] = auth()->user()->email;
                $emailData['subject'] = 'Payroll Custom Fields Import';
                $emailData['template'] = "<p> We couldn't find user_id " . $string_data . " with the user_id provided in the 'rep_id' column. Please check the user is registered in our system. </p>";
                $this->sendEmailNotification($emailData);
            }

            DB::commit();

            return [
                'status'  => true,
                'message' => 'successfully updated',
                'data' => []
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            return [
                'status'  => false,
                'message' => $e->getMessage() . ' ' . $e->getLine(),
                'error' => $e->getMessage() . ' ' . $e->getLine()
            ];
        }
    }
}
