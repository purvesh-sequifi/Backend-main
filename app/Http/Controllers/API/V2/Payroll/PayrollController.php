<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\Crms;
use App\Models\Payroll;
use App\Models\CustomField;
use Illuminate\Http\Request;
use App\Models\UserSchedule;
use App\Models\FrequencyType;
use App\Models\PayrollSsetup;
use App\Models\UserOverrides;
use App\Models\CompanyProfile;
use App\Models\PayrollHistory;
use App\Models\UserCommission;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\OneTimePayments;
use App\Core\Traits\EvereeTrait;
use App\Models\UserOverridesLock;
use App\Models\DailyPayFrequency;
use App\Models\WeeklyPayFrequency;
use App\Models\UserCommissionLock;
use App\Models\CustomFieldHistory;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollOvertimeLock;
use App\Models\MonthlyPayFrequency;
use App\Http\Controllers\Controller;
use App\Models\PayrollDeductionLock;
use App\Models\AdvancePaymentSetting;
use App\Models\W2PayrollTaxDeduction;
use App\Models\ClawbackSettlementLock;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\AdditionalPayFrequency;
use App\Traits\EmailNotificationTrait;
use App\Jobs\Payroll\ExecutePayrollJob;
use App\Models\ApprovalsAndRequestLock;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollHourlySalaryLock;
use App\Jobs\Payroll\FinalizePayrollJob;
use Illuminate\Support\Facades\Validator;
use App\Jobs\Payroll\FinalizeW2PayrollJob;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\ReconciliationFinalizeHistoryLock;

class PayrollController extends Controller
{
    use PayFrequencyTrait, EvereeTrait, EmailNotificationTrait;

    public function getPayrollData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "get-payroll-data",
                "error" => $validator->errors()
            ], 400);
        }

        $perPage = 10;
        if (!empty($request->input("perpage"))) {
            $perPage = $request->input("perpage");
        }
        $type = $request->type;
        $search = $request->search;
        $negativeNetPay = $request->netpay_filter;
        $isReconciliation = $request->is_reconciliation;
        $payFrequency = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $workerType = strtolower($request->worker_type);

        payrollRemoveDuplicateData($request); // REMOVES DUPLICATE PAYROLL ENTRIES
        payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        if ($type == "pid") {
            $sort = $request->input("sort", "pid");
            $sortValue = $request->input("sort_val", "ASC");

            $commissionPayrolls = UserCommission::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param)->where("status", "!=", "3")
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });

            $commissionPayrollHistory = UserCommissionLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });
            $commissionPayrolls = $commissionPayrolls->unionAll($commissionPayrollHistory)->get();

            $overridePayrolls = UserOverrides::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param)->where("status", "!=", "3")
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });

            $overridePayrollHistory = UserOverridesLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });
            $overridePayrolls = $overridePayrolls->unionAll($overridePayrollHistory)->get();

            $clawBackPayrolls = ClawbackSettlement::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'clawback_amount', 'type', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param)->where("status", "!=", "3")
                ->where(function ($q) {
                    $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });

            $clawBackPayrollHistory = ClawbackSettlementLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'clawback_amount', 'type', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });
            $clawBackPayrolls = $clawBackPayrolls->unionAll($clawBackPayrollHistory)->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param)->where("status", "!=", "3")
                ->whereIn('payroll_type', ['commission', 'overrides'])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });

            $adjustmentDetailsPayrollHistory = PayrollAdjustmentDetailLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount', 'payroll_id')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->whereIn('payroll_type', ['commission', 'overrides'])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                });
            $adjustmentDetailsPayrolls = $adjustmentDetailsPayrolls->unionAll($adjustmentDetailsPayrollHistory)->get();

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll["data_type"] = "commission";
                $data[$commissionPayroll["pid"]][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll["data_type"] = "override";
                $data[$overridePayroll["pid"]][] = $overridePayroll;
            }
            foreach ($clawBackPayrolls as $clawBackPayroll) {
                $clawBackPayroll["data_type"] = "clawback";
                $data[$clawBackPayroll["pid"]][] = $clawBackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll["data_type"] = "adjustment";
                $data[$adjustmentDetailsPayroll["pid"]][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            $companyProfile = CompanyProfile::first();
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $total = 0;
                $netEpc = 0;
                $loanAmount = 0;
                $payrollIds = [];
                $commissionPaid = $commissionNext = $commissionRecon = $commissionOneTime = 0;
                $overridePaid = $overrideNext = $overrideRecon = $overrideOneTime = 0;
                $adjustmentPaid = $adjustmentNext = $adjustmentRecon = $adjustmentOneTime = 0;
                foreach ($data as $inner) {
                    if ($inner["data_type"] == "commission" || ($inner["data_type"] == "clawback" && $inner["type"] == "commission")) {
                        if ($inner["is_mark_paid"] >= 1 || $inner["is_next_payroll"] >= 1 || $inner["is_move_to_recon"] >= 1 || $inner["is_onetime_payment"] >= 1) {
                            if ($inner["is_mark_paid"] >= 1) {
                                $commissionPaid += 1;
                            } else if ($inner["is_next_payroll"] >= 1) {
                                $commissionNext += 1;
                            } else if ($inner["is_move_to_recon"] >= 1) {
                                $commissionRecon += 1;
                            } else if ($inner["is_onetime_payment"] >= 1) {
                                $commissionOneTime += 1;
                            }
                        }

                        if (!$inner["is_mark_paid"] && !$inner["is_next_payroll"] && !$inner["is_move_to_recon"]) {
                            if ($inner["data_type"] == "clawback" && $inner["type"] == "commission") {
                                $commissionNoPaid += (0 - $inner["clawback_amount"]);
                            } else {
                                $commissionNoPaid += $inner["amount"];
                            }
                        }
                        $commission += ($inner["data_type"] == "clawback" && $inner["type"] == "commission") ? $inner["clawback_amount"] : $inner["amount"];
                    } else if ($inner["data_type"] == "override" || ($inner["data_type"] == "clawback" && $inner["type"] == "overrides")) {
                        if ($inner["is_mark_paid"] >= 1 || $inner["is_next_payroll"] >= 1 || $inner["is_move_to_recon"] >= 1 || $inner["is_onetime_payment"] >= 1) {
                            if ($inner["is_mark_paid"] >= 1) {
                                $overridePaid += 1;
                            } else if ($inner["is_next_payroll"] >= 1) {
                                $overrideNext += 1;
                            } else if ($inner["is_move_to_recon"] >= 1) {
                                $overrideRecon += 1;
                            } else if ($inner["is_onetime_payment"] >= 1) {
                                $overrideOneTime += 1;
                            }
                        }

                        if (!$inner["is_mark_paid"] && !$inner["is_next_payroll"] && !$inner["is_move_to_recon"]) {
                            if ($inner["data_type"] == "clawback" && $inner["type"] == "overrides") {
                                $overrideNoPaid += (0 - $inner["clawback_amount"]);
                            } else {
                                $overrideNoPaid += $inner["amount"];
                            }
                        }
                        $override += ($inner["data_type"] == "clawback" && $inner["type"] == "overrides") ? $inner["clawback_amount"] : $inner["amount"];
                    } else if ($inner["data_type"] == "adjustment") {
                        if ($inner["is_mark_paid"] >= 1 || $inner["is_next_payroll"] >= 1 || $inner["is_move_to_recon"] >= 1 || $inner["is_onetime_payment"] >= 1) {
                            if ($inner["is_mark_paid"] >= 1) {
                                $adjustmentPaid += 1;
                            } else if ($inner["is_next_payroll"] >= 1) {
                                $adjustmentNext += 1;
                            } else if ($inner["is_move_to_recon"] >= 1) {
                                $adjustmentRecon += 1;
                            } else if ($inner["is_onetime_payment"] >= 1) {
                                $adjustmentOneTime += 1;
                            }
                        }

                        if (!$inner["is_mark_paid"] && !$inner["is_next_payroll"] && !$inner["is_move_to_recon"]) {
                            $adjustmentNoPaid += $inner["amount"];
                        }
                        $adjustment += $inner["amount"];
                    }

                    $total += 1;
                    $payrollIds[] = $inner["payroll_id"];
                }

                $status = 1;
                if (Payroll::applyFrequencyFilter($param, ['status' => '2'])->whereIn('id', $payrollIds)->first()) {
                    $status = 2;
                }

                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    if ($companyProfile && $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        $loanAmount = @$data[0]["payrollSaleData"]["gross_account_value"] ?? 0;
                        $netEpc = round(@$data[0]["payrollSaleData"]["net_epc"] * 100, 4) ?? 0;
                    }

                    $commissionColor = payrollColorPallet(["paid" => $commissionPaid, "next" => $commissionNext, "recon" => $commissionRecon, "one_time" => $commissionOneTime]);
                    $overrideColor = payrollColorPallet(["paid" => $overridePaid, "next" => $overrideNext, "recon" => $overrideRecon, "one_time" => $overrideOneTime]);
                    $adjustmentColor = payrollColorPallet(["paid" => $adjustmentPaid, "next" => $adjustmentNext, "recon" => $adjustmentRecon, "one_time" => $adjustmentOneTime]);

                    $paidStatus = 0;
                    $nextStatus = 0;
                    $reconStatus = 0;
                    $oneTimeStatus = 0;
                    if ($total == ($commissionPaid + $overridePaid + $adjustmentPaid)) {
                        $paidStatus = 1;
                    } else if ($total == ($commissionNext + $overrideNext + $adjustmentNext)) {
                        $nextStatus = 1;
                    } else if ($total == ($commissionRecon + $overrideRecon + $adjustmentRecon)) {
                        $reconStatus = 1;
                    } else if ($total == ($commissionOneTime + $overrideOneTime + $adjustmentOneTime)) {
                        $oneTimeStatus = 1;
                    }

                    $reconciliationNoPaid = 0; // 
                    $finalData[] = [
                        "pid" => $key,
                        "customer_name" => @$data[0]["payrollSaleData"]["customer_name"] ?? NULL,
                        "commission" => round($commissionNoPaid, 2),
                        "override" => round($overrideNoPaid, 2),
                        "adjustment" => round($adjustmentNoPaid, 2),
                        "net_pay" => round($netPayAmount, 2),
                        "reconciliation" => $reconciliationNoPaid, // 
                        "gross_pay" => round($netPayAmount, 2),
                        "is_mark_paid" => $paidStatus,
                        "is_next_payroll" => $nextStatus,
                        "is_move_to_recon" => $reconStatus,
                        "is_onetime_payment" => $oneTimeStatus,
                        "commission_color_status" => $commissionColor,
                        "override_color_status" => $overrideColor,
                        "adjustment_color_status" => $adjustmentColor,
                        "loan_amount" => $loanAmount,
                        "net_epc" => $netEpc,
                        "status" => $status
                    ];
                }
            }

            if (!empty($negativeNetPay)) {
                $finalData = collect($finalData)->where("net_pay", "<", 0);
            }

            $finalData = collect($finalData)->sortBy(function ($item) use ($sort) {
                switch ($sort) {
                    case 'pid':
                        return $item['pid'] ?? '';
                    case 'customer_name':
                        return $item['customer_name'] ?? '';
                    case 'commission':
                        return (float) ($item['commission'] ?? 0);
                    case 'override':
                        return (float) ($item['override'] ?? 0);
                    case 'adjustment':
                        return (float) ($item['adjustment'] ?? 0);
                    case 'reconciliation':
                        return (float) ($item['reconciliation'] ?? 0);
                    case 'net_pay':
                        return (float) ($item['net_pay'] ?? 0);
                    default:
                        return $item['user_id'] ?? 0;
                }
            }, SORT_REGULAR, $sortValue === 'desc')->values()->toArray();

            $finalData = paginate($finalData, $perPage);
            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "get-payroll-data",
                "data" => $finalData,
                "display_close_payroll" => 0, // 1 = DISPLAY, 0 = HIDE
                "total" => round($payrollTotal, 2)
            ]);
        } else {
            $sort = $request->input("sort", "full_name");
            $sortValue = $request->input("sort_val", "ASC");
            $evereeStatus = Crms::where(["id" => 3, "status" => 1])->first();

            $payrollHistories = PayrollHistory::applyFrequencyFilter($param, ["is_onetime_payment" => 0, "pay_type" => "Bank"])->whereIn('everee_payment_status', [1, 2])->count();
            if ($payrollHistories > 0) {
                return (new PayrollReportController())->payrollReportData($request, 1);
            }

            $payrollHistoryDetails = PayrollHistory::select(
                "payroll_id as id",
                "user_id",
                "commission",
                "override",
                "reimbursement",
                "deduction",
                "adjustment",
                "reconciliation",
                "hourly_salary",
                "overtime",
                "net_pay",
                DB::raw("0 as subtract_amount"),
                DB::raw("1 as from_history"),
                "status",
                DB::raw("0 as finalize_status"),
                DB::raw("0 as is_mark_paid"),
                DB::raw("0 as is_next_payroll"),
                "is_onetime_payment",
                DB::raw("0 as user_request_count"),
            )->withCount([
                'payrollCommission as total_sale_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT pid)'))->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    });
                }
            ])->selectSub(function ($query) {
                $query->from('payroll_hourly_salary_lock')
                    ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_hourly_salary_lock.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_hourly_salary_lock.total')->where('payroll_hourly_salary_lock.total', '!=', 0);
                    });
            }, 'hourly_salary_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_overtimes_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_overtimes_lock.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_overtimes_lock.total')->where('payroll_overtimes_lock.total', '!=', 0);
                    });
                }, 'overtimes_statuses')
                ->selectSub(function ($query) {
                    $query->from('user_commission_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('user_commission_lock.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('user_commission_lock.amount')->where('user_commission_lock.amount', '!=', 0);
                    });
                }, 'commission_statuses')
                ->selectSub(function ($query) {
                    $query->from('clawback_settlements_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('clawback_settlements_lock.payroll_id', 'payroll_history.payroll_id')->where("clawback_settlements_lock.type", "commission")
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_settlements_lock.clawback_amount')->where('clawback_settlements_lock.clawback_amount', '!=', 0);
                    });
                }, 'commission_claw_back_statuses')
                ->selectSub(function ($query) {
                    $query->from('user_overrides_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('user_overrides_lock.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('user_overrides_lock.amount')->where('user_overrides_lock.amount', '!=', 0);
                    });
                }, 'overrides_statuses')
                ->selectSub(function ($query) {
                    $query->from('clawback_settlements_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('clawback_settlements_lock.payroll_id', 'payroll_history.payroll_id')->where("clawback_settlements_lock.type", "overrides")
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_settlements_lock.clawback_amount')->where('clawback_settlements_lock.clawback_amount', '!=', 0);
                    });
                }, 'overrides_claw_back_statuses')
                ->selectSub(function ($query) {
                    $query->from('approvals_and_requests_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('approvals_and_requests_lock.payroll_id', 'payroll_history.payroll_id')->whereNotIn("approvals_and_requests_lock.adjustment_type_id", [2, 7, 8, 9])
                    ->where(function ($q) {
                        $q->whereNotNull('approvals_and_requests_lock.amount')->where('approvals_and_requests_lock.amount', '!=', 0);
                    });
                }, 'approvals_and_requests_statuses')
                ->selectSub(function ($query) {
                    $query->from('approvals_and_requests_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('approvals_and_requests_lock.payroll_id', 'payroll_history.payroll_id')->where("approvals_and_requests_lock.adjustment_type_id", 2)
                    ->where(function ($q) {
                        $q->whereNotNull('approvals_and_requests_lock.amount')->where('approvals_and_requests_lock.amount', '!=', 0);
                    });
                }, 'reimbursement_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_adjustment_details_lock')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_adjustment_details_lock.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_adjustment_details_lock.amount')->where('payroll_adjustment_details_lock.amount', '!=', 0);
                    });
                }, 'adjustment_details_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_deduction_locks')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_deduction_locks.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_deduction_locks.total')->where('payroll_deduction_locks.total', '!=', 0);
                    });
                }, 'deductions_statuses')
                ->selectSub(function ($query) {
                    $query->from('reconciliation_finalize_history_locks')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('reconciliation_finalize_history_locks.payroll_id', 'payroll_history.payroll_id')
                    ->where(function ($q) {
                        $q->whereNotNull('reconciliation_finalize_history_locks.net_amount')->where('reconciliation_finalize_history_locks.net_amount', '!=', 0);
                    });
                }, 'reconciliation_statuses')
                ->selectSub(function ($query) {
                    $query->fromSub(function ($sub) {
                        $sub->from('user_commission_lock')
                            ->select('user_commission_lock.pid', DB::raw('MAX(sale_masters.gross_account_value) as gross_account_value'))
                            ->leftJoin('sale_masters', 'user_commission_lock.pid', '=', 'sale_masters.pid')
                            ->whereColumn('user_commission_lock.payroll_id', 'payroll_history.payroll_id')
                            ->where(function ($q) {
                                $q->whereNotNull('user_commission_lock.amount')->where('user_commission_lock.amount', '!=', 0);
                            })->groupBy('user_commission_lock.pid');
                    }, 'unique_sales')
                    ->selectRaw('SUM(gross_account_value)');
                }, 'total_gross_account_value')
                ->when(!empty($search), function ($query) use ($search) {
                    $query->whereHas("payrollUser", function ($query) use ($search) {
                        $query->where("first_name", "like", "%{$search}%")
                            ->orWhere("last_name", "like", "%{$search}%")
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"]);
                    });
                })->when(!empty($negativeNetPay), function ($query) {
                    $query->whereRaw('(COALESCE(net_pay, 0) - COALESCE(subtract_amount, 0)) < 0');
                })->when($isReconciliation == 1, function ($query) use ($payPeriodTo) {
                    $positionArray = getReconciliationPositions($payPeriodTo);
                    $query->whereIn("position_id", $positionArray);
                })->with([
                    "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll,everee_workerId,onboardProcess,everee_embed_onboard_profile,worker_type"
                ])->applyFrequencyFilter($param, ["is_onetime_payment" => 1]);

            $payrollDetails = Payroll::select(
                "id",
                "user_id",
                "commission",
                "override",
                "reimbursement",
                "deduction",
                "adjustment",
                "reconciliation",
                "hourly_salary",
                "overtime",
                "net_pay",
                "subtract_amount",
                DB::raw("0 as from_history"),
                "status",
                "finalize_status",
                "is_mark_paid",
                "is_next_payroll",
                "is_onetime_payment"
            )->withCount([
                "userRequestApprove as user_request_count" => function ($query) {
                    $query->where("status", "Approved")->whereNotIn('adjustment_type_id', [7, 8, 9]);
                }
            ])->withCount([
                'payrollCommission as total_sale_count' => function ($q) {
                    $q->select(DB::raw('COUNT(DISTINCT pid)'))->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    });
                },
            ])->selectSub(function ($query) {
                $query->from('payroll_hourly_salary')
                    ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_hourly_salary.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_hourly_salary.total')->where('payroll_hourly_salary.total', '!=', 0);
                    });
            }, 'hourly_salary_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_overtimes')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_overtimes.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_overtimes.total')->where('payroll_overtimes.total', '!=', 0);
                    });
                }, 'overtimes_statuses')
                ->selectSub(function ($query) {
                    $query->from('user_commission')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('user_commission.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('user_commission.amount')->where('user_commission.amount', '!=', 0);
                    });
                }, 'commission_statuses')
                ->selectSub(function ($query) {
                    $query->from('clawback_settlements')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('clawback_settlements.payroll_id', 'payrolls.id')->where("clawback_settlements.type", "commission")
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_settlements.clawback_amount')->where('clawback_settlements.clawback_amount', '!=', 0);
                    });
                }, 'commission_claw_back_statuses')
                ->selectSub(function ($query) {
                    $query->from('user_overrides')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('user_overrides.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('user_overrides.amount')->where('user_overrides.amount', '!=', 0);
                    });
                }, 'overrides_statuses')
                ->selectSub(function ($query) {
                    $query->from('clawback_settlements')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('clawback_settlements.payroll_id', 'payrolls.id')->where("clawback_settlements.type", "overrides")
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_settlements.clawback_amount')->where('clawback_settlements.clawback_amount', '!=', 0);
                    });
                }, 'overrides_claw_back_statuses')
                ->selectSub(function ($query) {
                    $query->from('approvals_and_requests')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('approvals_and_requests.payroll_id', 'payrolls.id')->whereNotIn("approvals_and_requests.adjustment_type_id", [2, 7, 8, 9])
                    ->where(function ($q) {
                        $q->whereNotNull('approvals_and_requests.amount')->where('approvals_and_requests.amount', '!=', 0);
                    });
                }, 'approvals_and_requests_statuses')
                ->selectSub(function ($query) {
                    $query->from('approvals_and_requests')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('approvals_and_requests.payroll_id', 'payrolls.id')->where("approvals_and_requests.adjustment_type_id", 2)
                    ->where(function ($q) {
                        $q->whereNotNull('approvals_and_requests.amount')->where('approvals_and_requests.amount', '!=', 0);
                    });
                }, 'reimbursement_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_adjustment_details')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_adjustment_details.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_adjustment_details.amount')->where('payroll_adjustment_details.amount', '!=', 0);
                    });
                }, 'adjustment_details_statuses')
                ->selectSub(function ($query) {
                    $query->from('payroll_deductions')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "recon", COUNT(CASE WHEN is_move_to_recon >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('payroll_deductions.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('payroll_deductions.total')->where('payroll_deductions.total', '!=', 0);
                    });
                }, 'deductions_statuses')
                ->selectSub(function ($query) {
                    $query->from('reconciliation_finalize_history')
                        ->selectRaw('JSON_OBJECT(
                        "paid", COUNT(CASE WHEN is_mark_paid >= 1 THEN 1 END),
                        "next", COUNT(CASE WHEN is_next_payroll >= 1 THEN 1 END),
                        "one_time", COUNT(CASE WHEN is_onetime_payment >= 1 THEN 1 END)
                    )')->whereColumn('reconciliation_finalize_history.payroll_id', 'payrolls.id')
                    ->where(function ($q) {
                        $q->whereNotNull('reconciliation_finalize_history.net_amount')->where('reconciliation_finalize_history.net_amount', '!=', 0);
                    });
                }, 'reconciliation_statuses')
                ->selectSub(function ($query) {
                    $query->fromSub(function ($sub) {
                        $sub->from('user_commission')
                            ->select('user_commission.pid', DB::raw('MAX(sale_masters.gross_account_value) as gross_account_value'))
                            ->leftJoin('sale_masters', 'user_commission.pid', '=', 'sale_masters.pid')
                            ->whereColumn('user_commission.payroll_id', 'payrolls.id')
                            ->where(function ($q) {
                                $q->whereNotNull('user_commission.amount')->where('user_commission.amount', '!=', 0);
                            })->groupBy('user_commission.pid');
                    }, 'unique_sales')
                        ->selectRaw('SUM(gross_account_value)');
                }, 'total_gross_account_value')
                ->when(!empty($search), function ($query) use ($search) {
                    $query->whereHas("payrollUser", function ($query) use ($search) {
                        $query->where("first_name", "like", "%{$search}%")
                            ->orWhere("last_name", "like", "%{$search}%")
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"]);
                    });
                })->when(!empty($negativeNetPay), function ($query) {
                    $query->whereRaw('(COALESCE(net_pay, 0) - COALESCE(subtract_amount, 0)) < 0');
                })->when($isReconciliation == 1, function ($query) use ($payPeriodTo) {
                    $positionArray = getReconciliationPositions($payPeriodTo);
                    $query->whereIn("position_id", $positionArray);
                })->with([
                    "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll,everee_workerId,onboardProcess,everee_embed_onboard_profile,worker_type",
                ])->applyFrequencyFilter($param);
            $results = $payrollDetails->unionAll($payrollHistoryDetails)->when($sort && $sortValue, function ($query) use ($sort, $sortValue) {
                if ($sort == 'full_name') {
                    $query->orderBy(DB::raw("(SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = user_id)"), $sortValue);
                } else if ($sort == "total_sale") {
                    $query->orderBy("total_sale_count", $sortValue);
                } else if ($sort == "total_gross_account_value") {
                    $query->orderBy("total_gross_account_value", $sortValue);
                } else {
                    $query->orderBy($sort, $sortValue);
                }
            })->paginate($perPage);

            $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
            $settings = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->orderBy('id', 'Asc')->get();
            $customFields = CustomField::whereIn('payroll_id', $results->getCollection()->pluck('id'))->get();
            $customFieldsHistory = CustomFieldHistory::whereIn('payroll_id', $results->getCollection()->pluck('id'))->get();

            $transformedCollection = $results->getCollection()->map(function ($payrollDetail) use ($settings, $customFields, $customFieldsHistory, $evereeStatus) {
                [$isError, $errorMessage] = checkPayrollUserForError($payrollDetail, $evereeStatus);

                $payrollCustomFields = [];
                foreach ($settings as $setting) {
                    if ($payrollDetail->from_history == 1) {
                        $customFieldsData = $customFieldsHistory;
                    } else {
                        $customFieldsData = $customFields;
                    }
                    $field = $customFieldsData->where('payroll_id', $payrollDetail->id)->where('column_id', $setting->id)->first();

                    $customObject = [
                        'paid' => $field ? $field->is_mark_paid : 0,
                        'next' => $field ? $field->is_next_payroll : 0,
                        'recon' => 0,
                        'one_time' => $field ? $field->is_onetime_payment : 0
                    ];
                    $customFieldColor = payrollColorPallet($customObject);
                    $payrollCustomFields[] = [
                        'id' => $setting->id,
                        'field_name' => $setting->field_name,
                        'value' => $field ? $field->value : '0',
                        "custom_field_color_status" => $customFieldColor
                    ];
                }

                $salaryColor = payrollColorPallet(json_decode($payrollDetail->hourly_salary_statuses, true));
                $overtimeColor = payrollColorPallet(json_decode($payrollDetail->overtimes_statuses, true));
                $commissionColor = payrollColorPallet(json_decode($payrollDetail->commission_statuses, true));
                $commissionClawBackColor = payrollColorPallet(json_decode($payrollDetail->commission_claw_back_statuses, true));
                $overrideColor = payrollColorPallet(json_decode($payrollDetail->overrides_statuses, true));
                $overrideClawBackColor = payrollColorPallet(json_decode($payrollDetail->overrides_claw_back_statuses, true));
                $adjustmentColor = payrollColorPallet(json_decode($payrollDetail->adjustment_details_statuses, true));
                $approvalsAndRequestsColor = payrollColorPallet(json_decode($payrollDetail->approvals_and_requests_statuses, true));
                $reimbursementColor = payrollColorPallet(json_decode($payrollDetail->reimbursement_statuses, true));
                $deductionColor = payrollColorPallet(json_decode($payrollDetail->deductions_statuses, true));
                $reconciliationColor = payrollColorPallet(json_decode($payrollDetail->reconciliation_statuses, true));

                $finalAdjustmentColor = 0;
                if ($adjustmentColor == 5 || $approvalsAndRequestsColor == 5) {
                    $finalAdjustmentColor = 5; // YELLOW
                } else if ($adjustmentColor && $approvalsAndRequestsColor && $adjustmentColor != $approvalsAndRequestsColor) {
                    $finalAdjustmentColor = 5; // YELLOW
                } else if ($adjustmentColor && $approvalsAndRequestsColor && $adjustmentColor == $approvalsAndRequestsColor) {
                    $finalAdjustmentColor = $adjustmentColor;
                } else if ($adjustmentColor) {
                    $finalAdjustmentColor = $adjustmentColor;
                } else if ($approvalsAndRequestsColor) {
                    $finalAdjustmentColor = $approvalsAndRequestsColor;
                }

                $finalCommissionColor = 0;
                if ($commissionColor == 5 || $commissionClawBackColor == 5) {
                    $finalCommissionColor = 5; // YELLOW
                } else if ($commissionColor && $commissionClawBackColor && $commissionColor != $commissionClawBackColor) {
                    $finalCommissionColor = 5; // YELLOW
                } else if ($commissionColor && $commissionClawBackColor && $commissionColor == $commissionClawBackColor) {
                    $finalCommissionColor = $commissionColor;
                } else if ($commissionColor) {
                    $finalCommissionColor = $commissionColor;
                } else if ($commissionClawBackColor) {
                    $finalCommissionColor = $commissionClawBackColor;
                }

                $finalOverrideColor = 0;
                if ($overrideColor == 5 || $overrideClawBackColor == 5) {
                    $finalOverrideColor = 5; // YELLOW
                } else if ($overrideColor && $overrideClawBackColor && $overrideColor != $overrideClawBackColor) {
                    $finalOverrideColor = 5; // YELLOW
                } else if ($overrideColor && $overrideClawBackColor && $overrideColor == $overrideClawBackColor) {
                    $finalOverrideColor = $overrideColor;
                } else if ($overrideColor) {
                    $finalOverrideColor = $overrideColor;
                } else if ($overrideClawBackColor) {
                    $finalOverrideColor = $overrideClawBackColor;
                }

                return [
                    "id" => $payrollDetail->id,
                    "user_id" => $payrollDetail->user_id,
                    "user_data" => payrollUserDataCommon($payrollDetail->payrollUser),
                    "total_sale" => $payrollDetail->total_sale_count,
                    "total_gross_account_value" => $payrollDetail->total_gross_account_value,
                    "commission" => $payrollDetail->commission,
                    "override" => $payrollDetail->override,
                    "reimbursement" => $payrollDetail->reimbursement,
                    "deduction" => $payrollDetail->deduction,
                    "adjustment" => $payrollDetail->adjustment,
                    "reconciliation" => $payrollDetail->reconciliation,
                    "hourly_salary" => $payrollDetail->hourly_salary,
                    "custom_fields" => $payrollCustomFields,
                    "overtime" => $payrollDetail->overtime,
                    "net_pay" => $payrollDetail->net_pay,
                    "gross_pay" => ($payrollDetail->net_pay - $payrollDetail->subtract_amount), // JUST TO DISPLAY ERROR MESSAGE
                    "status" => $payrollDetail->status,
                    "finalize_status" => $payrollDetail->finalize_status,
                    "is_mark_paid" => $payrollDetail->is_mark_paid,
                    "is_next_payroll" => $payrollDetail->is_next_payroll,
                    "is_onetime_payment" => $payrollDetail->is_onetime_payment,
                    "hourly_salary_color_status" => $salaryColor,
                    "overtime_color_status" => $overtimeColor,
                    "commission_color_status" => $finalCommissionColor,
                    "override_color_status" => $finalOverrideColor,
                    "adjustment_and_approve_request_color_status" => $finalAdjustmentColor,
                    "reimbursement_color_status" => $reimbursementColor,
                    "deduction_color_status" => $deductionColor,
                    "reconciliation_color_status" => $reconciliationColor,
                    "request_count" => ($payrollDetail->user_request_count >= 1) ? 1 : 0,
                    "is_error" => $isError,
                    "error_message" => $errorMessage
                ];
            });
            $results->setCollection($transformedCollection);

            $total = Payroll::whereHas('payrollUser', function ($query) {
                $query->where('stop_payroll', 0);
            })->applyFrequencyFilter($param)->sum("net_pay");
            $canBeClosed = statusForClosePayroll($param);
            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "get-payroll-data",
                "payment_failed" => 0, // 0 WHEN PAYROLL, 1 WHEN PAYROLL HISTORY
                "data" => $results,
                "display_close_payroll" => $canBeClosed, // 1 = DISPLAY, 0 = HIDE
                "total" => round($total, 2)
            ]);
        }
    }

    public function reCalculatePayrollData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "get-payroll-data",
                "error" => $validator->errors()
            ], 400);
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

        payrollRemoveDuplicateData($request); // REMOVES DUPLICATE PAYROLL ENTRIES
        $payrolls = Payroll::applyFrequencyFilter($param)->get();
        foreach ($payrolls as $payroll) {
            reCalculatePayrollData($payroll->id, $param);
        }
        payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0

        return response()->json([
            "status" => true,
            "ApiName" => "re-calculate-payroll-data",
            "message" => "Payroll data re-calculated successfully"
        ]);
    }

    public function checkPayrollStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "pay_period_from" => "required|date_format:Y-m-d",
                "pay_period_to" => "required|date_format:Y-m-d",
                "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
                "worker_type" => "required|in:1099,w2"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "get-payroll-data",
                    "error" => $validator->errors()
                ], 400);
            }

            $payPeriodFrom = $request->pay_period_from;
            $payPeriodTo = $request->pay_period_to;
            $payFrequency = $request->pay_frequency;
            $workerType = $request->worker_type;

            $param = [
                "pay_frequency" => $payFrequency,
                "worker_type" => $workerType,
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];

            $executing = Payroll::applyFrequencyFilter($param, ["status" => 3, "is_stop_payroll" => 0])->first();
            if ($executing) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => true,
                    "message" => "Executing.",
                    "finalize_status" => 3
                ]);
            }

            $executed = PayrollHistory::applyFrequencyFilter($param, ["is_onetime_payment" => 0])->first();
            if ($executed) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => true,
                    "message" => "Executed.",
                    "finalize_status" => 4
                ]);
            }

            $finalizing = Payroll::applyFrequencyFilter($param, ["finalize_status" => 1, "is_stop_payroll" => 0])->first();
            if ($finalizing) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => true,
                    "message" => "Finalizing.",
                    "finalize_status" => 2
                ]);
            }

            $failed = Payroll::applyFrequencyFilter($param, ["finalize_status" => 3, "is_stop_payroll" => 0])->first();
            if ($failed) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => false,
                    "message" => "Payroll processing has failed. Please re-finalize and try again.",
                    "finalize_status" => 0
                ], 400);
            }

            $nothing = Payroll::applyFrequencyFilter($param, ["finalize_status" => 0, "is_stop_payroll" => 0])->first();
            if ($nothing) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => true,
                    "message" => "Payroll.",
                    "finalize_status" => 0
                ]);
            }

            $finalized = Payroll::applyFrequencyFilter($param, ["finalize_status" => 2, "is_stop_payroll" => 0])->first();
            if ($finalized) {
                return response()->json([
                    "ApiName" => "finalize_status_Payroll",
                    "status" => true,
                    "message" => "Finalized successfully.",
                    "finalize_status" => 1
                ]);
            }

            return response()->json([
                "ApiName" => "finalize_status_Payroll",
                "status" => true,
                "message" => "No data found!!",
                "finalize_status" => 0
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                "ApiName" => "finalize_status_Payroll",
                "status" => false,
                "message" => $e->getMessage(),
                "line" => $e->getLine()
            ], 400);
        }
    }

    public function getSummaryPayroll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'worker_type' => 'required|in:1099,w2',
            'pay_period_from' => 'required|date_format:Y-m-d',
            'pay_period_to' => 'required|date_format:Y-m-d',
            'frequency_type_id' => 'required|in:' . FrequencyType::WEEKLY_ID . ',' . FrequencyType::MONTHLY_ID . ',' . FrequencyType::BI_WEEKLY_ID . ',' . FrequencyType::SEMI_MONTHLY_ID . ',' . FrequencyType::DAILY_PAY_ID
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return getPayrollDataSummary($request, Payroll::class);
    }

    public function getSummaryPayrollReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'worker_type' => 'required|in:1099,w2',
            'pay_period_from' => 'required|date_format:Y-m-d',
            'pay_period_to' => 'required|date_format:Y-m-d',
            'frequency_type_id' => 'required|in:' . FrequencyType::WEEKLY_ID . ',' . FrequencyType::MONTHLY_ID . ',' . FrequencyType::BI_WEEKLY_ID . ',' . FrequencyType::SEMI_MONTHLY_ID . ',' . FrequencyType::DAILY_PAY_ID
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return getPayrollDataSummary($request, PayrollHistory::class);
    }

    public function closePayroll(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                "pay_period_from" => "required|date_format:Y-m-d",
                "pay_period_to" => "required|date_format:Y-m-d",
                "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
                "worker_type" => "required|in:1099,w2"
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    "status" => false,
                    "ApiName" => "close-payroll",
                    "error" => $validator->errors()
                ], 400);
            }

            if (Payroll::where(['status' => 2, 'finalize_status' => 3])->first()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'close-payroll',
                    'message' => 'Some users failed to sync with SequiPay, so you cannot execute the payroll!!'
                ], 400);
            }

            if ($payroll = Payroll::where(["status" => 2])->whereIn("finalize_status", [1, 2])->first()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'close-payroll',
                    'message' => 'Payroll is being processed for the pay period from ' . date("m/d/Y", strtotime($payroll->pay_period_from)) . ' to ' . date("m/d/Y", strtotime($payroll->pay_period_to))
                ], 400);
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

            $deduction = payrollDeductionCalculation($param);
            if ($deduction['status'] == false) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => $deduction['message']
                ], 400);
            }

            payrollRemoveDuplicateData($request);
            $payrolls = Payroll::applyFrequencyFilter($param)->get();
            foreach ($payrolls as $payroll) {
                reCalculatePayrollData($payroll->id, $param);
            }
            payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0

            $canBeClosed = statusForClosePayroll($param);
            if (!$canBeClosed) {
                DB::rollBack();
                return response()->json([
                    "status" => false,
                    "ApiName" => "close-payroll",
                    "message" => "The payroll cannot be close because there are pending records that need to be processed."
                ], 400);
            }

            if (Carbon::parse($payPeriodTo . " 23:59:59")->isFuture()) {
                DB::rollBack();
                return response()->json([
                    "status" => false,
                    "ApiName" => "close-payroll",
                    "message" => "You can not close the future payroll."
                ], 400);
            }

            if ($payFrequency == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } else if ($payFrequency == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } else if ($payFrequency == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } else if ($payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            } else if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                $class = DailyPayFrequency::class;
            }

            if (!isset($class)) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => 'Invalid pay frequency.'
                ], 400);
            }

            $frequency = $class::query();
            if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                $frequency = $frequency->whereRaw('"' . $payPeriodFrom . '" between `pay_period_from` and `pay_period_to`');
            } else {
                $frequency = $frequency->where(["pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo]);
            }
            if ($payFrequency == FrequencyType::BI_WEEKLY_ID || $payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                $frequency = $frequency->where('type', $type);
            }

            $frequency = $frequency->first();
            if (!$frequency) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => 'Pay period not found.'
                ], 400);
            }

            if ($workerType == 'w2' && $frequency->w2_closed_status == 1) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => 'Pay period already closed.'
                ], 400);
            }

            if ($workerType == '1099' && $frequency->closed_status == 1) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => 'Pay period already closed.'
                ], 400);
            }

            $nextPeriod = $this->payFrequencyById($payPeriodTo, $payFrequency, $workerType);
            if (!isset($nextPeriod->second_pay_period_from)) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => 'No next pay period available, please contact administrator for further assistance.'
                ], 400);
            }

            $nextPeriodFrom = $nextPeriod->second_pay_period_from;
            $nextPeriodTo = $nextPeriod->second_pay_period_to;
            $nextPeriod = [
                'pay_period_from' => $nextPeriodFrom,
                'pay_period_to' => $nextPeriodTo
            ];
            $moveToNextPayrollData = moveToNextPayrollData($param, $nextPeriod);
            if (!$moveToNextPayrollData['status']) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => $moveToNextPayrollData['message']
                ], 400);
            }

            $markAsPaidPayrollData = markAsPaidPayrollData($param, $nextPeriod);
            if (!$markAsPaidPayrollData['status']) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'close-payroll',
                    'status' => false,
                    'message' => $markAsPaidPayrollData['message']
                ], 400);
            }

            Payroll::applyFrequencyFilter($param)->delete();
            if ($workerType == 'w2') {
                $frequency->w2_closed_status = 1;
                $frequency->w2_open_status_from_bank = 0;
                $frequency->save();
            } else {
                $frequency->closed_status = 1;
                $frequency->open_status_from_bank = 0;
                $frequency->save();
            }

            DB::commit();
            return response()->json([
                'ApiName' => 'close-payroll',
                'status' => true,
                'message' => 'Payroll has been closed successfully.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'ApiName' => 'close_payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 400);
        }
    }

    public function finalizePayroll(Request $request)
    {
        try {
            $validate = payrollFinalizeValidations($request);
            if (!$validate['success']) {
                return response()->json($validate, $validate['code']);
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

            if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                $token = $this->gettoken($workerType);
                if (isset($token->username) && empty($token->username)) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize-payroll',
                        'message' => 'Sequipay key is not set up. Please configure the Sequipay key or disable Sequipay on the server!!'
                    ], 400);
                }

                $check = $this->validateTenantApiKey($workerType);
                if (isset($check['error']) && $check['error'] == 'unauthorized') {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize-payroll',
                        'message' => 'Sequipay is not Authorized, Please contact your administrator!!'
                    ], 400);
                }
            }

            if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                DailyPayFrequency::updateOrCreate(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo], [
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'closed_status' => 0,
                    'open_status_from_bank' => 0
                ]);
            }

            $auth = auth()->user();
            $query = Payroll::whereHas('usersdata')->with('usersdata:id,everee_workerId,employee_id,worker_type,onboardProcess,stop_payroll', 'positionDetail')->where('status', '!=', 2)->whereIn('finalize_status', [0, 3])->applyFrequencyFilter($param, ['is_onetime_payment' => 0]);
            $payrolls = $query->get();

            if ($workerType == 'w2' || $workerType == 'W2') {
                $nextPayrollQuery = clone $query;
                $userIdArray = $nextPayrollQuery->pluck('user_id')->toArray();
                $attendanceCheck = $this->getUserAttendanceApprovedStatus($userIdArray, $payPeriodFrom, $payPeriodTo);
                if ($attendanceCheck) {
                    return response()->json([
                        'ApiName' => 'finalize-payroll',
                        'status' => false,
                        'unapprove_status' => 1,
                        'message' => 'Warning: Enable to process your request to finalize payroll for this pay period. Because this payroll users attendance is not approved.'
                    ], 400);
                }

                foreach ($payrolls as $payroll) {
                    FinalizeW2PayrollJob::dispatch($payroll, $payPeriodFrom, $payPeriodTo, $auth, $payFrequency);
                }
            } else {
                foreach ($payrolls as $payroll) {
                    FinalizePayrollJob::dispatch($payroll, $payPeriodFrom, $payPeriodTo, $auth, $payFrequency);
                }
            }
            Payroll::applyFrequencyFilter($param, ['status' => 1, 'finalize_status' => 0, 'is_onetime_payment' => 0])->update(['finalize_status' => 1]);

            return response()->json([
                'ApiName' => 'finalize-payroll',
                'status' => true,
                'message' => 'Successfully',
                'data' => []
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ApiName' => 'finalize-payroll',
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile()
            ], 400);
        }
    }

    private function getUserAttendanceApprovedStatus($userIdArray, $payPeriodFrom, $payPeriodTo)
    {
        $userSchedulesData = UserSchedule::join('users', 'user_schedules.user_id', 'users.id')
            ->join('user_schedule_details', 'user_schedule_details.schedule_id', 'user_schedules.id')
            ->whereBetween('user_schedule_details.schedule_from', [$payPeriodFrom, $payPeriodTo])
            ->whereIn('user_schedules.user_id', $userIdArray)
            ->where('user_schedule_details.attendance_status', 0)->count();
        if ($userSchedulesData > 0) {
            return 1;
        }

        return 0;
    }

    public function executePayroll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "pay_period_from" => "required|date_format:Y-m-d",
                "pay_period_to" => "required|date_format:Y-m-d",
                "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
                "worker_type" => "required|in:1099,w2"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'execute-payroll',
                    'status' => false,
                    'error' => $validator->errors()
                ], 400);
            }

            if ($request->pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $validator = Validator::make($request->all(), [
                    'pay_period_from' => 'required|date_format:Y-m-d|before_or_equal:today',
                    'pay_period_to' => 'required|date_format:Y-m-d|before_or_equal:today'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'ApiName' => 'execute-payroll',
                        'status' => false,
                        'error' => $validator->errors()
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

            if (Payroll::applyFrequencyFilter($param, ['status' => 2, 'finalize_status' => 3, 'is_onetime_payment' => 0])->first()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'execute-payroll',
                    'message' => 'Some users failed to sync with SequiPay, so you cannot execute the payroll!!'
                ], 400);
            }

            if ($payroll = Payroll::applyFrequencyFilter($param, ['status' => 3, 'is_onetime_payment' => 0])->first()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'execute-payroll',
                    'message' => 'Payroll is being processed for the pay period from ' . date("m/d/Y", strtotime($payroll->pay_period_from)) . ' to ' . date("m/d/Y", strtotime($payroll->pay_period_to))
                ], 400);
            }

            if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                $token = $this->gettoken($workerType);
                if (isset($token->username) && empty($token->username)) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'execute-payroll',
                        'message' => 'Sequipay key is not set up. Please configure the Sequipay key or disable Sequipay on the server!!'
                    ], 400);
                }

                $check = $this->validateTenantApiKey($workerType);
                if (isset($check['error']) && $check['error'] == 'unauthorized') {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'execute-payroll',
                        'message' => 'Sequipay is not Authorized, Please contact your administrator!!'
                    ], 400);
                }
            }

            if ($payFrequency == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } else if ($payFrequency == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } else if ($payFrequency == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } else if ($payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            } else if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                $class = DailyPayFrequency::class;
            }

            if (!isset($class)) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'execute-payroll',
                    'status' => false,
                    'message' => 'Invalid pay frequency.'
                ], 400);
            }

            $frequency = $class::query();
            if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                $frequency = $frequency->whereRaw('"' . $payPeriodFrom . '" between `pay_period_from` and `pay_period_to`');
            } else {
                $frequency = $frequency->where(["pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo]);
            }
            if ($payFrequency == FrequencyType::BI_WEEKLY_ID || $payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                $frequency = $frequency->where('type', $type);
            }

            $frequency = $frequency->first();
            if (!$frequency) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'execute-payroll',
                    'status' => false,
                    'message' => 'Pay period not found.'
                ], 400);
            }

            $checkNextPayroll = Payroll::applyFrequencyFilter($param, ['status' => 2, 'finalize_status' => 2, 'is_next_payroll' => 1, 'is_onetime_payment' => 0])->first();
            $nextPeriod = $this->payFrequencyById($payPeriodTo, $payFrequency, $workerType);
            if ($checkNextPayroll && !isset($nextPeriod->pay_period_from)) {
                return response()->json([
                    'ApiName' => 'execute-payroll',
                    'status' => false,
                    'error' => 'No next pay period available to move user to next pay period.'
                ], 400);
            }
            $advanceSetting = AdvancePaymentSetting::first();
            if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                if (!isset($nextPeriod->pay_period_from)) {
                    return response()->json([
                        'ApiName' => 'execute-payroll',
                        'status' => false,
                        'error' => 'No next pay period available to move user to next pay period.'
                    ], 400);
                }
            }

            Payroll::applyFrequencyFilter($param, ['status' => 2, 'finalize_status' => 2, 'is_onetime_payment' => 0])->update(['status' => 3]);

            $nextPeriodFrom = $nextPeriod->pay_period_from;
            $nextPeriodTo = $nextPeriod->pay_period_to;
            $nextPeriod = [
                'pay_period_from' => $nextPeriodFrom,
                'pay_period_to' => $nextPeriodTo
            ];
            $moveToNextPayrollData = moveToNextPayrollData($param, $nextPeriod);
            if (!$moveToNextPayrollData['status']) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'execute-payroll',
                    'status' => false,
                    'message' => $moveToNextPayrollData['message']
                ], 400);
            };

            $query = Payroll::with('usersdata', 'payrollUser')->applyFrequencyFilter($param, ['status' => 3, 'is_onetime_payment' => 0]);

            $openStatusFromBank = 0;
            $netPayQuery = clone $query;
            $netPayZeroAmount = $netPayQuery->where('net_pay', '>', 0)->count();
            if (Crms::where(['id' => 3, 'status' => 1])->first() && $netPayZeroAmount > 0) {
                $openStatusFromBank = 1;
            }

            $payrolls = $query->get();
            foreach ($payrolls as $payroll) {
                ExecutePayrollJob::dispatch($payroll, $nextPeriod);
            }

            if ($workerType == 'w2') {
                $frequency->w2_closed_status = 1;
                $frequency->w2_open_status_from_bank = $openStatusFromBank;
                $frequency->save();
            } else {
                $frequency->closed_status = 1;
                $frequency->open_status_from_bank = $openStatusFromBank;
                $frequency->save();
            }

            return response()->json([
                'ApiName' => 'execute-payroll',
                'status' => true,
                'message' => "payroll execution request sent successfully"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ApiName' => 'execute-payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 400);
        }
    }

    public function payrollPayStubData($userId, $payPeriodFrom, $payPeriodTo, $payFrequency, $workerType, $isOneTimePayment, $historyId)
    {
        $param = [
            'pay_period_from' => $payPeriodFrom,
            'pay_period_to' => $payPeriodTo,
            'pay_frequency' => $payFrequency,
            'worker_type' => $workerType
        ];

        $salary = 0;
        $overtime = 0;
        $commission = 0;
        $override = 0;
        $reconciliation = 0;
        $deduction = 0;
        $w2Deduction = 0;
        $adjustment = 0;
        $reimbursement = 0;
        $customField = 0;
        $oneTimeFlag = 0;
        if ($isOneTimePayment) {
            $oneTimePayment = OneTimePayments::find($historyId);
            if (!$oneTimePayment) {
                return;
            }

            if ($oneTimePayment->from_payroll) {
                $oneTimePayment = OneTimePayments::with(['oneTimeUser'])
                    ->withSum('oneTimeCommissions as one_time_commissions_sum_amount', 'amount')
                    ->withSum('oneTimeOverrides as one_time_overrides_sum_amount', 'amount')
                    ->withSum(['oneTimeClawBacks as one_time_commission_claw_backs_sum_amount' => function ($query) {
                        $query->where('type', 'commission');
                    }], 'clawback_amount')
                    ->withSum(['oneTimeClawBacks as one_time_overrides_claw_backs_sum_amount' => function ($query) {
                        $query->where('type', 'overrides');
                    }], 'clawback_amount')
                    ->withSum('oneTimeAdjustmentDetails as one_time_adjustment_details_sum_amount', 'amount')
                    ->withSum('oneTimeDeductions as one_time_deductions_sum_amount', 'total')
                    ->withSum('oneTimeTaxDeductions as one_time_tax_deductions_sum_amount', 'fica_tax')
                    ->withSum('oneTimeHourlySalary as one_time_hourly_salary_sum_amount', 'total')
                    ->withSum('oneTimeOverTimes as one_time_over_times_sum_amount', 'total')
                    ->withSum(['oneTimeApprovalsAndRequests as one_time_approvals_and_requests_sum_amount' => function ($query) {
                        $query->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9]);
                    }], 'amount')
                    ->withSum(['oneTimeApprovalsAndRequests as one_time_approvals_and_requests_reimbursement_sum_amount' => function ($query) {
                        $query->where('adjustment_type_id', 2);
                    }], 'amount')
                    ->withSum(['oneTimeApprovalsAndRequests as one_time_approvals_and_requests_fines_sum_amount' => function ($query) {
                        $query->where('adjustment_type_id', 5);
                    }], 'amount')
                    ->withSum('oneTimeCustomFields as one_time_custom_fields_sum_amount', 'value')
                    ->withSum('oneTimeReconciliationFinalizeHistories as one_time_reconciliation_finalize_histories_sum_amount', 'net_amount')
                    ->where('id', $historyId)->first();
                $user = $oneTimePayment?->oneTimeUser;
                $payDate = date('Y-m-d', strtotime($oneTimePayment?->pay_date)) ?? '';

                $salary = $oneTimePayment?->one_time_hourly_salary_sum_amount ?? 0;
                $overtime = $oneTimePayment?->one_time_over_times_sum_amount ?? 0;
                $commission = $oneTimePayment?->one_time_commissions_sum_amount ?? 0;
                $commissionClawBack = $oneTimePayment?->one_time_commission_claw_backs_sum_amount ?? 0;
                $commission = $commission - $commissionClawBack;
                $override = $oneTimePayment?->one_time_overrides_sum_amount ?? 0;
                $overrideClawBack = $oneTimePayment?->one_time_overrides_claw_backs_sum_amount ?? 0;
                $override = $override - $overrideClawBack;
                $reconciliation = $oneTimePayment?->one_time_reconciliation_finalize_histories_sum_amount ?? 0;
                $deduction = $oneTimePayment?->one_time_deductions_sum_amount ?? 0;
                $w2Deduction = $oneTimePayment?->one_time_tax_deductions_sum_amount ?? 0;
                $adjustment = $oneTimePayment?->one_time_adjustment_details_sum_amount ?? 0;
                $fines = $oneTimePayment?->one_time_approvals_and_requests_fines_sum_amount ?? 0;
                $approvalsAndRequests = $oneTimePayment?->one_time_approvals_and_requests_sum_amount ?? 0;
                $adjustment = ($adjustment + $approvalsAndRequests) - $fines;
                $reimbursement = $oneTimePayment?->one_time_approvals_and_requests_reimbursement_sum_amount ?? 0;
                $customField = $oneTimePayment?->one_time_custom_fields_sum_amount ?? 0;
                $netPay = ($salary + $overtime + $commission + $override + $reconciliation + $adjustment + $reimbursement + $customField) - ($deduction + $w2Deduction);
            } else {
                $oneTimePayment = OneTimePayments::with(['oneTimeUser'])->withSum('oneTimeTaxDeductions as one_time_tax_deductions_sum_amount', 'fica_tax')->where('id', $historyId)->first();
                $user = $oneTimePayment?->oneTimeUser;
                $payDate = date('Y-m-d', strtotime($oneTimePayment?->pay_date)) ?? '';
                if ($oneTimePayment) {
                    if ($oneTimePayment->adjustment_type_id == 2) {
                        $reimbursement = $oneTimePayment?->amount ?? 0;
                    } else if (!in_array($oneTimePayment->adjustment_type_id, [2, 5, 7, 8, 9])) {
                        $adjustment = $oneTimePayment?->amount ?? 0;
                    } else {
                        $adjustment = $oneTimePayment?->amount ?? 0;
                    }
                }

                $w2Deduction = $oneTimePayment?->one_time_tax_deductions_sum_amount ?? 0;
                $netPay = $oneTimePayment->amount;
            }
            $oneTimeFlag = 1;
            $accountCount = 1;
            $payPeriodFrom = $oneTimePayment->pay_date;
            $payPeriodTo = $oneTimePayment->pay_date;
            $YTDAccountCount = OneTimePayments::where('user_id', $userId)->whereYear('pay_date', date('Y', strtotime($oneTimePayment->pay_date)))->count();
        } else {
            $payrollHistory = PayrollHistory::with('payrollUser')
                ->withCount(['payrollCommissions as account_count' => function ($q) use ($isOneTimePayment) {
                    $q->select(DB::raw('COUNT(DISTINCT pid)'))->where('is_onetime_payment', $isOneTimePayment);
                }])
                ->withSum(['payrollCommissions as commissions_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollOverrides as overrides_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollClawBacks as commission_claw_backs_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['type' => 'commission', 'is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'clawback_amount')
                ->withSum(['payrollClawBacks as overrides_claw_backs_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['type' => 'overrides', 'is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'clawback_amount')
                ->withSum(['payrollPayrollAdjustmentDetails as adjustment_details_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollDeductions as deductions_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'total')
                ->withSum(['payrollSalaries as hourly_salary_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'total')
                ->withSum(['payrollOvertimes as over_times_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'total')
                ->withSum(['payrollApproveRequest as approvals_and_requests_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollApproveRequest as approvals_and_requests_reimbursement_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where('adjustment_type_id', 2)->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollApproveRequest as approvals_and_requests_fines_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where('adjustment_type_id', 5)->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'amount')
                ->withSum(['payrollCustomFields as custom_fields_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'value')
                ->withSum(['payrollReconciliations as reconciliation_finalize_histories_sum_amount' => function ($query) use ($isOneTimePayment) {
                    $query->where(['is_onetime_payment' => $isOneTimePayment, "is_mark_paid" => 0]);
                }], 'net_amount')->applyFrequencyFilter($param, ["status" => 3, "payroll_id" => $historyId, "is_onetime_payment" => $isOneTimePayment])->first();
            $user = $payrollHistory?->payrollUser;
            $payDate = date('Y-m-d', strtotime($payrollHistory?->created_at)) ?? '';

            $salary = $payrollHistory?->hourly_salary_sum_amount ?? 0;
            $overtime = $payrollHistory?->over_times_sum_amount ?? 0;
            $commission = $payrollHistory?->commissions_sum_amount ?? 0;
            $commissionClawBack = $payrollHistory?->commission_claw_backs_sum_amount ?? 0;
            $commission = $commission - $commissionClawBack;
            $override = $payrollHistory?->overrides_sum_amount ?? 0;
            $overrideClawBack = $payrollHistory?->overrides_claw_backs_sum_amount ?? 0;
            $override = $override - $overrideClawBack;
            $reconciliation = $payrollHistory?->reconciliation_finalize_histories_sum_amount ?? 0;
            $deduction = $payrollHistory?->deductions_sum_amount ?? 0;
            $w2Deduction = W2PayrollTaxDeduction::applyFrequencyFilter($param, ['user_id' => $userId, 'is_onetime_payment' => $isOneTimePayment])->sum('fica_tax') ?? 0;
            $adjustment = $payrollHistory?->adjustment_details_sum_amount ?? 0;
            $fines = $payrollHistory?->approvals_and_requests_fines_sum_amount ?? 0;
            $approvalsAndRequests = $payrollHistory?->approvals_and_requests_sum_amount ?? 0;
            $adjustment = ($adjustment + $approvalsAndRequests) - $fines;
            $reimbursement = $payrollHistory?->approvals_and_requests_reimbursement_sum_amount ?? 0;
            $customField = $payrollHistory?->custom_fields_sum_amount ?? 0;
            $netPay = ($salary + $overtime + $commission + $override + $reconciliation + $adjustment + $reimbursement + $customField) - ($deduction + $w2Deduction);
            $accountCount = $payrollHistory?->account_count;
            $YTDAccountCount = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $payrollHistory?->pay_period_to)->whereYear('pay_period_from', date('Y', strtotime($payrollHistory?->pay_period_from)))->distinct('pid')->count('pid');
        }

        $companyProfile = CompanyProfile::first();
        $baseUrl = config("app.aws_s3bucket_url") . "/" . config("app.domain_name");
        $companyLogo = $baseUrl . "/" . $companyProfile?->logo;
        $companyProfile->logo = $companyLogo;

        $YTDAmounts = payrollHistoryYTDCalculation($userId, $payPeriodFrom, $payPeriodTo);
        $netPayYTD = $YTDAmounts['netPayYTD'];
        $salaryYTD = $YTDAmounts['salaryYTD'];
        $overtimeYTD = $YTDAmounts['overtimeYTD'];
        $commissionYTD = $YTDAmounts['commissionYTD'];
        $overrideYTD = $YTDAmounts['overrideYTD'];
        $reconciliationYTD = $YTDAmounts['reconciliationYTD'];
        $deductionYTD = $YTDAmounts['deductionYTD'];
        $w2DeductionYTD = $YTDAmounts['w2DeductionYTD'];
        $adjustmentYTD = $YTDAmounts['adjustmentYTD'];
        $reimbursementYTD = $YTDAmounts['reimbursementYTD'];
        $customFieldYTD = $YTDAmounts['customFieldYTD'];

        $data = [
            "CompanyProfile" => $companyProfile,
            "pay_stub" => [
                "net_pay" => $netPay,
                "net_ytd" => $netPayYTD,
                "pay_date" => $payDate,
                "is_onetime_payment" => $oneTimeFlag,
                "pay_period_from" => $payPeriodFrom ?? '',
                "pay_period_to" => $payPeriodTo ?? '',
                "pay_frequency" => $payFrequency ?? '',
                "period_sale_count" => $accountCount ?? 0,
                "ytd_sale_count" => $YTDAccountCount
            ],
            "earnings" => [
                "salary" => [
                    "period_total" => $salary,
                    "ytd_total" => $salaryYTD
                ],
                "overtime" => [
                    "period_total" => $overtime,
                    "ytd_total" => $overtimeYTD
                ],
                "commission" => [
                    "period_total" => $commission,
                    "ytd_total" => $commissionYTD
                ],
                "overrides" => [
                    "period_total" => $override,
                    "ytd_total" => $overrideYTD
                ],
                "reconciliation" => [
                    "period_total" => $reconciliation,
                    "ytd_total" => $reconciliationYTD
                ]
            ],
            "deduction" => [
                "standard_deduction" => [
                    "period_total" => $deduction,
                    "ytd_total" => $deductionYTD
                ],
                "fica_tax" => [
                    "period_total" => $w2Deduction,
                    "ytd_total" => $w2DeductionYTD
                ]
            ],
            "miscellaneous" => [
                "adjustment" => [
                    "period_total" => $adjustment,
                    "ytd_total" => $adjustmentYTD
                ],
                "reimbursement" => [
                    "period_total" => $reimbursement,
                    "ytd_total" => $reimbursementYTD
                ],
                "Total additional values" => [
                    "period_total" => $customField,
                    "ytd_total" => $customFieldYTD
                ]
            ],
            "employee" => $user
        ];

        $commissionDetailsLock = $this->payStubCommissionDetails($isOneTimePayment, $historyId);
        $overrideDetailsLock = $this->payStubOverrideDetails($isOneTimePayment, $historyId);
        $adjustmentDetailsLock = $this->payStubAdjustmentDetails($isOneTimePayment, $historyId);
        $reimbursementDetailsLock = $this->payStubReimbursementDetails($isOneTimePayment, $historyId);
        $deductionsDetailsLock = $this->payStubDeductionsDetails($isOneTimePayment, $historyId);
        $reconciliationDetailsLock = $this->payStubReconciliationDetails($isOneTimePayment, $historyId);
        $additionalValueDetailsLock = $this->payStubAdditionalDetails($isOneTimePayment, $historyId);
        $wagesValueDetailsLock = $this->payStubWagesDetails($isOneTimePayment, $historyId);

        $pdfPath = public_path("/template/" . $user->first_name . "_" . $user->last_name . "_" . time() . "_pay_stub.pdf");
        $pdf = Pdf::loadView('mail.paystub_available', [
            'data' => $data,
            'commissionDetails' => $commissionDetailsLock,
            'overrideDetails' => $overrideDetailsLock,
            'adjustmentDetails' => $adjustmentDetailsLock,
            'reimbursementDetails' => $reimbursementDetailsLock,
            'deductionsDetails' => $deductionsDetailsLock,
            'reconciliationDetails' => $reconciliationDetailsLock,
            'additionalDetails' => $additionalValueDetailsLock,
            'wagesDetails' => $wagesValueDetailsLock
        ]);
        $pdf->save($pdfPath);

        $filePath = config('app.domain_name') . '/' . "paystyb/" . $user->first_name . "_" . $user->last_name . "_" . time() . "_pay_stub.pdf";
        $s3Data = s3_upload($filePath, $pdfPath, true, 'public');
        $s3filePath = config("app.aws_s3bucket_url") . "/" . $filePath;

        $finalize['email'] = $user->email;
        $finalize['subject'] = 'New Paystub Available';

        $emailData = [
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "pay_frequency" => $payFrequency ?? '',
            "pay_period_from" => $payPeriodFrom ?? '',
            "pay_period_to" => $payPeriodTo ?? '',
            "s3filePath" => $s3filePath
        ];
        $finalize['template'] = view('mail.one-time-payment', compact('emailData'));
        $this->sendEmailNotification($finalize);
    }

    private function payStubCommissionDetails($isOneTimePayment, $historyId)
    {
        $companyProfile = CompanyProfile::first();
        $userCommissions = UserCommissionLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "amount",
            DB::raw("CAST(amount_type AS CHAR) COLLATE utf8mb4_general_ci AS amount_type"),
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
            DB::raw("CAST(amount_type AS CHAR) COLLATE utf8mb4_general_ci AS payroll_type")
        )->with([
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
        });

        $userClawBackCommissions = ClawbackSettlementLock::select(
            "id",
            "pid",
            "ref_id",
            "user_id",
            "clawback_amount as amount",
            DB::raw("CAST(adders_type AS CHAR) COLLATE utf8mb4_general_ci AS amount_type"),
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
            DB::raw('"clawback" as payroll_type')
        )->with([
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->where("type", DB::raw("'commission'"))->when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
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

        $commissionAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "commission"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->whereIn("type", $commissionType)->whereIn("adjustment_type", $commissionType)->whereIn("pid", $commissionPid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "commission", "type" => "clawback"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $commissionAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        foreach ($userCommissions as $userCommission) {
            $compRate = 0;
            // $repRedline = formatRedline($userCommission->redline, $userCommission->redline_type);
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $userCommission->commission_type !== "per sale") {
                $compRate = number_format($userCommission->comp_rate, 4, ".", "");
            }
            $netEpc = $userCommission?->payrollSaleData?->net_epc;
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && is_numeric($netEpc)) {
                $feePercentage = number_format(((float) $netEpc) * 100, 4, '.', '');
            } else {
                $feePercentage = null;
            }

            if ($userCommission->is_claw_back) {
                $amount = (0 - $userCommission->amount);
            } else {
                $amount = $userCommission->amount;
            }

            $adjustment = adjustmentColumn($userCommission, $adjustments, "commission");
            $row = [
                "id" => $userCommission->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userCommission->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userCommission?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "amount" => $amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "product" => $userCommission->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "gross_account_value" => $userCommission?->payrollSaleData?->gross_account_value, // PEST, TURF, FIBER, MORTGAGE // WORKER
                "adjustment" => $adjustment, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "amount_type" => $userCommission->schema_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "trigger_date" => $userCommission?->payrollSaleData?->salesProductMaster, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "rep_redline" => $userCommission->redline, // SOLAR, MORTGAGE // BOTH
                "rep_redline_type" => $userCommission->redline_type, // SOLAR, MORTGAGE // BOTH
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
            $data[] = $row;
        }

        return $data;
    }

    private function payStubOverrideDetails($isOneTimePayment, $historyId)
    {
        $userOverrides = UserOverridesLock::select(
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
            "payrollSaleData:pid,customer_name,kw,gross_account_value",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
        });

        $userClawBackOverrides = ClawbackSettlementLock::select(
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
            "payrollSaleData:pid,customer_name,kw,gross_account_value",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->where(["type" => "overrides", "clawback_type" => "next payroll"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
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

        $overrideAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "overrides"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->whereIn("type", $overrideType)->whereIn("adjustment_type", $overrideType)->whereIn("pid", $overridePid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "overrides", "type" => "clawback"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $overrideAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        foreach ($userOverrides as $userOverride) {
            $overImage = NULL;
            if ($userOverride?->payrollOverUser && $userOverride?->payrollOverUser->image && $userOverride?->payrollOverUser->image != "Employee_profile/default-user.png") {
                $overImage = s3_getTempUrl(config("app.domain_name") . "/" . $userOverride?->payrollOverUser->image);
            }

            if ($userOverride->is_claw_back) {
                $amount = (0 - $userOverride?->amount);
            } else {
                $amount = $userOverride?->amount;
            }

            $adjustment = adjustmentColumn($userOverride, $adjustments, "override");
            $data[] = [
                "id" => $userOverride->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $userOverride->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "customer_name" => $userOverride?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "product" => $userOverride?->product_code, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_first_name" => $userOverride?->payrollOverUser?->first_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_last_name" => $userOverride?->payrollOverUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_position_id" => $userOverride?->payrollOverUser?->position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_sub_position_id" => $userOverride?->payrollOverUser?->sub_position_id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_super_admin" => $userOverride?->payrollOverUser?->is_super_admin, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_is_manager" => $userOverride?->payrollOverUser?->is_manager, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "over_image" => $overImage, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "type" => $userOverride?->type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
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
        }

        return $data;
    }

    private function payStubAdjustmentDetails($isOneTimePayment, $historyId)
    {
        $data = [];
        if ($isOneTimePayment) {
            $oneTimeRecords = OneTimePayments::with("userData", "adjustment", "paidBy")->where(["id" => $historyId, "payment_status" => "3", "from_payroll" => 0])->whereNotIn("adjustment_type_id", [2, 5, 7, 8, 9])->get();
            foreach ($oneTimeRecords as $oneTimeRecord) {
                $image = null;
                if ($oneTimeRecord?->paidBy?->image && $oneTimeRecord?->paidBy?->image != "Employee_profile/default-user.png") {
                    $image = s3_getTempUrl(config("app.domain_name") . "/" . $oneTimeRecord?->paidBy?->image);
                }

                $date = isset($oneTimeRecord->pay_date) ? date("m/d/Y", strtotime($oneTimeRecord->pay_date)) : NULL;
                $data[] = [
                    "id" => $oneTimeRecord->id,
                    "pid" => NULL,
                    "customer_name" => $oneTimeRecord?->userData?->first_name . " " . $oneTimeRecord?->userData?->last_name,
                    "payroll_type" => "onetimepayment",
                    "payroll_modified_date" => $date,
                    "amount" => $oneTimeRecord->amount,
                    "date" => $date,
                    "description" => $oneTimeRecord->description,
                    "adjustment" => [
                        "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_by" => $oneTimeRecord?->paidBy?->first_name . " " . $oneTimeRecord?->paidBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "position_id" => $oneTimeRecord?->paidBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "sub_position_id" => $oneTimeRecord?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "is_manager" => $oneTimeRecord?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "is_super_admin" => $oneTimeRecord?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    ],
                    "operation_type" => "onetimepayment",
                    "is_mark_paid" => 0,
                    "is_next_payroll" => 0,
                    "is_stop_payroll" => $oneTimeRecord?->userData?->stop_payroll,
                    "is_onetime_payment" => 1,
                    "is_move_to_recon" => 0
                ];
            }
        }

        $adjustmentDetails = PayrollAdjustmentDetailLock::with([
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData:pid,customer_name"
        ])->when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
        })->get();

        foreach ($adjustmentDetails as $adjustmentDetail) {
            $adjustment = adjustmentColumn($adjustmentDetail, $adjustmentDetail, "adjustment");
            $date = isset($adjustmentDetail->updated_at) ? date("m/d/Y", strtotime($adjustmentDetail->updated_at)) : NULL;
            $data[] = [
                "id" => $adjustmentDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "pid" => $adjustmentDetail->pid, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "customer_name" => $adjustmentDetail?->payrollSaleData?->customer_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE // WORKER
                "payroll_type" => $adjustmentDetail->payroll_type, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
                "payroll_modified_date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE // BOTH
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
        }

        $approvalAndRequestDetails = ApprovalsAndRequestLock::with([
            "payrollUser:id,first_name,last_name,stop_payroll",
            "payrollAdjustment",
            "payrollComments",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->whereNotIn("adjustment_type_id", [2, 7, 8, 9])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();

        foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
            $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
            $date = isset($approvalAndRequestDetail->updated_at) ? date("m/d/Y", strtotime($approvalAndRequestDetail->updated_at)) : NULL;
            $amount = ($approvalAndRequestDetail->adjustment_type_id == 5 && !empty($approvalAndRequestDetail["amount"])) ? -1 * $approvalAndRequestDetail["amount"] : 1 * $approvalAndRequestDetail["amount"];
            $data[] = [
                "id" => $approvalAndRequestDetail->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "pid" => $approvalAndRequestDetail->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "customer_name" => $approvalAndRequestDetail?->payrollUser?->first_name . " " . $approvalAndRequestDetail?->payrollUser?->last_name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "payroll_type" => $approvalAndRequestDetail?->payrollAdjustment?->name, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                "payroll_modified_date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
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
            ];
        }

        return $data;
    }

    public function payStubReimbursementDetails($isOneTimePayment, $historyId)
    {
        $data = [];
        if ($isOneTimePayment) {
            $oneTimeRecords = OneTimePayments::with("userData", "adjustment", "paidBy")->where(["id" => $historyId, "payment_status" => "3", "adjustment_type_id" => 2, "from_payroll" => 0])->get();
            foreach ($oneTimeRecords as $oneTimeRecord) {
                $image = null;
                if ($oneTimeRecord?->paidBy?->image && $oneTimeRecord?->paidBy?->image != "Employee_profile/default-user.png") {
                    $image = s3_getTempUrl(config("app.domain_name") . "/" . $oneTimeRecord?->paidBy?->image);
                }

                $date = isset($oneTimeRecord->updated_at) ? date("m/d/Y", strtotime($oneTimeRecord->updated_at)) : NULL;
                $data[] = [
                    "id" => $oneTimeRecord->id, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "req_no" => $oneTimeRecord->req_no, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "cost_center" => NULL, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "amount" => $oneTimeRecord->amount, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "description" => $oneTimeRecord->description, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "date" => $date, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "adjustment" => [
                        "adjustment_amount" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_by" => $oneTimeRecord?->paidBy?->first_name . " " . $oneTimeRecord?->paidBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_comment" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "adjustment_id" => null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "position_id" => $oneTimeRecord?->paidBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "sub_position_id" => $oneTimeRecord?->paidBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "is_manager" => $oneTimeRecord?->paidBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                        "is_super_admin" => $oneTimeRecord?->paidBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    ],
                    "operation_type" => "reimbursement", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                    "is_mark_paid" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_next_payroll" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_stop_payroll" => $oneTimeRecord?->userData?->stop_payroll, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_onetime_payment" => 1, // SOLAR, PEST, TURF, FIBER, MORTGAGE
                    "is_move_to_recon" => 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE // CAN NOT MOVE TO RECON
                ];
            }
        }

        $approvalAndRequestDetails = ApprovalsAndRequestLock::with([
            "payrollCostCenter",
            "payrollUser:id,stop_payroll",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->where(["adjustment_type_id" => 2])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();

        foreach ($approvalAndRequestDetails as $approvalAndRequestDetail) {
            $adjustment = adjustmentColumn($approvalAndRequestDetail, $approvalAndRequestDetail, "adjustment");
            $date = isset($approvalAndRequestDetail->cost_date) ? date("m/d/Y", strtotime($approvalAndRequestDetail->cost_date)) : NULL;

            $data[] = [
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
            ];
        }

        return $data;
    }

    public function payStubDeductionsDetails($isOneTimePayment, $historyId)
    {
        $deductionDetails = PayrollDeductionLock::with([
            "payrollUser:id,stop_payroll",
            "payrollCostCenter"
        ])->when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
        })->get();

        $deductionAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["payroll_type" => "deduction"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();

        $data = [];
        foreach ($deductionDetails as $deductionDetail) {
            $adjustment = adjustmentColumn($deductionDetail, $deductionAdjustments, "deduction");

            $data[] = [
                "type" => $deductionDetail?->payrollCostCenter?->name,
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
        }

        if ($isOneTimePayment) {
            $w2PayrollTaxDeductions = W2PayrollTaxDeduction::where(["one_time_payment_id" => $historyId])->get();
        } else {
            $payrollHistory = PayrollHistory::where(["payroll_id" => $historyId])->first();

            $param = [
                "pay_period_from" => $payrollHistory->pay_period_from,
                "pay_period_to" => $payrollHistory->pay_period_to,
                "pay_frequency" => $payrollHistory->pay_frequency,
                "worker_type" => $payrollHistory->worker_type
            ];
            $w2PayrollTaxDeductions = W2PayrollTaxDeduction::applyFrequencyFilter($param)->get();
        }

        $taxNames = W2PayrollTaxDeduction::TAXES;
        foreach ($w2PayrollTaxDeductions as $w2PayrollTaxDeduction) {
            foreach ($taxNames as $column => $name) {
                if (isset($w2PayrollTaxDeduction->$column) && !empty($w2PayrollTaxDeduction->$column)) {
                    $data[] = [
                        "type" => $name,
                        "amount" => $w2PayrollTaxDeduction->$column,
                        "limit" => null,
                        "total" => $w2PayrollTaxDeduction->$column,
                        "outstanding" => null,
                        "cost_center_id" => null,
                        "operation_type" => "deduction", // SOLAR, PEST, TURF, FIBER, MORTGAGE // FOR MARK AS PAID, NEXT PAYROLL
                        "is_mark_paid" => 0,
                        "is_next_payroll" => 0,
                        "is_stop_payroll" => 0,
                        "is_onetime_payment" => $isOneTimePayment,
                        "is_move_to_recon" => 0
                    ];
                }
            }
        }

        return $data;
    }

    public function payStubReconciliationDetails($isOneTimePayment, $historyId)
    {
        $data = [];
        $reconciliationPayrollDetails = ReconciliationFinalizeHistoryLock::when($isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
        })->when(!$isOneTimePayment, function ($q) use ($historyId) {
            $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
        })->get();
        foreach ($reconciliationPayrollDetails as $reconciliationPayrollDetail) {
            $total = ($reconciliationPayrollDetail->paid_commission + $reconciliationPayrollDetail->paid_override + $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions - $reconciliationPayrollDetail->clawback);
            $date = isset($reconciliationPayrollDetail->updated_at) ? date("m/d/Y", strtotime($reconciliationPayrollDetail->updated_at)) : NULL;

            $data[] = [
                "payroll_added_date" => date("m-d-Y h:s:a", strtotime($reconciliationPayrollDetail->updated_at)),
                "start_end" => date("m/d/Y", strtotime($reconciliationPayrollDetail->start_date)) . " to " . date("m/d/Y", strtotime($reconciliationPayrollDetail->end_date)),
                "commission" => $reconciliationPayrollDetail->paid_commission,
                "override" => $reconciliationPayrollDetail->paid_override,
                "clawback" => (-1 * $reconciliationPayrollDetail->clawback),
                "adjustment" => $reconciliationPayrollDetail->adjustments - $reconciliationPayrollDetail->deductions,
                "total" => $total,
                "payout" => $reconciliationPayrollDetail->payout,
                "payroll_modified_date" => $date
            ];
        }

        return $data;
    }

    public function payStubAdditionalDetails($isOneTimePayment, $historyId)
    {
        $data = [];
        $customFields = CustomFieldHistory::with(["getColumn", "getApprovedBy"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();
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
        }

        return $data;
    }

    public function payStubWagesDetails($isOneTimePayment, $historyId)
    {
        $salaryDetails = PayrollHourlySalaryLock::with(["payrollUser:id,stop_payroll"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();
        $salaryAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["type" => "hourlysalary", "payroll_type" => "hourlysalary"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();

        $data = [];
        foreach ($salaryDetails as $salaryDetail) {
            $adjustment = adjustmentColumn($salaryDetail, $salaryAdjustments, "hourlysalary");
            $date = isset($salaryDetail->updated_at) ? date("m/d/Y", strtotime($salaryDetail->updated_at)) : NULL;

            $data[] = [
                "id" => $salaryDetail->id,
                "date" => $salaryDetail->date ? date("m/d/Y", strtotime($salaryDetail->date)) : NULL,
                "hourly_rate" => $salaryDetail->hourly_rate * 1,
                "salary" => $salaryDetail->salary * 1,
                "regular_hour" => $salaryDetail->regular_hours,
                "total" => $salaryDetail->total * 1,
                "payroll_modified_date" => $date,
                "adjustment" => $adjustment,
                "operation_type" => "salary", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => 0,
                "is_next_payroll" => 0,
                "is_stop_payroll" => $salaryDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => 1
            ];
        }

        $overtimeDetails = PayrollOvertimeLock::with(["payrollUser:id,stop_payroll"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();
        $overtimeAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["type" => "overtime", "payroll_type" => "overtime"])
            ->when($isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 1, "one_time_payment_id" => $historyId]);
            })->when(!$isOneTimePayment, function ($q) use ($historyId) {
                $q->where(["is_onetime_payment" => 0, "payroll_id" => $historyId]);
            })->get();

        foreach ($overtimeDetails as $overtimeDetail) {
            $adjustment = adjustmentColumn($overtimeDetail, $overtimeAdjustments, "overtime");
            $date = isset($overtimeDetail->updated_at) ? date("m/d/Y", strtotime($overtimeDetail->updated_at)) : NULL;

            $data[] = [
                "id" => $overtimeDetail->id,
                "date" => $overtimeDetail->date ? date("m/d/Y", strtotime($overtimeDetail->date)) : NULL,
                "overtime_rate" => $overtimeDetail->overtime_rate * 1,
                "overtime_hour" => $overtimeDetail->overtime_hours,
                "total" => $overtimeDetail->total * 1,
                "payroll_modified_date" => $date,
                "adjustment" => $adjustment,
                "operation_type" => "overtime", // FOR MARK AS PAID, NEXT PAYROLL
                "is_mark_paid" => 0,
                "is_next_payroll" => 0,
                "is_stop_payroll" => $overtimeDetail?->payrollUser?->stop_payroll,
                "is_onetime_payment" => 1
            ];
        }

        return $data;
    }
}
