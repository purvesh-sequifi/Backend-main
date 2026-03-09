<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Http\Request;
use App\Models\FrequencyType;
use App\Models\PayrollSsetup;
use App\Models\CompanyProfile;
use App\Models\PayrollHistory;
use App\Models\DailyPayFrequency;
use App\Models\UserOverridesLock;
use Illuminate\Support\Facades\DB;
use App\Models\CustomFieldHistory;
use App\Models\UserCommissionLock;
use App\Models\WeeklyPayFrequency;
use App\Models\MonthlyPayFrequency;
use App\Models\PositionPayFrequency;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\W2PayrollTaxDeduction;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlementLock;
use Illuminate\Support\Facades\Validator;
use App\Models\PayrollAdjustmentDetailLock;
use App\Exports\Admin\PayrollExport\PidBasicExport;
use App\Exports\Admin\PayrollExport\PidDetailExport;
use App\Exports\Admin\PayrollExport\WorkerBasicExport;
use App\Exports\Admin\PayrollExport\WorkerDetailExport;
use App\Exports\Admin\PayrollExport\WorkerAllDetailsExport;
use App\Jobs\PayrollFailedRecordsProcess;
use App\Models\ApprovalsAndRequestLock;
use App\Models\Crms;
use App\Models\OneTimePayments;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\paystubEmployee;
use App\Models\ReconciliationFinalizeHistoryLock;

class PayrollReportController extends Controller
{
    use PayFrequencyTrait;

    const FILE_TYPE = ".xlsx";
    const EXPORT_FOLDER_PATH = 'exports/';
    const EXPORT_STORAGE_FOLDER_PATH = "exports/";

    public function payrollReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from_month" => "required",
            "pay_period_from_year" => "required|date_format:Y",
            "frequency_type" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "per_page" => "nullable|integer|min:1|max:100",
            "page" => "nullable|integer|min:1"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "payroll-report",
                "error" => $validator->errors()
            ], 400);
        }

        $frequency = FrequencyType::find($request->frequency_type);
        if (!$frequency) {
            return response()->json([
                "status" => false,
                "ApiName" => "payroll-report",
                "error" => "Frequency type not found"
            ], 400);
        }

        // Determine per page value (default to 10)
        $perPage = $request->input('per_page', 10);

        if ($frequency->id == FrequencyType::WEEKLY_ID) {
            $payFrequencies = WeeklyPayFrequency::where('closed_status', 1)->orWhere('w2_closed_status', 1);
        } else if ($frequency->id == FrequencyType::BI_WEEKLY_ID) {
            $payFrequencies = AdditionalPayFrequency::where(function ($query) {
                $query->where('closed_status', 1)->orWhere('w2_closed_status', 1);
            })->where('type', '1');
        } else if ($frequency->id == FrequencyType::SEMI_MONTHLY_ID) {
            $payFrequencies = AdditionalPayFrequency::where(function ($query) {
                $query->where('closed_status', 1)->orWhere('w2_closed_status', 1);
            })->where('type', '2');
        } else if ($frequency->id == FrequencyType::MONTHLY_ID) {
            $payFrequencies = MonthlyPayFrequency::where('closed_status', 1)->orWhere('w2_closed_status', 1);
        } else if ($frequency->id == FrequencyType::DAILY_PAY_ID) {
            $payFrequencies = DailyPayFrequency::where(['closed_status' => 1]);
        }

        // Get all pay frequencies first (not paginated yet)
        $payFrequencies = $payFrequencies->orderBy('pay_period_from', 'desc')->get();

        $data = [];
        $totalCommissions = 0;
        $totalOverride = 0;
        $totalHourlySalary = 0;
        $totalOvertime = 0;
        $totalAdjustment = 0;
        $totalReconciliation = 0;
        $totalDeduction = 0;
        $totalPay = 0;
        $totalTaxes = 0;
        $totalReimbursement = 0;
        $totalCustomPayment = 0;
        foreach ($payFrequencies as $payFrequency) {
            $payrollCheck = PayrollHistory::when($request->frequency_type == FrequencyType::DAILY_PAY_ID, function ($query) use ($payFrequency) {
                $query->whereBetween('pay_period_from', [$payFrequency->pay_period_from, $payFrequency->pay_period_to])
                    ->whereBetween('pay_period_to', [$payFrequency->pay_period_from, $payFrequency->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($payFrequency) {
                $query->where('pay_period_from', $payFrequency->pay_period_from)
                    ->where('pay_period_to', $payFrequency->pay_period_to);
            })->when($request->pay_period_from_month != 'all', function ($q) use ($request) {
                $q->whereMonth('created_at', $request->pay_period_from_month);
            })->whereYear('created_at', $request->pay_period_from_year)->where(['pay_frequency' => $frequency->id])->first();

            if ($payrollCheck) {
                $payrollHistory = PayrollHistory::selectRaw('
                    sum(hourly_salary) as hourly_salary, 
                    sum(overtime) as overtime, 
                    sum(commission) as commission, 
                    sum(override) as override, 
                    sum(deduction) as deduction, 
                    sum(reconciliation) as reconciliation, 
                    sum(reimbursement) as reimbursement, 
                    sum(adjustment) as adjustment, 
                    sum(custom_payment) as custom_payment, 
                    sum(net_pay) as net_pay, 
                    created_at, 
                    updated_at
                ')->when($request->frequency_type == FrequencyType::DAILY_PAY_ID, function ($query) use ($payFrequency) {
                    $query->whereBetween('pay_period_from', [$payFrequency->pay_period_from, $payFrequency->pay_period_to])
                        ->whereBetween('pay_period_to', [$payFrequency->pay_period_from, $payFrequency->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($payFrequency) {
                    $query->where('pay_period_from', $payFrequency->pay_period_from)
                        ->where('pay_period_to', $payFrequency->pay_period_to);
                })->when($request->pay_period_from_month != 'all', function ($q) use ($request) {
                    $q->whereMonth('created_at', $request->pay_period_from_month);
                })->whereYear('created_at', $request->pay_period_from_year)->where(['pay_frequency' => $frequency->id])->first();

                if ($payrollHistory) {
                    $w2taxDetails = W2PayrollTaxDeduction::selectRaw('(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes')
                        ->where(['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])
                        ->first();
                    $totalTax = ($w2taxDetails->total_taxes ?? 0);

                    $netPay = $payrollHistory->net_pay ?? 0;
                    if ($totalTax && $totalTax !== 0) {
                        $netPay -= $totalTax;
                    }

                    $totalHourlySalary += $payrollHistory->hourly_salary ?? 0;
                    $totalOvertime += $payrollHistory->overtime ?? 0;
                    $totalCommissions += $payrollHistory->commission ?? 0;
                    $totalOverride += $payrollHistory->override ?? 0;
                    $totalDeduction += $payrollHistory->deduction ?? 0;
                    $totalReconciliation += $payrollHistory->reconciliation ?? 0;
                    $totalReimbursement += $payrollHistory->reimbursement ?? 0;
                    $totalAdjustment += $payrollHistory->adjustment ?? 0;
                    $totalCustomPayment += $payrollHistory->custom_payment ?? 0;
                    $totalPay += $netPay;
                    $totalTaxes += $totalTax;

                    $data[] = [
                        'hourlysalary' => $payrollHistory->hourly_salary ?? 0,
                        'override' => $payrollHistory->override ?? 0,
                        'commission' => $payrollHistory->commission ?? 0,
                        'overtime' => $payrollHistory->overtime ?? 0,
                        'deduction' => $payrollHistory->deduction ?? 0,
                        'reconciliation' => $payrollHistory->reconciliation ?? 0,
                        'reimbursement' => $payrollHistory->reimbursement ?? 0,
                        'adjustment' => $payrollHistory->adjustment ?? 0,
                        'custom_payment' => $payrollHistory->custom_payment ?? 0,
                        'netPay' => $netPay,
                        'taxes' => $totalTax,
                        'payroll_date' => $payrollHistory->created_at ? date('Y-m-d', strtotime($payrollHistory->created_at)) : ($payrollHistory->updated_at ? date('Y-m-d', strtotime($payrollHistory->updated_at)) : null),
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to
                    ];
                }
            }
        }

        // Export functionality
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $exportData = [
            'year' => $request->pay_period_from_year,
            'total_hourlysalary' => $totalHourlySalary,
            'total_overtime' => $totalOvertime,
            'total_commissions' => $totalCommissions,
            'total_override' => $totalOverride,
            'total_deduction' => $totalDeduction,
            'total_reconciliation' => $totalReconciliation,
            'total_reimbursement' => $totalReimbursement,
            'total_adjustment' => $totalAdjustment,
            'total_custom_payment' => $totalCustomPayment,
            'total_Pay' => $totalPay,
            'total_taxes' => $totalTaxes,
            'payroll_report' => $data,
            ];
            $fileName = 'payroll_export_' . date('Y_m_d') . '.xlsx';
            Excel::store(
                new \App\Exports\ExportPayroll\ExportPayroll($exportData),
                'exports/payroll/frequency-type/' . $fileName,
                'public',
                \Maatwebsite\Excel\Excel::XLSX
            );
            $url = getStoragePath('exports/payroll/frequency-type/' . $fileName);
            return response()->json(['url' => $url]);
        }

        // Paginate the final data array using customPaginator helper
        $paginatedData = customPaginator($data, $perPage);
        $paginationData = $paginatedData->toArray();
        
        $response = [
            'current_page' => $paginationData['current_page'],
            'data' => $paginationData['data'],
            'first_page_url' => $paginationData['first_page_url'],
            'from' => $paginationData['from'],
            'last_page' => $paginationData['last_page'],
            'last_page_url' => $paginationData['last_page_url'],
            'links' => $paginationData['links'],
            'next_page_url' => $paginationData['next_page_url'],
            'path' => $paginationData['path'],
            'per_page' => $paginationData['per_page'],
            'prev_page_url' => $paginationData['prev_page_url'],
            'to' => $paginationData['to'],
            'total' => $paginationData['total'],
            // Additional summary fields
            'year' => $request->pay_period_from_year,
            'total_hourlysalary' => $totalHourlySalary,
            'total_overtime' => $totalOvertime,
            'total_commissions' => $totalCommissions,
            'total_override' => $totalOverride,
            'total_deduction' => $totalDeduction,
            'total_reconciliation' => $totalReconciliation,
            'total_reimbursement' => $totalReimbursement,
            'total_adjustment' => $totalAdjustment,
            'total_custom_payment' => $totalCustomPayment,
            'total_Pay' => $totalPay,
            'total_taxes' => $totalTaxes,
        ];

        return response()->json([
            "status" => true,
            "ApiName" => "payroll-report",
            "message" => "Successfully.",
            "data" => $response
        ]);
    }

    public function payrollReportData(Request $request, $fromPayroll = 0)
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

        $evereeStatus = Crms::where(["id" => 3, "status" => 1])->first();
        if (!empty($evereeStatus)) {
            $payrollHistoryForFailedStatuses = PayrollHistory::with(['payrollUser'])->applyFrequencyFilter($param, ["pay_type" => "Bank", 'everee_status' => '2'])->get();
            if ($payrollHistoryForFailedStatuses) {
                foreach ($payrollHistoryForFailedStatuses as $payrollHistoryForFailedStatus) {
                    if ($payrollHistoryForFailedStatus?->payrollUser?->everee_workerId && !empty($payrollHistoryForFailedStatus?->payrollUser?->everee_workerId)) {
                        PayrollFailedRecordsProcess::Dispatch($payrollHistoryForFailedStatus->payrollUser->id);
                    }
                }
            }
        }

        if ($type == "pid") {
            $sort = $request->input("sort", "pid");
            $sortValue = $request->input("sort_val", "ASC");

            $commissionPayrolls = UserCommissionLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                })->get();

            $overridePayrolls = UserOverridesLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                })->get();

            $clawBackPayrolls = ClawbackSettlementLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'clawback_amount', 'type')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->where(function ($q) {
                    $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                })->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::select('is_mark_paid', 'is_next_payroll', 'is_onetime_payment', 'is_move_to_recon', 'pid', 'amount')
                ->with("payrollSaleData:pid,customer_name,net_epc,gross_account_value")
                ->applyFrequencyFilter($param, ["status" => "3"])
                ->whereIn('payroll_type', ['commission', 'overrides'])
                ->where(function ($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })->when($search && !empty($search), function ($q) {
                    $q->whereHas("payrollSaleData", function ($q) {
                        $q->where("pid", "LIKE", "%" . request()->input("search") . "%")->orWhere("customer_name", "LIKE", "%" . request()->input("search") . "%");
                    });
                })->get();

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
                        "status" => 3
                    ];
                }
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
                "data" => $finalData
            ]);
        } else {
            $sort = $request->input("sort", "full_name");
            $sortValue = $request->input("sort_val", "ASC");

            $results = PayrollHistory::select(
                "payroll_id",
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
                DB::raw("0 as gross_pay"),
                DB::raw("1 as from_history"),
                "status",
                DB::raw("0 as finalize_status"),
                "is_mark_paid",
                "is_next_payroll",
                "is_onetime_payment",
                DB::raw("0 as user_request_count"),
                "everee_payment_status",
                "everee_status",
                "everee_webhook_json",
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
                            ->leftJoin('sale_masters', 'user_commission_lock.pid', 'sale_masters.pid')
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
                })->with([
                    "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll,onboardProcess,everee_embed_onboard_profile,worker_type"
                ])->applyFrequencyFilter($param, ["status" => 3])->when($sort && $sortValue, function ($query) use ($sort, $sortValue) {
                    if ($sort == 'full_name') {
                        $query->orderBy(DB::raw("(SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = user_id)"), $sortValue);
                    } else if ($sort == "total_sale") {
                        $query->orderBy("total_sale_count", $sortValue);
                    } else if ($sort == "total_gross_account_value") {
                        $query->orderBy("total_gross_account_value", $sortValue);
                    } else {
                        $query->orderBy($sort, $sortValue);
                    }
                })->when($fromPayroll, function ($query) {
                    $query->where(["is_onetime_payment" => 0, "pay_type" => "Bank"])->whereIn('everee_payment_status', [1, 2]);
                })->paginate($perPage);

            $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
            $settings = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->orderBy('id', 'Asc')->get();
            $customFieldsHistory = CustomFieldHistory::whereIn('payroll_id', $results->getCollection()->pluck('payroll_id'))->get();

            // Fetch tax data upfront for W2 users (similar to customFieldsHistory)
            $taxHistory = collect();
            if ($workerType == 'w2') {
                $userIds = $results->getCollection()->pluck('user_id')->unique();
                $taxHistory = W2PayrollTaxDeduction::select(
                    'user_id',
                    'pay_period_from',
                    'pay_period_to',
                    DB::raw('SUM(state_income_tax) as state_income_tax'),
                    DB::raw('SUM(federal_income_tax) as federal_income_tax'),
                    DB::raw('SUM(medicare_tax) as medicare_tax'),
                    DB::raw('SUM(social_security_tax) as social_security_tax'),
                    DB::raw('SUM(additional_medicare_tax) as additional_medicare_tax'),
                    DB::raw('(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes')
                )
                    ->whereIn('user_id', $userIds)
                    ->where('pay_period_from', $payPeriodFrom)
                    ->where('pay_period_to', $payPeriodTo)
                    ->where('is_onetime_payment', 0)
                    ->groupBy('user_id', 'pay_period_from', 'pay_period_to')
                    ->get();
            }

            $transformedCollection = $results->getCollection()->map(function ($payrollDetail) use ($settings, $customFieldsHistory, $taxHistory, $workerType) {
                $evereeWebhookMessage = null;
                if ($payrollDetail->everee_payment_status == 3) {
                    $evereeWebhookMessage = "Payment Success From Everee ";
                } else if ($payrollDetail->everee_payment_status == 2 && isset($payrollDetail->everee_status) && $payrollDetail->everee_status == 2 && ($payrollDetail->everee_webhook_json == null || $payrollDetail->everee_webhook_json == "")) {
                    // Differentiate between profile completion and self-onboarding completion
                    $user = $payrollDetail->payrollUser;
                    if (!$user || !$user->onboardProcess) {
                        // Self-onboarding completion - user hasn't completed Everee self-onboarding
                        $evereeWebhookMessage = "Payment will be processed once the user has logged in and completed the self-onboarding steps, confirming all required details.";
                    } else {
                        // Default fallback message
                        $evereeWebhookMessage = "Payment will be processed once the user profile is fully completed.";
                    }
                } else if ($payrollDetail->everee_payment_status == 2 && $payrollDetail->everee_webhook_json != null && $payrollDetail->everee_webhook_json != "") {
                    $evereeWebhookData = json_decode($payrollDetail->everee_webhook_json, true);
                    if (isset($evereeWebhookData['paymentStatus']) && $evereeWebhookData['paymentStatus'] == "ERROR") {
                        $evereeWebhookMessage = $evereeWebhookData['paymentErrorMessage'] ?? null;
                    } else {
                        $evereeWebhookMessage = $payrollDetail->everee_webhook_json;
                    }
                } else if ($payrollDetail->everee_payment_status == 1) {
                    $evereeWebhookMessage = "Waiting for payment status to be updated.";
                } else if ($payrollDetail->everee_payment_status == 0) {
                    $evereeWebhookMessage = "Everee Setting is Disabled. Payment Done";
                }

                $payrollCustomFields = [];
                foreach ($settings as $setting) {
                    $customFieldsData = $customFieldsHistory;
                    $field = $customFieldsData->where('payroll_id', $payrollDetail->payroll_id)->where('column_id', $setting->id)->first();
                    $payrollCustomFields[] = [
                        'id' => $setting->id,
                        'field_name' => $setting->field_name,
                        'value' => $field ? $field->value : '0'
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

                // Calculate total taxes and tax details for W2 users using taxHistory
                $totalTaxes = 0;
                $taxDetails = [];
                if ($workerType == 'w2') {
                    $w2taxDetails = $taxHistory->where('user_id', $payrollDetail->user_id)->first();
                    if ($w2taxDetails) {
                        $totalTaxes = $w2taxDetails->total_taxes ?? 0;

                        // Build tax_details array with individual tax items
                        $taxNames = W2PayrollTaxDeduction::TAXES;
                        foreach ($taxNames as $column => $name) {
                            $amount = $w2taxDetails->$column ?? 0;
                            if ($amount > 0) {
                                $taxDetails[] = [
                                    'name' => $name,
                                    'amount' => round($amount, 2)
                                ];
                            }
                        }
                    }
                }

                return [
                    "id" => $payrollDetail->payroll_id,
                    "user_id" => $payrollDetail->user_id,
                    "user_data" => payrollUserDataCommon($payrollDetail->payrollUser),
                    "total_sale" => $payrollDetail->total_sale_count,
                    "total_gross_account_value" => $payrollDetail->total_gross_account_value,
                    'position' => isset($payrollDetail->payrollUser->positionDetail->position_name) ? $payrollDetail->payrollUser->positionDetail->position_name : null,
                    "commission" => $payrollDetail->commission,
                    "override" => $payrollDetail->override,
                    "reimbursement" => $payrollDetail->reimbursement,
                    "deduction" => $payrollDetail->deduction,
                    "adjustment" => $payrollDetail->adjustment,
                    "reconciliation" => $payrollDetail->reconciliation,
                    "hourly_salary" => $payrollDetail->hourly_salary,
                    "custom_fields" => $payrollCustomFields,
                    "overtime" => $payrollDetail->overtime,
                    "taxes" => round($totalTaxes, 2),
                    "tax_details" => $taxDetails,
                    "net_pay" => $payrollDetail->net_pay,
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
                    "everee_payment_status" => $payrollDetail->everee_payment_status,
                    "everee_response" => $evereeWebhookMessage
                ];
            });
            $results->setCollection($transformedCollection);

            $paymentFailed = PayrollHistory::selectRaw("
                COALESCE(SUM(CASE WHEN everee_payment_status = 1 THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN everee_payment_status = 2 THEN 1 ELSE 0 END), 0) as failed_count
            ")->applyFrequencyFilter($param, ["is_onetime_payment" => 0, "pay_type" => "Bank"])->first();
            $pendingCount = (int) ($paymentFailed->pending_count ?? 0);
            $failedCount = (int) ($paymentFailed->failed_count ?? 0);
            $paymentStatus = ($pendingCount > 0 || $failedCount > 0) ? 1 : 0;
            return response()->json([
                "status" => true,
                "message" => "Successfully.",
                "ApiName" => "get-payroll-data",
                "data" => $results,
                "payment_failed" => $paymentStatus, // 0 WHEN PAYROLL, 1 WHEN PAYROLL HISTORY
                "payment_status_type" => $pendingCount > 0 ? "pending" : ($failedCount > 0 ? "failed" : "success")
            ]);
        }
    }

    public function workerBasic(Request $request)
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
                "ApiName" => "worker-basic",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-basic-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerBasicExport($request, 1), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function workerDetail(Request $request)
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
                "ApiName" => "worker-detail",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-detail-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerDetailExport($request, 1), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function workerAllDetails(Request $request)
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
                "ApiName" => "worker-all-details",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "worker-all-details-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new WorkerAllDetailsExport($request, 1), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function pidBasic(Request $request)
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
                "ApiName" => "pid-basic",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "pid-basic-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new PidBasicExport($request, 1), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function pidDetail(Request $request)
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
                "ApiName" => "pid-detail",
                "error" => $validator->errors()
            ], 400);
        }

        $filename = "pid-details-export-" . date("Y-m-d") . self::FILE_TYPE;
        Excel::store(new PidDetailExport($request, 1), self::EXPORT_FOLDER_PATH . $filename, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath(self::EXPORT_STORAGE_FOLDER_PATH . $filename);
        return response()->json(['url' => $url]);
    }

    public function pendingPay(Request $request)
    {
        $userId = $request->user_id;
        if (!$userId) {
            $userId = auth()->user()->id;
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => "User not found!!",
                'data' => []
            ], 400);
        }

        if (!$user->sub_position_id) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => "Position not set for this user!!",
                'data' => []
            ], 400);
        }

        $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
        if (!$positionPayFrequency) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => "User's position have no pay frequency!!",
                'data' => []
            ], 400);
        }

        $openPayFrequency = $this->openPayFrequency($user->sub_position_id, $userId);
        if (!isset($openPayFrequency->pay_period_from)) {
            return response()->json([
                'ApiName' => 'getPendingPay',
                'status' => false,
                'message' => "Pay period not found!!",
                'data' => []
            ], 400);
        }
        $payPeriodFrom = $openPayFrequency->pay_period_from ?? null;
        $payPeriodTo = $openPayFrequency->pay_period_to ?? null;

        $param = [
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo,
            "pay_frequency" => $positionPayFrequency->frequency_type_id,
            "worker_type" => $user->worker_type
        ];
        $payroll = Payroll::selectRaw('
            id,
            sum(hourly_salary) as hourly_salary, 
            sum(overtime) as overtime, 
            sum(commission) as commission, 
            sum(override) as override, 
            sum(deduction) as deduction, 
            sum(reconciliation) as reconciliation, 
            sum(reimbursement) as reimbursement, 
            sum(adjustment) as adjustment, 
            sum(custom_payment) as custom_payment, 
            sum(net_pay) as net_pay
        ')->applyFrequencyFilter($param, ['user_id' => $userId, 'finalize_status' => 0, 'status' => 1])->orderBy('pay_period_to', 'asc')->first();

        $totalCommissions = $payroll->commission ?? 0;
        $totalOverrides = $payroll->override ?? 0;
        $totalAdjustments = $payroll->adjustment ?? 0;
        $totalReimbursements = $payroll->reimbursement ?? 0;
        $totalDeductions = $payroll->deduction ?? 0;
        $totalReconciliations = $payroll->reconciliation ?? 0;
        $totalCustomPayments = $payroll->custom_payment ?? 0;
        $totalWages = ($payroll->hourly_salary ?? 0) + ($payroll->overtime ?? 0);
        $totalAnticipatedPay = $payroll->net_pay ?? 0;
        $data = [
            'commission' => $totalCommissions,
            'override' => $totalOverrides,
            'adjustment' => $totalAdjustments,
            'reimbursement' => $totalReimbursements,
            'deduction' => $totalDeductions,
            'reconciliation' => $totalReconciliations,
            'custom_field' => $totalCustomPayments,
            'wages' => $totalWages,
            'anticipated_pay' => $totalAnticipatedPay
        ];

        return response()->json([
            'ApiName' => 'get_payroll_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'payroll_id' => $payroll->id,
            'pay_period_from' => $payPeriodFrom,
            'pay_period_to' => $payPeriodTo,
            'pay_frequency' => $positionPayFrequency->frequency_type_id,
            'worker_type' => $user->worker_type
        ]);
    }

    public function pastPayStub(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "year" => "required|date_format:Y",
            "user_id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "past-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $year = $request->year;
        $userId = $request->user_id;
        $perPage = $request->input('perpage', 10);

        $payrollHistories = PayrollHistory::select([
            DB::raw('MAX(id) as id'),
            'user_id',
            'pay_period_from',
            'pay_period_to',
            DB::raw('(SELECT SUM(state_income_tax + federal_income_tax + medicare_tax + 
                          social_security_tax + additional_medicare_tax)
              FROM w2_payroll_tax_deductions
              WHERE w2_payroll_tax_deductions.user_id = payroll_history.user_id
                AND w2_payroll_tax_deductions.pay_period_from = payroll_history.pay_period_from
                AND w2_payroll_tax_deductions.pay_period_to = payroll_history.pay_period_to
                AND w2_payroll_tax_deductions.is_onetime_payment = 0
            ) as total_taxes'),
            DB::raw("DATE_FORMAT(MAX(created_at), '%Y-%m-%d') as payroll_date"),
            DB::raw('COUNT(id) as payroll_history_count'),
            DB::raw('COALESCE(SUM(hourly_salary), 0) as hourly_salary'),
            DB::raw('COALESCE(SUM(overtime), 0) as overtime'),
            DB::raw('COALESCE(SUM(commission), 0) as commission'),
            DB::raw('COALESCE(SUM(override), 0) as override'),
            DB::raw('COALESCE(SUM(reconciliation), 0) as reconciliation'),
            DB::raw('COALESCE(SUM(deduction), 0) as deduction'),
            DB::raw('COALESCE(SUM(adjustment), 0) as adjustment'),
            DB::raw('COALESCE(SUM(reimbursement), 0) as reimbursement'),
            DB::raw('COALESCE(SUM(net_pay), 0) as net_pay'),
            DB::raw('COALESCE(SUM(custom_payment), 0) as custom_payment')
        ])->whereYear('created_at', $year)
            ->where('payroll_id', '!=', 0)
            ->where('is_onetime_payment', 0)
            ->whereIn('everee_payment_status', [0, 3])
            ->when(!(auth()->user()->is_super_admin == '1' && $userId == 1), function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->groupBy(['user_id', 'pay_period_from', 'pay_period_to'])
            ->orderBy('pay_period_from', 'desc')->paginate($perPage);

        $result = $payrollHistories->map(function ($payrollHistory) {
            $grossTotal = $payrollHistory->hourly_salary + $payrollHistory->overtime + $payrollHistory->commission + $payrollHistory->override + $payrollHistory->reconciliation;
            $miscellaneous = $payrollHistory->adjustment + $payrollHistory->reimbursement;
            $customField = $payrollHistory->custom_payment;
            $netPay = $payrollHistory->net_pay;


            $totalTaxes = $payrollHistory->total_taxes ?? 0;

            if ($totalTaxes > 0) {
                $netPay = $netPay - $totalTaxes;
            }

            return [
                'id' => $payrollHistory->id,
                'user_id' => $payrollHistory->user_id,
                'pay_period_from' => $payrollHistory->pay_period_from,
                'pay_period_to' => $payrollHistory->pay_period_to,
                'payroll_date' => $payrollHistory->payroll_date,
                'gross_total' => $grossTotal,
                'miscellaneous' => $miscellaneous,
                'deduction' => $payrollHistory->deduction,
                'taxes' => $totalTaxes,
                'custom_payment' => $customField,
                'net_pay' => $netPay,
                'type' => 'paystub',
            ];
        });
        $payrollHistories->setCollection($result);

        return response()->json([
            'ApiName' => 'past_pay_stub_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $payrollHistories,
        ]);
    }

    public function pastPayStubDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "user_id" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "one-time-payment-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;

        $data = PayrollHistory::with("payrollUser", "payrollFrequency")->where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'user_id' => $userId, 'status' => '3'])->orderBy('id', 'desc')->first();
        if (!$data) {
            return response()->json([
                "status" => true,
                "ApiName" => "one-time-payment-pay-stub",
                "message" => "Successfully.",
                "data" => []
            ]);
        }

        $payStubEmployee = paystubEmployee::with("positionDetailTeam")->select(
            "company_name as name",
            "company_address as address",
            "company_website as company_website",
            "company_phone_number as phone_number",
            "company_type as company_type",
            "company_email as company_email",
            "company_business_name",
            "company_mailing_address as mailing_address",
            "company_business_ein as business_ein",
            "company_business_ein as company_business_ein",
            "company_business_phone as business_phone",
            "company_business_address as business_address",
            "company_business_city as business_city",
            "company_business_state as business_state",
            "company_business_zip as business_zip",
            "company_mailing_state as mailing_state",
            "company_mailing_city as mailing_city",
            "company_mailing_zip as mailing_zip",
            "company_time_zone as time_zone",
            "company_business_address_1 as business_address_1",
            "company_business_address_2 as business_address_2",
            "company_business_lat as business_lat",
            "company_business_long as business_long",
            "company_mailing_address_1 as mailing_address_1",
            "company_mailing_address_2 as mailing_address_2",
            "company_mailing_lat as mailing_lat",
            "company_mailing_long as mailing_long",
            "company_business_address_time_zone as business_address_time_zone",
            "company_mailing_address_time_zone as mailing_address_time_zone",
            "company_margin as company_margin",
            "company_country as country",
            "company_logo as logo",
            "company_lat as lat",
            "company_lng as lng",
            "user_first_name as first_name",
            "user_middle_name as middle_name",
            "user_last_name as last_name",
            "user_employee_id as employee_id",
            "user_name_of_bank as name_of_bank",
            "user_social_sequrity_no",
            "user_routing_no",
            "user_account_no",
            "user_type_of_account as type_of_account",
            "user_home_address as home_address",
            "user_zip_code as zip_code",
            "user_email as email",
            "user_work_email as work_email",
            "user_position_id as position_id",
            "user_entity_type as entity_type",
            "user_business_name",
            "user_business_type as business_type",
            "user_business_ein",
        )->where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0])->orderBy('id', 'desc')->first();

        $baseUrl = config("app.aws_s3bucket_url") . "/" . config("app.domain_name");
        $fileLink = $baseUrl . "/" . $payStubEmployee?->logo;
        $companyLogo = $fileLink;
        $companyData = [
            "name" => $payStubEmployee?->name,
            "address" => $payStubEmployee?->address,
            "company_website" => $payStubEmployee?->company_website,
            "phone_number" => $payStubEmployee?->phone_number,
            "company_type" => $payStubEmployee?->company_type,
            "company_email" => $payStubEmployee?->company_email,
            "business_name" => $payStubEmployee?->company_business_name,
            "mailing_address" => $payStubEmployee?->mailing_address,
            "business_ein" => $payStubEmployee?->business_ein,
            "company_business_ein" => $payStubEmployee?->company_business_ein,
            "business_phone" => $payStubEmployee?->business_phone,
            "business_address" => $payStubEmployee?->business_address,
            "business_city" => $payStubEmployee?->business_city,
            "business_state" => $payStubEmployee?->business_state,
            "business_zip" => $payStubEmployee?->business_zip,
            "mailing_state" => $payStubEmployee?->mailing_state,
            "mailing_city" => $payStubEmployee?->mailing_city,
            "mailing_zip" => $payStubEmployee?->mailing_zip,
            "time_zone" => $payStubEmployee?->time_zone,
            "business_address_1" => $payStubEmployee?->business_address_1,
            "business_address_2" => $payStubEmployee?->business_address_2,
            "business_lat" => $payStubEmployee?->business_lat,
            "business_long" => $payStubEmployee?->business_long,
            "mailing_address_1" => $payStubEmployee?->mailing_address_1,
            "mailing_address_2" => $payStubEmployee?->mailing_address_2,
            "mailing_lat" => $payStubEmployee?->mailing_lat,
            "mailing_long" => $payStubEmployee?->mailing_long,
            "business_address_time_zone" => $payStubEmployee?->business_address_time_zone,
            "mailing_address_time_zone" => $payStubEmployee?->mailing_address_time_zone,
            "company_margin" => $payStubEmployee?->company_margin,
            "country" => $payStubEmployee?->country,
            "logo" => $payStubEmployee?->logo,
            "company_logo_s3" => $companyLogo,
            "lat" => $payStubEmployee?->lat,
            "lng" => $payStubEmployee?->lng
        ];

        $result = [];
        $payDate = date("Y-m-d", strtotime($data->created_at));
        $result["CompanyProfile"] = $companyData;
        $result["pay_stub"]["pay_date"] = $payDate;
        $result["pay_stub"]["net_pay"] = $data->net_pay;

        $result["pay_stub"]["pay_frequency"] = $data?->payrollFrequency ? $data->payrollFrequency->name : null;
        $result["pay_stub"]["pay_period_from"] = $data->pay_period_from;
        $result["pay_stub"]["pay_period_to"] = $data->pay_period_to;
        $result["pay_stub"]["period_sale_count"] = 1;
        $result["pay_stub"]["ytd_sale_count"] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $data?->pay_period_to)->whereYear('pay_period_from', date('Y', strtotime($data?->pay_period_from)))->distinct('pid')->count('pid');

        $userData = [
            "first_name" => $payStubEmployee?->first_name,
            "middle_name" => $payStubEmployee?->middle_name,
            "last_name" => $payStubEmployee?->last_name,
            "employee_id" => $payStubEmployee?->employee_id,
            "name_of_bank" => $payStubEmployee?->name_of_bank,
            "user_social_sequrity_no" => $payStubEmployee?->user_social_sequrity_no,
            "user_routing_no" => $payStubEmployee?->user_routing_no,
            "user_account_no" => $payStubEmployee?->user_account_no,
            "type_of_account" => $payStubEmployee?->type_of_account,
            "home_address" => $payStubEmployee?->home_address,
            "zip_code" => $payStubEmployee?->zip_code,
            "email" => $payStubEmployee?->email,
            "work_email" => $payStubEmployee?->work_email,
            "position_id" => $payStubEmployee?->position_id,
            "entity_type" => $payStubEmployee?->entity_type,
            "business_name" => $payStubEmployee?->user_business_name,
            "business_type" => $payStubEmployee?->business_type,
            "user_business_ein" => $payStubEmployee?->user_business_ein,
            "account_no" => $payStubEmployee?->user_account_no,
            "routing_no" => $payStubEmployee?->user_routing_no,
            "social_sequrity_no" => $payStubEmployee?->user_social_sequrity_no,
            "business_ein" => $payStubEmployee?->user_business_ein,
            "position_detail_team" => $payStubEmployee?->positionDetailTeam
        ];

        $result["employee"] = $userData;
        $result["earnings"] = $this->payrollPaymentPayStubData($request, "earnings");
        $result["deduction"] = $this->payrollPaymentPayStubData($request, "deduction");
        $result["miscellaneous"] = $this->payrollPaymentPayStubData($request, "miscellaneous");
        $result["type"] = "paystub";

        $YTDAmounts = payrollHistoryYTDCalculation($data->user_id, $data->pay_period_from, $data->pay_period_to);
        $result["pay_stub"]["ytd_net_pay"] = $YTDAmounts['netPayYTD'];
        if ($result["earnings"]['commission']['is_ytd']) {
            $result["earnings"]['commission']['ytd_total'] = $YTDAmounts['commissionYTD'];
        }
        if ($result["earnings"]['overrides']['is_ytd']) {
            $result["earnings"]['overrides']['ytd_total'] = $YTDAmounts['overrideYTD'];
        }
        if ($result["earnings"]['reconciliation']['is_ytd']) {
            $result["earnings"]['reconciliation']['ytd_total'] = $YTDAmounts['reconciliationYTD'];
        }
        if ($result["earnings"]['additional']['is_ytd']) {
            $result["earnings"]['additional']['ytd_total'] = $YTDAmounts['customFieldYTD'];
        }
        if ($result["earnings"]['wages']['is_ytd']) {
            $result["earnings"]['wages']['ytd_total'] = ($YTDAmounts['salaryYTD'] + $YTDAmounts['overtimeYTD']);
        }
        if ($result["deduction"]['standard_deduction']['is_ytd']) {
            $result["deduction"]['standard_deduction']['ytd_total'] = $YTDAmounts['deductionYTD'];
        }
        if ($result["deduction"]['fica_tax']['is_ytd']) {
            $result["deduction"]['fica_tax']['ytd_total'] = $YTDAmounts['w2DeductionYTD'];
        }
        if ($result["miscellaneous"]['adjustment']['is_ytd']) {
            $result["miscellaneous"]['adjustment']['ytd_total'] = $YTDAmounts['adjustmentYTD'];
        }
        if ($result["miscellaneous"]['reimbursement']['is_ytd']) {
            $result["miscellaneous"]['reimbursement']['ytd_total'] = $YTDAmounts['reimbursementYTD'];
        }

        return response()->json([
            "ApiName" => "one-time-payment-pay-stub",
            "status" => true,
            "message" => "Successfully.",
            "data" => $result
        ]);
    }

    protected function payrollPaymentPayStubData(Request $request, $type)
    {
        $userId = $request->user_id;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;

        if ($type == "earnings") {
            $totalCommissionAmount = 0;
            $commissionAmount = UserCommissionLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("amount");
            $commissionClawBackAmount = ClawbackSettlementLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "type" => "commission", "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("clawback_amount");
            $totalCommissionAmount = $commissionAmount - $commissionClawBackAmount;
            $commission = [
                "period_total" => $totalCommissionAmount,
                "is_ytd" => ($commissionAmount || $commissionClawBackAmount) ? 1 : 0
            ];


            $totalOverrideAmount = 0;
            $overrideAmount = UserOverridesLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("amount");
            $overrideClawBackAmount = ClawbackSettlementLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "type" => "overrides", "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("clawback_amount");
            $totalOverrideAmount = $overrideAmount - $overrideClawBackAmount;
            $overrides = [
                "period_total" => $totalOverrideAmount,
                "is_ytd" => ($overrideAmount || $overrideClawBackAmount) ? 1 : 0
            ];


            $reconAmount = 0;
            $reconAmount = ReconciliationFinalizeHistoryLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("net_amount");
            $reconciliation = [
                "period_total" => $reconAmount,
                "is_ytd" => $reconAmount ? 1 : 0
            ];


            $additionalAmount = 0;
            $additionalAmount = CustomFieldHistory::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("value");
            $additional = [
                "period_total" => $additionalAmount,
                "is_ytd" => $additionalAmount ? 1 : 0
            ];


            $wagesAmount = 0;
            $salaryAmount = PayrollHourlySalaryLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("total");
            $overtimeAmount = PayrollOvertimeLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("total");
            $wagesAmount = $salaryAmount + $overtimeAmount;
            $wages = [
                "period_total" => $wagesAmount,
                "is_ytd" => ($salaryAmount || $overtimeAmount) ? 1 : 0
            ];

            return [
                "commission" => $commission,
                "overrides" => $overrides,
                "reconciliation" => $reconciliation,
                "additional" => $additional,
                "wages" => $wages
            ];
        }


        if ($type == "deduction") {
            $deductionAmount = 0;
            $deductionAmount = PayrollDeductionLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("total");
            $standardDeduction = [
                "period_total" => $deductionAmount,
                "is_ytd" => $deductionAmount ? 1 : 0
            ];

            $w2Deduction = W2PayrollTaxDeduction::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0])->sum('fica_tax');
            $taxDeduction = [
                "period_total" => $w2Deduction,
                "is_ytd" => $w2Deduction ? 1 : 0
            ];
            return [
                "standard_deduction" => $standardDeduction,
                "fica_tax" => $taxDeduction
            ];
        }


        if ($type == "miscellaneous") {
            $adjustmentAmount = 0;
            $positionApprovalAmount = ApprovalsAndRequestLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->whereNotIn("adjustment_type_id", [2, 5, 7, 8, 9])->sum("amount");
            $negativeApprovalAmount = ApprovalsAndRequestLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->whereIn("adjustment_type_id", [5])->sum("amount");
            $payrollAdjustmentAmount = PayrollAdjustmentDetailLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("amount");
            $adjustmentAmount = ($positionApprovalAmount - $negativeApprovalAmount) + $payrollAdjustmentAmount;
            $adjustment = [
                "period_total" => $adjustmentAmount,
                "is_ytd" => ($positionApprovalAmount || $negativeApprovalAmount || $payrollAdjustmentAmount) ? 1 : 0
            ];


            $totalReimbursementAmount = 0;
            $reimbursementAmount = ApprovalsAndRequestLock::where(["user_id" => $userId, "pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo, "adjustment_type_id" => 2, "is_onetime_payment" => 0, "is_mark_paid" => 0])->sum("amount");
            $totalReimbursementAmount = $reimbursementAmount;
            $reimbursement = [
                "period_total" => $totalReimbursementAmount,
                "is_ytd" => $reimbursementAmount ? 1 : 0
            ];

            return [
                "adjustment" => $adjustment,
                "reimbursement" => $reimbursement
            ];
        }

        return [];
    }

    public function oneTimePaymentPayStubList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "year" => "required|date_format:Y",
            "user_id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "past-pay-stub",
                "error" => $validator->errors()
            ], 400);
        }

        $year = $request->year;
        $userId = $request->user_id;
        $perPage = $request->input('perpage', 10);

        $oneTimePayments = OneTimePayments::withSum('oneTimeCommissions as one_time_commissions_sum_amount', 'amount')
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
            ->whereYear('created_at', $year)
            ->when(!(auth()->user()->is_super_admin == '1' && $userId == 1), function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('payment_status', 3)->orderBy('id', 'desc')->paginate($perPage);

        $result = $oneTimePayments->map(function ($oneTimePayment) {
            $adjustment = 0;
            $reimbursement = 0;
            $customField = 0;
            $deduction = 0;
            if ($oneTimePayment->from_payroll) {
                $salary = $oneTimePayment?->one_time_hourly_salary_sum_amount ?? 0;
                $overtime = $oneTimePayment?->one_time_over_times_sum_amount ?? 0;
                $commission = $oneTimePayment?->one_time_commissions_sum_amount ?? 0;
                $commissionClawBack = $oneTimePayment?->one_time_commission_claw_backs_sum_amount ?? 0;
                $commission = $commission - $commissionClawBack;
                $override = $oneTimePayment?->one_time_overrides_sum_amount ?? 0;
                $overrideClawBack = $oneTimePayment?->one_time_overrides_claw_backs_sum_amount ?? 0;
                $override = $override - $overrideClawBack;
                $reconciliation = $oneTimePayment?->one_time_reconciliation_finalize_histories_sum_amount ?? 0;
                $grossTotal = $salary + $overtime + $commission + $override + $reconciliation;

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
                $grossTotal = $oneTimePayment?->amount ?? 0;
                $w2Deduction = $oneTimePayment?->one_time_tax_deductions_sum_amount ?? 0;
                $netPay = $grossTotal - $w2Deduction;
            }

            return [
                'id' => $oneTimePayment->id,
                'user_id' => $oneTimePayment->user_id,
                'pay_period_from' => $oneTimePayment->pay_period_from,
                'pay_period_to' => $oneTimePayment->pay_period_to,
                'payroll_date' => $oneTimePayment->pay_date,
                'gross_total' => $grossTotal,
                'miscellaneous' => ($adjustment + $reimbursement),
                'deduction' => $deduction,
                'taxes' => $w2Deduction,
                'custom_payment' => $customField,
                'net_pay' => $netPay,
                'type' => 'onetimepayment'
            ];
        });
        $oneTimePayments->setCollection($result);

        return response()->json([
            'ApiName' => 'one_time_payment_pay_stub_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $oneTimePayments,
        ]);
    }

    public function commissionBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $companyProfile = CompanyProfile::first();
        $userCommissions = UserCommissionLock::select(
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
            "amount_type as payroll_type",
        )->with([
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "is_onetime_payment" => 0]);

        $userClawBackCommissions = ClawbackSettlementLock::select(
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
            "payrollSaleData:pid,customer_name,gross_account_value,kw,net_epc,adders,customer_state",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData.salesProductMaster" => function ($q) {
                $q->selectRaw("pid, milestone_date")->groupBy("pid", "type");
            }
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "type" => "commission", "clawback_type" => "next payroll", "is_onetime_payment" => 0]);
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
            ->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "payroll_type" => "commission", "is_onetime_payment" => 0])
            ->whereIn("type", $commissionType)->whereIn("adjustment_type", $commissionType)->whereIn("pid", $commissionPid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "payroll_type" => "commission", "type" => "clawback", "is_onetime_payment" => 0])
            ->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
        $adjustments = $commissionAdjustments->union($clawBackAdjustments)->get();

        $data = [];
        foreach ($userCommissions as $userCommission) {
            $compRate = 0;
            $repRedline = formatRedline($userCommission->redline, $userCommission->redline_type);
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
            $data[] = $row;
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-commission-details",
            "data" => $data
        ]);
    }

    public function overrideBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

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
            "payrollReference",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "is_onetime_payment" => 0]);

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
            "payrollReference",
            "payrollOverUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll"
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "type" => "overrides", "clawback_type" => "next payroll", "is_onetime_payment" => 0]);
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
            ->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "payroll_type" => "overrides", "is_onetime_payment" => 0])
            ->whereIn("type", $overrideType)->whereIn("adjustment_type", $overrideType)->whereIn("pid", $overridePid);

        $clawBackAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "payroll_type" => "overrides", "type" => "clawback", "is_onetime_payment" => 0])
            ->whereIn("adjustment_type", $clawBackType)->whereIn("pid", $clawBackPid);
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

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-override-details",
            "data" => $data
        ]);
    }

    public function adjustmentBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "adjustment-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $adjustmentDetails = PayrollAdjustmentDetailLock::with([
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
            "payrollReference",
            "payrollUser:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin,stop_payroll",
            "payrollSaleData:pid,customer_name"
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "is_onetime_payment" => 0])->get();

        $data = [];
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
            "payrollReference",
            "payrollUser:id,first_name,last_name,stop_payroll",
            "payrollAdjustment",
            "payrollComments",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "is_onetime_payment" => 0])->where("adjustment_type_id", "!=", 2)->get();

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

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-adjustment-details",
            "data" => $data
        ]);
    }

    public function reimbursementBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "reimbursement-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $approvalAndRequestDetails = ApprovalsAndRequestLock::with([
            "payrollReference",
            "payrollCostCenter",
            "payrollUser:id,stop_payroll",
            "payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin",
        ])->where([
            "user_id" => $request->user_id,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to,
            "adjustment_type_id" => 2,
            "is_onetime_payment" => 0
        ])->get();

        $data = [];
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

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reimbursement-details",
            "data" => $data
        ]);
    }

    public function deductionBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $deductionDetails = PayrollDeductionLock::with([
            "payrollUser:id,stop_payroll",
            "payrollCostCenter",
            "payrollReference"
        ])->where([
            "user_id" => $request->user_id,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to,
            "is_onetime_payment" => 0
        ])->get();

        $deductionAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where([
                "user_id" => $request->user_id,
                "pay_period_from" => $request->pay_period_from,
                "pay_period_to" => $request->pay_period_to,
                "is_onetime_payment" => 0,
                "payroll_type" => "deduction"
            ])->get();

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

        $w2PayrollTaxDeductions = W2PayrollTaxDeduction::where(["user_id" => $request->user_id, "pay_period_from" => $request->pay_period_from, "pay_period_to" => $request->pay_period_to, "is_onetime_payment" => 0])->get();
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
                        "is_onetime_payment" => 0,
                        "is_move_to_recon" => 0
                    ];
                }
            }
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reimbursement-details",
            "data" => $data
        ]);
    }

    public function reconciliationBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "reconciliation-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $reconciliationPayrollDetails = ReconciliationFinalizeHistoryLock::with("payrollReference")
            ->where([
                "user_id" => $request->user_id,
                "pay_period_from" => $request->pay_period_from,
                "pay_period_to" => $request->pay_period_to,
                "is_onetime_payment" => 0,
            ])->get();

        $data = [];
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

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-reconciliation-details",
            "data" => $data
        ]);
    }

    public function wagesBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "wages-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $salaryDetails = PayrollHourlySalaryLock::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->where([
            "user_id" => $request->user_id,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to,
            "is_onetime_payment" => 0,
        ])->get();

        $salaryAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where([
                "user_id" => $request->user_id,
                "pay_period_from" => $request->pay_period_from,
                "pay_period_to" => $request->pay_period_to,
                "is_onetime_payment" => 0,
                "type" => "hourlysalary",
                "payroll_type" => "hourlysalary"
            ])->get();

        $data = [];
        foreach ($salaryDetails as $salaryDetail) {
            $adjustment = adjustmentColumn($salaryDetail, $salaryAdjustments, "hourlysalary");
            $date = isset($salaryDetail->updated_at) ? date("m/d/Y", strtotime($salaryDetail->updated_at)) : NULL;

            $data[] = [
                "id" => $salaryDetail->id,
                "date" => $salaryDetail->date ? date("m/d/Y", strtotime($salaryDetail->date)) : NULL,
                "hourly_rate" => $salaryDetail->hourly_rate * 1,
                "salary" => $salaryDetail->salary * 1,
                "regular_hours" => $salaryDetail->regular_hours,
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

        $overtimeDetails = PayrollOvertimeLock::with([
            "payrollUser:id,stop_payroll",
            "payrollReference"
        ])->where([
            "user_id" => $request->user_id,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to,
            "is_onetime_payment" => 0,
        ])->get();

        $overtimeAdjustments = PayrollAdjustmentDetailLock::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")
            ->where([
                "user_id" => $request->user_id,
                "pay_period_from" => $request->pay_period_from,
                "pay_period_to" => $request->pay_period_to,
                "is_onetime_payment" => 0,
                "type" => "overtime",
                "payroll_type" => "overtime"
            ])->get();

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

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-wages-details",
            "data" => $data
        ]);
    }

    public function additionalFieldsBreakdown(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required",
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "additional-fields-breakdown",
                "error" => $validator->errors()
            ], 400);
        }

        $payroll = PayrollHistory::where([
            "user_id" => $request->user_id,
            "pay_period_from" => $request->pay_period_from,
            "pay_period_to" => $request->pay_period_to,
            "is_onetime_payment" => 0,
        ])->with(["payrollCustomFields.getColumn", "payrollCustomFields.getApprovedBy"])->first();

        $data = [];
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
        }

        return response()->json([
            "status" => true,
            "message" => "Successfully.",
            "ApiName" => "one-time-payment-additional-details",
            "data" => $data
        ]);
    }
}
