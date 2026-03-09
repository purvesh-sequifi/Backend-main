<?php

use App\Core\Traits\PayFrequencyTrait;
use App\Events\PusherPayrollCompleteEvent;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crmcustomfields;
use App\Models\Crms;
use App\Models\evereeTransectionLog;
use App\Models\FrequencyType;
use App\Models\GroupPermissions;
use App\Models\Lead;
use App\Models\LegacyWeeklySheet;
use App\Models\Payroll;
use App\Models\PositionCommission;
use App\Models\Positions;
use App\Models\PositionTier;
use App\Models\Products;
use App\Models\SalesMaster;
use App\Models\SchedulingApprovalSetting;
use App\Models\SchemaTriggerDate;
use App\Models\TierDuration;
use App\Models\TierMetrics;
use App\Models\TiersLevel;
use App\Models\TiersResetHistory;
use App\Models\TiersSchema;
use App\Models\TierSystem;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDismissHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserTerminateHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;

// Created by Ashish for all the optimised and new helper tasks
if (! function_exists('payrollFinalisePusherNotification')) {
    function payrollFinalisePusherNotification($start_date, $end_date, $message, $finalize_status = 1, $status = true)
    {
        $response = [
            'event' => 'Payroll',
            'status' => $status,
            'finalize_status' => $finalize_status,
            'pay_period_from' => $start_date,
            'pay_period_to' => $end_date,
            'message' => $message,
        ];

        // Dispatch the event with the modified message
        event(new PusherPayrollCompleteEvent($response));

        return true;
    }
}

if (! function_exists('updateExistingPayroll')) {
    function updateExistingPayroll($userId, $pay_period_from, $pay_period_to, $amount = 0, $type = null, $position = null, $stopPayroll = 0)
    {
        $payRoll = Payroll::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if ($payRoll) {
            if ($type == 'clawback') {
                $payRoll->clawback = ($payRoll->clawback + ($amount));
            } elseif ($type == 'commission') {
                $payRoll->commission = ($payRoll->commission + ($amount));
            } elseif ($type == 'override') {
                $payRoll->override = ($payRoll->override + ($amount));
            } elseif ($type == 'adjustment') {
                $payRoll->adjustment = ((0 - $amount) + $payRoll->adjustment);
            }
            $payRoll->is_mark_paid = 0;
            $payRoll->is_next_payroll = 0;
            $payRoll->save();
        } else {
            if ($type == 'clawback') {
                $payRoll = Payroll::create(
                    [
                        'user_id' => $userId,
                        'position_id' => $position,
                        'clawback' => $amount,
                        'commission' => (0 - $amount),
                        'pay_period_from' => isset($pay_period_from) ? $pay_period_from : null,
                        'pay_period_to' => isset($pay_period_to) ? $pay_period_to : null,
                        'status' => 1,
                        'is_stop_payroll' => $stopPayroll,
                    ]
                );
            } elseif ($type == 'commission') {
                $payRoll = Payroll::create(
                    [
                        'user_id' => $userId,
                        'position_id' => $position,
                        'commission' => $amount,
                        'pay_period_from' => isset($pay_period_from) ? $pay_period_from : null,
                        'pay_period_to' => isset($pay_period_to) ? $pay_period_to : null,
                        'status' => 1,
                        'is_stop_payroll' => $stopPayroll,
                    ]
                );
            } elseif ($type == 'override') {
                $payRoll = Payroll::create(
                    [
                        'user_id' => $userId,
                        'position_id' => $position,
                        'override' => $amount,
                        'pay_period_from' => isset($pay_period_from) ? $pay_period_from : null,
                        'pay_period_to' => isset($pay_period_to) ? $pay_period_to : null,
                        'status' => 1,
                        'is_stop_payroll' => $stopPayroll,
                    ]
                );
            } elseif ($type == 'adjustment') {
                $payRoll = Payroll::create([
                    'user_id' => $userId,
                    'position_id' => $position,
                    'adjustment' => (0 - $amount),
                    'pay_period_from' => isset($pay_period_from) ? $pay_period_from : null,
                    'pay_period_to' => isset($pay_period_to) ? $pay_period_to : null,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                ]);
            }
        }

        return $payRoll->id;
    }
}

if (! function_exists('getDateFromFilter')) {
    function getDateFromFilter(Request $request)
    {
        $startDate = null;
        $endDate = null;
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            } elseif ($filterDataDateWise == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = date('Y-m-d', strtotime($request->input('start_date')));
                $endDate = date('Y-m-d', strtotime($request->input('end_date')));
            }
        }

        return [$startDate, $endDate];
    }
}

if (! function_exists('milestoneTriggerDates')) {
    function milestoneTriggerDates()
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $triggerDate = [
                ['name' => 'Initial Service Date', 'value' => 'm1', 'custom' => 0],
                ['name' => 'Service Completion Date', 'value' => 'm2', 'custom' => 0],
            ];
        } else {
            $triggerDate = [
                ['name' => 'M1 Date', 'value' => 'm1', 'custom' => 0],
                ['name' => 'M2 Date', 'value' => 'm2', 'custom' => 0],
            ];
        }

        $customFields = Crmcustomfields::where('type', 'date')->get();
        foreach ($customFields as $customField) {
            $triggerDate[] = ['name' => ucwords(str_replace('_', ' ', $customField->name)), 'value' => $customField->name];
        }

        return $triggerDate;
    }
}

if (! function_exists('milestoneSchemaTriggerDates')) {
    function milestoneSchemaTriggerDates()
    {
        $triggerDate = [];
        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $triggerDate[] = $schemaTriggerDate->name;
        }

        return $triggerDate;
    }
}

if (! function_exists('getTriggerDatesForSample')) {
    function getTriggerDatesForSample()
    {
        $triggerDate = [];
        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $triggerDate[] = ['name' => $schemaTriggerDate->name, 'value' => $schemaTriggerDate->name];
        }

        return $triggerDate;
    }
}

if (! function_exists('subroutineCreatePayrollRecord')) {
    function subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency)
    {
        $user = User::select('id', 'stop_payroll')->where('id', $userId)->first();
        if (!PayRoll::where(['user_id' => $user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'pay_frequency' => $payFrequency->pay_frequency, 'status' => 1])->first()) {
            PayRoll::create([
                'user_id' => $userId,
                'pay_frequency' => $payFrequency->pay_frequency,
                'position_id' => $subPositionId,
                'pay_period_from' => $payFrequency->pay_period_from,
                'pay_period_to' => $payFrequency->pay_period_to,
                'status' => 1,
                'is_stop_payroll' => $user->stop_payroll ?? 0,
            ]);
        }
    }
}

if (! function_exists('customPaginator')) {
    function customPaginator($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }
}

if (! function_exists('getdates')) {
    function getdates()
    {
        $trigger_date = ['m1_date', 'm2_date', 'install_complete_date'];
        $custom_field_names = Crmcustomfields::where('type', 'date')->pluck('name')->toArray();

        return array_merge($trigger_date, $custom_field_names);
    }
}

if (! function_exists('displayDateRanges')) {
    function displayDateRanges($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $numberOfDays = $start->diffInDays($end);

        $output = [];
        if ($numberOfDays <= 31) {
            $period = CarbonPeriod::create($start, $end);
            $output['type'] = 'day';
            foreach ($period as $date) {
                $output['data'][] = [
                    'start' => $date->format('Y-m-d'),
                    'end' => $date->format('Y-m-d'),
                    'label' => $date->format('m/d/Y'),
                ];
            }
        } elseif ($numberOfDays <= 93) {
            $output['type'] = 'week';
            while ($start->lessThanOrEqualTo($end)) {
                $weekStart = $start->copy();
                $weekEnd = $start->copy()->addDays(6);

                if ($weekStart->greaterThan($end)) {
                    $weekEnd = $end;
                }

                $output['data'][] = [
                    'start' => $weekStart->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                    'label' => $weekEnd->format('m/d/Y').' to '.$weekEnd->format('m/d/Y'),
                ];
                $start->addWeek();
            }
        } else {
            $output['type'] = 'month';
            while ($start->lessThanOrEqualTo($end)) {
                $monthStart = $start->copy();
                $monthEnd = $start->copy()->endOfMonth();

                if ($monthEnd->greaterThan($end)) {
                    $monthEnd = $end;
                }

                $output['data'][] = [
                    'start' => $monthStart->startOfMonth()->format('Y-m-d'),
                    'end' => $monthEnd->format('Y-m-d'),
                    'label' => $monthStart->format('F'),
                ];
                $start->addMonth();
            }
        }

        return $output;
    }
}

if (! function_exists('getLastEffectiveDates')) {
    function getLastEffectiveDates($userId, $effectiveDate, $productId)
    {
        $userCommission = UserCommissionHistory::where(['user_id' => $userId])
            ->when($productId, function ($q) use ($productId) {
                $q->where(['product_id' => $productId]);
            })->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $userRedLine = UserRedlines::where(['user_id' => $userId])
            ->when($productId, function ($q) use ($productId) {
                $q->where(['product_id' => $productId]);
            })->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
        $userRUpFront = UserUpfrontHistory::where(['user_id' => $userId])
            ->when($productId, function ($q) use ($productId) {
                $q->where(['product_id' => $productId]);
            })->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $userOverride = UserOverrideHistory::where(['user_id' => $userId])
            ->when($productId, function ($q) use ($productId) {
                $q->where(['product_id' => $productId]);
            })->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $userWithHeld = UserWithheldHistory::where(['user_id' => $userId])
            ->when($productId, function ($q) use ($productId) {
                $q->where(['product_id' => $productId]);
            })->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

        return [$userCommission?->commission_effective_date, $userRedLine?->start_date, $userRUpFront?->upfront_effective_date, $userOverride?->override_effective_date, $userWithHeld?->withheld_effective_date];
    }
}

if (! function_exists('checkUsersProductForCalculations')) {
    function checkUsersProductForCalculations($userId, $effectiveDate, $productId)
    {
        $organization = UserOrganizationHistory::where('effective_date', '<=', $effectiveDate)->where('user_id', $userId)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId, 'effective_date' => $organization?->effective_date])->first();
        if ($userOrganizationHistory) {
            $product = Products::withTrashed()->where('id', $productId)->first();
        } else {
            $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
            $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $product->id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        }

        return [
            'product' => $product,
            'organization' => $userOrganizationHistory,
        ];
    }
}

if (! function_exists('finalizePayrollValidations')) {
    function finalizePayrollValidations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'error' => $validator->errors(),
                'code' => 400,
            ];
        }

        $frequencyTypeId = $request->pay_frequency;
        if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                'end_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            ]);

            if ($validator->fails()) {
                return [
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'finalize_payroll',
                    'error' => $validator->errors(),
                    'code' => 400,
                ];
            }
        }

        $workerType = isset($request->worker_type) ? $request->worker_type : '1099';
        if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID && ($workerType == 'w2' || $workerType == 'W2')) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => 'We do not support daily pay payroll with w2 users!!',
                'code' => 400,
            ];
        }

        $legacySheet = LegacyWeeklySheet::orderBy('id', 'DESC')->first();
        if ($legacySheet && $legacySheet->in_process == '1') {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => "We regret to inform you that we're unable to process your request to finalize payroll at this time. Our system is currently engaged in the Sale import process. Please try again later. Thank you for your understanding and patience",
                'code' => 400,
            ];
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        if ($workerType == 'w2' || $workerType == 'W2') {
            $setting = SchedulingApprovalSetting::find(1);
            if (! $setting) {
                return [
                    'status' => false,
                    'is_show' => false,
                    'success' => false,
                    'ApiName' => 'finalize_payroll',
                    'message' => 'Scheduling approval settings not configured.',
                    'code' => 400,
                ];
            }

            $id = auth()->user()->id;
            $user = User::with('positionDetail')->find($id);
            $groupId = $user->group_id;
            if (($user->is_super_admin == 1) || $setting->scheduling_setting == 'automatic') {
                $show = GroupPermissions::where('group_id', $groupId)->whereIn('role_id', GroupPermissions::distinct()->pluck('role_id'))
                    ->whereIn('permissions_id', function ($query) {
                        $query->select('id')->from('permissions')->where('name', 'scheduling-timesheet-approval');
                    })->exists();

                if (! $show) {
                    return [
                        'status' => false,
                        'is_show' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'message' => 'You do not have the necessary permissions for timesheet approval, or the scheduling setting is set to manual.',
                        'code' => 400,
                    ];
                }
            }
        }

        $usersIds = User::where('worker_type', $workerType)->whereIn('sub_position_id', function ($query) use ($frequencyTypeId) {
            $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $frequencyTypeId);
        })->pluck('id');
        $checkOtherFinalizedPayroll = Payroll::with('workerType')->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereNotBetween('pay_period_from', [$startDate, $endDate])->whereNotBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where('pay_period_from', '!=', $startDate)->where('pay_period_to', '!=', $endDate);
        })->whereHas('workerType', function ($q) use ($workerType) {
            $q->where('worker_type', $workerType);
        })->where(['status' => 2, 'is_stop_payroll' => 0, 'is_onetime_payment' => 0])->whereIn('user_id', $usersIds)->first();
        if ($checkOtherFinalizedPayroll) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => 'Warning: Another payroll finalized for the period '.date('m/d/Y', strtotime($checkOtherFinalizedPayroll->pay_period_from)).' to '.date('m/d/Y', strtotime($checkOtherFinalizedPayroll->pay_period_to)),
                'code' => 400,
            ];
        }

        $checkNegative = Payroll::with('workerType')->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->where(['status' => 1, 'is_stop_payroll' => 0, 'is_onetime_payment' => 0])->where('is_mark_paid', '!=', 1)->where('is_next_payroll', '!=', 1)->where('gross_pay', '<', '0')->whereIn('user_id', $usersIds)
            ->whereHas('workerType', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            })->count();
        if ($checkNegative > 0) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => 'Error: The Net Pay, excluding Reimbursements, should not be negative during the selected Pay Period. Kindly adjust to ensure that the Net Pay (excluding reimbursements) is a positive value.',
                'code' => 400,
            ];
        }

        return ['success' => true];
    }
}

if (! function_exists('upFrontCalculationValue')) {
    function upFrontCalculationValue($tierParam)
    {
        $info = $tierParam['info'];
        $sale = $tierParam['sale'];
        $userId = $tierParam['user_id'];
        $schemaId = $tierParam['schema_id'];
        $productId = $tierParam['product_id'];
        $isSelfGen = $tierParam['is_self_gen'];
        $userOrganizationHistory = $tierParam['user_organization_history'];

        $isTiered = 0;
        $level = [
            'level' => null,
            'is_locked' => 0,
        ];
        $tierSchema = null;
        $upfrontHistory = null;
        $approvalDate = $sale->customer_signoff;
        $subPositionId = @$userOrganizationHistory->sub_position_id;
        if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            if (@$userOrganizationHistory->self_gen_accounts == 1) {
                if ($isSelfGen) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId, 'self_gen_user' => '1'])->whereNull('core_position_id')
                        ->where('upfront_effective_date', '<=', $approvalDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                } elseif ($userOrganizationHistory->position_id == '2' || $userOrganizationHistory->position_id == '3') {
                    $corePosition = '';
                    if ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    } elseif ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }

                    if ($corePosition) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId, 'core_position_id' => $corePosition])
                            ->where('upfront_effective_date', '<=', $approvalDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }
            } else {
                $corePosition = '';
                if ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                } elseif ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                }

                if ($corePosition) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId, 'self_gen_user' => '0', 'core_position_id' => $corePosition])
                        ->where('upfront_effective_date', '<=', $approvalDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            if ($upfrontHistory && $upfrontHistory->tiers_id) {
                $isTiered = 1;
                $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $upfrontHistory->tiers_id)->first();
                if ($tierSchema) {
                    $level = getTierLevelForUser($tierSchema, $userOrganizationHistory, $sale, $userId, $subPositionId, 'upfront');
                }
            }
        }

        return [
            'is_tiered' => $isTiered,
            'level' => $level['level'],
            'is_locked' => $level['is_locked'],
            'schema' => $tierSchema,
        ];
    }
}

if (! function_exists('commissionCalculationValue')) {
    function commissionCalculationValue($tierParam)
    {
        $info = $tierParam['info'];
        $sale = $tierParam['sale'];
        $userId = $tierParam['user_id'];
        $productId = $tierParam['product_id'];
        $isSelfGen = $tierParam['is_self_gen'];
        $userOrganizationHistory = $tierParam['user_organization_history'];

        $isTiered = 0;
        $level = [
            'level' => null,
            'is_locked' => 0,
        ];
        $tierSchema = null;
        $commissionHistory = null;
        $approvalDate = $sale->customer_signoff;
        $subPositionId = @$userOrganizationHistory->sub_position_id;
        if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                if ($isSelfGen) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                } elseif ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                    $corePosition = '';
                    if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }

                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }
            } else {
                $corePosition = '';
                if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                }
                if ($corePosition) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            if ($commissionHistory && $commissionHistory->tiers_id) {
                $isTiered = 1;
                $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $commissionHistory->tiers_id)->first();
                if ($tierSchema) {
                    $level = getTierLevelForUser($tierSchema, $userOrganizationHistory, $sale, $userId, $subPositionId, 'commission');
                }
            }
        }

        return [
            'is_tiered' => $isTiered,
            'level' => $level['level'],
            'is_locked' => $level['is_locked'],
            'schema' => $tierSchema,
        ];
    }
}

if (! function_exists('overrideCalculationValue')) {
    function overrideCalculationValue($tierParam)
    {
        $sale = $tierParam['sale'];
        $tierId = $tierParam['tier_id'];
        $userId = $tierParam['user_id'];
        $userOrganizationHistory = $tierParam['user_organization_history'];

        $isTiered = 0;
        $level = [
            'level' => null,
            'is_locked' => 0,
        ];
        $tierSchema = null;
        $subPositionId = @$userOrganizationHistory->sub_position_id;
        if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            if ($tierId) {
                $isTiered = 1;
                $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $tierId)->first();
                if ($tierSchema) {
                    $level = getTierLevelForUser($tierSchema, $userOrganizationHistory, $sale, $userId, $subPositionId, 'override');
                }
            }
        }

        return [
            'is_tiered' => $isTiered,
            'level' => $level['level'],
            'is_locked' => $level['is_locked'],
            'schema' => $tierSchema,
        ];
    }
}

if (! function_exists('getTierLevelForUser')) {
    function getTierLevelForUser($tierSchema, $userOrganizationHistory, $sale, $userId, $subPositionId, $type)
    {
        $approvalDate = $sale->customer_signoff;
        $tierLevel = TiersLevel::where('tiers_schema_id', $tierSchema->id)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if ($tierLevel) {
            $tierLevels = TiersLevel::where(['tiers_schema_id' => $tierSchema->id, 'effective_date' => $tierLevel->effective_date])->orderBy('level')->get();
        } else {
            $tierLevels = TiersLevel::where('tiers_schema_id', $tierSchema->id)->whereNull('effective_date')->orderBy('level')->get();
        }

        $tierStatus = PositionTier::where(['position_id' => $subPositionId, 'type' => $type])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $tierStatus) {
            $tierStatus = PositionTier::where(['position_id' => $subPositionId, 'type' => $type])->whereNull('effective_date')->first();
        }

        $level = null;
        $isLocked = 0;
        if ($tierSchema && $tierSchema->tier_system && $tierSchema->tier_metrics && count($tierLevels) != 0 && $tierStatus && $tierStatus->status == '1') {
            $productId = 'all';
            $tierAdvancement = $tierStatus->tier_advancement;
            if ($tierAdvancement == PositionTier::SELECTED_PRODUCTS) {
                $productId = $sale->product_id;
                if (! $productId) {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $productId = $product->id;
                }
            }

            $tierSystem = $tierSchema->tier_system->value;
            $tierMetric = $tierSchema->tier_metrics->value;
            $data = getDurationForTier($tierSchema, $userOrganizationHistory, $approvalDate);
            [$level, $isLocked] = getCurrentTier($data, $tierSystem, $tierMetric, $productId, $tierLevels, $userId, $approvalDate, $sale);
        }

        return [
            'level' => $level,
            'is_locked' => $isLocked,
        ];
    }
}

if (! function_exists('getDurationForTier')) {
    function getDurationForTier($tierSchema, $userOrganizationHistory, $date)
    {
        if ($tierSchema->tier_type == TiersSchema::PROGRESSIVE) {
            $duration = $tierSchema->tier_duration;
            if ($duration) {
                $durationValue = $duration->value;
                $userId = @$userOrganizationHistory->user_id;
                $subPositionId = @$userOrganizationHistory->sub_position_id;
                if ($durationValue == TierDuration::PER_PAY_PERIOD) {
                    if (! class_exists('NewClass')) {
                        class NewClass
                        {
                            use PayFrequencyTrait;
                        }
                    }
                    $payFrequencyTrait = new NewClass;
                    $payFrequency = $payFrequencyTrait->payFrequency($date, $subPositionId, $userId);

                    return [
                        'start_date' => $payFrequency->pay_period_from,
                        'end_date' => $payFrequency->pay_period_to,
                        'type' => TierDuration::PER_PAY_PERIOD,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::WEEKLY) {
                    $date = Carbon::parse($date);
                    $startIndex = TierDuration::WEEK_DAYS[$tierSchema->start_day];
                    $endIndex = TierDuration::WEEK_DAYS[$tierSchema->end_day];
                    $weekStart = $date->copy()->startOfWeek($startIndex);
                    $weekEnd = $date->copy()->endOfWeek($endIndex);

                    return [
                        'start_date' => $weekStart->toDateString(),
                        'end_date' => $weekEnd->toDateString(),
                        'type' => TierDuration::WEEKLY,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::MONTHLY) {
                    $now = Carbon::parse($date);
                    $start = Carbon::parse($tierSchema->start_day);
                    $end = Carbon::parse($tierSchema->end_day);
                    if ($start->copy()->format('d') == '01') {
                        if ($now->lt($start)) {
                            $month = $now->month;
                            $year = $now->year;
                            if ($now->month == '01') {
                                $year = $now->year - 1;
                                $month = 12;
                            }
                            $start->setMonth($month)->setYear($year);
                            $end->setMonth($month)->setYear($year);
                        }

                        while ($start->lt($now)) {
                            if ($now->between($start, $end)) {
                                $startDate = $start->toISOString();
                                $endDate = $end->toISOString();
                                break;
                            }
                            $start->addMonths(1)->startOfMonth();
                            $end = $start->copy()->endOfMonth();
                        }

                        if (! isset($startDate)) {
                            $startDate = $start->toISOString();
                            $endDate = $end->toISOString();
                        }
                    } elseif ($start->copy()->format('d') == '31') {
                        if ($now->lt($start)) {
                            $month = $now->month;
                            $year = $now->year;
                            if ($now->month == '01') {
                                $year = $now->year - 1;
                                $month = 12;
                            }
                            $start->setMonth($month)->setYear($year);
                            $end->setMonth($month + 1)->endOfMonth()->setYear($year);
                        }

                        while ($start->lt($now)) {
                            if ($now->between($start, $end)) {
                                $startDate = $start->toISOString();
                                $endDate = $end->toISOString();
                                break;
                            }
                            $start = $end->addDay();
                            $end = $start->copy()->endOfMonth();
                        }

                        if (! isset($startDate)) {
                            $startDate = $start->toISOString();
                            $endDate = $end->toISOString();
                        }
                    } else {
                        if ($now->lt($start)) {
                            $start->setMonth($now->month)->setYear($now->year)->subMonths(2);
                            $end->setMonth($now->month)->setYear($now->year)->subMonth();
                        }

                        while ($start->lt($now)) {
                            if ($now->between($start, $end)) {
                                $startDate = $start->toISOString();
                                $endDate = $end->toISOString();
                                break;
                            }
                            $start->addMonths(1);
                            $end->addMonths(1);
                        }

                        if (! isset($startDate)) {
                            $startDate = $start->toISOString();
                            $endDate = $end->toISOString();
                        }
                    }
                    // $now = Carbon::parse($date);
                    // $start = Carbon::parse($tierSchema->start_day);
                    // $end = Carbon::parse($tierSchema->end_day);
                    // if ($start->copy()->format('d') == '01') {
                    //     $startDate = $start->year($now->year)->startOfMonth($now->month)->subMonth()->toISOString();
                    //     $endDate = $end->year($now->year)->endOfMonth($now->month)->subMonth()->toISOString();
                    // } else if ($start->copy()->format('d') == '30' || $start->copy()->format('d') == '31') {

                    // } else {
                    //     $startDate = $start->year($now->year)->month($now->month)->subMonth()->toISOString();
                    //     $endDate = $end->year($now->year)->month($now->month + 1)->subMonth()->toISOString();
                    // }

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::MONTHLY,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::QUARTERLY) {
                    $month = date('n', strtotime($date));
                    if ($month >= 1 && $month <= 3) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                    } elseif ($month >= 4 && $month <= 6) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                    } elseif ($month >= 7 && $month <= 9) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                    } elseif ($month >= 10 && $month <= 12) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                    }

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::QUARTERLY,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::SEMI_ANNUALLY) {
                    $month = date('n', strtotime($date));
                    if ($month >= 1 && $month <= 6) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                    } elseif ($month >= 7 && $month <= 12) {
                        $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                        $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                    }

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::SEMI_ANNUALLY,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::ANNUALLY) {
                    $now = Carbon::parse($date);
                    $start = Carbon::parse($tierSchema->start_day);
                    $end = Carbon::parse($tierSchema->end_day);
                    $startDate = $start->year($now->year)->format('Y-m-d');
                    $endDate = $end->year($now->year + 1)->format('Y-m-d');

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::ANNUALLY,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::PER_RECON_PERIOD) {
                    $startDate = Carbon::now()->format('Y-m-d');
                    $endDate = Carbon::now()->format('Y-m-d');
                    $lastReset = TiersResetHistory::where(['tier_schema_id' => $tierSchema->id])->orderBy('end_date', 'DESC')->first();
                    if ($lastReset) {
                        $startDate = Carbon::parse($lastReset->end_date)->addDay()->format('Y-m-d');
                    } else {
                        $firstSale = SalesMaster::whereNotNull('customer_signoff')->orderBy('customer_signoff', 'ASC')->first();
                        if ($firstSale) {
                            $startDate = $firstSale->customer_signoff;
                        }
                    }

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::PER_RECON_PERIOD,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::ON_DEMAND) {
                    $startDate = Carbon::now()->format('Y-m-d');
                    $endDate = Carbon::now()->format('Y-m-d');
                    $lastReset = TiersResetHistory::where(['tier_schema_id' => $tierSchema->id])->orderBy('end_date', 'DESC')->first();
                    if ($lastReset) {
                        $startDate = Carbon::parse($lastReset->end_date)->addDay()->format('Y-m-d');
                    } else {
                        $firstSale = SalesMaster::whereNotNull('customer_signoff')->orderBy('customer_signoff', 'ASC')->first();
                        if ($firstSale) {
                            $startDate = $firstSale->customer_signoff;
                        }
                    }

                    return [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'type' => TierDuration::ON_DEMAND,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                } elseif ($durationValue == TierDuration::CONTINUOUS) {
                    $startDate = Carbon::now()->format('Y-m-d');
                    $firstSale = SalesMaster::whereNotNull('customer_signoff')->orderBy('customer_signoff', 'ASC')->first();
                    if ($firstSale) {
                        $startDate = $firstSale->customer_signoff;
                    }

                    return [
                        'start_date' => $startDate,
                        'end_date' => Carbon::now()->format('Y-m-d'),
                        'type' => TierDuration::CONTINUOUS,
                        'tier_type' => $tierSchema->tier_type,
                    ];
                }
            }
        } elseif ($tierSchema->tier_type == TiersSchema::RETROACTIVE) {
            $startDate = Carbon::now()->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
            $lastReset = TiersResetHistory::where(['tier_schema_id' => $tierSchema->id])->orderBy('end_date', 'DESC')->first();
            if ($lastReset) {
                $startDate = Carbon::parse($lastReset->end_date)->addDay()->format('Y-m-d');
            } else {
                $firstSale = SalesMaster::whereNotNull('customer_signoff')->orderBy('customer_signoff', 'ASC')->first();
                if ($firstSale) {
                    $startDate = $firstSale->customer_signoff;
                }
            }

            return [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'type' => TiersSchema::RETROACTIVE,
                'tier_type' => $tierSchema->tier_type,
            ];
        }
    }
}

if (! function_exists('getCurrentTier')) {
    function getCurrentTier($data, $tierSystem, $tierMetric, $productId, $tierLevels, $userId, $approvalDate, $sale = null)
    {
        $other = [];
        $level = null;
        $isLocked = 0;
        $startDate = isset($data['start_date']) ? $data['start_date'] : null;
        $endDate = isset($data['end_date']) ? $data['end_date'] : null;
        $other['tierSystem'] = $tierSystem;
        $other['tierMetric'] = $tierMetric;
        if ($tierSystem == TierSystem::TIERED_BASED_ON_INDIVIDUAL_PERFORMANCE) {
            if ($tierMetric == TierMetrics::ACCOUNT_SOLD) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($accountSold) {
                    return (is_null($range['from_value']) || $accountSold >= $range['from_value']) && (is_null($range['to_value']) || $accountSold <= $range['to_value']);
                });
                $other['current'] = $accountSold;
            } elseif ($tierMetric == TierMetrics::ACCOUNT_INSTALLED || $tierMetric == TierMetrics::ACCOUNT_SERVICED) {
                $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->count() ?? 0;

                $level = $tierLevels->first(function ($range) use ($accountInstalled) {
                    return (is_null($range['from_value']) || $accountInstalled >= $range['from_value']) && (is_null($range['to_value']) || $accountInstalled <= $range['to_value']);
                });
                $other['current'] = $accountInstalled;
            } elseif ($tierMetric == TierMetrics::KW_SOLD || $tierMetric == TierMetrics::SQ_FT_SOLD) {
                $kwSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw') ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($kwSold) {
                    return (is_null($range['from_value']) || $kwSold >= $range['from_value']) && (is_null($range['to_value']) || $kwSold <= $range['to_value']);
                });
                $other['current'] = $kwSold;
            } elseif ($tierMetric == TierMetrics::KW_INSTALLED || $tierMetric == TierMetrics::SQ_FT_INSTALLED) {
                $kwInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->sum('kw') ?? 0;

                $level = $tierLevels->first(function ($range) use ($kwInstalled) {
                    return (is_null($range['from_value']) || $kwInstalled >= $range['from_value']) && (is_null($range['to_value']) || $kwInstalled <= $range['to_value']);
                });
                $other['current'] = $kwInstalled;
            } elseif ($tierMetric == TierMetrics::REVENUE_SOLD) {
                $revenueSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value') ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($revenueSold) {
                    return (is_null($range['from_value']) || $revenueSold >= $range['from_value']) && (is_null($range['to_value']) || $revenueSold <= $range['to_value']);
                });
                $other['current'] = $revenueSold;
            } elseif ($tierMetric == TierMetrics::REVENUE_INSTALLED || $tierMetric == TierMetrics::REVENUE_SERVICED) {
                $revenueInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->sum('gross_account_value') ?? 0;

                $level = $tierLevels->first(function ($range) use ($revenueInstalled) {
                    return (is_null($range['from_value']) || $revenueInstalled >= $range['from_value']) && (is_null($range['to_value']) || $revenueInstalled <= $range['to_value']);
                });
                $other['current'] = $revenueInstalled;
            } elseif ($tierMetric == TierMetrics::AVERAGE_CONTRACT_VALUE) {
                $averageContractValue = SalesMaster::selectRaw('SUM(gross_account_value) as gross_account_value, COUNT(id) as sale_count')
                    ->when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                        $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->first();
                $grossAccountValue = $averageContractValue->gross_account_value ?? 0;
                $saleCount = $averageContractValue->sale_count ?? 0;
                $average = 0;
                if ($saleCount) {
                    $average = $grossAccountValue / $saleCount;
                }

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($average) {
                    return (is_null($range['from_value']) || $average >= $range['from_value']) && (is_null($range['to_value']) || $average <= $range['to_value']);
                });
                $other['current'] = $average;
            } elseif ($tierMetric == TierMetrics::INSTALL_RATE || $tierMetric == TierMetrics::SERVICE_RATE) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->count() ?? 0;
                $installRate = 0;
                if ($accountSold) {
                    $installRate = (($accountInstalled * 100) / $accountSold);
                }

                $level = $tierLevels->first(function ($range) use ($installRate) {
                    return (is_null($range['from_value']) || $installRate >= $range['from_value']) && (is_null($range['to_value']) || $installRate <= $range['to_value']);
                });
                $other['current'] = $installRate;
            } elseif ($tierMetric == TierMetrics::CANCELLATION_RATE) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                $accountCanceled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereNotNull('date_cancelled')->whereBetween('date_cancelled', [$startDate, $endDate])->count() ?? 0;
                $cancellationRate = 0;
                if ($accountSold) {
                    $cancellationRate = (($accountCanceled * 100) / $accountSold);
                }

                $level = $tierLevels->first(function ($range) use ($cancellationRate) {
                    return (is_null($range['from_value']) || $cancellationRate >= $range['from_value']) && (is_null($range['to_value']) || $cancellationRate <= $range['to_value']);
                });
                $other['current'] = $cancellationRate;
            } elseif ($tierMetric == TierMetrics::AUTO_PAY_ENROLMENT_RATE) {
                $autoPay = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->where('auto_pay', '!=', '0')->count() ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($autoPay) {
                    return (is_null($range['from_value']) || $autoPay >= $range['from_value']) && (is_null($range['to_value']) || $autoPay <= $range['to_value']);
                });
                $other['current'] = $autoPay;
            }
        } elseif ($tierSystem == TierSystem::TIERED_BASED_ON_OFFICE_PERFORMANCE) {
            $officeId = null;
            $userTransferHistory = UserTransferHistory::where('user_id', $userId)->where('transfer_effective_date', '<=', $approvalDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId) {
                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvalDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId, 'user_id' => $userId])->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));
                $userIdArr = User::whereIn('id', $userIdArr)->where('dismiss', '0')->pluck('id')->toArray();

                if ($tierMetric == TierMetrics::ACCOUNT_SOLD) {
                    $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($accountSold) {
                        return (is_null($range['from_value']) || $accountSold >= $range['from_value']) && (is_null($range['to_value']) || $accountSold <= $range['to_value']);
                    });
                    $other['current'] = $accountSold;
                } elseif ($tierMetric == TierMetrics::ACCOUNT_INSTALLED || $tierMetric == TierMetrics::ACCOUNT_SERVICED) {
                    $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                        $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->count() ?? 0;

                    $level = $tierLevels->first(function ($range) use ($accountInstalled) {
                        return (is_null($range['from_value']) || $accountInstalled >= $range['from_value']) && (is_null($range['to_value']) || $accountInstalled <= $range['to_value']);
                    });
                    $other['current'] = $accountInstalled;
                } elseif ($tierMetric == TierMetrics::KW_SOLD || $tierMetric == TierMetrics::SQ_FT_SOLD) {
                    $kwSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw') ?? 0;

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($kwSold) {
                        return (is_null($range['from_value']) || $kwSold >= $range['from_value']) && (is_null($range['to_value']) || $kwSold <= $range['to_value']);
                    });
                    $other['current'] = $kwSold;
                } elseif ($tierMetric == TierMetrics::KW_INSTALLED || $tierMetric == TierMetrics::SQ_FT_INSTALLED) {
                    $kwInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                        $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->sum('kw') ?? 0;

                    $level = $tierLevels->first(function ($range) use ($kwInstalled) {
                        return (is_null($range['from_value']) || $kwInstalled >= $range['from_value']) && (is_null($range['to_value']) || $kwInstalled <= $range['to_value']);
                    });
                    $other['current'] = $kwInstalled;
                } elseif ($tierMetric == TierMetrics::REVENUE_SOLD) {
                    $revenueSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value') ?? 0;

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($revenueSold) {
                        return (is_null($range['from_value']) || $revenueSold >= $range['from_value']) && (is_null($range['to_value']) || $revenueSold <= $range['to_value']);
                    });
                    $other['current'] = $revenueSold;
                } elseif ($tierMetric == TierMetrics::REVENUE_INSTALLED || $tierMetric == TierMetrics::REVENUE_SERVICED) {
                    $revenueInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                        $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->sum('gross_account_value') ?? 0;

                    $level = $tierLevels->first(function ($range) use ($revenueInstalled) {
                        return (is_null($range['from_value']) || $revenueInstalled >= $range['from_value']) && (is_null($range['to_value']) || $revenueInstalled <= $range['to_value']);
                    });
                    $other['current'] = $revenueInstalled;
                } elseif ($tierMetric == TierMetrics::AVERAGE_CONTRACT_VALUE) {
                    $averageContractValue = SalesMaster::selectRaw('SUM(gross_account_value) as gross_account_value, COUNT(id) as sale_count')
                        ->when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->when($productId == '1', function ($q) use ($productId) {
                            $q->where(function ($q) use ($productId) {
                                $q->where('product_id', $productId)->orWhereNull('product_id');
                            });
                        })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                            $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                        })->whereBetween('customer_signoff', [$startDate, $endDate])->first();
                    $grossAccountValue = $averageContractValue->gross_account_value ?? 0;
                    $saleCount = $averageContractValue->sale_count ?? 0;
                    $average = 0;
                    if ($saleCount) {
                        $average = $grossAccountValue / $saleCount;
                    }

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($average) {
                        return (is_null($range['from_value']) || $average >= $range['from_value']) && (is_null($range['to_value']) || $average <= $range['to_value']);
                    });
                    $other['current'] = $average;
                } elseif ($tierMetric == TierMetrics::INSTALL_RATE || $tierMetric == TierMetrics::SERVICE_RATE) {
                    $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                    $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                        $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->count() ?? 0;
                    $installRate = 0;
                    if ($accountSold) {
                        $installRate = (($accountInstalled * 100) / $accountSold);
                    }

                    $level = $tierLevels->first(function ($range) use ($installRate) {
                        return (is_null($range['from_value']) || $installRate >= $range['from_value']) && (is_null($range['to_value']) || $installRate <= $range['to_value']);
                    });
                    $other['current'] = $installRate;
                } elseif ($tierMetric == TierMetrics::CANCELLATION_RATE) {
                    $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                    $accountCanceled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereNotNull('date_cancelled')->whereBetween('date_cancelled', [$startDate, $endDate])->count() ?? 0;
                    $cancellationRate = 0;
                    if ($accountSold) {
                        $cancellationRate = (($accountCanceled * 100) / $accountSold);
                    }

                    $level = $tierLevels->first(function ($range) use ($cancellationRate) {
                        return (is_null($range['from_value']) || $cancellationRate >= $range['from_value']) && (is_null($range['to_value']) || $cancellationRate <= $range['to_value']);
                    });
                    $other['current'] = $cancellationRate;
                } elseif ($tierMetric == TierMetrics::AUTO_PAY_ENROLMENT_RATE) {
                    $autoPay = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->where('auto_pay', '!=', '0')->count() ?? 0;

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($autoPay) {
                        return (is_null($range['from_value']) || $autoPay >= $range['from_value']) && (is_null($range['to_value']) || $autoPay <= $range['to_value']);
                    });
                    $other['current'] = $autoPay;
                }
            }
        } elseif ($tierSystem == TierSystem::TIERED_BASED_ON_DOWN_LINE_PERFORMANCE) {
            $userIdArr = User::where(function ($q) use ($userId) {
                $q->where('recruiter_id', $userId)->orWhere('additional_recruiter_id1', $userId)->orWhere('additional_recruiter_id2', $userId);
            })->where('dismiss', 0)->pluck('id')->toArray();

            if ($tierMetric == TierMetrics::ACCOUNT_SOLD) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($accountSold) {
                    return (is_null($range['from_value']) || $accountSold >= $range['from_value']) && (is_null($range['to_value']) || $accountSold <= $range['to_value']);
                });
                $other['current'] = $accountSold;
            } elseif ($tierMetric == TierMetrics::ACCOUNT_INSTALLED || $tierMetric == TierMetrics::ACCOUNT_SERVICED) {
                $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->count() ?? 0;

                $level = $tierLevels->first(function ($range) use ($accountInstalled) {
                    return (is_null($range['from_value']) || $accountInstalled >= $range['from_value']) && (is_null($range['to_value']) || $accountInstalled <= $range['to_value']);
                });
                $other['current'] = $accountInstalled;
            } elseif ($tierMetric == TierMetrics::KW_SOLD || $tierMetric == TierMetrics::SQ_FT_SOLD) {
                $kwSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw') ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($kwSold) {
                    return (is_null($range['from_value']) || $kwSold >= $range['from_value']) && (is_null($range['to_value']) || $kwSold <= $range['to_value']);
                });
                $other['current'] = $kwSold;
            } elseif ($tierMetric == TierMetrics::KW_INSTALLED || $tierMetric == TierMetrics::SQ_FT_INSTALLED) {
                $kwInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->sum('kw') ?? 0;

                $level = $tierLevels->first(function ($range) use ($kwInstalled) {
                    return (is_null($range['from_value']) || $kwInstalled >= $range['from_value']) && (is_null($range['to_value']) || $kwInstalled <= $range['to_value']);
                });
                $other['current'] = $kwInstalled;
            } elseif ($tierMetric == TierMetrics::REVENUE_SOLD) {
                $revenueSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value') ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($revenueSold) {
                    return (is_null($range['from_value']) || $revenueSold >= $range['from_value']) && (is_null($range['to_value']) || $revenueSold <= $range['to_value']);
                });
                $other['current'] = $revenueSold;
            } elseif ($tierMetric == TierMetrics::REVENUE_INSTALLED || $tierMetric == TierMetrics::REVENUE_SERVICED) {
                $revenueInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->sum('gross_account_value') ?? 0;

                $level = $tierLevels->first(function ($range) use ($revenueInstalled) {
                    return (is_null($range['from_value']) || $revenueInstalled >= $range['from_value']) && (is_null($range['to_value']) || $revenueInstalled <= $range['to_value']);
                });
                $other['current'] = $revenueInstalled;
            } elseif ($tierMetric == TierMetrics::AVERAGE_CONTRACT_VALUE) {
                $averageContractValue = SalesMaster::selectRaw('SUM(gross_account_value) as gross_account_value, COUNT(id) as sale_count')
                    ->when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                        $q->where('product_id', $productId);
                    })->when($productId == '1', function ($q) use ($productId) {
                        $q->where(function ($q) use ($productId) {
                            $q->where('product_id', $productId)->orWhereNull('product_id');
                        });
                    })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                        $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                    })->whereBetween('customer_signoff', [$startDate, $endDate])->first();
                $grossAccountValue = $averageContractValue->gross_account_value ?? 0;
                $saleCount = $averageContractValue->sale_count ?? 0;
                $average = 0;
                if ($saleCount) {
                    $average = $grossAccountValue / $saleCount;
                }

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($average) {
                    return (is_null($range['from_value']) || $average >= $range['from_value']) && (is_null($range['to_value']) || $average <= $range['to_value']);
                });
                $other['current'] = $average;
            } elseif ($tierMetric == TierMetrics::INSTALL_RATE || $tierMetric == TierMetrics::SERVICE_RATE) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                $accountInstalled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereNull('date_cancelled')->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                    $q->where('is_last_date', '1')->whereNotNull('milestone_date')->whereBetween('milestone_date', [$startDate, $endDate]);
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->count() ?? 0;
                $installRate = 0;
                if ($accountSold) {
                    $installRate = (($accountInstalled * 100) / $accountSold);
                }

                $level = $tierLevels->first(function ($range) use ($installRate) {
                    return (is_null($range['from_value']) || $installRate >= $range['from_value']) && (is_null($range['to_value']) || $installRate <= $range['to_value']);
                });
                $other['current'] = $installRate;
            } elseif ($tierMetric == TierMetrics::CANCELLATION_RATE) {
                $accountSold = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->count() ?? 0;
                $accountCanceled = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereNotNull('date_cancelled')->whereBetween('date_cancelled', [$startDate, $endDate])->count() ?? 0;
                $cancellationRate = 0;
                if ($accountSold) {
                    $cancellationRate = (($accountCanceled * 100) / $accountSold);
                }

                $level = $tierLevels->first(function ($range) use ($cancellationRate) {
                    return (is_null($range['from_value']) || $cancellationRate >= $range['from_value']) && (is_null($range['to_value']) || $cancellationRate <= $range['to_value']);
                });
                $other['current'] = $cancellationRate;
            } elseif ($tierMetric == TierMetrics::AUTO_PAY_ENROLMENT_RATE) {
                $autoPay = SalesMaster::when(($productId != 'all' && $productId != '1'), function ($q) use ($productId) {
                    $q->where('product_id', $productId);
                })->when($productId == '1', function ($q) use ($productId) {
                    $q->where(function ($q) use ($productId) {
                        $q->where('product_id', $productId)->orWhereNull('product_id');
                    });
                })->whereHas('salesMasterProcessInfo', function ($q) use ($userIdArr) {
                    $q->whereIn('closer1_id', $userIdArr)->orWhereIn('closer2_id', $userIdArr)->orWhereIn('setter1_id', $userIdArr)->orWhereIn('setter2_id', $userIdArr);
                })->whereBetween('customer_signoff', [$startDate, $endDate])->where('auto_pay', '!=', '0')->count() ?? 0;

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($autoPay) {
                    return (is_null($range['from_value']) || $autoPay >= $range['from_value']) && (is_null($range['to_value']) || $autoPay <= $range['to_value']);
                });
                $other['current'] = $autoPay;
            }
        } elseif ($tierSystem == TierSystem::TIERED_BASED_ON_JOB_METRICS_PERFORMANCE) {
            if ($tierMetric == TierMetrics::GROSS_ACCOUNT_VALUE || $tierMetric == TierMetrics::GROSS_ACCOUNT_VALUE_SERVICED) {
                $grossAccountValue = $sale->gross_account_value ?? 0;
                $level = $tierLevels->first(function ($range) use ($grossAccountValue) {
                    return (is_null($range['from_value']) || $grossAccountValue >= $range['from_value']) && (is_null($range['to_value']) || $grossAccountValue <= $range['to_value']);
                });
                $other['current'] = $grossAccountValue;
            } elseif ($tierMetric == TierMetrics::KW || $tierMetric == TierMetrics::SQ_FT) {
                $kw = $sale->kw ?? 0;
                $level = $tierLevels->first(function ($range) use ($kw) {
                    return (is_null($range['from_value']) || $kw >= $range['from_value']) && (is_null($range['to_value']) || $kw <= $range['to_value']);
                });
                $other['current'] = $kw;
            } elseif ($tierMetric == TierMetrics::NET_EPC || $tierMetric == TierMetrics::NET_SQ_FT) {
                $netEPC = $sale->net_epc ?? 0;
                $level = $tierLevels->first(function ($range) use ($netEPC) {
                    return (is_null($range['from_value']) || $netEPC >= $range['from_value']) && (is_null($range['to_value']) || $netEPC <= $range['to_value']);
                });
                $other['current'] = $netEPC;
            } elseif ($tierMetric == TierMetrics::GROSS_EPC || $tierMetric == TierMetrics::GROSS_SQ_FT) {
                $grossEPC = $sale->epc ?? 0;
                $level = $tierLevels->first(function ($range) use ($grossEPC) {
                    return (is_null($range['from_value']) || $grossEPC >= $range['from_value']) && (is_null($range['to_value']) || $grossEPC <= $range['to_value']);
                });
                $other['current'] = $grossEPC;
            } elseif ($tierMetric == TierMetrics::DEALER_FEE_PERCENTAGE) {
                $dealerFeePercentage = $sale->dealer_fee_percentage ?? 0;
                $level = $tierLevels->first(function ($range) use ($dealerFeePercentage) {
                    return (is_null($range['from_value']) || $dealerFeePercentage >= $range['from_value']) && (is_null($range['to_value']) || $dealerFeePercentage <= $range['to_value']);
                });
                $other['current'] = $dealerFeePercentage;
            } elseif ($tierMetric == TierMetrics::DEALER_FEE_DOLLAR) {
                $dealerFeeDollar = $sale->dealer_fee_amount ?? 0;
                $level = $tierLevels->first(function ($range) use ($dealerFeeDollar) {
                    return (is_null($range['from_value']) || $dealerFeeDollar >= $range['from_value']) && (is_null($range['to_value']) || $dealerFeeDollar <= $range['to_value']);
                });
                $other['current'] = $dealerFeeDollar;
            } elseif ($tierMetric == TierMetrics::SOW) {
                $adders = $sale->adders ?? 0;
                $level = $tierLevels->first(function ($range) use ($adders) {
                    return (is_null($range['from_value']) || $adders >= $range['from_value']) && (is_null($range['to_value']) || $adders <= $range['to_value']);
                });
                $other['current'] = $adders;
            } elseif ($tierMetric == TierMetrics::INITIAL_SERVICE_COST) {
                $initialServiceCost = $sale->initial_service_cost ?? 0;
                $level = $tierLevels->first(function ($range) use ($initialServiceCost) {
                    return (is_null($range['from_value']) || $initialServiceCost >= $range['from_value']) && (is_null($range['to_value']) || $initialServiceCost <= $range['to_value']);
                });
                $other['current'] = $initialServiceCost;
            } elseif ($tierMetric == TierMetrics::SUBSCRIPTION_PAYMENT) {
                $subscriptionPayment = $sale->subscription_payment ?? 0;
                $level = $tierLevels->first(function ($range) use ($subscriptionPayment) {
                    return (is_null($range['from_value']) || $subscriptionPayment >= $range['from_value']) && (is_null($range['to_value']) || $subscriptionPayment <= $range['to_value']);
                });
                $other['current'] = $subscriptionPayment;
            }
        } elseif ($tierSystem == TierSystem::TIERED_BASED_ON_JOB_METRICS_EXACT_MATCH_PERFORMANCE) {
            if ($tierMetric == TierMetrics::INSTALLER) {
                $installPartner = $sale->install_partner;
                $level = $tierLevels->filter(function ($range) use ($installPartner) {
                    return strtolower($range['from_value']) === strtolower($installPartner);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $installPartner;
            } elseif ($tierMetric == TierMetrics::LOCATION_CODE) {
                $locationCode = $sale->location_code;
                $level = $tierLevels->filter(function ($range) use ($locationCode) {
                    return strtolower($range['from_value']) === strtolower($locationCode);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $locationCode;
            } elseif ($tierMetric == TierMetrics::CUSTOMER_STATE) {
                $customerState = $sale->customer_state;
                $level = $tierLevels->filter(function ($range) use ($customerState) {
                    return strtolower($range['from_value']) === strtolower($customerState);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $customerState;
            } elseif ($tierMetric == TierMetrics::CUSTOMER_ZIP) {
                $customerZip = $sale->customer_zip;
                $level = $tierLevels->filter(function ($range) use ($customerZip) {
                    return strtolower($range['from_value']) === strtolower($customerZip);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $customerZip;
            } elseif ($tierMetric == TierMetrics::PRODUCT_NAME) {
                $saleProductName = $sale->sale_product_name;
                $level = $tierLevels->filter(function ($range) use ($saleProductName) {
                    return strtolower($range['from_value']) === strtolower($saleProductName);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $saleProductName;
            } elseif ($tierMetric == TierMetrics::SERVICE_PROVIDER) {
                $installPartner = $sale->install_partner;
                $level = $tierLevels->filter(function ($range) use ($installPartner) {
                    return strtolower($range['from_value']) === strtolower($installPartner);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $installPartner;
            } elseif ($tierMetric == TierMetrics::CARD_ON_FILE) {
                $cardOnFile = $sale->card_on_file;
                $level = $tierLevels->filter(function ($range) use ($cardOnFile) {
                    return strtolower($range['from_value']) === strtolower($cardOnFile);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $cardOnFile;
            } elseif ($tierMetric == TierMetrics::AUTO_PAY) {
                $autoPay = $sale->auto_pay;
                $level = $tierLevels->filter(function ($range) use ($autoPay) {
                    return strtolower($range['from_value']) === strtolower($autoPay);
                })->first();

                if (! $level) {
                    $level = $tierLevels->first();
                }
                $other['current'] = $autoPay;
            }
        } elseif ($tierSystem == TierSystem::TIERED_BASED_ON_HIRING_PERFORMANCE) {
            if ($tierMetric == TierMetrics::WORKERS_HIRED) {
                $workerCount = User::where(function ($q) use ($userId) {
                    $q->where('recruiter_id', $userId)->orWhere('additional_recruiter_id1', $userId)->orWhere('additional_recruiter_id2', $userId);
                })->whereHas('agreement', function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('period_of_agreement')->whereBetween('period_of_agreement', [$startDate, $endDate]);
                })->where('dismiss', 0)->count();

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($workerCount) {
                    return (is_null($range['from_value']) || $workerCount >= $range['from_value']) && (is_null($range['to_value']) || $workerCount <= $range['to_value']);
                });
                $other['current'] = $workerCount;
            } elseif ($tierMetric == TierMetrics::LEADS_GENERATED) {
                $leadCount = Lead::whereBetween('created_at', [$startDate, $endDate])->where('recruiter_id', $userId)->count();

                $isLocked = 1;
                $level = $tierLevels->first(function ($range) use ($leadCount) {
                    return (is_null($range['from_value']) || $leadCount >= $range['from_value']) && (is_null($range['to_value']) || $leadCount <= $range['to_value']);
                });
                $other['current'] = $leadCount;
            } elseif ($tierMetric == TierMetrics::WORKERS_MANAGED) {
                $managerCount = 0;
                $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($isManager && $isManager->is_manager == '1') {
                    $subQuery = UserManagerHistory::select(
                        'id',
                        'user_id',
                        'effective_date',
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                    )->where('effective_date', '<=', $approvalDate);
                    $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
                    $userIds = UserManagerHistory::whereIn('id', $results->pluck('id'))->where('manager_id', $userId)->pluck('user_id')->toArray();
                    $managerCount = User::whereIn('id', $userIds)->where('dismiss', 0)->count();

                    $isLocked = 1;
                    $level = $tierLevels->first(function ($range) use ($managerCount) {
                        return (is_null($range['from_value']) || $managerCount >= $range['from_value']) && (is_null($range['to_value']) || $managerCount <= $range['to_value']);
                    });
                }
                $other['current'] = $managerCount;
            }
        }

        return [$level, $isLocked, $other];
    }
}

if (! function_exists('tiersCurrentDescription')) {
    function tiersCurrentDescription($other)
    {
        // $tierMetric = $other['tierMetric'];
        // $normalFormat = [TierMetrics::ACCOUNT_SOLD, TierMetrics::ACCOUNT_INSTALLED, TierMetrics::ACCOUNT_SERVICED, TierMetrics::WORKERS_HIRED, TierMetrics::LEADS_GENERATED, TierMetrics::WORKERS_MANAGED];
        // $dollarFormat = [TierMetrics::KW_SOLD, TierMetrics::KW_INSTALLED, TierMetrics::REVENUE_SOLD, TierMetrics::REVENUE_INSTALLED, TierMetrics::AVERAGE_CONTRACT_VALUE, TierMetrics::SQ_FT_SOLD, TierMetrics::SQ_FT_INSTALLED, TierMetrics::REVENUE_SERVICED];
        // $percentageFormat = [TierMetrics::INSTALL_RATE, TierMetrics::CANCELLATION_RATE, TierMetrics::SERVICE_RATE, TierMetrics::AUTO_PAY_ENROLMENT_RATE];
        // if (in_array($tierMetric, $normalFormat)) {
        //     return 'Currently ' . $other['current'] . ' of ' . $other['tierMetric'] . '.';
        // } else if (in_array($tierMetric, $dollarFormat)) {
        //     return 'Currently $ ' . number_format($other['current'], 2) . ' of ' . $other['tierMetric'] . '.';
        // } else if (in_array($tierMetric, $percentageFormat)) {
        //     return 'Current ' . $other['tierMetric'] . ' is ' . $other['current'] . '%.';
        // }
        return isset($other['current']) ? $other['current'] : null;
    }
}

if (! function_exists('tiersRemainingDescription')) {
    function tiersRemainingDescription($other, $nextLevel)
    {
        // $tierMetric = $other['tierMetric'];
        // $normalFormat = [TierMetrics::ACCOUNT_SOLD, TierMetrics::ACCOUNT_INSTALLED, TierMetrics::ACCOUNT_SERVICED, TierMetrics::WORKERS_HIRED, TierMetrics::LEADS_GENERATED, TierMetrics::WORKERS_MANAGED];
        // $dollarFormat = [TierMetrics::KW_SOLD, TierMetrics::KW_INSTALLED, TierMetrics::REVENUE_SOLD, TierMetrics::REVENUE_INSTALLED, TierMetrics::AVERAGE_CONTRACT_VALUE, TierMetrics::SQ_FT_SOLD, TierMetrics::SQ_FT_INSTALLED, TierMetrics::REVENUE_SERVICED];
        // $percentageFormat = [TierMetrics::INSTALL_RATE, TierMetrics::CANCELLATION_RATE, TierMetrics::SERVICE_RATE, TierMetrics::AUTO_PAY_ENROLMENT_RATE];
        // if (in_array($tierMetric, $normalFormat)) {
        //     return ($nextLevel->from_value - $other['current']).' more to tier '. $nextLevel->level.'.';
        // } else if (in_array($tierMetric, $dollarFormat)) {
        //     return '$ '.number_format(($nextLevel->from_value - $other['current']), 2).' more to tier '. $nextLevel->level.'.';
        // } else if (in_array($tierMetric, $percentageFormat)) {
        //     return ($nextLevel->from_value - $other['current']).'% more to tier '. $nextLevel->level.'.';
        // }
        if (isset($nextLevel->from_value) && isset($other['current'])) {
            if ($other['current'] >= $nextLevel->from_value) {
                return [1, 0];
            } else {
                return [0, $nextLevel->from_value - $other['current']];
            }
        }

        return [null, null];
    }
}

if (! function_exists('getPositionNameById')) {
    function getPositionNameById($positionId)
    {
        $positionName = Positions::where('id', $positionId)->withoutGlobalScopes()->value('position_name');

        return $positionName;
    }
}

if (! function_exists('getTieredPositions')) {
    function getTieredPositions()
    {
        $nonUpdatedTiers = [];
        $updatedTiers = PositionTier::whereNotNull('effective_date')->pluck('position_id');
        if (count($updatedTiers) != 0) {
            $nonUpdatedTiers = Positions::whereNotIn('id', $updatedTiers)->pluck('id')->toArray();
        }
        $subQuery = PositionTier::select('id', 'position_id', 'effective_date', DB::raw('ROW_NUMBER() OVER (PARTITION BY position_id ORDER BY effective_date DESC, id DESC) as rn'))->where('effective_date', '<=', date('Y-m-d'));
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
        $updatedTiers = PositionTier::whereIn('id', $results->pluck('id'))->whereIn('position_id', $updatedTiers)->groupBy('position_id')->pluck('position_id');

        return array_unique(array_merge($nonUpdatedTiers, $updatedTiers->toArray()));
    }
}

if (! function_exists('displayTieredCommission')) {
    function displayTieredCommission($data)
    {
        $isTired = 0;
        $commissionAmount = 0;
        $commissionType = null;
        $commissionHistory = $data['commissionHistory'];
        $userId = $data['userId'];
        $approvedDate = $data['approvedDate'];
        $productId = $data['productId'];
        $request = $data['request'];
        if ($commissionHistory && $commissionHistory->tiers_id) {
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }

            if ($commission && $commission->commission_status == 1 && CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
                $sale = [
                    'customer_signoff' => $approvedDate,
                    'product_id' => $productId,
                    'gross_account_value' => $request->gross_account_value,
                    'kw' => $request->kw,
                    'net_epc' => $request->net_epc,
                    'epc' => $request->epc,
                    'dealer_fee_percentage' => $request->dealer_fee_percentage,
                    'dealer_fee_amount' => $request->dealer_fee_amount,
                    'adders' => $request->adders,
                    'initial_service_cost' => $request->initial_service_cost,
                    'subscription_payment' => $request->subscription_payment,
                    'install_partner' => $request->install_partner,
                    'location_code' => $request->location_code,
                    'customer_state' => $request->customer_state,
                    'customer_zip' => $request->customer_zip,
                    'sale_product_name' => $request->sale_product_name,
                    'card_on_file' => $request->card_on_file,
                    'auto_pay' => $request->auto_pay,
                ];
                $sale = new Fluent($sale);
                $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $commissionHistory->tiers_id)->first();
                if ($tierSchema) {
                    $isTired = 1;
                    $level = getTierLevelForUser($tierSchema, $userOrganizationHistory, $sale, $userId, $subPositionId, 'commission');
                    if (isset($level['level']['level'])) {
                        $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                            $q->where('level', $level['level']['level']);
                        })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                        if ($commissionTier) {
                            $commissionAmount = $commissionTier->value;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    }
                }
            }
        }

        return [
            'is_tired' => $isTired,
            'commission' => $commissionAmount,
            'commission_type' => $commissionType,
        ];
    }
}

if (! function_exists('isUserTerminatedOn')) {
    function isUserTerminatedOn($userId, $date)
    {
        $user = User::find($userId);

        if (! $user) {
            return false; // If user does not exist, consider them non-terminated
        }

        return $user->isTerminatedOn($date);
    }
}

if (! function_exists('isUserDismisedOn')) {
    function isUserDismisedOn($userId, $date)
    {
        // Get the latest dismissal entry before or on the given date
        $dismissEntry = UserDismissHistory::where('user_id', $userId)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id') // Ensure latest entry is checked
            ->first();

        return $dismissEntry ? $dismissEntry->dismiss == 1 : false;
    }
}

if (! function_exists('isUserContractEnded')) {
    function isUserContractEnded($userId)
    {
        $effectiveDate = date('Y-m-d');

        // Find the currently active contract based on period_of_agreement (contract start date)
        // Get the most recent contract that has started on or before today
        $currentContract = UserAgreementHistory::where('user_id', $userId)
            ->whereNotNull('period_of_agreement')
            ->where('period_of_agreement', '<=', $effectiveDate) // Only contracts that have started
            ->orderBy('period_of_agreement', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        // Check if the currently active contract has ended
        return $currentContract && $currentContract->end_date && $currentContract->end_date <= $effectiveDate;
    }
}

if (! function_exists('checkDismissFlag')) {
    function checkDismissFlag($userId, $effectiveDate)
    {
        // effective_date represents the LAST WORKING DAY of the employee
        // They should be allowed to work ON that date, but dismissed AFTER that date
        // So we use '<' (strictly less than) not '<=' (less than or equal to)
        return UserDismissHistory::where('user_id', $userId)->where('effective_date', '<', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
    }
}

if (! function_exists('checkContractEndFlag')) {
    function checkContractEndFlag($userId, $effectiveDate)
    {
        // Find the currently active contract based on period_of_agreement (contract start date)
        // Get the most recent contract that has started on or before the effective date
        $currentContract = UserAgreementHistory::where('user_id', $userId)
            ->whereNotNull('period_of_agreement')
            ->where('period_of_agreement', '<=', $effectiveDate) // Only contracts that have started
            ->orderBy('period_of_agreement', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        // Check if the currently active contract has ended
        if ($currentContract && $currentContract->end_date && $currentContract->end_date <= $effectiveDate) {
            return $currentContract;
        }

        return null;
    }
}

if (! function_exists('checkTerminateFlag')) {
    function checkTerminateFlag($userId, $effectiveDate)
    {
        // terminate_effective_date represents the LAST WORKING DAY of the employee
        // They should be allowed to work ON that date, but terminated AFTER that date
        // So we use '<' (strictly less than) not '<=' (less than or equal to)
        return UserTerminateHistory::where('user_id', $userId)->where('terminate_effective_date', '<', $effectiveDate)->orderBy('terminate_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
    }
}

if (! function_exists('dismissedUsers')) {
    function dismissedUsers($effectiveDate = null)
    {

        if (!$effectiveDate) {
            // BUSINESS LOGIC: Check CURRENT status (as of today) regardless of sale date
            // If user is currently enabled, they should be available for ALL sales (past/future)
            // If user is currently dismissed, they should be excluded from ALL sales
            $effectiveDate = date('Y-m-d');
        }

        $subQuery = UserDismissHistory::select(
            'id',
            'user_id',
            'dismiss',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<', $effectiveDate); // effective_date is LAST WORKING DAY, so user dismissed AFTER that date
        
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('id')
            ->where('rn', 1);  // Get most recent record per user

        return UserDismissHistory::whereIn('id', $results->pluck('id'))
            ->where('dismiss', UserDismissHistory::DISMISSED)  // Filter for dismissed status
            ->pluck('user_id');
    }
}

if (! function_exists('terminatedUsers')) {
    function terminatedUsers($effectiveDate = null)
    {
        if (! $effectiveDate) {
            $effectiveDate = date('Y-m-d');
        }
        $subQuery = UserTerminateHistory::select(
            'id',
            'user_id',
            'terminate_effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY terminate_effective_date DESC, id DESC) as rn')
        )->where('terminate_effective_date', '<', $effectiveDate); // terminate_effective_date is LAST WORKING DAY, so user terminated AFTER that date
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

        return UserTerminateHistory::whereIn('id', $results->pluck('id'))->where('is_terminate', UserTerminateHistory::TERMINATED)->pluck('user_id');
    }
}

if (! function_exists('contractEndedUsers')) {
    function contractEndedUsers($effectiveDate = null)
    {
        if (! $effectiveDate) {
            $effectiveDate = date('Y-m-d');
        }

        // Get the currently active contract for each user based on period_of_agreement (contract start date)
        // Find the most recent contract that has started on or before the effective date
        $subQuery = UserAgreementHistory::select(
            'id',
            'user_id',
            'end_date',
            'period_of_agreement',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY period_of_agreement DESC, id DESC) as rn')
        )->whereNotNull('period_of_agreement')
            ->where('period_of_agreement', '<=', $effectiveDate); // Only contracts that have started

        // Then check if the currently active contract has ended
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('id')
            ->where('rn', 1)  // Get the most recent started contract
            ->whereNotNull('end_date')  // Only contracts with end_date
            ->where('end_date', '<=', $effectiveDate);  // Check if it has ended

        return UserAgreementHistory::whereIn('id', $results->pluck('id'))->pluck('user_id');
    }
}

if (! function_exists('checkSalesReps')) {
    function checkSalesReps($userId, $effectiveDate, $type = null)
    {
        $terminated = checkTerminateFlag($userId, $effectiveDate);
        if ($terminated && $terminated->is_terminate) {
            return [
                'status' => false,
                'message' => $type.' has been terminated for selected sale date.',
            ];
        }

        $dismissed = checkDismissFlag($userId, $effectiveDate);
        if ($dismissed && $dismissed->dismiss) {
            return [
                'status' => false,
                'message' => $type.' has been disabled for selected sale date.',
            ];
        }

        $contractEnded = checkContractEndFlag($userId, $effectiveDate);
        if ($contractEnded) {
            return [
                'status' => false,
                'message' => $type.' has ended for selected sale date.',
            ];
        }

        return [
            'status' => true,
            'message' => null,
        ];
    }
}

if (! function_exists('checkEvereeErrorStructured')) {
    function checkEvereeErrorStructured($userId)
    {
        // Check if Everee is enabled
        $evereeEnabled = Crms::where('id', 3)->where('status', 1)->exists();

        //if (! $evereeEnabled) {
            // If Everee is disabled, check database values - only show "required" errors for missing fields
            $user = User::find($userId);

            if (! $user) {
                return ['general' => 'User not found'];
            }

            $errors = [];

            // Only check for missing required fields (remove "required" conditions if data exists)
            if (empty($user->first_name)) {
                $errors['first_name'] = 'First name is required.';
            }
            if (empty($user->last_name)) {
                $errors['last_name'] = 'Last name is required.';
            }
            if (empty($user->mobile_no)) {
                $errors['phone_number'] = 'Phone number is required.';
            }
            if (empty($user->email)) {
                $errors['email'] = 'Email is required.';
            }
            if (empty($user->employee_id)) {
                $errors['employee_id'] = 'Employee ID is required.';
            }
            if (empty($user->home_address_line_1)) {
                $errors['address'] = 'Address is required.';
            }
            if (empty($user->home_address_city)) {
                $errors['city'] = 'City is required.';
            }
            if (empty($user->home_address_state)) {
                $errors['state'] = 'State is required.';
            }
            if (empty($user->home_address_zip)) {
                $errors['zip_code'] = 'Zip code is required.';
            }
            if (empty($user->dob)) {
                $errors['date_of_birth'] = 'Date of birth is required.';
            }
            if (empty($user->name_of_bank)) {
                $errors['bank_name'] = 'Bank name is required.';
            }
            if (empty($user->account_name)) {
                $errors['account_holder_name'] = 'Account holder name is required.';
            }
            if (empty($user->type_of_account)) {
                $errors['account_type'] = 'Account type is required.';
            }
            if (empty($user->routing_no)) {
                $errors['routing_number'] = 'Routing number is required.';
            }
            if (empty($user->account_no)) {
                $errors['account_number'] = 'Account number is required.';
            }

            // Entity type-based validation
            $entityType = strtolower($user->entity_type ?? '');

            if ($entityType === 'individual') {
                // Individual entity type - only check for social security number
                if (empty($user->social_sequrity_no)) {
                    $errors['social_security_number'] = 'Social Security Number is required.';
                }
            } elseif ($entityType === 'business') {
                // Business entity type - check for business-specific fields
                if (empty($user->business_ein)) {
                    $errors['business_ein'] = 'Business EIN is required.';
                }
                if (empty($user->business_name)) {
                    $errors['business_name'] = 'Business name is required.';
                }
                if (empty($user->business_type)) {
                    $errors['business_type'] = 'Business type is required.';
                }
            } else {
                // Default behavior for unknown or empty entity types (backward compatibility)
                if (empty($user->social_sequrity_no) && empty($user->business_ein)) {
                    $errors['tax_info'] = 'Either Social Security Number or Business EIN is required.';
                }
            }

            // Keep "invalid" checks for existing data
            if (! empty($user->email) && ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format.';
            }
            if (! empty($user->routing_no) && ! preg_match('/^\d{9}$/', $user->routing_no)) {
                $errors['routing_number'] = 'Invalid routing number format.';
            }
            
            //condition for errors to be added.
            //return empty($errors) ? null : $errors;
            
        //}
        
        // If Everee is enabled, continue with existing logic
        $errorObj = null;

         //if user onboarding is not complete, return errors
        if(count($errors) > 0) {
            $errorObj = $errors;
        }

        // apis name for everee error message
        $apiNames = [
            'update_emp_personal_info',
            'add_complete_contractor',
            'update_emp_work_location',
            'update_emp_banking_info',
            'update_emp_taxpayer_info',
            'update_hireDate',
            'update_evree_external_worker_id',
            'update_home_address_state',
            'Create employee for embedded onboarding',
        ];

        $log = evereeTransectionLog::select('response')
            ->where('user_id', $userId)
            ->whereIn('api_name', ['add_complete_contractor', 'update_emp_personal_info'])
            ->latest('id')
            ->first();

        if ($log && $log->response) {
            $decodedResponse = json_decode($log->response, true);

            // Check if there are errors (no workerId means error)
            if (! isset($decodedResponse['workerId'])) {
                if (is_array($decodedResponse)) {
                    if (isset($decodedResponse['errorMessage'])) {
                        // Handle errorMessage field (string or array)
                        $errorObj = parseEvereeErrors($decodedResponse['errorMessage']);
                    } elseif (! empty($decodedResponse)) {
                        // Handle direct array of errors or comma-separated values
                        $errorObj = parseEvereeErrors($decodedResponse);
                    }
                } elseif (is_string($decodedResponse)) {
                    // Handle case where response is a string
                    $errorObj = ['general' => $decodedResponse];
                } elseif ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                    // JSON decode failed, use original response as-is
                    $errorObj = ['general' => $log->response];
                } else {
                    // For any other case, use original response
                    $errorObj = ['general' => $log->response];
                }
            }
        }

        return $errorObj;
    }
}

if (! function_exists('parseEvereeErrors')) {
    function parseEvereeErrors($errors)
    {
        $errorObj = [];

        // Field mapping from technical names to user-friendly names
        $fieldMapping = [
            'type_of_account' => 'account_type',
            'home_address_line_1' => 'address',
            'home_address_city' => 'city',
            'home_address_state' => 'state',
            'home_address_zip' => 'zip_code',
            'dob' => 'date_of_birth',
            'name_of_bank' => 'bank_name',
            'account_name' => 'account_holder_name',
            'routing_no' => 'routing_number',
            'account_no' => 'account_number',
            'social_sequrity_no' => 'social_security_number',
            'business_ein' => 'business_ein',
            'business_name' => 'business_name',
            'business_type' => 'business_type',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'mobile_no' => 'phone_number',
            'email' => 'email',
            'employee_id' => 'employee_id',
            'location' => 'location',
        ];

        // Normalize input to array format
        if (is_string($errors)) {
            // Handle comma-separated string
            $errors = explode(', ', $errors);
        } elseif (! is_array($errors)) {
            // Handle unexpected format
            return ['general' => 'Unexpected error format'];
        }

        // Process each error
        foreach ($errors as $error) {
            if (is_string($error)) {
                $error = trim($error);

                // Extract field name from "required" error message
                if (preg_match("/The field '([^']+)' is required\./", $error, $matches)) {
                    $fieldName = $matches[1];
                    $friendlyName = $fieldMapping[$fieldName] ?? str_replace('_', ' ', $fieldName);
                    $errorObj[$friendlyName] = ucfirst(str_replace('_', ' ', $friendlyName)).' is required.';
                }
                // Extract field name from "invalid" error message
                elseif (preg_match("/The field '([^']+)' is invalid\./", $error, $matches)) {
                    $fieldName = $matches[1];
                    $friendlyName = $fieldMapping[$fieldName] ?? str_replace('_', ' ', $fieldName);
                    $errorObj[$friendlyName] = ucfirst(str_replace('_', ' ', $friendlyName)).' is invalid.';
                }
                // Handle special SSN/EIN requirement
                elseif (strpos($error, "Either 'social_sequrity_no' or 'business_ein' is required") !== false) {
                    $errorObj['tax_info'] = 'Either Social Security Number or Business EIN is required.';
                }
                // Handle specific bank routing number error
                elseif (strpos($error, 'Invalid bank routing number') !== false) {
                    $errorObj['routing_number'] = 'Invalid bank routing number.';
                }
                // Handle other specific banking errors
                elseif (strpos($error, 'Invalid account number') !== false) {
                    $errorObj['account_number'] = 'Invalid account number.';
                } elseif (strpos($error, 'Invalid bank account') !== false) {
                    $errorObj['account_number'] = 'Invalid bank account.';
                }
                // Handle any other error format
                else {
                    $errorObj['general'] = isset($errorObj['general'])
                        ? $errorObj['general'].'; '.$error
                        : $error;
                }
            } elseif (is_array($error) && isset($error['message'])) {
                $errorObj['general'] = isset($errorObj['general'])
                    ? $errorObj['general'].'; '.$error['message']
                    : $error['message'];
            }
        }

        return $errorObj;
    }
}

if (! function_exists('getDatesFromToToday')) {
    function getDatesFromToToday($startDate)
    {
        $dates = collect();
        $start = Carbon::parse($startDate);
        $end = Carbon::today();

        while ($start->lte($end)) {
            $dates->push($start->copy()->toDateString());
            $start->addDay();
        }

        return $dates;
    }
}

/**
 * Get all sales for a user from the effective date and recalculate them
 * This function is used after contract application to ensure all sales are calculated with new terms
 *
 * @param  int  $userId  The user ID
 * @param  string  $effectiveDate  The contract effective date (Y-m-d format)
 * @return array Results of the recalculation process
 */
if (! function_exists('recalculateUserSalesFromEffectiveDate')) {
    function recalculateUserSalesFromEffectiveDate($userId, $effectiveDate = null)
    {
        $effectiveDate = $effectiveDate ?? date('Y-m-d');

        try {
            // Get user's PIDs from SaleMasterProcess
            $userPids = \App\Models\SaleMasterProcess::where('closer1_id', $userId)
                ->orWhere('closer2_id', $userId)
                ->orWhere('setter1_id', $userId)
                ->orWhere('setter2_id', $userId)
                ->pluck('pid')
                ->toArray();

            if (empty($userPids)) {
                return [
                    'status' => false,
                    'message' => 'No sales found for user',
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                    'processed_count' => 0,
                    'failed_count' => 0,
                ];
            }

            // Get all sales from the effective date forward
            $salesToRecalculate = SalesMaster::whereIn('pid', $userPids)
                ->where('customer_signoff', '>=', $effectiveDate)
                ->where('date_cancelled', null) // Only active sales
                ->pluck('pid')
                ->toArray();

            if (empty($salesToRecalculate)) {
                return [
                    'status' => true,
                    'message' => 'No sales found from effective date to recalculate',
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                    'processed_count' => 0,
                    'failed_count' => 0,
                ];
            }

            $processedCount = 0;
            $failedCount = 0;
            $failedPids = [];

            \Illuminate\Support\Facades\Log::info('Starting sales recalculation after contract application', [
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
                'total_sales_to_recalculate' => count($salesToRecalculate),
            ]);

            // Use the existing SalesController recalculateSale method
            $salesController = new \App\Http\Controllers\API\V2\Sales\SalesController;

            foreach ($salesToRecalculate as $pid) {
                try {
                    $request = new \Illuminate\Http\Request(['pid' => $pid]);
                    $result = $salesController->recalculateSale($request, true); // true for $recalAll parameter

                    if ($result == 1) {
                        $processedCount++;
                    } else {
                        $failedCount++;
                        $failedPids[] = $pid;
                    }

                } catch (\Exception $e) {
                    $failedCount++;
                    $failedPids[] = $pid;

                    \Illuminate\Support\Facades\Log::error('Failed to recalculate sale during contract application', [
                        'user_id' => $userId,
                        'pid' => $pid,
                        'error' => $e->getMessage(),
                        'effective_date' => $effectiveDate,
                    ]);
                }
            }

            \Illuminate\Support\Facades\Log::info('Completed sales recalculation after contract application', [
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'failed_pids' => $failedPids,
            ]);

            return [
                'status' => true,
                'message' => "Sales recalculation completed. Processed: {$processedCount}, Failed: {$failedCount}",
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'failed_pids' => $failedPids,
                'total_sales_found' => count($salesToRecalculate),
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error during sales recalculation after contract application', [
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => 'Error during sales recalculation: '.$e->getMessage(),
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
                'processed_count' => 0,
                'failed_count' => 0,
            ];
        }
    }
}

/**
 * Get today's sales for a specific user
 *
 * @param  int  $userId  The user ID
 * @param  string  $date  Optional date (defaults to today)
 * @return \Illuminate\Support\Collection Collection of sales for the user on the specified date
 */
if (! function_exists('getTodaysSalesForUser')) {
    function getTodaysSalesForUser($userId, $date = null)
    {
        $date = $date ?? date('Y-m-d');

        // Get user's PIDs
        $pid = \App\Models\SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid')
            ->toArray();

        // Get sales for the date
        return SalesMaster::whereIn('pid', $pid)
            ->whereDate('customer_signoff', $date)
            ->where('date_cancelled', null) // Only active sales
            ->with('salesMasterProcess', 'userDetail')
            ->get();
    }
}

if (! function_exists('applyPayrollCommissionSorting')) {
    function applyPayrollCommissionSorting(array &$data, string $sortKey, int $sortDirection = SORT_ASC)
    {
        foreach ($data as $statusKey => &$periodGroup) {
            if (in_array($statusKey, ['subtotal', 'common_data'])) {
                continue;
            }

            foreach ($periodGroup as $periodKey => &$items) {
                usort($items, function ($a, $b) use ($sortKey, $sortDirection) {
                    // Default values
                    $valueA = $a[$sortKey] ?? null;
                    $valueB = $b[$sortKey] ?? null;

                    switch ($sortKey) {
                        case 'rep_redline':
                            $valueA = is_string($valueA) ? floatval(preg_replace('/[^0-9.-]+/', '', $valueA)) : $valueA;
                            $valueB = is_string($valueB) ? floatval(preg_replace('/[^0-9.-]+/', '', $valueB)) : $valueB;
                            break;

                        case 'employee_name':
                        case 'customer_name':
                            // Handle customer_name array or string
                            $customerA = $a['customer_name'] ?? '';
                            $customerB = $b['customer_name'] ?? '';

                            $valueA = is_array($customerA) ? strtolower($customerA[0] ?? '') : strtolower($customerA);
                            $valueB = is_array($customerB) ? strtolower($customerB[0] ?? '') : strtolower($customerB);
                            break;

                        default:
                            // Normalize strings for default fields
                            $valueA = is_string($valueA) ? strtolower($valueA) : $valueA;
                            $valueB = is_string($valueB) ? strtolower($valueB) : $valueB;
                            break;
                    }

                    if ($valueA == $valueB) {
                        return 0;
                    }

                    return ($sortDirection === SORT_ASC)
                        ? ($valueA < $valueB ? -1 : 1)
                        : ($valueA > $valueB ? -1 : 1);
                });
            }
        }

        unset($periodGroup, $items);
    }
}

// Define the function only if it doesn't already exist
if (! function_exists('applyPayrollSorting')) {
    function applyPayrollSorting(array &$data, string $sortKey, string $sortDirection = 'asc')
    {
        // Define the list of allowed keys that can be sorted
        $allowedSortKeys = [
            'pid', 'customer_name', 'commission', 'override', 'adjustment',
            'net_pay', 'gross_pay', 'loan_amount', 'net_epc',
        ];

        // If the requested sort key is not in the allowed list, exit the function
        if (! in_array($sortKey, $allowedSortKeys)) {
            return; // Ignore unsupported sort keys
        }

        // Use Laravel's Collection to sort the data array by the given key
        $data = collect($data)->sortBy(function ($item) use ($sortKey) {
            // Sort by the value of the specified key, fallback to null if not present
            return $item[$sortKey] ?? null;
        }, SORT_REGULAR, strtolower($sortDirection) === 'desc')->values()->toArray();
    }

    /**
     * Log exception with conditional stack trace based on environment
     * Stack traces are only included in non-production environments
     *
     * @param string $message Log message
     * @param \Throwable $exception Exception to log
     * @param array $context Additional context
     * @param string $level Log level (error, critical, warning, etc.)
     * @return void
     */
    if (! function_exists('log_exception')) {
        function log_exception(
            string $message,
            \Throwable $exception,
            array $context = [],
            string $level = 'error'
        ): void {
            // Build base context
            $logContext = array_merge($context, [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            // Only include stack trace in non-production environments
            if (!app()->isProduction()) {
                $logContext['trace'] = $exception->getTraceAsString();
            }

            // Log with appropriate level
            \Illuminate\Support\Facades\Log::$level($message, $logContext);
        }
    }
}
