<?php

namespace App\Core\Traits\SaleTraits;

use App\Models\User;
use App\Models\Positions;
use App\Models\SalesMaster;
use App\Models\UserOverrides;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\OverrideStatus;
use App\Models\UserCommission;
use App\Models\PositionOverride;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\AdditionalLocations;
use App\Models\UserOverrideHistory;
use App\Models\UserTransferHistory;
use App\Models\overrideSystemSetting;
use App\Models\UserCommissionHistory;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\PositionReconciliations;
use App\Models\UserOrganizationHistory;
use App\Core\Traits\ReconciliationPeriodTrait;
use App\Core\Traits\OverrideArchiveCheckTrait;
use Illuminate\Support\Facades\Log;

trait OverrideStackTrait
{
    use PayFrequencyTrait, ReconciliationPeriodTrait, EditSaleTrait, OverrideArchiveCheckTrait;

    public function stackUserOverride($saleUserId, $pid, $kw, $date, $forExternal = 0)
    {
        Log::info("stackUserOverride called", ['saleUserId' => $saleUserId, 'pid' => $pid]);
        $userData = User::where('id', $saleUserId)->first();
        if (!$userData) {
            return false;
        }
        $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        $approvedDate = $saleData->customer_signoff;
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $userData = User::where(['id' => $saleUserId])->first();
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if ($check['status'] && $userData) {
            $officeId = $userData->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId && $stackSystemSetting) {
                $netEpc = $saleData->net_epc;
                $productId = $saleData->product_id;
                $closerId = isset($saleData->salesMasterProcess->closer1_id) ? $saleData->salesMasterProcess->closer1_id : NULL;
                $setterId = isset($saleData->salesMasterProcess->setter1_id) ? $saleData->salesMasterProcess->setter1_id : NULL;
                if (config("app.domain_name") == 'flex') {
                    $saleState = $saleData->customer_state;
                } else {
                    $saleState = $saleData->location_code;
                }
                $saleUsers = [$closerId, $setterId];
                if (isset($saleData->salesMasterProcess->closer2_id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
                }
                if (isset($saleData->salesMasterProcess->setter2_id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->setter2_id;
                }

                $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
                $organization = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $subPosition = $organization?->sub_position_id;
                $position = Positions::where('id', $subPosition)->first();
                if (!$position || $position->is_selfgen == 3) {
                    return false;
                }
                $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$commissionHistory) {
                    return false;
                }

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])
                    ->whereNotIn('user_id', [$saleUserId])
                    ->where('effective_date', '<=', $approvedDate)
                    ->where(function ($q) use ($approvedDate) {
                        $q->whereNull('effective_end_date')
                          ->orWhere('effective_end_date', '>=', $approvedDate);
                    })
                    ->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
                foreach ($allUsers as $allUser) {
                    // Skip users whose Stack override was previously deleted (archived)
                    if (!$this->shouldCreateOverride($allUser, $pid, 'Stack', $saleUserId, false)) {
                        Log::info("Skipping eligible user - override archived", ['user_id' => $allUser, 'pid' => $pid, 'sale_user_id' => $saleUserId]);
                        continue;
                    }
                    
                    $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                    $userProductId = $userOrganizationData['product']->id;
                    $userProductCode = $userOrganizationData['product']->product_id;

                    $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                        $eligibleUsers[] = [
                            'user_id' => $allUser,
                            'product_id' => $userProductId,
                            'product_code' => $userProductCode
                        ];
                    }
                }

                $stackData = [];
                $commissionType = $commissionHistory->commission_type;
                if ($commissionType == 'per sale') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->userPerSale($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'per kw') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->userPerKw($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'percent') {
                    $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                    if ($closerRedline['redline']) {
                        foreach ($eligibleUsers as $eligibleUser) {
                            $closerData = $this->userPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
                $x = 1;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $marginPercentage = $companyMargin->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }

                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalCommissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('clawback_amount');
                $finalCommission = $totalCommission - $totalCommissionClawBack;
                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        $productId = $stack['product_id'];
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $check = checkSalesReps($userData->id, $approvedDate, '');
                            if (!$check['status']) {
                                continue;
                            }
                            $positionId = $userData->sub_position_id;
                            $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequency = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->whereNull('effective_date')->first();
                            }
                            if ($positionOverride && $positionOverride->status == 1) {
                                $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'per kw') {
                                        $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        $amount = ((($netEpc - $value) * $x) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    }

                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'product_id' => $productId,
                                        'product_code' => $stack['product_code'],
                                        'type' => 'Stack',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $kw,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                        'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                        'worker_type' => ($forExternal == 1)?'external':'internal',
                                        'user_worker_type' => $userData->worker_type,
                                        'pay_frequency' => @$payFrequency->pay_frequency,
                                    ];

                                    $stackOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                    if ($stackOverride) {
                                        $sum = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($sum, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($stackOverride->overrides_settlement_type == 'during_m2') {
                                                if ($stackOverride->status == '1') {
                                                    $stackOverride->update($stackData);
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            } else if ($stackOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($stackOverride->recon_status == '1' || $stackOverride->recon_status == '2') {
                                                    if ($stackOverride->recon_status == '1' && number_format($stackOverride->amount, 3, '.', '') == number_format($dueStack, 3, '.', '')) {
                                                        $stackOverride->delete();
                                                    } else {
                                                        unset($stackData['overrides_settlement_type']);
                                                        unset($stackData['pay_period_from']);
                                                        unset($stackData['pay_period_to']);
                                                        unset($stackData['status']);
                                                        $stackOverride->update($stackData);
                                                    }
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        // Apply removal status from pivot table before creating Stack override
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            UserOverrides::create($stackData);
                                        }
                                    }

                                    if (isset($stackData['overrides_settlement_type']) && $stackData['overrides_settlement_type'] == 'during_m2') {
                                        // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
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
    }

    public function addersStackOverride($saleUserId, $pid, $kw, $date, $during = NULL, $forExternal = 0)
    {
        $userData = User::where(['id' => $saleUserId])->first();
        if (!$userData) {
            return false;
        }
        $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
        if (sizeOf($stackOverrides) != 0) {
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $productId = $saleData->product_id;
            $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
            $setterId = isset($saleData->salesMasterProcess->setter1Detail->id) ? $saleData->salesMasterProcess->setter1Detail->id : NULL;
            if (config("app.domain_name") == 'flex') {
                $saleState = $saleData->customer_state;
            } else {
                $saleState = $saleData->location_code;
            }
            $saleUsers = [$closerId, $setterId];
            if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
            }
            if (isset($saleData->salesMasterProcess->setter2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->setter2Detail->id;
            }
            $approvedDate = $saleData->customer_signoff;
            $netEpc = $saleData->net_epc;

            $check = checkSalesReps($saleUserId, $approvedDate, '');
            if (!$check['status']) {
                return false;
            }
            $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
            $organization = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $subPosition = $organization?->sub_position_id;
            $position = Positions::where('id', $subPosition)->first();
            if (!$position || $position->is_selfgen == 3) {
                return false;
            }
            $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$commissionHistory) {
                return false;
            }

            $eligibleUsers = [];
            $allUsers = User::whereIn('id', $stackOverrides)->pluck('id');
            foreach ($allUsers as $allUser) {
                $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                $userProductId = $userOrganizationData['product']->id;
                $userProductCode = $userOrganizationData['product']->product_id;

                $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                    $eligibleUsers[] = [
                        'user_id' => $allUser,
                        'product_id' => $userProductId,
                        'product_code' => $userProductCode
                    ];
                }
            }

            $stackData = [];
            $commissionType = $commissionHistory->commission_type;
            if ($commissionType == 'per sale') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->userPerSale($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'per kw') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->userPerKw($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'percent') {
                $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                if (!$closerRedline['redline_missing']) {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->userPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
            $x = 1;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
            }
            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
            $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
            $totalCommissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('clawback_amount');
            $finalCommission = $totalCommission - $totalCommissionClawBack;
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    $productId = $stack['product_id'];
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$overrideStatus || $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $check = checkSalesReps($userData->id, $approvedDate, '');
                        if (!$check['status']) {
                            continue;
                        }
                        $positionId = $userData->sub_position_id;
                        $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$positionReconciliation) {
                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                        }
                        if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                            $settlementType = 'reconciliation';
                            $payFrequency = NULL;
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                            if ($key == 'per sale') {
                                $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            } else if ($key == 'per kw') {
                                $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            } else if ($key == 'percent') {
                                $amount = ((($netEpc - $value) * $x) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            }
                            $stackData = [
                                'user_id' => $userData->id,
                                'product_id' => $productId,
                                'product_code' => $stack['product_code'],
                                'type' => 'Stack',
                                'during' => 'm2 update',
                                'sale_user_id' => $saleUserId,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_stack_overrides_amount,
                                'overrides_type' => 'per sale',
                                'calculated_redline' => $value,
                                'calculated_redline_type' => $valueType,
                                'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                'overrides_settlement_type' => $settlementType,
                                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                'is_stop_payroll' => $userData->stop_payroll,
                                'worker_type' => ($forExternal == 1)?'external':'internal',
                                'user_worker_type' => $userData->worker_type,
                                'pay_frequency' => @$payFrequency->pay_frequency,
                            ];

                            $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                            $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                            if ($dueStack) {
                                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                if ($userOverride->overrides_settlement_type == 'during_m2') {
                                    if ($userOverride->status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        $userOverride->delete();
                                        $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($during) {
                                                $stackData['during'] = $during;
                                            }
                                            // Check if Stack override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                // Apply removal status from pivot table before creating Stack override
                                                $userOverride = UserOverrides::create($stackData);
                                                if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                                }
                                            }
                                        }
                                        // $userOverride->during = $userOverride->during;
                                        // $userOverride->amount = $dueStack;
                                        // $userOverride->update();
                                        // if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                        //     subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        // }
                                    }
                                } else if ($userOverride->overrides_settlement_type == 'reconciliation') {
                                    if ($userOverride->recon_status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            $userOverride = UserOverrides::create($stackData);
                                        }

                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        // unset($stackData['overrides_settlement_type']);
                                        // unset($stackData['pay_period_from']);
                                        // unset($stackData['pay_period_to']);
                                        // unset($stackData['status']);
                                        $userOverride->during = $userOverride->during;
                                        $userOverride->amount = $userOverride->amount + $dueStack;
                                        $userOverride->save();
                                    }
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

    public function stackUserTurfOverride($saleUserId, $pid, $kw, $date, $forExternal = 0)
    {
        $userData = User::where('id', $saleUserId)->first();
        if (!$userData) {
            return false;
        }
        $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        $saleState = $saleData->location_code;
        $approvedDate = $saleData->customer_signoff;
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $userData = User::where(['id' => $saleUserId])->first();
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if ($check['status'] && $userData) {
            $officeId = $userData->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId && $stackSystemSetting) {
                $netEpc = $saleData->net_epc;
                $productId = $saleData->product_id;
                $grossAmountValue = $saleData->gross_account_value;
                $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
                $setterId = isset($saleData->salesMasterProcess->setter1Detail->id) ? $saleData->salesMasterProcess->setter1Detail->id : NULL;
                $saleUsers = [$closerId, $setterId];
                if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
                }
                if (isset($saleData->salesMasterProcess->setter2Detail->id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->setter2Detail->id;
                }

                $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
                $organization = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $subPosition = $organization?->sub_position_id;
                $position = Positions::where('id', $subPosition)->first();
                if (!$position || $position->is_selfgen == 3) {
                    return false;
                }
                $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$commissionHistory) {
                    return false;
                }

                $finalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])
                    ->whereNotIn('user_id', [$saleUserId])
                    ->where('effective_date', '<=', $approvedDate)
                    ->where(function ($q) use ($approvedDate) {
                        $q->whereNull('effective_end_date')
                          ->orWhere('effective_end_date', '>=', $approvedDate);
                    })
                    ->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
                foreach ($allUsers as $allUser) {
                    // Skip users whose Stack override was previously deleted (archived)
                    if (!$this->shouldCreateOverride($allUser, $pid, 'Stack', $saleUserId, false)) {
                        Log::info("Skipping eligible user - override archived", ['user_id' => $allUser, 'pid' => $pid, 'sale_user_id' => $saleUserId]);
                        continue;
                    }
                    
                    $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                    $userProductId = $userOrganizationData['product']->id;
                    $userProductCode = $userOrganizationData['product']->product_id;

                    $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                        $eligibleUsers[] = [
                            'user_id' => $allUser,
                            'product_id' => $userProductId,
                            'product_code' => $userProductCode
                        ];
                    }
                }

                $stackData = [];
                $commissionType = $commissionHistory->commission_type;
                if ($commissionType == 'per sale') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->turfUserPerSale($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'per kw') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->turfUserPerKw($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'percent') {
                    $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                    if ($closerRedline['redline']) {
                        foreach ($eligibleUsers as $eligibleUser) {
                            //  $closerData = $this->turfUserPercentage($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
                            $closerData = $this->turfUserPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
                    $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }

                $i = 0;
                $x = 1;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $marginPercentage = $companyMargin->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        $productId = $stack['product_id'];
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $check = checkSalesReps($userData->id, $approvedDate, '');
                            if (!$check['status']) {
                                continue;
                            }
                            $positionId = $userData->sub_position_id;
                            $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequency = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->whereNull('effective_date')->first();
                            }
                            if ($positionOverride && $positionOverride->status == 1) {
                                $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                                }

                                if ($userData->office_stack_overrides_amount) {
                                    $stackShare = $userData->office_stack_overrides_amount;
                                    $value = $stack['value'] ?? 0;
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
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'per kw') {
                                        $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        if (config('app.domain_name') == 'frdmturf') {
                                            $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                        } else {
                                           // dd($stack);
                                            //dd("value: " . $value, "grossAmountValue: " . $grossAmountValue, "netEpc: " . $netEpc, "x: " . $x, "kw: " . $kw, "finalCommission: " . $finalCommission, "finalOverride: " . $finalOverride, "lowerStackPay: " . $lowerStackPay, "stackShare: " . $stackShare);
                                            $amount = (($grossAmountValue + (($netEpc - $value) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        }
                                    }

                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'product_id' => $productId,
                                        'product_code' => $stack['product_code'],
                                        'type' => 'Stack',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $kw,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => $payFrequency?->pay_period_from ?? null,
                                        'pay_period_to' => $payFrequency?->pay_period_to ?? null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                        'user_worker_type' => $userData->worker_type,
                                        'pay_frequency' => $payFrequency?->pay_frequency ?? null,
                                    ];

                                    $stackOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                    if ($stackOverride) {
                                        $sum = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($sum, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($stackOverride->overrides_settlement_type == 'during_m2') {
                                                if ($stackOverride->status == '1') {
                                                    $stackOverride->update($stackData);
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        // Apply removal status from pivot table before creating Stack override
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            } else if ($stackOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($stackOverride->recon_status == '1' || $stackOverride->recon_status == '2') {
                                                    if ($stackOverride->recon_status == '1' && number_format($stackOverride->amount, 3, '.', '') == number_format($dueStack, 3, '.', '')) {
                                                        $stackOverride->delete();
                                                    } else {
                                                        unset($stackData['overrides_settlement_type']);
                                                        unset($stackData['pay_period_from']);
                                                        unset($stackData['pay_period_to']);
                                                        unset($stackData['status']);
                                                        $stackOverride->update($stackData);
                                                    }
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        // Apply removal status from pivot table before creating Stack override
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            UserOverrides::create($stackData);
                                        }
                                    }

                                    if (isset($stackData['overrides_settlement_type']) && $stackData['overrides_settlement_type'] == 'during_m2') {
                                        // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
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
    }

    public function addersStackTurfOverride($saleUserId, $pid, $kw, $date, $during = NULL, $forExternal = 0)
    {
        $userData = User::where(['id' => $saleUserId])->first();
        if (!$userData) {
            return false;
        }
        $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
        if (sizeOf($stackOverrides) != 0) {
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $saleState = $saleData?->location_code ?? NULL;
            $productId = $saleData?->product_id ?? 0;
            $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
            $setterId = isset($saleData->salesMasterProcess->setter1Detail->id) ? $saleData->salesMasterProcess->setter1Detail->id : NULL;
            $saleUsers = [$closerId, $setterId];
            if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
            }
            if (isset($saleData->salesMasterProcess->setter2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->setter2Detail->id;
            }
            $approvedDate = $saleData->customer_signoff;
            $netEpc = $saleData->net_epc;
            $grossAmountValue = $saleData->gross_account_value;
            $check = checkSalesReps($saleUserId, $approvedDate, '');
            if (!$check['status']) {
                return false;
            }

            $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
            $organization = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $subPosition = $organization?->sub_position_id;
            $position = Positions::where('id', $subPosition)->first();
            if (!$position || $position->is_selfgen == 3) {
                return false;
            }
            $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$commissionHistory) {
                return false;
            }

            $eligibleUsers = [];
            $allUsers = User::whereIn('id', $stackOverrides)->pluck('id');
            foreach ($allUsers as $allUser) {
                $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                $userProductId = $userOrganizationData['product']->id;
                $userProductCode = $userOrganizationData['product']->product_id;

                $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                    $eligibleUsers[] = [
                        'user_id' => $allUser,
                        'product_id' => $userProductId,
                        'product_code' => $userProductCode
                    ];
                }
            }

            $stackData = [];
            $commissionType = $commissionHistory->commission_type;
            if ($commissionType == 'per sale') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->turfUserPerSale($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'per kw') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->turfUserPerKw($eligibleUser['user_id'], $saleState, $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'percent') {
                $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                if (!$closerRedline['redline_missing']) {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->turfUserPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
                $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }

            $i = 0;
            $x = 1;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
            }
            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
            $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
            $totalCommissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('clawback_amount');
            $finalCommission = $totalCommission - $totalCommissionClawBack;
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    $productId = $stack['product_id'];
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$overrideStatus || $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $check = checkSalesReps($userData->id, $approvedDate, '');
                        if (!$check['status']) {
                            continue;
                        }
                        $positionId = $userData->sub_position_id;
                        $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$positionReconciliation) {
                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                        }
                        if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                            $settlementType = 'reconciliation';
                            $payFrequency = NULL;
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                        }

                        if ($userData->office_stack_overrides_amount) {
                            $stackShare = $userData->office_stack_overrides_amount;
                            $value = $stack['value'] ?? 0;
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
                            if ($key == 'per sale') {
                                $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            } else if ($key == 'per kw') {
                                $amount = ((($value * $kw) * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            } else if ($key == 'percent') {
                                if (config('app.domain_name') == 'frdmturf') {
                                    $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                } else {
                                    $amount = (($grossAmountValue + (($netEpc - $value) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                }
                            }

                            $stackData = [
                                'user_id' => $userData->id,
                                'product_id' => $productId,
                                'product_code' => $stack['product_code'],
                                'type' => 'Stack',
                                'during' => 'm2 update',
                                'sale_user_id' => $saleUserId,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_stack_overrides_amount,
                                'overrides_type' => 'per sale',
                                'calculated_redline' => $value,
                                'calculated_redline_type' => $valueType,
                                'pay_period_from' => $payFrequency?->pay_period_from ?? null,
                                'pay_period_to' => $payFrequency?->pay_period_to ?? null,
                                'overrides_settlement_type' => $settlementType,
                                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                'is_stop_payroll' => $userData->stop_payroll,
                                'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                'user_worker_type' => $userData->worker_type,
                                'pay_frequency' => $payFrequency?->pay_frequency ?? null,
                            ];

                            $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                            $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                            if ($dueStack) {
                                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                if ($userOverride->overrides_settlement_type == 'during_m2') {
                                    if ($userOverride->status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        $userOverride->delete();
                                        $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($during) {
                                                $stackData['during'] = $during;
                                            }
                                            // Check if Stack override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                // Apply removal status from pivot table before creating Stack override
                                                $userOverride = UserOverrides::create($stackData);
                                                if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                                }
                                            }
                                        }
                                        // $userOverride->during = $userOverride->during;
                                        // $userOverride->amount = $dueStack;
                                        // $userOverride->update();
                                        // if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                        //     subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        // }
                                    }
                                } else if ($userOverride->overrides_settlement_type == 'reconciliation') {
                                    if ($userOverride->recon_status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            // Apply removal status from pivot table before creating Stack override
                                            $userOverride = UserOverrides::create($stackData);
                                        }

                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        // unset($stackData['overrides_settlement_type']);
                                        // unset($stackData['pay_period_from']);
                                        // unset($stackData['pay_period_to']);
                                        // unset($stackData['status']);
                                        $userOverride->during = $userOverride->during;
                                        $userOverride->amount = $userOverride->amount + $dueStack;
                                        $userOverride->save();
                                    }
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

    public function pestStackUserOverride($saleUserId, $pid, $date, $forExternal = 0)
    {
        $userData = User::where('id', $saleUserId)->first();
        if (!$userData) {
            return false;
        }
        $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        $approvedDate = $saleData->customer_signoff;
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $userData = User::where(['id' => $saleUserId])->first();
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if ($check['status'] && $userData) {
            $officeId = $userData->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId && $stackSystemSetting) {
                $productId = $saleData->product_id;
                $grossAmountValue = $saleData->gross_account_value;
                $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
                $saleUsers = [$closerId];
                if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
                }

                $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
                $organization = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $subPosition = $organization?->sub_position_id;
                $position = Positions::where('id', $subPosition)->first();
                if (!$position || $position->is_selfgen == 3) {
                    return false;
                }
                $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$commissionHistory) {
                    return false;
                }

                $finalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])
                    ->whereNotIn('user_id', [$saleUserId])
                    ->where('effective_date', '<=', $approvedDate)
                    ->where(function ($q) use ($approvedDate) {
                        $q->whereNull('effective_end_date')
                          ->orWhere('effective_end_date', '>=', $approvedDate);
                    })
                    ->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)->pluck('id');
                foreach ($allUsers as $allUser) {
                    // Skip users whose Stack override was previously deleted (archived)
                    if (!$this->shouldCreateOverride($allUser, $pid, 'Stack', $saleUserId, false)) {
                        Log::info("Skipping eligible user - override archived", ['user_id' => $allUser, 'pid' => $pid, 'sale_user_id' => $saleUserId]);
                        continue;
                    }
                    
                    $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                    $userProductId = $userOrganizationData['product']->id;
                    $userProductCode = $userOrganizationData['product']->product_id;

                    $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                        $eligibleUsers[] = [
                            'user_id' => $allUser,
                            'product_id' => $userProductId,
                            'product_code' => $userProductCode
                        ];
                    }
                }

                $stackData = [];
                $commissionType = $commissionHistory->commission_type;
                if ($commissionType == 'per sale') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->pestUserPerSale($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'percent') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->pestUserPercentage($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
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
                $x = 1;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $marginPercentage = $companyMargin->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        $productId = $stack['product_id'];
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $check = checkSalesReps($userData->id, $approvedDate, '');
                            if (!$check['status']) {
                                continue;
                            }
                            $positionId = $userData->sub_position_id;
                            $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequency = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->whereNull('effective_date')->first();
                            }
                            if ($positionOverride && $positionOverride->status == 1) {
                                $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                    }

                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'product_id' => $productId,
                                        'product_code' => $stack['product_code'],
                                        'type' => 'Stack',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $grossAmountValue,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                        'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                        'user_worker_type' => $userData->worker_type,
                                        'pay_frequency' => @$payFrequency->pay_frequency,
                                    ];

                                    $stackOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                    if ($stackOverride) {
                                        $sum = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($sum, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($stackOverride->overrides_settlement_type == 'during_m2') {
                                                if ($stackOverride->status == '1') {
                                                    $stackOverride->update($stackData);
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        // Apply removal status from pivot table before creating Stack override
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            } else if ($stackOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($stackOverride->recon_status == '1' || $stackOverride->recon_status == '2') {
                                                    if ($stackOverride->recon_status == '1' && number_format($stackOverride->amount, 3, '.', '') == number_format($dueStack, 3, '.', '')) {
                                                        $stackOverride->delete();
                                                    } else {
                                                        unset($stackData['overrides_settlement_type']);
                                                        unset($stackData['pay_period_from']);
                                                        unset($stackData['pay_period_to']);
                                                        unset($stackData['status']);
                                                        $stackOverride->update($stackData);
                                                    }
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            UserOverrides::create($stackData);
                                        }
                                    }

                                    if (isset($stackData['overrides_settlement_type']) && $stackData['overrides_settlement_type'] == 'during_m2') {
                                        // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
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
    }

    public function pestAddersStackOverride($saleUserId, $pid, $date, $during = NULL, $forExternal = 0)
    {
        $userData = User::where(['id' => $saleUserId])->first();
        if (!$userData) {
            return false;
        }
        $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
        if (sizeOf($stackOverrides) != 0) {
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $productId = $saleData->product_id;
            $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
            $saleUsers = [$closerId];
            if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
            }
            $approvedDate = $saleData->customer_signoff;
            $grossAmountValue = $saleData->gross_account_value;

            $check = checkSalesReps($saleUserId, $approvedDate, '');
            if (!$check['status']) {
                return false;
            }
            $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
            $organization = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $subPosition = $organization?->sub_position_id;
            $position = Positions::where('id', $subPosition)->first();
            if (!$position || $position->is_selfgen == 3) {
                return false;
            }
            $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$commissionHistory) {
                return false;
            }

            $eligibleUsers = [];
            $allUsers = User::whereIn('id', $stackOverrides)->pluck('id');
            foreach ($allUsers as $allUser) {
                $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                $userProductId = $userOrganizationData['product']->id;
                $userProductCode = $userOrganizationData['product']->product_id;

                $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                    $eligibleUsers[] = [
                        'user_id' => $allUser,
                        'product_id' => $userProductId,
                        'product_code' => $userProductCode
                    ];
                }
            }

            $stackData = [];
            $commissionType = $commissionHistory->commission_type;
            if ($commissionType == 'per sale') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->pestUserPerSale($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'percent') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->pestUserPercentage($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
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
            $x = 1;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
            }
            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
            $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
            $totalCommissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('clawback_amount');
            $finalCommission = $totalCommission - $totalCommissionClawBack;
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    $productId = $stack['product_id'];
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$overrideStatus || $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $check = checkSalesReps($userData->id, $approvedDate, '');
                        if (!$check['status']) {
                            continue;
                        }
                        $positionId = $userData->sub_position_id;
                        $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$positionReconciliation) {
                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                        }
                        if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                            $settlementType = 'reconciliation';
                            $payFrequency = NULL;
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                            if ($key == 'per sale') {
                                $amount = (($value * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                            } else if ($key == 'percent') {
                                $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                            }

                            $stackData = [
                                'user_id' => $userData->id,
                                'product_id' => $productId,
                                'product_code' => $stack['product_code'],
                                'type' => 'Stack',
                                'during' => 'm2 update',
                                'sale_user_id' => $saleUserId,
                                'pid' => $pid,
                                'kw' => $grossAmountValue,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_stack_overrides_amount,
                                'overrides_type' => 'per sale',
                                'calculated_redline' => $value,
                                'calculated_redline_type' => $valueType,
                                'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                'overrides_settlement_type' => $settlementType,
                                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                'is_stop_payroll' => $userData->stop_payroll,
                                'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                'user_worker_type' => $userData->worker_type,
                                'pay_frequency' => @$payFrequency->pay_frequency,
                            ];

                            $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                            $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                            if ($dueStack) {
                                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                if ($userOverride->overrides_settlement_type == 'during_m2') {
                                    if ($userOverride->status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        $userOverride->delete();
                                        $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($during) {
                                                $stackData['during'] = $during;
                                            }
                                            // Check if Stack override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                $userOverride = UserOverrides::create($stackData);
                                                if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                                }
                                            }
                                        }
                                        // $userOverride->during = $userOverride->during;
                                        // $userOverride->amount = $dueStack;
                                        // $userOverride->update();
                                        // if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                        //     subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        // }
                                    }
                                } else if ($userOverride->overrides_settlement_type == 'reconciliation') {
                                    if ($userOverride->recon_status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            $userOverride = UserOverrides::create($stackData);
                                        }

                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        // unset($stackData['overrides_settlement_type']);
                                        // unset($stackData['pay_period_from']);
                                        // unset($stackData['pay_period_to']);
                                        // unset($stackData['status']);
                                        $userOverride->during = $userOverride->during;
                                        $userOverride->amount = $userOverride->amount + $dueStack;
                                        $userOverride->save();
                                    }
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

    public function stackUserMortgageOverride($saleUserId, $pid, $kw, $date, $forExternal = 0)
    {
        $userData = User::where('id', $saleUserId)->first();
        if (!$userData) {
            return false;
        }
        $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        if (config("app.domain_name") == 'flex') {
            $saleState = $saleData->customer_state;
        } else {
            $saleState = $saleData->location_code;
        }
        $approvedDate = $saleData->customer_signoff;
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $userData = User::where(['id' => $saleUserId])->first();
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if ($check['status'] && $userData) {
            $officeId = $userData->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId && $stackSystemSetting) {
                $netEpc = $saleData->net_epc;
                $productId = $saleData->product_id;
                $grossAmountValue = $saleData->gross_account_value;
                $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
                $setterId = isset($saleData->salesMasterProcess->setter1Detail->id) ? $saleData->salesMasterProcess->setter1Detail->id : NULL;
                $saleUsers = [$closerId, $setterId];
                if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
                }
                if (isset($saleData->salesMasterProcess->setter2Detail->id)) {
                    $saleUsers[] = $saleData->salesMasterProcess->setter2Detail->id;
                }

                $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
                $organization = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $subPosition = $organization?->sub_position_id;
                $position = Positions::where('id', $subPosition)->first();
                if (!$position || $position->is_selfgen == 3) {
                    return false;
                }
                $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$commissionHistory) {
                    return false;
                }

                $finalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
                $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
                $finalOverride = $totalOverride - $totalOverrideClawBack;

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])
                    ->whereNotIn('user_id', [$saleUserId])
                    ->where('effective_date', '<=', $approvedDate)
                    ->where(function ($q) use ($approvedDate) {
                        $q->whereNull('effective_end_date')
                          ->orWhere('effective_end_date', '>=', $approvedDate);
                    })
                    ->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
                foreach ($allUsers as $allUser) {
                    // Skip users whose Stack override was previously deleted (archived)
                    if (!$this->shouldCreateOverride($allUser, $pid, 'Stack', $saleUserId, false)) {
                        Log::info("Skipping eligible user - override archived", ['user_id' => $allUser, 'pid' => $pid, 'sale_user_id' => $saleUserId]);
                        continue;
                    }
                    
                    $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                    $userProductId = $userOrganizationData['product']->id;
                    $userProductCode = $userOrganizationData['product']->product_id;

                    $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                        $eligibleUsers[] = [
                            'user_id' => $allUser,
                            'product_id' => $userProductId,
                            'product_code' => $userProductCode
                        ];
                    }
                }

                $stackData = [];
                $commissionType = $commissionHistory->commission_type;
                if ($commissionType == 'per sale') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->mortgageUserPerSale($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'percent') {
                    $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                    if ($closerRedline['redline']) {
                        foreach ($eligibleUsers as $eligibleUser) {
                            $closerData = $this->mortgageUserPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
                if (isset($stackData['percent'])) {
                    $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                        return $item['value'];
                    })->toArray();
                }

                $i = 0;
                $x = 1;
                $userIds = [];
                $previousValue = 0;
                $lowerStackPay = 0;
                $companyMargin = CompanyProfile::first();
                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $marginPercentage = $companyMargin->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }
                foreach ($sortedArray as $key => $stacks) {
                    foreach ($stacks as $stack) {
                        $productId = $stack['product_id'];
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $userData = User::where(['id' => $stack['user_id']])->first();
                            $check = checkSalesReps($userData->id, $approvedDate, '');
                            if (!$check['status']) {
                                continue;
                            }
                            $positionId = $userData->sub_position_id;
                            $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequency = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $productId, 'override_id' => '4'])->whereNull('effective_date')->first();
                            }
                            if ($positionOverride && $positionOverride->status == 1) {
                                $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        //$amount = (($grossAmountValue + (($netEpc - 0) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        $redline = $value ? $value / 100 : 0; 
                                        $amount = (((($netEpc - $redline) * $x) * $kw) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    }

                                    $stackData = [
                                        'user_id' => $userData->id,
                                        'product_id' => $productId,
                                        'product_code' => $stack['product_code'],
                                        'type' => 'Stack',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $kw,
                                        'amount' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                        'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                        'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                        'is_stop_payroll' => $userData->stop_payroll,
                                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                        'user_worker_type' => $userData->worker_type,
                                        'pay_frequency' => @$payFrequency->pay_frequency,
                                    ];

                                    $stackOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                    if ($stackOverride) {
                                        $sum = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'sale_user_id' => $saleUserId, 'type' => 'Stack', 'during' => 'm2', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($sum, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($stackOverride->overrides_settlement_type == 'during_m2') {
                                                if ($stackOverride->status == '1') {
                                                    $stackOverride->update($stackData);
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            } else if ($stackOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($stackOverride->recon_status == '1' || $stackOverride->recon_status == '2') {
                                                    if ($stackOverride->recon_status == '1' && number_format($stackOverride->amount, 3, '.', '') == number_format($dueStack, 3, '.', '')) {
                                                        $stackOverride->delete();
                                                    } else {
                                                        unset($stackData['overrides_settlement_type']);
                                                        unset($stackData['pay_period_from']);
                                                        unset($stackData['pay_period_to']);
                                                        unset($stackData['status']);
                                                        $stackOverride->update($stackData);
                                                    }
                                                } else {
                                                    // Check if Stack override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                        UserOverrides::create($stackData);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            UserOverrides::create($stackData);
                                        }
                                    }

                                    if (isset($stackData['overrides_settlement_type']) && $stackData['overrides_settlement_type'] == 'during_m2') {
                                        // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
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
    }

    public function addersStackMortgageOverride($saleUserId, $pid, $kw, $date, $during = NULL, $forExternal = 0)
    {
        $userData = User::where(['id' => $saleUserId])->first();
        if (!$userData) {
            return false;
        }
        $stackOverrides = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Stack', 'is_displayed' => '1'])->pluck('user_id');
        if (sizeOf($stackOverrides) != 0) {
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            if (config("app.domain_name") == 'flex') {
                $saleState = $saleData->customer_state;
            } else {
                $saleState = $saleData->location_code;
            }
            $productId = $saleData->product_id;
            $closerId = isset($saleData->salesMasterProcess->closer1Detail->id) ? $saleData->salesMasterProcess->closer1Detail->id : NULL;
            $setterId = isset($saleData->salesMasterProcess->setter1Detail->id) ? $saleData->salesMasterProcess->setter1Detail->id : NULL;
            $saleUsers = [$closerId, $setterId];
            if (isset($saleData->salesMasterProcess->closer2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2Detail->id;
            }
            if (isset($saleData->salesMasterProcess->setter2Detail->id)) {
                $saleUsers[] = $saleData->salesMasterProcess->setter2Detail->id;
            }
            $approvedDate = $saleData->customer_signoff;
            $netEpc = $saleData->net_epc;
            $grossAmountValue = $saleData->gross_account_value;

            $check = checkSalesReps($saleUserId, $approvedDate, '');
            if (!$check['status']) {
                return false;
            }
            $userOrganizationData = checkUsersProductForCalculations($saleUserId, $approvedDate, $productId);
            $organization = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $subPosition = $organization?->sub_position_id;
            $position = Positions::where('id', $subPosition)->first();
            if (!$position || $position->is_selfgen == 3) {
                return false;
            }
            $commissionHistory = UserCommissionHistory::where(['user_id' => $saleUserId, 'product_id' => $actualProductId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$commissionHistory) {
                return false;
            }

            $eligibleUsers = [];
            $allUsers = User::whereIn('id', $stackOverrides)->pluck('id');
            foreach ($allUsers as $allUser) {
                $userOrganizationData = checkUsersProductForCalculations($allUser, $approvedDate, $productId);
                $userProductId = $userOrganizationData['product']->id;
                $userProductCode = $userOrganizationData['product']->product_id;

                $overrideHistory = UserOverrideHistory::where(['user_id' => $allUser, 'product_id' => $userProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($overrideHistory && $overrideHistory->office_stack_overrides_amount && $overrideHistory->office_stack_overrides_amount > 0) {
                    $eligibleUsers[] = [
                        'user_id' => $allUser,
                        'product_id' => $userProductId,
                        'product_code' => $userProductCode
                    ];
                }
            }

            $stackData = [];
            $commissionType = $commissionHistory->commission_type;
            if ($commissionType == 'per sale') {
                foreach ($eligibleUsers as $eligibleUser) {
                    $closerData = $this->mortgageUserPerSale($eligibleUser['user_id'], $approvedDate, $commissionHistory, $eligibleUser);
                    if ($closerData) {
                        $stackData[$closerData['type']][] = $closerData;
                    }
                }
            } else if ($commissionType == 'percent') {
                $closerRedline = $this->userRedline($userData, $saleState, $approvedDate, $actualProductId);
                if ($closerRedline['redline']) {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->mortgageUserPercentage($eligibleUser['user_id'], $saleState, $approvedDate, $closerRedline['redline'], $eligibleUser);
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

            if (isset($stackData['percent'])) {
                $sortedArray['percent'] = collect($stackData['percent'])->sortBy(function ($item) {
                    return $item['value'];
                })->toArray();
            }

            $i = 0;
            $x = 1;
            $userIds = [];
            $previousValue = 0;
            $lowerStackPay = 0;
            $companyMargin = CompanyProfile::first();
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
            }
            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
            $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
            $totalCommissionClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('clawback_amount');
            $finalCommission = $totalCommission - $totalCommissionClawBack;
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('amount');
            $totalOverrideClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'overrides', 'is_displayed' => '1'])->where('adders_type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('clawback_amount');
            $finalOverride = $totalOverride - $totalOverrideClawBack;
            foreach ($sortedArray as $key => $stacks) {
                foreach ($stacks as $stack) {
                    $productId = $stack['product_id'];
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $stack['user_id'], 'product_id' => $productId, 'type' => 'Stack'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$overrideStatus || $overrideStatus->status == 0) {
                        $userData = User::where(['id' => $stack['user_id']])->first();
                        $check = checkSalesReps($userData->id, $approvedDate, '');
                        if (!$check['status']) {
                            continue;
                        }
                        $positionId = $userData->sub_position_id;
                        $organizationHistory = UserOrganizationHistory::where(['user_id' => $userData->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$positionReconciliation) {
                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                        }
                        if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->stack_settlement == 'Reconciliation') {
                            $settlementType = 'reconciliation';
                            $payFrequency = NULL;
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequency = $this->payFrequencyNew($date, $positionId, $userData->id);
                        }

                        $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
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
                            if ($key == 'per sale') {
                                $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                            } else if ($key == 'percent') {
                                //$amount = (($grossAmountValue + (($netEpc - 0) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                $redline = $value ? $value / 100 : 0; 
                                $amount = (((($netEpc - $redline) * $x) * $kw) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);

                            }

                            $stackData = [
                                'user_id' => $userData->id,
                                'product_id' => $productId,
                                'product_code' => $stack['product_code'],
                                'type' => 'Stack',
                                'during' => 'm2 update',
                                'sale_user_id' => $saleUserId,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_stack_overrides_amount,
                                'overrides_type' => 'per sale',
                                'calculated_redline' => $value,
                                'calculated_redline_type' => $valueType,
                                'pay_period_from' => @$payFrequency->pay_period_from ? $payFrequency->pay_period_from : NULL,
                                'pay_period_to' => @$payFrequency->pay_period_to ? $payFrequency->pay_period_to : NULL,
                                'overrides_settlement_type' => $settlementType,
                                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                'is_stop_payroll' => $userData->stop_payroll,
                                'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                'user_worker_type' => $userData->worker_type,
                                'pay_frequency' => @$payFrequency->pay_frequency,
                            ];

                            $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                            $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                            if ($dueStack) {
                                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                                if ($userOverride->overrides_settlement_type == 'during_m2') {
                                    if ($userOverride->status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        $userOverride->delete();
                                        $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userData->id, 'type' => 'Stack', 'is_displayed' => '1'])->sum('amount');
                                        $dueStack = number_format($amount, 3, '.', '') - number_format($userOverride, 3, '.', '');
                                        if ($dueStack) {
                                            $stackData['amount'] = $dueStack;
                                            if ($during) {
                                                $stackData['during'] = $during;
                                            }
                                            // Check if Stack override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                                $userOverride = UserOverrides::create($stackData);
                                                if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                                }
                                            }
                                        }
                                        // $userOverride->during = $userOverride->during;
                                        // $userOverride->amount = $dueStack;
                                        // $userOverride->update();
                                        // if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                        //     subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        // }
                                    }
                                } else if ($userOverride->overrides_settlement_type == 'reconciliation') {
                                    if ($userOverride->recon_status == '3') {
                                        $stackData['amount'] = $dueStack;
                                        if ($during) {
                                            $stackData['during'] = $during;
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            $userOverride = UserOverrides::create($stackData);
                                        }
                                        // Check if Stack override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, false)) {
                                            $userOverride = UserOverrides::create($stackData);
                                        }

                                        if ($stackData['overrides_settlement_type'] == 'during_m2') {
                                            // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequency);
                                        }
                                    } else {
                                        // unset($stackData['overrides_settlement_type']);
                                        // unset($stackData['pay_period_from']);
                                        // unset($stackData['pay_period_to']);
                                        // unset($stackData['status']);
                                        $userOverride->during = $userOverride->during;
                                        $userOverride->amount = $userOverride->amount + $dueStack;
                                        $userOverride->save();
                                    }
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
