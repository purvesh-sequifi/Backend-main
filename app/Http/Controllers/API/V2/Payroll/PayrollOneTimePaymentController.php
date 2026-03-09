<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\Crms;
use App\Models\Payroll;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Models\UserOverrides;
use App\Models\FrequencyType;
use App\Models\UserCommission;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use App\Models\PayrollOvertime;
use App\Models\ReconAdjustment;
use App\Core\Traits\EvereeTrait;
use App\Models\UserOverridesLock;
use App\Models\PayrollDeductions;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\CustomFieldHistory;
use App\Models\UserCommissionLock;
use App\Models\ApprovalsAndRequest;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertimeLock;
use App\Models\ReconAdjustmentLock;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\PayrollDeductionLock;
use App\Models\ReconClawbackHistory;
use App\Models\ReconOverrideHistory;
use App\Models\AdvancePaymentSetting;
use App\Models\ReconDeductionHistory;
use App\Models\ClawbackSettlementLock;
use App\Models\ReconCommissionHistory;
use App\Models\ApprovalsAndRequestLock;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollHourlySalaryLock;
use App\Models\ReconClawbackHistoryLock;
use App\Models\ReconOverrideHistoryLock;
use App\Models\ReconDeductionHistoryLock;
use Illuminate\Support\Facades\Validator;
use App\Models\ReconCommissionHistoryLock;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\UserReconciliationCommission;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\UserReconciliationCommissionLock;
use App\Models\ReconciliationFinalizeHistoryLock;

class PayrollOneTimePaymentController extends Controller
{
    use EvereeTrait;

    public function userOneTimePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2",
            "select_type" => "required|in:this_page,all_page"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "user-one-time-payment",
                "errors" => $validator->errors()
            ], 400);
        }

        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                "pid" => "required|array",
                "pid.*" => "required"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "user-one-time-payment",
                    "errors" => $validator->errors()
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                "payroll_id" => "required|array",
                "payroll_id.*" => "integer|exists:payrolls,id"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "user-one-time-payment",
                    "errors" => $validator->errors()
                ], 400);
            }
        }

        $payFrequency = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $workerType = strtolower($request->worker_type);

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        $check = Payroll::whereIn("finalize_status", [1, 2])->count();
        if ($check) {
            return response()->json([
                "status" => false,
                "ApiName" => "user-one-time-payment",
                "message" => "At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.",
                "errors" => [
                    "At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience."
                ]
            ], 400);
        }

        $crmData = Crms::where(['id' => 3, 'status' => 1])->first();
        if (!$crmData) {
            return response()->json([
                "status" => false,
                "ApiName" => "user-one-time-payment",
                "message" => "Sequipay is currently disabled. Please contact the administrator to proceed.",
                "errors" => [
                    "Sequipay is currently disabled. Please contact the administrator to proceed."
                ]
            ], 400);
        }

        $errors = [];
        $adjustmentTypeId = 12;
        if ($request->type == 'pid') {
            $pid = $request->pid;
            $commissionPayrolls = UserCommission::selectRaw("payroll_id, pid, sum(amount) as amount")
                ->applyFrequencyFilter($param)->when($request->select_type == 'this_page', function ($query) use ($pid) {
                    return $query->whereHas("payrollSaleData", function ($q) use ($pid) {
                        $q->whereIn("pid", $pid);
                    });
                })->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->groupBy("payroll_id")->get();

            $overridePayrolls = UserOverrides::selectRaw("payroll_id, pid, sum(amount) as amount")
                ->applyFrequencyFilter($param)->when($request->select_type == 'this_page', function ($query) use ($pid) {
                    return $query->whereHas("payrollSaleData", function ($q) use ($pid) {
                        $q->whereIn("pid", $pid);
                    });
                })->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->groupBy("payroll_id")->get();

            $clawBackPayrolls = ClawbackSettlement::selectRaw("payroll_id, pid, sum(clawback_amount) as amount")
                ->applyFrequencyFilter($param)->when($request->select_type == 'this_page', function ($query) use ($pid) {
                    return $query->whereHas("payrollSaleData", function ($q) use ($pid) {
                        $q->whereIn("pid", $pid);
                    });
                })->where(function ($q) {
                    $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->groupBy("payroll_id")->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::selectRaw("payroll_id, pid, sum(amount) as amount")
                ->whereIn('payroll_type', ['commission', 'overrides'])
                ->applyFrequencyFilter($param)->when($request->select_type == 'this_page', function ($query) use ($pid) {
                    return $query->whereHas("payrollSaleData", function ($q) use ($pid) {
                        $q->whereIn("pid", $pid);
                    });
                })->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->groupBy("payroll_id")->get();

            $data = [];
            $pidData = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $pidData[$commissionPayroll->payroll_id][] = $commissionPayroll->pid;
                if (isset($data[$commissionPayroll->payroll_id])) {
                    $data[$commissionPayroll->payroll_id] += $commissionPayroll->amount;
                } else {
                    $data[$commissionPayroll->payroll_id] = $commissionPayroll->amount;
                }
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $pidData[$overridePayroll->payroll_id][] = $overridePayroll->pid;
                if (isset($data[$overridePayroll->payroll_id])) {
                    $data[$overridePayroll->payroll_id] += $overridePayroll->amount;
                } else {
                    $data[$overridePayroll->payroll_id] = $overridePayroll->amount;
                }
            }
            foreach ($clawBackPayrolls as $clawBackPayroll) {
                $pidData[$clawBackPayroll->payroll_id][] = $clawBackPayroll->pid;
                if (isset($data[$clawBackPayroll->payroll_id])) {
                    $data[$clawBackPayroll->payroll_id] -= $clawBackPayroll->amount;
                } else {
                    $data[$clawBackPayroll->payroll_id] = (0 - $clawBackPayroll->amount);
                }
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $pidData[$adjustmentDetailsPayroll->payroll_id][] = $adjustmentDetailsPayroll->pid;
                if (isset($data[$adjustmentDetailsPayroll->payroll_id])) {
                    $data[$adjustmentDetailsPayroll->payroll_id] += $adjustmentDetailsPayroll->amount;
                } else {
                    $data[$adjustmentDetailsPayroll->payroll_id] = $adjustmentDetailsPayroll->amount;
                }
            }

            foreach ($data as $key => $amount) {
                try {
                    DB::beginTransaction();
                    $payroll = Payroll::with('payrollUser')->find($key);
                    if (!$payroll) {
                        DB::rollBack();
                        $errors[] = "Payroll ID {$key} not found";
                        continue;
                    }

                    $user = $payroll->payrollUser;
                    if (!$user) {
                        DB::rollBack();
                        $errors[] = "User not found";
                        continue;
                    }

                    if ($user->stop_payroll) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} is stopped";
                        continue;
                    }

                    if ($amount <= 0) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} has negative amount";
                        continue;
                    }

                    if ($user && (!$user->employee_id || !$user->everee_workerId)) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} has no employee ID or Everee worker ID";
                        continue;
                    }

                    $check = OneTimePayments::where("adjustment_type_id", $adjustmentTypeId)->count();
                    $prefix = oneTimePaymentPrefix($adjustmentTypeId);
                    if (!empty($check)) {
                        $reqNo = $prefix . str_pad($check + 1, 6, "0", STR_PAD_LEFT);
                    } else {
                        $reqNo = $prefix . str_pad("000000" + 1, 6, "0", STR_PAD_LEFT);
                    }

                    $externalWorkerId = $user->employee_id;
                    $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                    if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                        foreach ($payAblesList['items'] as $payAbleValue) {
                            $this->delete_payable($payAbleValue['id'], $user->id);
                        }
                    }

                    $externalId = 'PNP-' . $user->employee_id . "-" . $key . "-" . strtotime('now');
                    $evereeFields = [
                        "usersdata" => [
                            "employee_id" => $user->employee_id,
                            "everee_workerId" => $user->everee_workerId,
                            "id" => $user->id,
                            "worker_type" => $workerType,
                            "onboardProcess" => $user->onboardProcess
                        ],
                        "everee_external_id" => $externalId,
                        "net_pay" => $amount,
                        "payable_type" => "one time payment",
                        "payable_label" => "one time payment"
                    ];
                    $payable = $this->add_payable($evereeFields, $externalId, "COMMISSION");
                    if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} payable failed!!";
                        continue;
                    }

                    $payableRequest = $this->payable_request($evereeFields, 1);
                    $oneTimePayment = OneTimePayments::create([
                        "user_id" => $user->id,
                        "req_id" => null,
                        "pay_by" => Auth::user()->id,
                        "req_no" => $reqNo ? $reqNo : null,
                        "everee_external_id" => $externalId,
                        "everee_payment_req_id" => isset($payableRequest["success"]["paymentId"]) ? $payableRequest["success"]["paymentId"] : null,
                        "everee_paymentId" => isset($payableRequest["success"]["everee_payment_id"]) ? $payableRequest["success"]["everee_payment_id"] : null,
                        "adjustment_type_id" => $adjustmentTypeId,
                        "amount" => $amount,
                        "description" => null,
                        "pay_date" => date("Y-m-d"),
                        "payment_status" => 3,
                        "everee_status" => 1,
                        "everee_json_response" => isset($payableRequest) ? json_encode($payableRequest) : null,
                        "everee_webhook_response" => null,
                        "everee_payment_status" => 0,
                        "from_payroll" => 1,
                        "pay_frequency" => $payFrequency,
                        "user_worker_type" => $workerType,
                        "pay_period_from" => $payPeriodFrom,
                        "pay_period_to" => $payPeriodTo
                    ]);

                    create_paystub_employee([
                        "one_time_payment_id" => $oneTimePayment->id,
                        "user_id" => $user->id,
                        "pay_period_from" => $payPeriodFrom,
                        "pay_period_to" => $payPeriodTo
                    ], 1);

                    $details = isset($pidData[$key]) ? $pidData[$key] : [];
                    $this->updateOnetimePaymentForPID($payroll, $oneTimePayment, $details);
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $errors[] = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
                }
            }
        } else {
            $payrollIds = $request->payroll_id;

            $checkNegative = Payroll::when($request->select_type == 'this_page', function ($query) use ($payrollIds) {
                return $query->whereIn('id', $payrollIds);
            })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereRaw('(COALESCE(net_pay, 0) - COALESCE(subtract_amount, 0)) < 0')->count();
            if ($checkNegative) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "user-one-time-payment",
                    "message" => "Error: The Net Pay, excluding Reimbursements, should not be negative during the selected Pay Period. Kindly adjust to ensure that the Net Pay (excluding reimbursements) is a positive value.",
                    "errors" => [
                        "Error: The Net Pay, excluding Reimbursements, should not be negative during the selected Pay Period. Kindly adjust to ensure that the Net Pay (excluding reimbursements) is a positive value."
                    ]
                ], 400);
            }

            $payrolls = Payroll::with('payrollUser')->when($request->select_type == 'this_page', function ($query) use ($payrollIds) {
                return $query->whereIn('id', $payrollIds);
            })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->get();
            foreach ($payrolls as $payroll) {
                try {
                    DB::beginTransaction();
                    $user = $payroll->payrollUser;
                    if (!$user) {
                        DB::rollBack();
                        $errors[] = "User not found";
                        continue;
                    }

                    if ($user->stop_payroll) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} is stopped";
                        continue;
                    }

                    if (!$user->employee_id || !$user->everee_workerId) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} has no employee ID or Everee worker ID";
                        continue;
                    }

                    $check = OneTimePayments::where("adjustment_type_id", $adjustmentTypeId)->count();
                    $prefix = oneTimePaymentPrefix($adjustmentTypeId);
                    if (!empty($check)) {
                        $reqNo = $prefix . str_pad($check + 1, 6, "0", STR_PAD_LEFT);
                    } else {
                        $reqNo = $prefix . str_pad("000000" + 1, 6, "0", STR_PAD_LEFT);
                    }

                    $isSuccess = true;
                    $externalId = null;
                    $reimbursementAmount = $payroll->reimbursement ?? 0;
                    $amount = ($payroll->net_pay ?? 0) - ($reimbursementAmount);
                    $negativeCheck = ($payroll->net_pay ?? 0) - ($payroll->subtract_amount ?? 0);
                    if ($negativeCheck < 0) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} has negative amount excluding bonus and reimbursement";
                        continue;
                    }

                    if ($reimbursementAmount < 0) {
                        DB::rollBack();
                        $errors[] = "User {$user->first_name} {$user->last_name} has negative reimbursement amount";
                        continue;
                    }

                    $externalWorkerId = $user->employee_id;
                    $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                    if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                        foreach ($payAblesList['items'] as $payAbleValue) {
                            $this->delete_payable($payAbleValue['id'], $user->id);
                        }
                    }

                    if ($reimbursementAmount > 0) {
                        $cExternalId = 'PNR-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                        $evereeFields = [
                            "usersdata" => [
                                "employee_id" => $user->employee_id,
                                "everee_workerId" => $user->everee_workerId,
                                "id" => $user->id,
                                "worker_type" => $workerType,
                                "onboardProcess" => $user->onboardProcess
                            ],
                            "everee_external_id" => $cExternalId,
                            "net_pay" => $reimbursementAmount,
                            "payable_type" => "one time payment",
                            "payable_label" => "one time payment"
                        ];
                        $payable = $this->add_payable($evereeFields, $cExternalId, "REIMBURSEMENT");
                        if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                            DB::rollBack();
                            $isSuccess = false;
                            $errors[] = "User {$user->first_name} {$user->last_name} reimbursement payable failed!!";
                            continue;
                        }

                        if (empty($externalId)) {
                            $externalId = $cExternalId;
                        } else {
                            $externalId .= ',' . $cExternalId;
                        }
                    }

                    if ($amount > 0) {
                        $bonusAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept'])->whereIn('adjustment_type_id', [3, 6])->sum('amount') ?? 0;
                        $bonusExcludedNetPay = $amount - $bonusAmount;
                        if ($bonusExcludedNetPay > 0) {
                            $cExternalId = 'PNP-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                            $evereeFields = [
                                "usersdata" => [
                                    "employee_id" => $user->employee_id,
                                    "everee_workerId" => $user->everee_workerId,
                                    "id" => $user->id,
                                    "worker_type" => $workerType,
                                    "onboardProcess" => $user->onboardProcess
                                ],
                                "everee_external_id" => $cExternalId,
                                "net_pay" => $bonusExcludedNetPay,
                                "payable_type" => "one time payment",
                                "payable_label" => "one time payment"
                            ];
                            $payable = $this->add_payable($evereeFields, $cExternalId, "COMMISSION");
                            if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                DB::rollBack();
                                $isSuccess = false;
                                $errors[] = "User {$user->first_name} {$user->last_name} payable failed!!";
                                continue;
                            }

                            if (empty($externalId)) {
                                $externalId = $cExternalId;
                            } else {
                                $externalId .= ',' . $cExternalId;
                            }

                            if ($bonusAmount > 0) {
                                $cExternalId = 'PNB-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                                $evereeFields = [
                                    "usersdata" => [
                                        "employee_id" => $user->employee_id,
                                        "everee_workerId" => $user->everee_workerId,
                                        "id" => $user->id,
                                        "worker_type" => $workerType,
                                        "onboardProcess" => $user->onboardProcess
                                    ],
                                    "everee_external_id" => $cExternalId,
                                    "net_pay" => $bonusAmount,
                                    "payable_type" => "one time payment",
                                    "payable_label" => "one time payment"
                                ];
                                $payable = $this->add_payable($evereeFields, $cExternalId, "BONUS");
                                if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                    DB::rollBack();
                                    $isSuccess = false;
                                    $errors[] = "User {$user->first_name} {$user->last_name} bonus payable failed!!";
                                    continue;
                                }

                                if (empty($externalId)) {
                                    $externalId = $cExternalId;
                                } else {
                                    $externalId .= ',' . $cExternalId;
                                }
                            }
                        } else {
                            $bonusAmount = $bonusExcludedNetPay + $bonusAmount;
                            if ($bonusAmount > 0) {
                                $cExternalId = 'PNB-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                                $evereeFields = [
                                    "usersdata" => [
                                        "employee_id" => $user->employee_id,
                                        "everee_workerId" => $user->everee_workerId,
                                        "id" => $user->id,
                                        "worker_type" => $workerType,
                                        "onboardProcess" => $user->onboardProcess
                                    ],
                                    "everee_external_id" => $cExternalId,
                                    "net_pay" => $bonusAmount,
                                    "payable_type" => "one time payment",
                                    "payable_label" => "one time payment"
                                ];
                                $payable = $this->add_payable($evereeFields, $cExternalId, "BONUS");
                                if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                    DB::rollBack();
                                    $isSuccess = false;
                                    $errors[] = "User {$user->first_name} {$user->last_name} bonus payable failed!!";
                                    continue;
                                }

                                if (empty($externalId)) {
                                    $externalId = $cExternalId;
                                } else {
                                    $externalId .= ',' . $cExternalId;
                                }
                            }
                        }
                    }

                    if ($isSuccess) {
                        $payableRequest = $this->payable_request($evereeFields, 1);
                        $oneTimePayment = OneTimePayments::create([
                            "user_id" => $user->id,
                            "req_id" => null,
                            "pay_by" => Auth::user()->id,
                            "req_no" => $reqNo ? $reqNo : null,
                            "everee_external_id" => $externalId,
                            "everee_payment_req_id" => isset($payableRequest["success"]["paymentId"]) ? $payableRequest["success"]["paymentId"] : null,
                            "everee_paymentId" => isset($payableRequest["success"]["everee_payment_id"]) ? $payableRequest["success"]["everee_payment_id"] : null,
                            "adjustment_type_id" => $adjustmentTypeId,
                            "amount" => $amount,
                            "description" => null,
                            "pay_date" => date("Y-m-d"),
                            "payment_status" => 3,
                            "everee_status" => 1,
                            "everee_json_response" => isset($payableRequest) ? json_encode($payableRequest) : null,
                            "everee_webhook_response" => null,
                            "everee_payment_status" => 0,
                            "from_payroll" => 1,
                            "pay_frequency" => $payFrequency,
                            "user_worker_type" => $workerType,
                            "pay_period_from" => $payPeriodFrom,
                            "pay_period_to" => $payPeriodTo
                        ]);

                        create_paystub_employee([
                            "one_time_payment_id" => $oneTimePayment->id,
                            "user_id" => $user->id,
                            "pay_period_from" => $payPeriodFrom,
                            "pay_period_to" => $payPeriodTo
                        ], 1);

                        $this->updateOnetimePaymentForUser($payroll, $oneTimePayment);
                        DB::commit();
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $errors[] = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
                }
            }
        }

        if (sizeof($errors) > 0) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'One time payment created successfully',
            'errors' => []
        ]);
    }

    public function updateOnetimePaymentForPID($payroll, $oneTimePayment, $details)
    {
        $payrollId = $payroll->id;
        UserCommission::where('payroll_id', $payrollId)->whereIn('pid', $details)->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        UserOverrides::where('payroll_id', $payrollId)->whereIn('pid', $details)->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        ClawbackSettlement::where('payroll_id', $payrollId)->whereIn('pid', $details)->where(function ($q) {
            $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
        })->where("status", "!=", "3")->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        PayrollAdjustmentDetail::where('payroll_id', $payrollId)->whereIn('pid', $details)->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->whereIn('payroll_type', ['commission', 'overrides'])->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->where("status", "!=", "3")->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);

        // Define amount column mapping for filtering zero amounts
        $modelAmountColumns = [
            UserCommission::class => 'amount',
            UserOverrides::class => 'amount',
            ClawbackSettlement::class => 'clawback_amount',
            PayrollAdjustmentDetail::class => 'amount'
        ];

        $modelToLocks = [
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class
        ];

        foreach ($modelToLocks as $model => $modelToLock) {
            $amountColumn = $modelAmountColumns[$model];
            // Only copy non-zero amount records for one-time payments
            $addToLock = $model::where(['one_time_payment_id' => $oneTimePayment->id])
                ->where(function ($q) use ($amountColumn) {
                    $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
                })->get();

            $addToLock->each(function ($value) use ($modelToLock) {
                $modelToLock::updateOrCreate(['id' => $value->id, 'payroll_id' => $value->payroll_id], $value->toArray());
            });
        }

        $commission = UserCommissionLock::where(['one_time_payment_id' => $oneTimePayment->id])->sum('amount');
        $override = UserOverridesLock::where(['one_time_payment_id' => $oneTimePayment->id])->sum('amount');
        $clawBack = ClawbackSettlementLock::selectRaw("
            SUM(CASE WHEN type = 'commission' THEN clawback_amount ELSE 0 END) as commissions,
            SUM(CASE WHEN type = 'overrides' THEN clawback_amount ELSE 0 END) as overrides
        ")->where(['one_time_payment_id' => $oneTimePayment->id])->first();
        $adjustment = PayrollAdjustmentDetailLock::where(['one_time_payment_id' => $oneTimePayment->id])->sum('amount');

        $commissionClawBack = $clawBack->commissions ?? 0;
        $overrideClawBack = $clawBack->overrides ?? 0;
        $onlyCommission = $commission;
        $onlyOverride = $override;
        $commission = $commission - $commissionClawBack;
        $override = $override - $overrideClawBack;

        $payroll->commission = $payroll->commission - $commission;
        $payroll->override = $payroll->override - $override;
        $payroll->adjustment = $payroll->adjustment - $adjustment;
        $payroll->gross_pay = $payroll->gross_pay - ($onlyCommission - $onlyOverride - $adjustment);
        $payroll->net_pay = $payroll->net_pay - ($commission + $override + $adjustment);
        $payroll->save();
    }

    public function updateOnetimePaymentForUser($payroll, $oneTimePayment)
    {
        $payrollId = $payroll->id;
        UserCommission::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        UserOverrides::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        ClawbackSettlement::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        PayrollAdjustmentDetail::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        PayrollDeductions::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        PayrollHourlySalary::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        PayrollOvertime::where('payroll_id', $payrollId)->where("status", "!=", "3")->where(function ($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(['status' => 3, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        ApprovalsAndRequest::where('payroll_id', $payrollId)->where("status", "!=", "Paid")->where(function ($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })->where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(['status' => 'Paid', 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);

        $requests = ApprovalsAndRequest::where(['status' => 'Paid', 'payroll_id' => $payrollId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id])->get();
        $requests->each(function ($request) use ($oneTimePayment) {
            $childReqAmount = ApprovalsAndRequest::where(['parent_id' => $request->parent_id, 'status' => 'Paid', 'one_time_payment_id' => $oneTimePayment->id])->sum('amount');
            $parentReqAmount = ApprovalsAndRequest::where(['id' => $request->parent_id, 'status' => 'Accept', 'one_time_payment_id' => $oneTimePayment->id])->sum('amount');
            if ($childReqAmount == $parentReqAmount) {
                ApprovalsAndRequest::where('id', $request->parent_id)->update(['status' => 'Paid']);
            }
        });

        // Define amount column mapping for filtering zero amounts
        $modelAmountColumns = [
            UserCommission::class => 'amount',
            UserOverrides::class => 'amount',
            ClawbackSettlement::class => 'clawback_amount',
            PayrollAdjustmentDetail::class => 'amount',
            PayrollDeductions::class => 'total',
            PayrollHourlySalary::class => 'total',
            PayrollOvertime::class => 'total'
        ];

        $modelToLocks = [
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class,
            PayrollDeductions::class => PayrollDeductionLock::class,
            PayrollHourlySalary::class => PayrollHourlySalaryLock::class,
            PayrollOvertime::class => PayrollOvertimeLock::class
        ];

        foreach ($modelToLocks as $model => $modelToLock) {
            $amountColumn = $modelAmountColumns[$model];

            // Only copy non-zero amount records for one-time payments
            $addToLock = $model::where(['one_time_payment_id' => $oneTimePayment->id])
                ->where(function ($q) use ($amountColumn) {
                    $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
                })->get();

            $addToLock->each(function ($value) use ($modelToLock) {
                $modelToLock::updateOrCreate(['id' => $value->id], $value->toArray());
            });
        }

        // ApprovalsAndRequest - only copy non-zero amounts
        $approvalsAndRequestData = ApprovalsAndRequest::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('amount')->where('amount', '!=', 0);
            })->get();
        foreach ($approvalsAndRequestData as $value) {
            ApprovalsAndRequestLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        $customFieldRecords = CustomField::where(['payroll_id' => $payrollId])->where(function ($q) {
            $q->whereNotNull('value')->where('value', '!=', 0);
        })->get();
        $customFieldRecords->each(function ($value) use ($oneTimePayment) {
            $data = $value->only(['user_id', 'payroll_id', 'column_id', 'value', 'comment', 'approved_by', 'is_mark_paid', 'is_next_payroll', 'pay_period_from', 'pay_period_to']);
            $data['is_onetime_payment'] = 1;
            $data['one_time_payment_id'] = $oneTimePayment->id;
            CustomFieldHistory::updateOrCreate(['payroll_id' => $value->payroll_id, 'column_id' => $value->column_id], $data);
        });
        CustomField::where(['payroll_id' => $payrollId])->delete();

        $startDateNext = NULL;
        $endDateNext = NULL;
        $advanceRequestStatus = "Approved";
        $advanceSetting = AdvancePaymentSetting::first();
        if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
            $startDateNext = $payroll->pay_period_from;
            $endDateNext = $payroll->pay_period_to;
            $advanceRequestStatus = "Accept";
        }

        $approvalAndRequests = ApprovalsAndRequest::where(['one_time_payment_id' => $oneTimePayment->id, 'status' => 'Paid', 'adjustment_type_id' => 4])->where('amount', '>', 0)->whereNotNull('req_no')->get();
        foreach ($approvalAndRequests as $approvalAndRequest) {
            if (!ApprovalsAndRequest::where(['parent_id' => $approvalAndRequest->id])->first()) {
                ApprovalsAndRequest::create([
                    'user_id' => $approvalAndRequest->user_id,
                    'parent_id' => $approvalAndRequest->id,
                    'manager_id' => $approvalAndRequest->manager_id,
                    'approved_by' => $approvalAndRequest->approved_by,
                    'adjustment_type_id' => $approvalAndRequest->adjustment_type_id,
                    'state_id' => $approvalAndRequest->state_id,
                    'dispute_type' => $approvalAndRequest->dispute_type,
                    'customer_pid' => $approvalAndRequest->customer_pid,
                    'cost_tracking_id' => $approvalAndRequest->cost_tracking_id,
                    'cost_date' => $approvalAndRequest->cost_date,
                    'request_date' => $approvalAndRequest->request_date,
                    'amount' => (0 - $approvalAndRequest->amount),
                    'status' => $advanceRequestStatus,
                    'description' => 'Advance payment request Id: ' . $approvalAndRequest->req_no . ' Date of request: ' . date("m/d/Y"),
                    'pay_period_from' => isset($startDateNext) ? $startDateNext : NULL,
                    'pay_period_to' => isset($endDateNext) ? $endDateNext : NULL,
                    'user_worker_type' => $approvalAndRequest->user_worker_type,
                    'pay_frequency' => $approvalAndRequest->pay_frequency
                ]);
            }
        }

        ReconciliationFinalizeHistory::where(['payroll_id' => $payrollId, 'status' => "payroll",  'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3, "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconAmount = ReconciliationFinalizeHistory::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('net_amount')->where('net_amount', '!=', 0);
            })->get();
        foreach ($finalizeReconAmount as $value) {
            ReconciliationFinalizeHistoryLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        UserReconciliationCommission::where(['payroll_id' => $payrollId, 'is_onetime_payment' => 0])->where("status", "!=", "paid")->update(['status' => 'paid', 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id]);
        $userReconciliationCommissionData = UserReconciliationCommission::where(['status' => 'paid', 'payroll_id' => $payrollId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->whereNotNull('amount')->where('amount', '!=', 0);
                })->orWhere(function ($query) {
                    $query->whereNotNull('total_due')->where('total_due', '!=', 0);
                });
            })->get();
        foreach ($userReconciliationCommissionData as $value) {
            UserReconciliationCommissionLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        ReconCommissionHistory::where(['payroll_id' => $payrollId, 'status' => "payroll", 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3, "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconCommissionAmount = ReconCommissionHistory::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->whereNotNull('total_amount')->where('total_amount', '!=', 0);
                })->orWhere(function ($query) {
                    $query->whereNotNull('paid_amount')->where('paid_amount', '!=', 0);
                });
            })->get();
        foreach ($finalizeReconCommissionAmount as $value) {
            ReconCommissionHistoryLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        ReconOverrideHistory::where(['payroll_id' => $payrollId, 'status' => "payroll", 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3, "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconOverrideAmount = ReconOverrideHistory::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->where(function ($query) {
                    $query->whereNotNull('total_amount')->where('total_amount', '!=', 0);
                })->orWhere(function ($query) {
                    $query->whereNotNull('paid')->where('paid', '!=', 0);
                });
            })->get();
        foreach ($finalizeReconOverrideAmount as $value) {
            ReconOverrideHistoryLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        ReconClawbackHistory::where(['payroll_id' => $payrollId, 'status' => "payroll", 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3, "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconClawbackAmount = ReconClawbackHistory::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('paid_amount')->where('paid_amount', '!=', 0);
            })->get();
        foreach ($finalizeReconClawbackAmount as $value) {
            ReconClawbackHistoryLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        ReconAdjustment::where(['payroll_id' => $payrollId, 'is_onetime_payment' => 0])->where("payroll_execute_status", "!=", 3)->update(["payroll_execute_status" => 3, "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconAdjustmentAmount = ReconAdjustment::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('adjustment_amount')->where('adjustment_amount', '!=', 0);
            })->get();
        foreach ($finalizeReconAdjustmentAmount as $value) {
            ReconAdjustmentLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        ReconDeductionHistory::where(['payroll_id' => $payrollId, 'status' => "payroll", 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(["payroll_executed_status" => "3", "is_onetime_payment" => 1, "one_time_payment_id" => $oneTimePayment->id]);
        $finalizeReconDeductionAmount = ReconDeductionHistory::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('amount')->where('amount', '!=', 0);
            })->get();
        foreach ($finalizeReconDeductionAmount as $value) {
            ReconDeductionHistoryLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        $remainingPayrollData = Payroll::where('id', $payrollId)
            ->where(function ($q) {
                $q->whereHas('payrollSalary', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1);
                        });
                })->orWhereHas('payrollOvertime', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1);
                        });
                })->orWhereHas('payrollCommission', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })->orWhereHas('payrollOverride', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })->orWhereHas('payrollClawBack', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })->orWhereHas('userRequestApprove', function ($q) {
                    $q->where('status', '!=', 'Paid')
                        ->whereNotIn('approvals_and_requests.adjustment_type_id', [7, 8, 9])
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1);
                        });
                })->orWhereHas('payrollPayrollAdjustmentDetails', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })->orWhereHas('payrollDeductions', function ($q) {
                    $q->where('status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })->orWhereHas('payrollReconciliation', function ($q) {
                    $q->where('payroll_execute_status', '!=', '3')
                        ->where(function ($q) {
                            $q->where('is_mark_paid', 1)
                                ->orWhere('is_next_payroll', 1);
                        });
                });
            })->first();

        if (!$remainingPayrollData) {
            $evereeStatus = $oneTimePayment->everee_payment_req_id ? 1 : 2;

            $param = [
                "pay_frequency" => $payroll->pay_frequency,
                "worker_type" => $payroll->worker_type,
                "pay_period_from" => $payroll->pay_period_from,
                "pay_period_to" => $payroll->pay_period_to
            ];
            $hourlySalarySum = PayrollHourlySalaryLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('total');
            $overtimeSum = PayrollOvertimeLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('total');
            $commissionSum = UserCommissionLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('amount');
            $overrideSum = UserOverridesLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('amount');
            $reconSum = ReconciliationFinalizeHistoryLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id, 'status' => "payroll",  'payroll_execute_status' => 3])->sum('net_amount');
            $clawBackData = ClawbackSettlementLock::selectRaw('
                SUM(CASE WHEN type = "commission" THEN clawback_amount ELSE 0 END) as commission_claw_back_sum,
                SUM(CASE WHEN type = "overrides" THEN clawback_amount ELSE 0 END) as override_claw_back_sum
            ')->applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->whereIn('type', ['commission', 'overrides'])->first();
            $commissionClawBackSum = $clawBackData->commission_claw_back_sum ?? 0;
            $overrideClawBackSum = $clawBackData->override_claw_back_sum ?? 0;

            $adjustmentSum = PayrollAdjustmentDetailLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('amount');
            $approvalData = ApprovalsAndRequestLock::selectRaw('
                SUM(CASE WHEN adjustment_type_id NOT IN (2, 5, 7, 8, 9) THEN amount ELSE 0 END) as approvals_and_request_sum,
                SUM(CASE WHEN adjustment_type_id = 5 THEN amount ELSE 0 END) as fine_and_fee_sum,
                SUM(CASE WHEN adjustment_type_id = 2 THEN amount ELSE 0 END) as reimbursement_sum
            ')->applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->first();
            $approvalsAndRequestSum = $approvalData->approvals_and_request_sum ?? 0;
            $fineAndFeeSum = $approvalData->fine_and_fee_sum ?? 0;
            $reimbursementSum = $approvalData->reimbursement_sum ?? 0;

            $deductionSum = PayrollDeductionLock::applyFrequencyFilter($param, ['user_id' => $payroll->user_id])->sum('total');
            $customFieldSum = CustomFieldHistory::where(['payroll_id' => $payroll->id])->sum('value');

            $finalCommissionSum = $commissionSum - $commissionClawBackSum;
            $finalOverrideSum = $overrideSum - $overrideClawBackSum;
            $finalAdjustmentSum = ($adjustmentSum + $approvalsAndRequestSum) - $fineAndFeeSum;
            $netPaySum = ($hourlySalarySum + $overtimeSum + $finalCommissionSum + $finalOverrideSum + $finalAdjustmentSum + $reimbursementSum + $customFieldSum) - $deductionSum;

            PayrollHistory::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'position_id' => $payroll->position_id,
                'everee_status' => $evereeStatus,
                'commission' => $finalCommissionSum,
                'override' => $finalOverrideSum,
                'reimbursement' => $reimbursementSum,
                'clawback' => 0,
                'deduction' => $deductionSum,
                'adjustment' => $finalAdjustmentSum,
                'reconciliation' => $reconSum,
                'hourly_salary' => $hourlySalarySum,
                'overtime' => $overtimeSum,
                'net_pay' => $netPaySum,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'status' => '3',
                'pay_type' => 'Bank',
                'pay_frequency_date' => $payroll->created_at,
                'everee_external_id' => $oneTimePayment->everee_external_id,
                'everee_payment_status' => $evereeStatus,
                'everee_paymentId' => $oneTimePayment->everee_paymentId,
                'everee_payment_requestId' => $oneTimePayment->everee_payment_req_id,
                'everee_json_response' => $oneTimePayment->everee_json_response,
                'worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency,
                'is_onetime_payment' => 1,
                'one_time_payment_id' => $oneTimePayment->id
            ]);
            Payroll::where('id', $payroll->id)->delete();
        } else {
            $param = [
                "pay_frequency" => $payroll->pay_frequency,
                "worker_type" => $payroll->worker_type,
                "pay_period_from" => $payroll->pay_period_from,
                "pay_period_to" => $payroll->pay_period_to
            ];
            reCalculatePayrollData($payroll->id, $param);
        }
    }

    public function singleOneTimePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "data" => "required|array",
            "data.*.id" => "required",
            "data.*.operation_type" => "required",
            "pay_period_from" => "required",
            "pay_period_to" => "required",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "single-one-time-payment",
                "errors" => $validator->errors()
            ], 400);
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->count();
        if ($payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "single-one-time-payment",
                "message" => "At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience."
            ], 400);
        }

        $amounts = [];
        $object = [
            'amount' => 0,
            'bonus' => 0,
            'reimbursement' => 0
        ];
        foreach ($request->data as $item) {
            if ($item['operation_type'] == "commission") {
                $commissionDetail = UserCommission::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($commissionDetail) {
                    if (!isset($amounts[$commissionDetail->payroll_id])) {
                        $amounts[$commissionDetail->payroll_id] = $object;
                    }
                    $amounts[$commissionDetail->payroll_id]['amount'] += $commissionDetail->amount;

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $commissionDetail->id, "type" => $commissionDetail->schema_type, "payroll_type" => "commission", "adjustment_type" => $commissionDetail->schema_type, "is_onetime_payment" => 0])->sum('amount') ?? 0;
                    $amounts[$commissionDetail->payroll_id]['amount'] += $payrollAdjustmentDetail;
                }
            } else if ($item['operation_type'] == "override") {
                $overrideDetail = UserOverrides::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($overrideDetail) {
                    if (!isset($amounts[$overrideDetail->payroll_id])) {
                        $amounts[$overrideDetail->payroll_id] = $object;
                    }
                    $amounts[$overrideDetail->payroll_id]['amount'] += $overrideDetail->amount;

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $overrideDetail->id, "type" => $overrideDetail->type, "payroll_type" => "overrides", "adjustment_type" => $overrideDetail->type, "is_onetime_payment" => 0])->sum('amount') ?? 0;
                    $amounts[$overrideDetail->payroll_id]['amount'] += $payrollAdjustmentDetail;
                }
            } else if ($item['operation_type'] == "clawback") {
                $clawBackDetail = ClawbackSettlement::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($clawBackDetail) {
                    if (!isset($amounts[$clawBackDetail->payroll_id])) {
                        $amounts[$clawBackDetail->payroll_id] = $object;
                    }
                    $amounts[$clawBackDetail->payroll_id]['amount'] -= $clawBackDetail->clawback_amount;

                    if ($clawBackDetail->type == "commission") {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackDetail->id, "type" => "clawback", "payroll_type" => "commission", "adjustment_type" => $clawBackDetail->schema_type, "is_onetime_payment" => 0])->sum('amount') ?? 0;
                        $amounts[$clawBackDetail->payroll_id]['amount'] += $payrollAdjustmentDetail;
                    } else {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackDetail->id, "type" => "clawback", "payroll_type" => "overrides", "adjustment_type" => $clawBackDetail->type, "is_onetime_payment" => 0])->sum('amount') ?? 0;
                        $amounts[$clawBackDetail->payroll_id]['amount'] += $payrollAdjustmentDetail;
                    }
                }
            } else if ($item['operation_type'] == "request_approval") {
                $requestApprovalDetail = ApprovalsAndRequest::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($requestApprovalDetail) {
                    if (!isset($amounts[$requestApprovalDetail->payroll_id])) {
                        $amounts[$requestApprovalDetail->payroll_id] = $object;
                    }

                    if ($requestApprovalDetail->adjustment_type_id == 3 || $requestApprovalDetail->adjustment_type_id == 6) {
                        $amounts[$requestApprovalDetail->payroll_id]['bonus'] += $requestApprovalDetail->amount;
                    } else {
                        if ($requestApprovalDetail->adjustment_type_id == 5) {
                            $amounts[$requestApprovalDetail->payroll_id]['amount'] -= $requestApprovalDetail->amount;
                        } else {
                            $amounts[$requestApprovalDetail->payroll_id]['amount'] += $requestApprovalDetail->amount;
                        }
                    }
                }
            } else if ($item['operation_type'] == "reimbursement") {
                $requestApprovalDetail = ApprovalsAndRequest::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($requestApprovalDetail) {
                    if (!isset($amounts[$requestApprovalDetail->payroll_id])) {
                        $amounts[$requestApprovalDetail->payroll_id] = $object;
                    }
                    $amounts[$requestApprovalDetail->payroll_id]['reimbursement'] += $requestApprovalDetail->amount;
                }
            } else {
                return response()->json([
                    "status" => false,
                    "ApiName" => "single-one-time-payment",
                    "message" => "Invalid type detected, please check payload!!",
                    "errors" => [
                        "Invalid type detected, please check payload!!"
                    ]
                ], 400);
            }
        }

        $adjustmentTypeId = 10;
        foreach ($amounts as $key => $amount) {
            try {
                DB::beginTransaction();
                $payroll = Payroll::with('payrollUser')->find($key);
                if (!$payroll) {
                    DB::rollBack();
                    $errors[] = "Payroll ID {$key} not found";
                    continue;
                }

                $user = $payroll->payrollUser;
                if (!$user) {
                    DB::rollBack();
                    $errors[] = "User not found";
                    continue;
                }

                if ($user->stop_payroll) {
                    DB::rollBack();
                    $errors[] = "User {$user->first_name} {$user->last_name} is stopped payroll";
                    continue;
                }

                if (($amount['amount'] + $amount['reimbursement'] + $amount['bonus']) <= 0) {
                    DB::rollBack();
                    $errors[] = "User {$user->first_name} {$user->last_name} has negative amount";
                    continue;
                }

                if ($user && (!$user->employee_id || !$user->everee_workerId)) {
                    DB::rollBack();
                    $errors[] = "User {$user->first_name} {$user->last_name} has no employee ID or Everee worker ID";
                    continue;
                }

                $check = OneTimePayments::where("adjustment_type_id", $adjustmentTypeId)->count();
                $prefix = oneTimePaymentPrefix($adjustmentTypeId);
                if (!empty($check)) {
                    $reqNo = $prefix . str_pad($check + 1, 6, "0", STR_PAD_LEFT);
                } else {
                    $reqNo = $prefix . str_pad("000000" + 1, 6, "0", STR_PAD_LEFT);
                }

                $externalWorkerId = $user->employee_id;
                $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                    foreach ($payAblesList['items'] as $payAbleValue) {
                        $this->delete_payable($payAbleValue['id'], $user->id);
                    }
                }

                $isSuccess = true;
                $externalId = null;
                if (($amount['amount'] + $amount['reimbursement'] + $amount['bonus']) > 0) {
                    if ($amount['reimbursement'] > 0) {
                        $cExternalId = 'PNR-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                        $evereeFields = [
                            "usersdata" => [
                                "employee_id" => $user->employee_id,
                                "everee_workerId" => $user->everee_workerId,
                                "id" => $user->id,
                                "worker_type" => $payroll->worker_type,
                                "onboardProcess" => $user->onboardProcess
                            ],
                            "everee_external_id" => $cExternalId,
                            "net_pay" => $amount['reimbursement'],
                            "payable_type" => "one time payment",
                            "payable_label" => "one time payment"
                        ];
                        $payable = $this->add_payable($evereeFields, $cExternalId, "REIMBURSEMENT");
                        if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                            DB::rollBack();
                            $isSuccess = false;
                            $errors[] = "User {$user->first_name} {$user->last_name} reimbursement payable failed!!";
                            continue;
                        }

                        if (empty($externalId)) {
                            $externalId = $cExternalId;
                        } else {
                            $externalId .= ',' . $cExternalId;
                        }
                    }

                    $netPay = $amount['amount'] + $amount['bonus'];
                    if ($netPay > 0) {
                        $bonusAmount = $amount['bonus'];
                        $bonusExcludedNetPay = $amount['amount'];
                        if ($bonusExcludedNetPay > 0) {
                            $cExternalId = 'PNP-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                            $evereeFields = [
                                "usersdata" => [
                                    "employee_id" => $user->employee_id,
                                    "everee_workerId" => $user->everee_workerId,
                                    "id" => $user->id,
                                    "worker_type" => $payroll->worker_type,
                                    "onboardProcess" => $user->onboardProcess
                                ],
                                "everee_external_id" => $cExternalId,
                                "net_pay" => $bonusExcludedNetPay,
                                "payable_type" => "one time payment",
                                "payable_label" => "one time payment"
                            ];
                            $payable = $this->add_payable($evereeFields, $cExternalId, "COMMISSION");
                            if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                DB::rollBack();
                                $isSuccess = false;
                                $errors[] = "User {$user->first_name} {$user->last_name} payable failed!!";
                                continue;
                            }

                            if (empty($externalId)) {
                                $externalId = $cExternalId;
                            } else {
                                $externalId .= ',' . $cExternalId;
                            }

                            if ($bonusAmount > 0) {
                                $cExternalId = 'PNB-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                                $evereeFields = [
                                    "usersdata" => [
                                        "employee_id" => $user->employee_id,
                                        "everee_workerId" => $user->everee_workerId,
                                        "id" => $user->id,
                                        "worker_type" => $payroll->worker_type,
                                        "onboardProcess" => $user->onboardProcess
                                    ],
                                    "everee_external_id" => $cExternalId,
                                    "net_pay" => $bonusAmount,
                                    "payable_type" => "one time payment",
                                    "payable_label" => "one time payment"
                                ];
                                $payable = $this->add_payable($evereeFields, $cExternalId, "BONUS");
                                if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                    DB::rollBack();
                                    $isSuccess = false;
                                    $errors[] = "User {$user->first_name} {$user->last_name} bonus payable failed!!";
                                    continue;
                                }

                                if (empty($externalId)) {
                                    $externalId = $cExternalId;
                                } else {
                                    $externalId .= ',' . $cExternalId;
                                }
                            }
                        } else {
                            if ($bonusAmount > 0) {
                                $cExternalId = 'PNB-' . $user->employee_id . "-" . $payroll->id . "-" . strtotime('now');
                                $evereeFields = [
                                    "usersdata" => [
                                        "employee_id" => $user->employee_id,
                                        "everee_workerId" => $user->everee_workerId,
                                        "id" => $user->id,
                                        "worker_type" => $payroll->worker_type,
                                        "onboardProcess" => $user->onboardProcess
                                    ],
                                    "everee_external_id" => $cExternalId,
                                    "net_pay" => $bonusAmount,
                                    "payable_type" => "one time payment",
                                    "payable_label" => "one time payment"
                                ];
                                $payable = $this->add_payable($evereeFields, $cExternalId, "BONUS");
                                if ((isset($payable["success"]["status"]) && !$payable["success"]["status"])) {
                                    DB::rollBack();
                                    $isSuccess = false;
                                    $errors[] = "User {$user->first_name} {$user->last_name} bonus payable failed!!";
                                    continue;
                                }

                                if (empty($externalId)) {
                                    $externalId = $cExternalId;
                                } else {
                                    $externalId .= ',' . $cExternalId;
                                }
                            }
                        }
                    }

                    if ($isSuccess) {
                        $payableRequest = $this->payable_request($evereeFields, 1);
                        $oneTimePayment = OneTimePayments::create([
                            "user_id" => $user->id,
                            "req_id" => null,
                            "pay_by" => Auth::user()->id,
                            "req_no" => $reqNo ? $reqNo : null,
                            "everee_external_id" => $externalId,
                            "everee_payment_req_id" => isset($payableRequest["success"]["paymentId"]) ? $payableRequest["success"]["paymentId"] : null,
                            "everee_paymentId" => isset($payableRequest["success"]["everee_payment_id"]) ? $payableRequest["success"]["everee_payment_id"] : null,
                            "adjustment_type_id" => $adjustmentTypeId,
                            "amount" => ($amount['amount'] + $amount['bonus'] + $amount['reimbursement']),
                            "description" => null,
                            "pay_date" => date("Y-m-d"),
                            "payment_status" => 3,
                            "everee_status" => 1,
                            "everee_json_response" => isset($payableRequest) ? json_encode($payableRequest) : null,
                            "everee_webhook_response" => null,
                            "everee_payment_status" => 0,
                            "from_payroll" => 1,
                            "pay_frequency" => $payroll->pay_frequency,
                            "user_worker_type" => $payroll->worker_type,
                            "pay_period_from" => $payroll->pay_period_from,
                            "pay_period_to" => $payroll->pay_period_to
                        ]);

                        create_paystub_employee([
                            "one_time_payment_id" => $oneTimePayment->id,
                            "user_id" => $user->id,
                            "pay_period_from" => $payroll->pay_period_from,
                            "pay_period_to" => $payroll->pay_period_to
                        ], 1);
                        $payroll->save();

                        $this->updateSingleOnetimePaymentForUser($request, $payroll, $oneTimePayment);
                        DB::commit();
                    }
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine();
            }
        }

        if (isset($errors) && is_array($errors) && sizeof($errors) > 0) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'One time payment created successfully',
            'errors' => []
        ]);
    }

    protected function updateSingleOnetimePaymentForUser(Request $request, $payroll, $oneTimePayment)
    {
        foreach ($request->data as $item) {
            if ($item['operation_type'] == "commission") {
                $commissionDetail = UserCommission::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($commissionDetail) {
                    $commissionDetail->status = 3;
                    $commissionDetail->is_mark_paid = 0;
                    $commissionDetail->is_next_payroll = 0;
                    $commissionDetail->is_onetime_payment = 1;
                    $commissionDetail->one_time_payment_id = $oneTimePayment->id;
                    $commissionDetail->save();

                    $payroll->commission = $payroll->commission - $commissionDetail->amount;
                    $payroll->gross_pay = $payroll->gross_pay - $commissionDetail->amount;
                    $payroll->net_pay = $payroll->net_pay - $commissionDetail->amount;

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $commissionDetail->id, "type" => $commissionDetail->schema_type, "payroll_type" => "commission", "adjustment_type" => $commissionDetail->schema_type, "is_onetime_payment" => 0])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->status = 3;
                        $payrollAdjustmentDetail->is_mark_paid = 0;
                        $payrollAdjustmentDetail->is_next_payroll = 0;
                        $payrollAdjustmentDetail->is_onetime_payment = 1;
                        $payrollAdjustmentDetail->one_time_payment_id = $oneTimePayment->id;
                        $payrollAdjustmentDetail->save();

                        $payroll->adjustment = $payroll->adjustment - $payrollAdjustmentDetail->amount;
                        $payroll->gross_pay = $payroll->gross_pay - $payrollAdjustmentDetail->amount;
                        $payroll->net_pay = $payroll->net_pay - $payrollAdjustmentDetail->amount;
                    }
                    $payroll->save();
                }
            } else if ($item['operation_type'] == "override") {
                $overrideDetail = UserOverrides::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($overrideDetail) {
                    $overrideDetail->status = 3;
                    $overrideDetail->is_mark_paid = 0;
                    $overrideDetail->is_next_payroll = 0;
                    $overrideDetail->is_onetime_payment = 1;
                    $overrideDetail->one_time_payment_id = $oneTimePayment->id;
                    $overrideDetail->save();

                    $payroll->override = $payroll->override - $overrideDetail->amount;
                    $payroll->gross_pay = $payroll->gross_pay - $overrideDetail->amount;
                    $payroll->net_pay = $payroll->net_pay - $overrideDetail->amount;

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $overrideDetail->id, "type" => $overrideDetail->type, "payroll_type" => "overrides", "adjustment_type" => $overrideDetail->type, "is_onetime_payment" => 0])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->status = 3;
                        $payrollAdjustmentDetail->is_mark_paid = 0;
                        $payrollAdjustmentDetail->is_next_payroll = 0;
                        $payrollAdjustmentDetail->is_onetime_payment = 1;
                        $payrollAdjustmentDetail->one_time_payment_id = $oneTimePayment->id;
                        $payrollAdjustmentDetail->save();

                        $payroll->adjustment = $payroll->adjustment - $payrollAdjustmentDetail->amount;
                        $payroll->gross_pay = $payroll->gross_pay - $payrollAdjustmentDetail->amount;
                        $payroll->net_pay = $payroll->net_pay - $payrollAdjustmentDetail->amount;
                    }
                    $payroll->save();
                }
            } else if ($item['operation_type'] == "clawback") {
                $clawBackDetail = ClawbackSettlement::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($clawBackDetail) {
                    $clawBackDetail->status = 3;
                    $clawBackDetail->is_mark_paid = 0;
                    $clawBackDetail->is_next_payroll = 0;
                    $clawBackDetail->is_onetime_payment = 1;
                    $clawBackDetail->one_time_payment_id = $oneTimePayment->id;
                    $clawBackDetail->save();

                    if ($clawBackDetail->type == "commission") {
                        $payroll->commission = $payroll->commission + $clawBackDetail->amount;
                        $payroll->gross_pay = $payroll->gross_pay + $clawBackDetail->amount;
                        $payroll->net_pay = $payroll->net_pay + $clawBackDetail->amount;
                    } else {
                        $payroll->override = $payroll->override + $clawBackDetail->amount;
                        $payroll->gross_pay = $payroll->gross_pay + $clawBackDetail->amount;
                        $payroll->net_pay = $payroll->net_pay + $clawBackDetail->amount;
                    }

                    if ($clawBackDetail->type == "commission") {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackDetail->id, "type" => "clawback", "payroll_type" => "commission", "adjustment_type" => $clawBackDetail->schema_type, "is_onetime_payment" => 0])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->status = 3;
                            $payrollAdjustmentDetail->is_mark_paid = 0;
                            $payrollAdjustmentDetail->is_next_payroll = 0;
                            $payrollAdjustmentDetail->is_onetime_payment = 1;
                            $payrollAdjustmentDetail->one_time_payment_id = $oneTimePayment->id;
                            $payrollAdjustmentDetail->save();

                            $payroll->adjustment = $payroll->adjustment - $payrollAdjustmentDetail->amount;
                            $payroll->gross_pay = $payroll->gross_pay - $payrollAdjustmentDetail->amount;
                            $payroll->net_pay = $payroll->net_pay - $payrollAdjustmentDetail->amount;
                        }
                    } else {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackDetail->id, "type" => "clawback", "payroll_type" => "overrides", "adjustment_type" => $clawBackDetail->type, "is_onetime_payment" => 0])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->status = 3;
                            $payrollAdjustmentDetail->is_mark_paid = 0;
                            $payrollAdjustmentDetail->is_next_payroll = 0;
                            $payrollAdjustmentDetail->is_onetime_payment = 1;
                            $payrollAdjustmentDetail->one_time_payment_id = $oneTimePayment->id;
                            $payrollAdjustmentDetail->save();

                            $payroll->adjustment = $payroll->adjustment - $payrollAdjustmentDetail->amount;
                            $payroll->gross_pay = $payroll->gross_pay - $payrollAdjustmentDetail->amount;
                            $payroll->net_pay = $payroll->net_pay - $payrollAdjustmentDetail->amount;
                        }
                    }
                    $payroll->save();
                }
            } else if ($item['operation_type'] == "request_approval" || $item['operation_type'] == "reimbursement") {
                $requestApprovalDetail = ApprovalsAndRequest::where(['id' => $item['id'], 'is_onetime_payment' => 0])->first();
                if ($requestApprovalDetail) {
                    $requestApprovalDetail->status = 'Paid';
                    $requestApprovalDetail->is_mark_paid = 0;
                    $requestApprovalDetail->is_next_payroll = 0;
                    $requestApprovalDetail->is_onetime_payment = 1;
                    $requestApprovalDetail->one_time_payment_id = $oneTimePayment->id;
                    $requestApprovalDetail->save();
                    $requests = ApprovalsAndRequest::where(['status' => 'Paid', 'id' => $item['id'], 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePayment->id])->get();
                    $requests->each(function ($request) use ($oneTimePayment) {
                        $childReqAmount = ApprovalsAndRequest::where(['parent_id' => $request->parent_id, 'status' => 'Paid', 'one_time_payment_id' => $oneTimePayment->id])->sum('amount');
                        $parentReqAmount = ApprovalsAndRequest::where(['id' => $request->parent_id, 'status' => 'Accept', 'one_time_payment_id' => $oneTimePayment->id])->sum('amount');
                        if ($childReqAmount == $parentReqAmount) {
                            ApprovalsAndRequest::where('id', $request->parent_id)->update(['status' => 'Paid']);
                        }
                    });

                    if ($item['operation_type'] == "request_approval") {
                        if ($requestApprovalDetail->adjustment_type_id == 5) {
                            $payroll->adjustment = $payroll->adjustment + $requestApprovalDetail->amount;
                            $payroll->gross_pay = $payroll->gross_pay + $requestApprovalDetail->amount;
                            $payroll->net_pay = $payroll->net_pay + $requestApprovalDetail->amount;
                        } else {
                            $payroll->adjustment = $payroll->adjustment - $requestApprovalDetail->amount;
                            $payroll->gross_pay = $payroll->gross_pay - $requestApprovalDetail->amount;
                            $payroll->net_pay = $payroll->net_pay - $requestApprovalDetail->amount;
                        }
                        $payroll->save();
                    } else if ($item['operation_type'] == "reimbursement") {
                        $reimbursementAmount = $requestApprovalDetail->amount;
                        $payroll->reimbursement = $payroll->reimbursement - $reimbursementAmount;
                        $payroll->gross_pay = $payroll->gross_pay - $reimbursementAmount;
                        $payroll->net_pay = $payroll->net_pay - $reimbursementAmount;
                        $payroll->save();
                    }
                }
            }
        }

        // Define amount column mapping for filtering zero amounts
        $modelAmountColumns = [
            UserCommission::class => 'amount',
            UserOverrides::class => 'amount',
            ClawbackSettlement::class => 'clawback_amount',
            PayrollAdjustmentDetail::class => 'amount'
        ];

        $modelToLocks = [
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class
        ];

        foreach ($modelToLocks as $model => $modelToLock) {
            $amountColumn = $modelAmountColumns[$model];

            // Only copy non-zero amount records for one-time payments
            $addToLock = $model::where(['one_time_payment_id' => $oneTimePayment->id])
                ->where(function ($q) use ($amountColumn) {
                    $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
                })->get();

            $addToLock->each(function ($value) use ($modelToLock) {
                $modelToLock::updateOrCreate(['id' => $value->id], $value->toArray());
            });
        }

        // ApprovalsAndRequest - only copy non-zero amounts
        $approvalsAndRequestData = ApprovalsAndRequest::where(['one_time_payment_id' => $oneTimePayment->id])
            ->where(function ($q) {
                $q->whereNotNull('amount')->where('amount', '!=', 0);
            })->get();
        foreach ($approvalsAndRequestData as $value) {
            ApprovalsAndRequestLock::updateOrCreate(['id' => $value->id], $value->toArray());
        }

        $advanceSetting = AdvancePaymentSetting::first();
        if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
            $startDateNext = $payroll->pay_period_from;
            $endDateNext = $payroll->pay_period_to;
            $advanceRequestStatus = "Accept";
        } else {
            $startDateNext = NULL;
            $endDateNext = NULL;
            $advanceRequestStatus = "Approved";
        }

        $approvalAndRequests = ApprovalsAndRequest::where(['one_time_payment_id' => $oneTimePayment->id, 'status' => 'Paid', 'adjustment_type_id' => 4])->where('amount', '>', 0)->whereNotNull('req_no')->get();
        foreach ($approvalAndRequests as $approvalAndRequest) {
            if (!ApprovalsAndRequest::where(['parent_id' => $approvalAndRequest->id])->first()) {
                ApprovalsAndRequest::create([
                    'user_id' => $approvalAndRequest->user_id,
                    'parent_id' => $approvalAndRequest->id,
                    'manager_id' => $approvalAndRequest->manager_id,
                    'approved_by' => $approvalAndRequest->approved_by,
                    'adjustment_type_id' => $approvalAndRequest->adjustment_type_id,
                    'state_id' => $approvalAndRequest->state_id,
                    'dispute_type' => $approvalAndRequest->dispute_type,
                    'customer_pid' => $approvalAndRequest->customer_pid,
                    'cost_tracking_id' => $approvalAndRequest->cost_tracking_id,
                    'cost_date' => $approvalAndRequest->cost_date,
                    'request_date' => $approvalAndRequest->request_date,
                    'amount' => (0 - $approvalAndRequest->amount),
                    'status' => $advanceRequestStatus,
                    'description' => 'Advance payment request Id: ' . $approvalAndRequest->req_no . ' Date of request: ' . date("m/d/Y"),
                    'pay_period_from' => isset($startDateNext) ? $startDateNext : NULL,
                    'pay_period_to' => isset($endDateNext) ? $endDateNext : NULL,
                    'user_worker_type' => $approvalAndRequest->user_worker_type,
                    'pay_frequency' => $approvalAndRequest->pay_frequency
                ]);
            }
        }
    }
}
