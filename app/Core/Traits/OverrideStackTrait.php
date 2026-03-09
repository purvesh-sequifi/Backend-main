<?php

namespace App\Core\Traits;

use App\Models\AdditionalLocations;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\Payroll;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UserTransferHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait OverrideStackTrait
{
    use PayFrequencyTrait;
    use ReconciliationPeriodTrait;

    public function StackUserOverride($sale_user_id, $pid, $kw, $date) // Per Sale, Per KW, Percent
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $user_data = User::where('id', $sale_user_id)->first();

        if ($user_data && $user_data->office_id && $stackSystemSetting) {
            $office_id = $user_data->office_id;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $closer1_id = $saleData->salesMasterProcess->closer1_id;
            $setter1_id = $saleData->salesMasterProcess->setter1_id;
            if (config('app.domain_name') == 'flex') {
                $saleState = $saleData->customer_state;
            } else {
                $saleState = $saleData->location_code;
            }
            $saleUsers = [$closer1_id, $setter1_id];
            if ($saleData->salesMasterProcess->closer2_id) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
            }
            if ($saleData->salesMasterProcess->setter2_id) {
                $saleUsers[] = $saleData->salesMasterProcess->setter2_id;
            }

            $approvedDate = $saleData->customer_signoff;
            $netEpc = $saleData->net_epc;

            $finalCommission = $saleData->salesMasterProcess->closer1_commission + $saleData->salesMasterProcess->closer2_commission + $saleData->salesMasterProcess->setter1_commission + $saleData->salesMasterProcess->setter2_commission;

            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            Log::info([$totalOverride, $totalOverrideClawBack, $finalOverride]);

            $userTransferHistory = UserTransferHistory::where('user_id', $sale_user_id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }

            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $userIdArr = UserOverrideHistory::whereIn('id', $results->pluck('id'))->whereHas('user', function ($q) use ($office_id, $closer1_id, $setter1_id, $sale_user_id) {
                $q->where(['office_id' => $office_id, 'dismiss' => '0'])->when(($closer1_id == $setter1_id), function ($q) use ($sale_user_id) {
                    $q->where('id', '!=', $sale_user_id);
                });
            })->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();

            $userIds = AdditionalLocations::where('office_id', $office_id)
                ->where('user_id', '!=', $sale_user_id)
                ->where('effective_date', '<=', $approvedDate)
                ->where(function ($q) use ($approvedDate) {
                    $q->whereNull('effective_end_date')
                      ->orWhere('effective_end_date', '>=', $approvedDate);
                })
                ->pluck('user_id')->toArray();
            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $additionalUserIdArr = UserOverrideHistory::whereHas('user', function ($q) {
                $q->where('dismiss', '0');
            })->whereIn('id', $results->pluck('id'))->whereIn('user_id', $userIds)->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr, $additionalUserIdArr));

            $organizationHistory = UserOrganizationHistory::where('user_id', $sale_user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if (! $organizationHistory) {
                return false;
            }
            $positionId = $organizationHistory->position_id;

            if ($organizationHistory->self_gen_accounts == 1 && $positionId == 3) {
                $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
            } else {
                $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
            }
            if (! $commissionHistory) {
                return false;
            }
            $commissionType = $commissionHistory->commission_type;
            $stackData = [];
            if ($commissionType == 'per sale') {
                foreach ($userIdArr as $userId) {
                    $closerData = $this->userPerSale($userId, $saleState, $approvedDate, $commissionHistory);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } elseif ($commissionType == 'per kw') {
                foreach ($userIdArr as $userId) {
                    $closerData = $this->userPerKw($userId, $saleState, $approvedDate, $commissionHistory);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } elseif ($commissionType == 'percent') {
                $closerRedline = $this->userRedline($user_data, $saleState, $approvedDate);
                if ($closerRedline['redline']) {
                    foreach ($userIdArr as $userId) {
                        $closerData = $this->userPercentage($userId, $saleState, $approvedDate, $closerRedline['redline']);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                }
            }

            $sortedArray = [];
            if (isset($stackData['per sale'])) {
                $sortedArray['per sale'] = collect($stackData['per sale'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }
            if (isset($stackData['per kw'])) {
                $sortedArray['per kw'] = collect($stackData['per kw'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }
            if (isset($stackData['percent'])) {
                $sortedArray['percent'] = collect($stackData['percent'])->sortByDesc(function ($item) {
                    return $item['value'];
                })->toArray();
            }

            $i = 0;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack', 'status' => 1])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideStatus && $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userData->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                            // $payFrequency = $this->reconciliationPeriod($date);
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '4', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $value = $stack['value'];
                                $valueType = $stack['value_type'];

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $stack['value']) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = UserOverrides::where(['type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('amount');
                                        $lowerStackClawBackPay = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                                        $lowerStackPay = $lowerStackPay - $lowerStackClawBackPay;
                                    }
                                }

                                $amount = 0;
                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $margin_percentage = $companyMargin->company_margin;
                                    $x = ((100 - $margin_percentage) / 100);
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'per kw') {
                                        $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $kw, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = ((($netEpc - $value) * $x) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $netEpc, $value, $x, $kw, 1000, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                } else {
                                    if ($key == 'per sale') {
                                        $amount = ($value - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'per kw') {
                                        $amount = (($value * $kw) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $kw, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (($netEpc - $value) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $netEpc, $value, $kw, 1000, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                }

                                $stackData = [
                                    'user_id' => $userData->id,
                                    'type' => 'Stack',
                                    'sale_user_id' => $sale_user_id,
                                    'pid' => $pid,
                                    'kw' => $kw,
                                    'amount' => $amount,
                                    'overrides_amount' => $userData->office_stack_overrides_amount,
                                    'overrides_type' => 'per sale',
                                    'calculated_redline' => $value,
                                    'calculated_redline_type' => $valueType,
                                    'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                    'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $userData->stop_payroll,
                                ];

                                $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                                $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                if ($override && number_format($override, 2, '.', '') == number_format($clawBack, 2, '.', '')) {
                                    $userOverrode = UserOverrides::create($stackData);
                                } else {
                                    if (! UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Stack', 'status' => '3', 'is_displayed' => '1'])->first()) {
                                        $userOverrode = UserOverrides::create($stackData);
                                    }
                                }

                                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $userData->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'override' => 0,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                }
                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $stack['value'];
                            }
                        }
                    }
                }
            }
        }
    }

    public function addersStackOverride($sale_user_id, $pid, $kw, $date)
    {
        $user_data = User::where('id', $sale_user_id)->first();
        if ($user_data && $user_data->office_id) {
            $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
            if (count($stackOverrides) != 0) {
                $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                $closer1_id = $saleData->salesMasterProcess->closer1_id;
                $setter1_id = $saleData->salesMasterProcess->setter1_id;
                if (config('app.domain_name') == 'flex') {
                    $saleState = $saleData->customer_state;
                } else {
                    $saleState = $saleData->location_code;
                }
                $saleUsers = [$closer1_id, $setter1_id];
                if ($saleData->salesMasterProcess->closer2_id) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
                }
                if ($saleData->salesMasterProcess->setter2_id) {
                    $saleUsers[] = $saleData->salesMasterProcess->setter2_id;
                }

                $approvedDate = $saleData->customer_signoff;
                $netEpc = $saleData->net_epc;

                $finalCommission = $saleData->salesMasterProcess->closer1_commission + $saleData->salesMasterProcess->closer2_commission + $saleData->salesMasterProcess->setter1_commission + $saleData->salesMasterProcess->setter2_commission;

                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;
                Log::info(['update', $totalOverride, $totalOverrideClawBack, $finalOverride]);

                $organizationHistory = UserOrganizationHistory::where('user_id', $sale_user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (! $organizationHistory) {
                    return false;
                }
                $positionId = $organizationHistory->position_id;

                if ($organizationHistory->self_gen_accounts == 1 && $positionId == 3) {
                    $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
                } else {
                    $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                }
                if (! $commissionHistory) {
                    return false;
                }
                $commissionType = $commissionHistory->commission_type;
                $userIdArr = User::whereIn('id', $stackOverrides)->where('dismiss', 0)->pluck('id');
                $stackData = [];
                if ($commissionType == 'per sale') {
                    foreach ($userIdArr as $userId) {
                        $closerData = $this->userPerSale($userId, $saleState, $approvedDate, $commissionHistory);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } elseif ($commissionType == 'per kw') {
                    foreach ($userIdArr as $userId) {
                        $closerData = $this->userPerKw($userId, $saleState, $approvedDate, $commissionHistory);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } elseif ($commissionType == 'percent') {
                    $closerRedline = $this->userRedline($user_data, $saleState, $approvedDate);
                    if ($closerRedline['redline']) {
                        foreach ($userIdArr as $userId) {
                            $closerData = $this->userPercentage($userId, $saleState, $approvedDate, $closerRedline['redline']);
                            if ($closerData) {
                                $stackData[$closerData['type']][] = $closerData;
                            }
                        }
                    }
                }

                $sortedArray = [];
                if (isset($stackData['per sale'])) {
                    $sortedArray['per sale'] = collect($stackData['per sale'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }
                if (isset($stackData['per kw'])) {
                    $sortedArray['per kw'] = collect($stackData['per kw'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }
                if (isset($stackData['percent'])) {
                    $sortedArray['percent'] = collect($stackData['percent'])->sortByDesc(function ($item) {
                        return $item['value'];
                    })->toArray();
                }

                $i = 0;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack', 'status' => 1])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($overrideStatus && $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            } else {
                                $positionId = $userData->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                            if ($positionReconciliation) {
                                $settlementType = 'reconciliation';
                                // $payFrequency = $this->reconciliationPeriod($date);
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $value = $stack['value'];
                                $valueType = $stack['value_type'];

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $stack['value']) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = UserOverrides::where(['type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('amount');
                                        $lowerStackClawBackPay = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                                        $lowerStackPay = $lowerStackPay - $lowerStackClawBackPay;
                                    }
                                }

                                $amount = 0;
                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $margin_percentage = $companyMargin->company_margin;
                                    $x = ((100 - $margin_percentage) / 100);
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'per kw') {
                                        $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $kw, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = ((($netEpc - $value) * $x) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $netEpc, $value, $x, $kw, 1000, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                } else {
                                    if ($key == 'per sale') {
                                        $amount = ($value - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'per kw') {
                                        $amount = (($value * $kw) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $value, $kw, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (($netEpc - $value) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        Log::info([$key, $netEpc, $value, $kw, 1000, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                }

                                if ($override = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Stack', 'during' => 'm2', 'status' => '1', 'is_displayed' => '1'])->first()) {
                                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $key, 'type' => 'Stack', 'status' => 1])->first();
                                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $key, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                                    if ($overrideStatus && $overrideStatus->status == 0) {
                                        $override->update([
                                            'during' => 'm2',
                                            'sale_user_id' => $sale_user_id,
                                            'kw' => $kw,
                                            'amount' => $amount,
                                            'overrides_amount' => $stackShare,
                                            'overrides_type' => 'per sale',
                                            'calculated_redline' => $value,
                                            'calculated_redline_type' => $valueType,
                                            'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                            'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                            'overrides_settlement_type' => $settlementType,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                } else {
                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'during' => 'm2 update',
                                        'sale_user_id' => $sale_user_id,
                                        'pid' => $pid,
                                        'kw' => $kw,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                        'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                    ];

                                    $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                    $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                    if ($override && number_format($override, 2, '.', '') == number_format($clawBack, 2, '.', '')) {
                                        $userOverride = UserOverrides::create($stackData);
                                    } else {
                                        $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                        $amount = number_format($amount, 2, '.', '') - (number_format($override, 2, '.', '') - number_format($clawBack, 2, '.', ''));
                                        if ($amount) {
                                            $stackData['amount'] = $amount;
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                    }
                                }

                                if (isset($userOverride) && $userOverride->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $userData->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'override' => 0,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                }
                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    public function userPerSale($userId, $saleState, $approvedDate, $commissionHistory)
    {
        $organizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        if (! $organizationHistory) {
            return false;
        }
        $positionId = $organizationHistory->position_id;

        if ($organizationHistory->self_gen_accounts == 1 && $positionId == 3) {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
        } else {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
        }
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission >= $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                ];
            }
        } elseif ($history->commission_type == 'per kw') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
            ];
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate);
            if ($userRedLine['redline']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                ];
            }
        }

        return false;
    }

    public function userPerKw($userId, $saleState, $approvedDate, $commissionHistory)
    {
        $organizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        if (! $organizationHistory) {
            return false;
        }
        $positionId = $organizationHistory->position_id;

        if ($organizationHistory->self_gen_accounts == 1 && $positionId == 3) {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
        } else {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
        }
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per kw') {
            if ($history->commission >= $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                ];
            }
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate);
            if ($userRedLine['redline']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                ];
            }
        }

        return false;
    }

    public function userPercentage($userId, $saleState, $approvedDate, $closerRedLine)
    {
        $organizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
        if (! $organizationHistory) {
            return false;
        }
        $positionId = $organizationHistory->position_id;

        if ($organizationHistory->self_gen_accounts == 1 && $positionId == 3) {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('commission_effective_date', 'DESC')->first();
        } else {
            $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
        }
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate);
            if ($userRedLine['redline'] && $userRedLine['redline'] <= $closerRedLine) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                ];
            }
        }

        return false;
    }

    public function userRedline($userData, $saleState, $approvedDate)
    {
        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = $generalCode->redline_standard;
            }
        } else {
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

        if ($userData) {
            $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $userRedlinesData = $organizationHistory;
            } else {
                $userRedlinesData = $userData;
            }

            if ($userRedlinesData->self_gen_accounts == 1 && $userRedlinesData->position_id == 3) {
                $userRedlines = UserRedlines::where('user_id', $userData->id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
            } else {
                $userRedlines = UserRedlines::where('user_id', $userData->id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
            }

            if ($userRedlines) {
                $closer_redline = $userRedlines->redline;
                $redline_amount_type = $userRedlines->redline_amount_type;
            } else {
                $closer_redline = $userData->redline;
                $redline_amount_type = $userData->redline_amount_type;
            }
            $closerOfficeId = $userData->office_id;

            if ($redline_amount_type == 'Fixed') {
                $closer1_redline = $closer_redline;
                $closer1_redline_type = 'Fixed';
            } else {
                $userTransferHistory = UserTransferHistory::where('user_id', $userData->id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $closerOfficeId = $userTransferHistory->office_id;
                }

                $closerLocation = Locations::where('id', $closerOfficeId)->first();
                $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $closerStateRedline = $locationRedlines->redline_standard;
                } else {
                    $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                }

                $redline = $saleStandardRedline + ($closer_redline - $closerStateRedline);
                $closer1_redline = $redline;
                $closer1_redline_type = 'Shift Based on Location';
            }
        }

        return [
            'redline' => $closer1_redline,
            'redline_type' => $closer1_redline_type,
        ];
    }

    public function pestStackUserOverride($sale_user_id, $pid, $date)
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $user_data = User::where('id', $sale_user_id)->first();

        if ($user_data && $user_data->office_id && $stackSystemSetting) {
            $office_id = $user_data->office_id;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

            $saleUsers = [];
            if ($saleData->salesMasterProcess->closer1_id) {
                $saleUsers[] = $saleData->salesMasterProcess->closer1_id;
            }
            if ($saleData->salesMasterProcess->closer2_id) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
            }

            $approvedDate = $saleData->customer_signoff;
            $grossAmountValue = $saleData->gross_account_value;
            $finalCommission = $saleData->salesMasterProcess->closer1_commission + $saleData->salesMasterProcess->closer2_commission;

            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            Log::info([$totalOverride, $totalOverrideClawBack, $finalOverride]);

            $userTransferHistory = UserTransferHistory::where('user_id', $sale_user_id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }

            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $userIdArr = UserOverrideHistory::whereIn('id', $results->pluck('id'))->whereHas('user', function ($q) use ($office_id) {
                $q->where(['office_id' => $office_id, 'dismiss' => '0']);
            })->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();

            $userIds = AdditionalLocations::where('office_id', $office_id)
                ->where('user_id', '!=', $sale_user_id)
                ->where('effective_date', '<=', $approvedDate)
                ->where(function ($q) use ($approvedDate) {
                    $q->whereNull('effective_end_date')
                      ->orWhere('effective_end_date', '>=', $approvedDate);
                })
                ->pluck('user_id')->toArray();
            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $additionalUserIdArr = UserOverrideHistory::whereHas('user', function ($q) {
                $q->where('dismiss', '0');
            })->whereIn('id', $results->pluck('id'))->whereIn('user_id', $userIds)->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr, $additionalUserIdArr));

            $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
            if (! $commissionHistory) {
                return false;
            }
            $commissionType = $commissionHistory->commission_type;
            $stackData = [];
            if ($commissionType == 'per sale') {
                foreach ($userIdArr as $userId) {
                    $closerData = $this->pestUserPerSale($userId, $approvedDate, $commissionHistory);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } elseif ($commissionType == 'percent') {
                foreach ($userIdArr as $userId) {
                    $closerData = $this->pestUserPercentage($userId, $approvedDate, $commissionHistory);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            }

            $sortedArray = [];
            if (isset($stackData['per sale'])) {
                $sortedArray['per sale'] = collect($stackData['per sale'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }
            if (isset($stackData['percent'])) {
                $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }

            $i = 0;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack', 'status' => 1])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideStatus && $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userData->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                            // $payFrequency = $this->reconciliationPeriod($date);
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '4', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $value = $stack['value'];
                                $valueType = $stack['value_type'];

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $stack['value']) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = UserOverrides::where(['type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('amount');
                                        $lowerStackClawBackPay = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                                        $lowerStackPay = $lowerStackPay - $lowerStackClawBackPay;
                                    }
                                }

                                $amount = 0;
                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $margin_percentage = $companyMargin->company_margin;
                                    $x = ((100 - $margin_percentage) / 100);
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, 100, $grossAmountValue, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                } else {
                                    if ($key == 'per sale') {
                                        $amount = (($value) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue)) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, 100, $grossAmountValue, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                }

                                $stackData = [
                                    'user_id' => $userData->id,
                                    'type' => 'Stack',
                                    'sale_user_id' => $sale_user_id,
                                    'pid' => $pid,
                                    'kw' => $grossAmountValue,
                                    'amount' => $amount,
                                    'overrides_amount' => $userData->office_stack_overrides_amount,
                                    'overrides_type' => 'per sale',
                                    'calculated_redline' => $value,
                                    'calculated_redline_type' => $valueType,
                                    'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                    'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $userData->stop_payroll,
                                    'office_id' => $user_data->office_id,
                                ];

                                $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                                $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                if ($override && number_format($override, 2, '.', '') == number_format($clawBack, 2, '.', '')) {
                                    $userOverrode = UserOverrides::create($stackData);
                                } else {
                                    if (! UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Stack', 'status' => '3', 'is_displayed' => '1'])->first()) {
                                        $userOverrode = UserOverrides::create($stackData);
                                    }
                                }

                                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $userData->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'override' => 0,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                }
                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $stack['value'];
                            }
                        }
                    }
                }
            }
        }
    }

    public function pestAddersStackOverride($sale_user_id, $pid, $date)
    {
        $user_data = User::where('id', $sale_user_id)->first();
        if ($user_data && $user_data->office_id) {
            $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
            if (count($stackOverrides) != 0) {
                $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
                $saleUsers = [];
                if ($saleData->salesMasterProcess->closer1_id) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer1_id;
                }
                if ($saleData->salesMasterProcess->closer2_id) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
                }

                $approvedDate = $saleData->customer_signoff;
                $grossAmountValue = $saleData->gross_account_value;
                $finalCommission = $saleData->salesMasterProcess->closer1_commission + $saleData->salesMasterProcess->closer2_commission;

                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;
                Log::info(['update', $totalOverride, $totalOverrideClawBack, $finalOverride]);

                $commissionHistory = UserCommissionHistory::where('user_id', $sale_user_id)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                if (! $commissionHistory) {
                    return false;
                }
                $userIdArr = User::whereIn('id', $stackOverrides)->where('dismiss', 0)->pluck('id');
                $commissionType = $commissionHistory->commission_type;
                $stackData = [];
                if ($commissionType == 'per sale') {
                    foreach ($userIdArr as $userId) {
                        $closerData = $this->pestUserPerSale($userId, $approvedDate, $commissionHistory);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } elseif ($commissionType == 'percent') {
                    foreach ($userIdArr as $userId) {
                        $closerData = $this->pestUserPercentage($userId, $approvedDate, $commissionHistory);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                }

                $sortedArray = [];
                if (isset($stackData['per sale'])) {
                    $sortedArray['per sale'] = collect($stackData['per sale'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }
                if (isset($stackData['percent'])) {
                    $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }

                $i = 0;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack', 'status' => 1])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $stack['user_id'], 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($overrideStatus && $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            } else {
                                $positionId = $userData->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                            if ($positionReconciliation) {
                                $settlementType = 'reconciliation';
                                // $payFrequency = $this->reconciliationPeriod($date);
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $value = $stack['value'];
                                $valueType = $stack['value_type'];

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $stack['value']) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = UserOverrides::where(['type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('amount');
                                        $lowerStackClawBackPay = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                                        $lowerStackPay = $lowerStackPay - $lowerStackClawBackPay;
                                    }
                                }

                                $amount = 0;
                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $margin_percentage = $companyMargin->company_margin;
                                    $x = ((100 - $margin_percentage) / 100);
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, 100, $grossAmountValue, $x, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                } else {
                                    if ($key == 'per sale') {
                                        $amount = (($value) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    } elseif ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue)) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        Log::info([$key, $value, 100, $grossAmountValue, $finalCommission, $finalOverride, $lowerStackPay, $stackShare / 100, $amount]);
                                    }
                                }

                                if ($override = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Stack', 'during' => 'm2', 'status' => '1', 'is_displayed' => '1'])->first()) {
                                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $key, 'type' => 'Stack', 'status' => 1])->first();
                                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $key, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                                    if ($overrideStatus && $overrideStatus->status == 0) {
                                        $override->update([
                                            'during' => 'm2',
                                            'sale_user_id' => $sale_user_id,
                                            'kw' => $grossAmountValue,
                                            'amount' => $amount,
                                            'overrides_amount' => $stackShare,
                                            'overrides_type' => 'per sale',
                                            'calculated_redline' => $value,
                                            'calculated_redline_type' => $valueType,
                                            'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                            'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                            'overrides_settlement_type' => $settlementType,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                } else {
                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'during' => 'm2 update',
                                        'sale_user_id' => $sale_user_id,
                                        'pid' => $pid,
                                        'kw' => $grossAmountValue,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : null,
                                        'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                    ];

                                    $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                    $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                    if ($override && number_format($override, 2, '.', '') == number_format($clawBack, 2, '.', '')) {
                                        $userOverride = UserOverrides::create($stackData);
                                    } else {
                                        $override = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $clawBack = ClawbackSettlement::where(['user_id' => $userData->id, 'sale_user_id' => $sale_user_id, 'pid' => $pid, 'type' => 'overrides', 'adders_type' => 'Stack', 'is_displayed' => '1'])->sum('clawback_amount');
                                        $amount = number_format($amount, 2, '.', '') - (number_format($override, 2, '.', '') - number_format($clawBack, 2, '.', ''));
                                        if ($amount) {
                                            $stackData['amount'] = $amount;
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                    }
                                }

                                if (isset($userOverride) && $userOverride->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $userData->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'override' => 0,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $userData->stop_payroll,
                                        ]);
                                    }
                                }
                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    public function pestUserPerSale($userId, $approvedDate, $commissionHistory)
    {
        $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission >= $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                ];
            }
        } elseif ($history->commission_type == 'percent') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
            ];
        }

        return false;
    }

    public function pestUserPercentage($userId, $approvedDate, $closerCommission)
    {
        $history = UserCommissionHistory::where('user_id', $userId)->where('commission_effective_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            if ($history->commission >= $closerCommission->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                ];
            }
        }

        return false;
    }
}
