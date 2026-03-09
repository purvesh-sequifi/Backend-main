<?php

namespace App\Core\Traits\SaleTraits;

use App\Models\User;
use App\Models\Products;
use App\Models\SalesMaster;
use App\Models\UserOverrides;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\OverrideStatus;
use App\Models\UserCommission;
use App\Models\ManualOverrides;
use App\Models\SaleTiersDetail;
use App\Models\PositionOverride;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\AdditionalLocations;
use App\Models\UserOverrideHistory;
use App\Models\UserTransferHistory;
use App\Models\ReconOverrideHistory;
use App\Models\overrideSystemSetting;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\ManualOverridesHistory;
use App\Models\PositionReconciliations;
use App\Core\Traits\ReconciliationPeriodTrait;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserAdditionalOfficeOverrideHistoryTiersRange;
use App\Models\ProjectionUserOverrides;
use App\Models\ProjectionUserCommission;
use App\Core\Traits\OverrideArchiveCheckTrait;

trait SubroutineOverrideTrait
{
    use EditSaleTrait, PayFrequencyTrait, ReconciliationPeriodTrait, OverrideArchiveCheckTrait;

    public function userOverride($info, $pid, $kw, $date, $commission, $forExternal = 0)
    {
        $saleUserId = $info['id'];
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : NULL;
        $productId = $saleMaster->product_id;
        $recruiterIdData = User::where(['id' => $saleUserId])->first();
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if ($check['status'] && $recruiterIdData) {
            $companyMargin = CompanyProfile::first();
            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $kw = $saleMaster->gross_account_value;
            }

            $x = 1;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
            }

            $finalCommission = @$commission[$info['type']] ? $commission[$info['type']] : 0;
            if($forExternal){
                $finalCommission = @$commission[$info['id']] ? $commission[$info['id']] : 0;
            }
            $commission = UserCommission::where(['pid' => $pid, 'user_id' => $saleUserId, 'settlement_type' => 'during_m2', 'is_last' => '1', 'status' => '3', 'is_displayed' => '1'])->first();
            $reconCommission = UserCommission::where(['pid' => $pid, 'user_id' => $saleUserId, 'settlement_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
            if ($commission || $reconCommission) {
                $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $saleUserId, 'is_displayed' => '1'])->sum('amount');
                $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleUserId, 'type' => 'commission', 'is_displayed' => '1'])->sum('clawback_amount');
                $finalCommission = $totalCommission - $totalClawBack;
            }

            // OFFICE OVERRIDES CODE
            $officeId = $recruiterIdData->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }

            if ($officeId) {
                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);

                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->select('id')
                    ->where('rn', 1);

                $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereNotNull('office_id')->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type', 'worker_type')->whereIn('id', $userIdArr)->get();
                foreach ($userIdArr1 as $userData) {
                    $check = checkSalesReps($userData->id, $approvedDate, '');
                    if (!$check['status']) {
                        continue;
                    }

                    $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                    $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                    $organizationHistory = $userOrganizationData['organization'];
                    $actualProductId = $userOrganizationData['product']->id;
                    $productCode = $userOrganizationData['product']->product_id;
                    $positionId = $userData->sub_position_id;
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionReconciliation) {
                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }
                    if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                        $settlementType = 'reconciliation';
                        $payFrequencyOffice = NULL;
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $userData->id);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionOverride) {
                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                    }
                    if ($positionOverride && $positionOverride->status == 1) {
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'product_id' => $actualProductId, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ((!$overrideStatus || $overrideStatus->status == 0) && $userData) {
                            $userData->office_overrides_amount = 0;
                            $userData->office_overrides_type = '';

                            $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                if ($overrideHistory->office_tiers_id) {
                                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Override', 'sub_type' => 'Office'])->whereNotNull('tier_level')->first();
                                    if ($level) {
                                        $officeTier = UserOfficeOverrideHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                            $q->where('level', $level->tier_level);
                                        })->with('level')->where(['user_office_override_history_id' => $overrideHistory->id])->first();
                                        if ($officeTier) {
                                            $userData->office_overrides_amount = $officeTier->value;
                                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                        }
                                    } else {
                                        $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                        $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                    }
                                } else {
                                    $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                    $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                }
                            }

                            if ($userData->office_overrides_amount && $userData->office_overrides_type) {
                                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                    // For frdmturf and other Turfdomain
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $saleMaster->kw;
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                    //Code to calculate override limit from percentage to normal value for mortgage only company type
                                    // $overridesLimitType = $positionOverride->override_limit_type;
                                    // if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    //     // Calculate the amount as percentage of original amount
                                    //     $amount = $amount * ($positionOverride->override_limit / 100);
                                    // }
                                }else {
                                    if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $kw;
                                    } else if ($userData->office_overrides_type == 'percent') {
                                        $amount = $finalCommission * ($userData->office_overrides_amount / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                }

                                $overridesLimitType = $positionOverride->override_limit_type;
                                if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    // Calculate the amount as percentage of original amount
                                    $amount = $amount * ($positionOverride->override_limit / 100);
                                }else{
                                    if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                        $amount = $positionOverride->override_limit;
                                    }
                                }

                                $officeData = [
                                    'user_id' => $userData->id,
                                    'product_id' => $actualProductId,
                                    'type' => 'Office',
                                    'sale_user_id' => $saleUserId,
                                    'pid' => $pid,
                                    'product_code' => $productCode,
                                    'kw' => $kw,
                                    'amount' => $amount,
                                    'overrides_amount' => $userData->office_overrides_amount,
                                    'overrides_type' => $userData->office_overrides_type,
                                    'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : NULL,
                                    'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : NULL,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                    'is_stop_payroll' => $stopPayroll,
                                    'office_id' => $officeId,
                                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                    'user_worker_type' => $userData->worker_type,
                                    'pay_frequency' => isset($payFrequencyOffice->pay_frequency) ? $payFrequencyOffice->pay_frequency : NULL,
                                ];

                                $officeOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office', 'during' => 'm2', 'is_displayed' => '1'])->first();
                                if ($officeOverride) {
                                    if ($officeOverride->overrides_settlement_type == 'during_m2') {
                                        if ($officeOverride->status == '1') {
                                            if ($amount > $officeOverride->amount) {
                                                $officeOverride->update($officeData);
                                            }
                                        }
                                    } else if ($officeOverride->overrides_settlement_type == 'reconciliation') {
                                        if ($officeOverride->recon_status == '1' || $officeOverride->recon_status == '2') {
                                            if ($officeOverride->recon_status == '1') {
                                                if ($amount > $officeOverride->amount) {
                                                    unset($officeData['overrides_settlement_type']);
                                                    unset($officeData['pay_period_from']);
                                                    unset($officeData['pay_period_to']);
                                                    unset($officeData['status']);
                                                    $officeOverride->update($officeData);
                                                }
                                            } else if ($officeOverride->recon_status == '2') {
                                                $paidRecon = ReconOverrideHistory::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'during' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
                                                if ($paidRecon < $amount) {
                                                    unset($officeData['overrides_settlement_type']);
                                                    unset($officeData['pay_period_from']);
                                                    unset($officeData['pay_period_to']);
                                                    unset($officeData['status']);
                                                    $officeOverride->update($officeData);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Check if Office override was previously deleted (archived) before creating
                                    // Use $saleUserId (not null) because Office normal overrides store sale_user_id
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, false)) {

                                        UserOverrides::create($officeData);
                                    }
                                }

                                if (isset($officeData['overrides_settlement_type']) && $officeData['overrides_settlement_type'] == 'during_m2') {
                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequencyOffice);
                                }
                            }
                        }
                    }
                }

                $userIdArr2 = AdditionalLocations::with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type,worker_type')->where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->get();
                foreach ($userIdArr2 as $userData) {
                    $userData = $userData->user;
                    $check = checkSalesReps($userData->id, $approvedDate, '');
                    if (!$check['status']) {
                        continue;
                    }
                    $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                    $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                    $organizationHistory = $userOrganizationData['organization'];
                    $actualProductId = $userOrganizationData['product']->id;
                    $productCode = $userOrganizationData['product']->product_id;
                    $positionId = $userData->sub_position_id;
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionReconciliation) {
                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }
                    if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                        $settlementType = 'reconciliation';
                        $payFrequencyAdditionalOffice = NULL;
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyAdditionalOffice = $this->payFrequencyNew($date, $positionId, $userData->id);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionOverride) {
                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                    }
                    if ($positionOverride && $positionOverride->status == 1) {
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'product_id' => $actualProductId, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ((!$overrideStatus || $overrideStatus->status == 0) && $userData) {
                            $userData->office_overrides_amount = 0;
                            $userData->office_overrides_type = '';

                            $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $actualProductId, 'office_id' => $officeId])->where('override_effective_date', '<=', $approvedDate)->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                if ($overrideHistory->tiers_id) {
                                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Override', 'sub_type' => 'Additional Office'])->whereNotNull('tier_level')->first();
                                    if ($level) {
                                        $additionalOfficeTier = UserAdditionalOfficeOverrideHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                            $q->where('level', $level->tier_level);
                                        })->with('level')->where(['user_add_office_override_history_id' => $overrideHistory->id])->first();
                                        if ($additionalOfficeTier) {
                                            $userData->office_overrides_amount = $additionalOfficeTier->value;
                                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                        }
                                    } else {
                                        $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                        $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                    }
                                } else {
                                    $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                    $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                                }
                            }

                            if ($userData->office_overrides_amount && $userData->office_overrides_type) {
                                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                    // For frdmturf domain
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else if ($userData->office_overrides_type == 'per sale') {
                                        $amount = $userData->office_overrides_amount;
                                    } else {
                                        $amount = $userData->office_overrides_amount * $saleMaster->kw;
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                    //Code to calculate override limit from percentage to normal value for mortgage only company type
                                    // $overridesLimitType = $positionOverride->override_limit_type;
                                    // if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    //     // Calculate the amount as percentage of original amount
                                    //     $amount = $amount * ($positionOverride->override_limit / 100);
                                    // }
                                }
                                else {
                                    if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $kw;
                                    } else if ($userData->office_overrides_type == 'percent') {
                                        $amount = $finalCommission * ($userData->office_overrides_amount / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                }

                                $overridesLimitType = $positionOverride->override_limit_type;
                                if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    // Calculate the amount as percentage of original amount
                                    $amount = $amount * ($positionOverride->override_limit / 100);
                                }else{
                                    if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                        $amount = $positionOverride->override_limit;
                                    }
                                }
                                

                                $officeData = [
                                    'user_id' => $userData->id,
                                    'product_id' => $actualProductId,
                                    'type' => 'Office',
                                    'sale_user_id' => $saleUserId,
                                    'pid' => $pid,
                                    'product_code' => $productCode,
                                    'kw' => $kw,
                                    'amount' => $amount,
                                    'overrides_amount' => $userData->office_overrides_amount,
                                    'overrides_type' => $userData->office_overrides_type,
                                    'pay_period_from' => isset($payFrequencyAdditionalOffice->pay_period_from) ? $payFrequencyAdditionalOffice->pay_period_from : NULL,
                                    'pay_period_to' => isset($payFrequencyAdditionalOffice->pay_period_to) ? $payFrequencyAdditionalOffice->pay_period_to : NULL,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                    'is_stop_payroll' => $stopPayroll,
                                    'office_id' => $officeId,
                                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                    'user_worker_type' => $userData->worker_type,
                                    'pay_frequency' => isset($payFrequencyAdditionalOffice->pay_frequency) ? $payFrequencyAdditionalOffice->pay_frequency : NULL,
                                ];

                                $officeOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office', 'during' => 'm2', 'is_displayed' => '1'])->first();
                                if ($officeOverride) {
                                    if ($officeOverride->overrides_settlement_type == 'during_m2') {
                                        if ($officeOverride->status == '1') {
                                            if ($amount > $officeOverride->amount) {
                                                $officeOverride->update($officeData);
                                            }
                                        }
                                    } else if ($officeOverride->overrides_settlement_type == 'reconciliation') {
                                        if ($officeOverride->recon_status == '1' || $officeOverride->recon_status == '2') {
                                            if ($officeOverride->recon_status == '1') {
                                                if ($amount > $officeOverride->amount) {
                                                    unset($officeData['overrides_settlement_type']);
                                                    unset($officeData['pay_period_from']);
                                                    unset($officeData['pay_period_to']);
                                                    unset($officeData['status']);
                                                    $officeOverride->update($officeData);
                                                }
                                            } else if ($officeOverride->recon_status == '2') {
                                                $paidRecon = ReconOverrideHistory::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'during' => 'm2', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
                                                if ($paidRecon < $amount) {
                                                    unset($officeData['overrides_settlement_type']);
                                                    unset($officeData['pay_period_from']);
                                                    unset($officeData['pay_period_to']);
                                                    unset($officeData['status']);
                                                    $officeOverride->update($officeData);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Check if Office override was previously deleted (archived) before creating
                                    // Use $saleUserId (not null) because Office normal overrides store sale_user_id
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, false)) {

                                        UserOverrides::create($officeData);
                                    }
                                }

                                if (isset($officeData['overrides_settlement_type']) && $officeData['overrides_settlement_type'] == 'during_m2') {
                                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequencyAdditionalOffice);
                                }
                            }
                        }
                    }
                }
            }

            // DIRECT & INDIRECT OVERRIDES CODE
            $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first(); // pay override with the highest value
            if ($recruiterIdData && $recruiterIdData->recruiter_id) {
                $recruiterIds = $recruiterIdData->recruiter_id;
                if (!empty($recruiterIdData->additional_recruiter_id1)) {
                    $recruiterIds .= ',' . $recruiterIdData->additional_recruiter_id1;
                }
                if (!empty($recruiterIdData->additional_recruiter_id2)) {
                    $recruiterIds .= ',' . $recruiterIdData->additional_recruiter_id2;
                }

                $idsArr = explode(',', $recruiterIds);
                $directs = User::whereIn('id', $idsArr)->get();
                foreach ($directs as $value) {
                    $check = checkSalesReps($value->id, $approvedDate, '');
                    if (!$check['status']) {
                        continue;
                    }
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;
                    $userOrganizationData = checkUsersProductForCalculations($value->id, $approvedDate, $productId);
                    $organizationHistory = $userOrganizationData['organization'];
                    $actualProductId = $userOrganizationData['product']->id;
                    $productCode = $userOrganizationData['product']->product_id;
                    $positionId = $value->sub_position_id;
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionReconciliation) {
                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                    }
                    if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                        $settlementType = 'reconciliation';
                        $payFrequencyDirect = NULL;
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyDirect = $this->payFrequencyNew($date, $positionId, $value->id);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionOverride) {
                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->whereNull('effective_date')->first();
                    }
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $actualProductId, 'type' => 'Direct'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ((!$overrideStatus || $overrideStatus->status == 0) && $positionOverride && $positionOverride->status == 1) {
                        $value->direct_overrides_amount = 0;
                        $value->direct_overrides_type = '';

                        $overrideHistory = UserOverrideHistory::where(['user_id' => $value->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory) {
                            if ($overrideHistory->direct_tiers_id) {
                                $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $value->id, 'type' => 'Override', 'sub_type' => 'Direct'])->whereNotNull('tier_level')->first();
                                if ($level) {
                                    $directTier = UserDirectOverrideHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                        $q->where('level', $level->tier_level);
                                    })->with('level')->where(['user_override_history_id' => $overrideHistory->id])->first();
                                    if ($directTier) {
                                        $value->direct_overrides_amount = $directTier->value;
                                        $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                                    }
                                } else {
                                    $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                                    $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                                }
                            } else {
                                $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                                $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                            }
                        }

                        if ($value->direct_overrides_amount && $value->direct_overrides_type) {
                            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                if ($value->direct_overrides_type == 'percent') {
                                    $amount = (($kw * $value->direct_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                            } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                // For frdmturf domain
                                if(config('app.domain_name') == 'frdmturf') {
                                    if ($value->direct_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $value->direct_overrides_amount * $x) / 100);
                                    } else if ($value->direct_overrides_type == 'per kw') {
                                        $amount = $value->direct_overrides_amount * $saleMaster->kw;
                                    } else {
                                        $amount = $value->direct_overrides_amount;
                                    }
                                } else {
                                    // For other turf domains
                                    if ($value->direct_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $value->direct_overrides_amount * $x) / 100);
                                    } else if ($value->direct_overrides_type == 'per kw') {
                                        $amount = $value->direct_overrides_amount * $saleMaster->kw;
                                    } else {
                                        $amount = $value->direct_overrides_amount;
                                    }
                                }
                            } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                if ($value->direct_overrides_type == 'percent') {
                                    $amount = (($saleMaster->gross_account_value * $value->direct_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                                //Code to calculate override limit from percentage to normal value for mortgage only company type
                                // $overridesLimitType = $positionOverride->override_limit_type;
                                // if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                //     // Calculate the amount as percentage of original amount
                                //     $amount = $amount * ($positionOverride->override_limit / 100);
                                // }
                            }else {
                                if ($value->direct_overrides_type == 'per kw') {
                                    $amount = $value->direct_overrides_amount * $kw;
                                } else if ($value->direct_overrides_type == 'percent') {
                                    $amount = $finalCommission * ($value->direct_overrides_amount / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                            }
                           
                            $overridesLimitType = $positionOverride->override_limit_type;
                            if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                // Calculate the amount as percentage of original amount
                                $amount = $amount * ($positionOverride->override_limit / 100);
                            }else{
                                if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                    $amount = $positionOverride->override_limit;
                                }
                            }

                            $dataDirect = [
                                'user_id' => $value->id,
                                'product_id' => $actualProductId,
                                'type' => 'Direct',
                                'sale_user_id' => $saleUserId,
                                'pid' => $pid,
                                'product_code' => $productCode,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $value->direct_overrides_amount,
                                'overrides_type' => $value->direct_overrides_type,
                                'pay_period_from' => isset($payFrequencyDirect->pay_period_from) ? $payFrequencyDirect->pay_period_from : NULL,
                                'pay_period_to' => isset($payFrequencyDirect->pay_period_to) ? $payFrequencyDirect->pay_period_to : NULL,
                                'overrides_settlement_type' => $settlementType,
                                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                'is_stop_payroll' => $stopPayroll,
                                'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                'user_worker_type' => $value->worker_type,
                                'pay_frequency' => isset($payFrequencyDirect->pay_frequency) ? $payFrequencyDirect->pay_frequency : NULL,
                            ];

                            // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                            if ($overrideSystemSetting) {
                                $directOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $value->id, 'during' => 'm2', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first();
                                if ($directOverride) {
                                    if ($directOverride->overrides_settlement_type == 'during_m2') {
                                        if ($directOverride->status == '1') {
                                            if ($amount > $directOverride->amount) {
                                                $directOverride->update($dataDirect);
                                            }
                                        }
                                    } else if ($directOverride->overrides_settlement_type == 'reconciliation') {
                                        if ($directOverride->recon_status == '1' || $directOverride->recon_status == '2') {
                                            if ($directOverride->recon_status == '1') {
                                                if ($amount > $directOverride->amount) {
                                                    unset($dataDirect['overrides_settlement_type']);
                                                    unset($dataDirect['pay_period_from']);
                                                    unset($dataDirect['pay_period_to']);
                                                    unset($dataDirect['status']);
                                                    $directOverride->update($dataDirect);
                                                }
                                            } else if ($directOverride->recon_status == '2') {
                                                $paidRecon = ReconOverrideHistory::where(['user_id' => $value->id, 'overrider' => $saleUserId, 'pid' => $pid, 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->sum('paid');
                                                if ($paidRecon && $paidRecon < $amount) {
                                                    unset($dataDirect['overrides_settlement_type']);
                                                    unset($dataDirect['pay_period_from']);
                                                    unset($dataDirect['pay_period_to']);
                                                    unset($dataDirect['status']);
                                                    $directOverride->update($dataDirect);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Check if Direct override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($value->id, $pid, 'Direct', $saleUserId, false)) {
                                      
                                        UserOverrides::create($dataDirect);
                                    }
                                }
                            } else {
                                $directOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $value->id, 'sale_user_id' => $saleUserId, 'type' => 'Direct', 'during' => 'm2', 'is_displayed' => '1'])->first();
                                if ($directOverride) {
                                    if ($directOverride->overrides_settlement_type == 'during_m2') {
                                        if ($directOverride->status == '1') {
                                            if ($amount > $directOverride->amount) {
                                                $directOverride->update($dataDirect);
                                            }
                                        }
                                    } else if ($directOverride->overrides_settlement_type == 'reconciliation') {
                                        if ($directOverride->recon_status == '1' || $directOverride->recon_status == '2') {
                                            if ($directOverride->recon_status == '1') {
                                                if ($amount > $directOverride->amount) {
                                                    unset($dataDirect['overrides_settlement_type']);
                                                    unset($dataDirect['pay_period_from']);
                                                    unset($dataDirect['pay_period_to']);
                                                    unset($dataDirect['status']);
                                                    $directOverride->update($dataDirect);
                                                }
                                            } else if ($directOverride->recon_status == '2') {
                                                $paidRecon = ReconOverrideHistory::where(['user_id' => $value->id, 'overrider' => $saleUserId, 'pid' => $pid, 'type' => 'Direct', 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->sum('paid');
                                                if ($paidRecon && $paidRecon < $amount) {
                                                    unset($dataDirect['overrides_settlement_type']);
                                                    unset($dataDirect['pay_period_from']);
                                                    unset($dataDirect['pay_period_to']);
                                                    unset($dataDirect['status']);
                                                    $directOverride->update($dataDirect);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Check if Direct override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($value->id, $pid, 'Direct', $saleUserId, false)) {
                                      
                                        UserOverrides::create($dataDirect);
                                    }
                                }
                            }

                            if (isset($dataDirect['overrides_settlement_type']) && $dataDirect['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($value->id, $positionId, $payFrequencyDirect);
                            }
                        }
                    }

                    // INDIRECT
                    if ($value->recruiter_id) {
                        $recruiterIds = $value->recruiter_id;
                        if (!empty($value->additional_recruiter_id1)) {
                            $recruiterIds .= ',' . $value->additional_recruiter_id1;
                        }
                        if (!empty($value->additional_recruiter_id2)) {
                            $recruiterIds .= ',' . $value->additional_recruiter_id2;
                        }
                        $idsArr = explode(',', $recruiterIds);

                        $additional = User::whereIn('id', $idsArr)->get();
                        foreach ($additional as $val) {
                            $check = checkSalesReps($val->id, $approvedDate, '');
                            if (!$check['status']) {
                                continue;
                            }
                            $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;
                            $userOrganizationData = checkUsersProductForCalculations($val->id, $approvedDate, $productId);
                            $organizationHistory = $userOrganizationData['organization'];
                            $actualProductId = $userOrganizationData['product']->id;
                            $productCode = $userOrganizationData['product']->product_id;
                            $positionId = $val->sub_position_id;
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequencyInDirect = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequencyInDirect = $this->payFrequencyNew($date, $positionId, $val->id);
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->whereNull('effective_date')->first();
                            }
                            $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $val->id, 'product_id' => $actualProductId, 'type' => 'Indirect'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ((!$overrideStatus || $overrideStatus->status == 0) && $positionOverride && $positionOverride->status == 1) {
                                $val->indirect_overrides_amount = 0;
                                $val->indirect_overrides_type = '';

                                $overrideHistory = UserOverrideHistory::where(['user_id' => $val->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    if ($overrideHistory->indirect_tiers_id) {
                                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $val->id, 'type' => 'Override', 'sub_type' => 'InDirect'])->whereNotNull('tier_level')->first();
                                        if ($level) {
                                            $inDirectTier = UserIndirectOverrideHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                                $q->where('level', $level->tier_level);
                                            })->with('level')->where(['user_override_history_id' => $overrideHistory->id])->first();
                                            if ($inDirectTier) {
                                                $val->indirect_overrides_amount = $inDirectTier->value;
                                                $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                                            }
                                        } else {
                                            $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                            $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                                        }
                                    } else {
                                        $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                        $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                                    }
                                }

                                if ($val->indirect_overrides_amount && $val->indirect_overrides_type) {
                                    if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                        if ($val->indirect_overrides_type == 'percent') {
                                            $amount = (($kw * $val->indirect_overrides_amount * $x) / 100);
                                        } else {
                                            $amount = $val->indirect_overrides_amount;
                                        }
                                    } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                        // For frdmturf domain
                                        if (config('app.domain_name') == 'frdmturf') {
                                            if ($val->indirect_overrides_type == 'percent') {
                                                $amount = (($saleMaster->gross_account_value * $val->indirect_overrides_amount * $x) / 100);
                                            } else if ($val->indirect_overrides_type == 'per kw') {
                                                $amount = $val->indirect_overrides_amount * $saleMaster->kw;
                                            } else {
                                                $amount = $val->indirect_overrides_amount;
                                            }
                                        } else {
                                            // For other turf domains
                                            if ($val->indirect_overrides_type == 'percent') {
                                                $amount = 0;
                                            } else if ($val->indirect_overrides_type == 'per kw') {
                                                $amount = $val->indirect_overrides_amount * $saleMaster->kw;
                                            } else {
                                                $amount = $val->indirect_overrides_amount;
                                            }
                                        }
                                    } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                        if ($val->indirect_overrides_type == 'percent') {
                                            $amount = (($saleMaster->gross_account_value * $val->indirect_overrides_amount * $x) / 100);
                                        } else {
                                            $amount = $val->indirect_overrides_amount;
                                        }
                                        //Code to calculate override limit from percentage to normal value for mortgage only company type
                                        // $overridesLimitType = $positionOverride->override_limit_type;
                                        // if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                        //     // Calculate the amount as percentage of original amount
                                        //     $amount = $amount * ($positionOverride->override_limit / 100);
                                        // }
                                    } else {
                                        if ($val->indirect_overrides_type == 'per kw') {
                                            $amount = $val->indirect_overrides_amount * $kw;
                                        } else if ($val->indirect_overrides_type == 'percent') {
                                            $amount = $finalCommission * ($val->indirect_overrides_amount / 100);
                                        } else {
                                            $amount = $val->indirect_overrides_amount;
                                        }
                                    }

                                    $overridesLimitType = $positionOverride->override_limit_type;
                                    if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                        // Calculate the amount as percentage of original amount
                                        $amount = $amount * ($positionOverride->override_limit / 100);
                                    }else{
                                        if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                            $amount = $positionOverride->override_limit;
                                        }
                                    }
                                  
                                    $dataIndirect = [
                                        'user_id' => $val->id,
                                        'product_id' => $actualProductId,
                                        'type' => 'Indirect',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'product_code' => $productCode,
                                        'kw' => $kw,
                                        'amount' => $amount,
                                        'overrides_amount' => $val->indirect_overrides_amount,
                                        'overrides_type' => $val->indirect_overrides_type,
                                        'pay_period_from' => isset($payFrequencyInDirect->pay_period_from) ? $payFrequencyInDirect->pay_period_from : NULL,
                                        'pay_period_to' => isset($payFrequencyInDirect->pay_period_to) ? $payFrequencyInDirect->pay_period_to : NULL,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                        'is_stop_payroll' => $stopPayroll,
                                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                        'user_worker_type' => $val->worker_type,
                                        'pay_frequency' => isset($payFrequencyInDirect->pay_frequency) ? $payFrequencyInDirect->pay_frequency : NULL,
                                    ];

                                    // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                                    if ($overrideSystemSetting) {
                                        $inDirectOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $val->id, 'during' => 'm2', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first();
                                        if ($inDirectOverride) {
                                            if ($inDirectOverride->overrides_settlement_type == 'during_m2') {
                                                if ($inDirectOverride->status == '1') {
                                                    if ($amount > $inDirectOverride->amount) {
                                                        $inDirectOverride->update($dataIndirect);
                                                    }
                                                }
                                            } else if ($inDirectOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($inDirectOverride->recon_status == '1' || $inDirectOverride->recon_status == '2') {
                                                    if ($inDirectOverride->recon_status == '1') {
                                                        if ($amount > $inDirectOverride->amount) {
                                                            unset($dataIndirect['overrides_settlement_type']);
                                                            unset($dataIndirect['pay_period_from']);
                                                            unset($dataIndirect['pay_period_to']);
                                                            unset($dataIndirect['status']);
                                                            $inDirectOverride->update($dataIndirect);
                                                        }
                                                    } else if ($inDirectOverride->recon_status == '2') {
                                                        $paidRecon = ReconOverrideHistory::where(['user_id' => $val->id, 'overrider' => $saleUserId, 'pid' => $pid, 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->sum('paid');
                                                        if ($paidRecon && $paidRecon < $amount) {
                                                            unset($dataIndirect['overrides_settlement_type']);
                                                            unset($dataIndirect['pay_period_from']);
                                                            unset($dataIndirect['pay_period_to']);
                                                            unset($dataIndirect['status']);
                                                            $inDirectOverride->update($dataIndirect);
                                                        }
                                                    }
                                                }
                                            }
                                        } else {
                                            // Check if Indirect override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($val->id, $pid, 'Indirect', $saleUserId, false)) {
                                              
                                                UserOverrides::create($dataIndirect);
                                            }
                                        }
                                    } else {
                                        $inDirectOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $val->id, 'sale_user_id' => $saleUserId, 'type' => 'Indirect', 'during' => 'm2', 'is_displayed' => '1'])->first();
                                        if ($inDirectOverride) {
                                            if ($inDirectOverride->overrides_settlement_type == 'during_m2') {
                                                if ($inDirectOverride->status == '1') {
                                                    if ($amount > $inDirectOverride->amount) {
                                                        $inDirectOverride->update($dataIndirect);
                                                    }
                                                }
                                            } else if ($inDirectOverride->overrides_settlement_type == 'reconciliation') {
                                                if ($inDirectOverride->recon_status == '1' || $inDirectOverride->recon_status == '2') {
                                                    if ($inDirectOverride->recon_status == '1') {
                                                        if ($amount > $inDirectOverride->amount) {
                                                            unset($dataIndirect['overrides_settlement_type']);
                                                            unset($dataIndirect['pay_period_from']);
                                                            unset($dataIndirect['pay_period_to']);
                                                            unset($dataIndirect['status']);
                                                            $inDirectOverride->update($dataIndirect);
                                                        }
                                                    } else if ($inDirectOverride->recon_status == '2') {
                                                        $paidRecon = ReconOverrideHistory::where(['user_id' => $val->id, 'overrider' => $saleUserId, 'pid' => $pid, 'type' => 'Indirect', 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->sum('paid');
                                                        if ($paidRecon && $paidRecon < $amount) {
                                                            unset($dataIndirect['overrides_settlement_type']);
                                                            unset($dataIndirect['pay_period_from']);
                                                            unset($dataIndirect['pay_period_to']);
                                                            unset($dataIndirect['status']);
                                                            $inDirectOverride->update($dataIndirect);
                                                        }
                                                    }
                                                }
                                            }
                                        } else {
                                            // Check if Indirect override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($val->id, $pid, 'Indirect', $saleUserId, false)) {
                                               
                                                UserOverrides::create($dataIndirect);
                                            }
                                        }
                                    }

                                    if (isset($dataIndirect['overrides_settlement_type']) && $dataIndirect['overrides_settlement_type'] == 'during_m2') {
                                        // subroutineCreatePayrollRecord($val->id, $positionId, $payFrequencyInDirect);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // END DIRECT & INDIRECT OVERRIDES CODE

            // MANUAL OVERRIDES CODE
            if ($saleUserId) {
                if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
                    $manualOverrides = ManualOverrides::where('manual_user_id', $saleUserId)->pluck('user_id');
                    $users = User::whereIn('id', $manualOverrides)->get();
                    foreach ($users as $value) {
                        $check = checkSalesReps($value->id, $approvedDate, '');
                        if (!$check['status']) {
                            continue;
                        }
                        $userOrganizationData = checkUsersProductForCalculations($value->id, $approvedDate, $productId);
                        $organizationHistory = $userOrganizationData['organization'];
                        $positionId = $userData->sub_position_id;
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        if ($productId) {
                            $product = Products::withTrashed()->where('id', $productId)->first();
                            $manualProductId = $product->id;
                            $manualProductCode = $product->id;
                        } else {
                            $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                            $manualProductId = $product->id;
                            $manualProductCode = $product->id;
                        }
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $manualProductId, 'type' => 'Manual'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $manualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                                $payFrequencyManual = NULL;
                            } else {
                                $settlementType = 'during_m2';
                                $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $value->id);
                            }

                            $value->overrides_amount = 0;
                            $value->overrides_type = '';
                            $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->id, 'manual_user_id' => $saleUserId, 'product_id' => $manualProductId])
                                ->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $value->overrides_amount = $overrideHistory->overrides_amount;
                                $value->overrides_type = $overrideHistory->overrides_type;
                            }

                            if ($value->overrides_amount && $value->overrides_type) {
                                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    if ($value->overrides_type == 'percent') {
                                        $amount = (($kw * $value->overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $value->overrides_amount;
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                    // For frdmturf domain
                                    if (config('app.domain_name') == 'frdmturf') {
                                        if ($value->overrides_type == 'percent') {
                                            $amount = (($saleMaster->gross_account_value * $value->overrides_amount * $x) / 100);
                                        } else if ($value->overrides_type == 'per kw') {
                                            $amount = $value->overrides_amount * $saleMaster->kw;
                                        } else {
                                            $amount = $value->overrides_amount;
                                        }
                                    } else {
                                        // For frdmturf domain
                                        if ($value->overrides_type == 'percent') {
                                            $amount = 0;
                                        } else if ($value->overrides_type == 'per kw') {
                                            $amount = $value->overrides_amount * $saleMaster->kw;
                                        } else {
                                            $amount = $value->overrides_amount;
                                        }
                                    }
                                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                    if ($value->overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $value->overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $value->overrides_amount;
                                    }
                                } else {
                                    if ($value->overrides_type == 'per kw') {
                                        $amount = $value->overrides_amount * $kw;
                                    } else if ($value->overrides_type == 'percent') {
                                        $amount = $finalCommission * ($value->overrides_amount / 100);
                                    } else {
                                        $amount = $value->overrides_amount;
                                    }
                                }

                                $dataManual = [
                                    'user_id' => $value->id,
                                    'product_id' => $manualProductId,
                                    'type' => 'Manual',
                                    'sale_user_id' => $saleUserId,
                                    'pid' => $pid,
                                    'product_code' => $manualProductCode,
                                    'kw' => $kw,
                                    'amount' => $amount,
                                    'overrides_amount' => $value->overrides_amount,
                                    'overrides_type' => $value->overrides_type,
                                    'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : NULL,
                                    'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : NULL,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                                    'is_stop_payroll' => $stopPayroll,
                                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                                    'user_worker_type' => $value->worker_type,
                                    'pay_frequency' => isset($payFrequencyManual->pay_frequency) ? $payFrequencyManual->pay_frequency : NULL,
                                ];

                                // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                                if ($overrideSystemSetting) {
                                    $manualOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $value->id, 'during' => 'm2', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first();
                                    if ($manualOverride) {
                                        if ($manualOverride->overrides_settlement_type == 'during_m2') {
                                            if ($manualOverride->status == '1') {
                                                if ($amount > $manualOverride->amount) {
                                                    $manualOverride->update($dataManual);
                                                }
                                            }
                                        } else if ($manualOverride->overrides_settlement_type == 'reconciliation') {
                                            if ($manualOverride->recon_status == '1' || $manualOverride->recon_status == '2') {
                                                if ($manualOverride->recon_status == '1') {
                                                    if ($amount > $manualOverride->amount) {
                                                        unset($dataManual['overrides_settlement_type']);
                                                        unset($dataManual['pay_period_from']);
                                                        unset($dataManual['pay_period_to']);
                                                        unset($dataManual['status']);
                                                        $manualOverride->update($dataManual);
                                                    }
                                                } else if ($manualOverride->recon_status == '2') {
                                                    $paidRecon = ReconOverrideHistory::where(['user_id' => $value->id, 'overrider' => $saleUserId, 'pid' => $pid, 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->sum('paid');
                                                    if ($paidRecon && $paidRecon < $amount) {
                                                        unset($dataManual['overrides_settlement_type']);
                                                        unset($dataManual['pay_period_from']);
                                                        unset($dataManual['pay_period_to']);
                                                        unset($dataManual['status']);
                                                        $manualOverride->update($dataManual);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Manual override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($value->id, $pid, 'Manual', $saleUserId, false)) {
                                           
                                            UserOverrides::create($dataManual);
                                        }
                                    }
                                } else {
                                    $manualOverride = UserOverrides::where(['pid' => $pid, 'user_id' => $value->id, 'sale_user_id' => $saleUserId, 'type' => 'Manual', 'during' => 'm2', 'is_displayed' => '1'])->first();
                                    if ($manualOverride) {
                                        if ($manualOverride->overrides_settlement_type == 'during_m2') {
                                            if ($manualOverride->status == '1') {
                                                if ($amount > $manualOverride->amount) {
                                                    $manualOverride->update($dataManual);
                                                }
                                            }
                                        } else if ($manualOverride->overrides_settlement_type == 'reconciliation') {
                                            if ($manualOverride->recon_status == '1' || $manualOverride->recon_status == '2') {
                                                if ($manualOverride->recon_status == '1') {
                                                    if ($amount > $manualOverride->amount) {
                                                        unset($dataManual['overrides_settlement_type']);
                                                        unset($dataManual['pay_period_from']);
                                                        unset($dataManual['pay_period_to']);
                                                        unset($dataManual['status']);
                                                        $manualOverride->update($dataManual);
                                                    }
                                                } else if ($manualOverride->recon_status == '2') {
                                                    $paidRecon = ReconOverrideHistory::where(['user_id' => $value->id, 'overrider' => $saleUserId, 'pid' => $pid, 'type' => 'Manual', 'is_displayed' => '1', 'during' => 'm2', 'is_ineligible' => '0'])->sum('paid');
                                                    if ($paidRecon && $paidRecon < $amount) {
                                                        unset($dataManual['overrides_settlement_type']);
                                                        unset($dataManual['pay_period_from']);
                                                        unset($dataManual['pay_period_to']);
                                                        unset($dataManual['status']);
                                                        $manualOverride->update($dataManual);
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // Check if Manual override was previously deleted (archived) before creating
                                        if ($this->checkAndSkipIfArchived($value->id, $pid, 'Manual', $saleUserId, false)) {
                                           
                                            UserOverrides::create($dataManual);
                                        }
                                    }
                                }

                                if (isset($dataManual['overrides_settlement_type']) && $dataManual['overrides_settlement_type'] == 'during_m2') {
                                    // subroutineCreatePayrollRecord($value->id, $positionId, $payFrequencyManual);
                                }
                            }
                        }
                    }
                }
            }
            // END MANUAL OVERRIDES CODE
        }
    }

    public function addersOverrides($saleUserId, $pid, $kw, $date, $during = NULL, $forExternal = 0)
    {
        if (!User::find($saleUserId)) {
            return false;
        }
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $productId = $saleMaster->product_id;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : NULL;
        $companyMargin = CompanyProfile::first();
        if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $saleMaster->gross_account_value;
        }
        $check = checkSalesReps($saleUserId, $approvedDate, '');
        if (!$check['status']) {
            return false;
        }

        $x = 1;
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $marginPercentage = $companyMargin->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $saleUserId, 'is_displayed' => '1'])->sum('amount');
        $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'user_id' => $saleUserId, 'is_displayed' => '1'])->sum('clawback_amount');
        $finalCommission = $totalCommission - $totalClawBack;

        // OFFICE OVERRIDE
        $officeOverrides = UserOverrides::with('userdata')->where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Office', 'is_displayed' => '1'])->groupBy('user_id')->get();
        foreach ($officeOverrides as $officeOverride) {
            $userId = $officeOverride->userdata->id;
            $check = checkSalesReps($userId, $approvedDate, '');
            if (!$check['status']) {
                continue;
            }
            $stopPayroll = ($officeOverride->userdata->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $organizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $positionId = $officeOverride->userdata->sub_position_id;
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                $settlementType = 'reconciliation';
                $payFrequencyOffice = NULL;
            } else {
                $settlementType = 'during_m2';
                $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $userId);
            }

            if ($officeOverride->overrides_amount && $officeOverride->overrides_type) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($officeOverride->overrides_type == 'percent') {
                        $amount = (($kw * $officeOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                    // For frdmturf and other Turfdomain
                    if ($officeOverride->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $officeOverride->overrides_amount * $x) / 100);
                    } else if ($officeOverride->overrides_type == 'per kw') {
                        $amount = $officeOverride->overrides_amount * $saleMaster->kw;
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    if ($officeOverride->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $officeOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                } else {
                    if ($officeOverride->overrides_type == 'per kw') {
                        $amount = $officeOverride->overrides_amount * $kw;
                    } else if ($officeOverride->overrides_type == 'percent') {
                        $amount = $finalCommission * ($officeOverride->overrides_amount / 100);
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                }
                //Code to calculate override limit from percentage to normal value for mortgage only company type
               
                $overrideLimitType = $positionOverride->override_limit_type;
                if ($overrideLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                    // Calculate the amount as percentage of original amount
                    $amount = $amount * ($positionOverride->override_limit / 100);
                }else{
                    if ($positionOverride && $positionOverride->status == 1 && $positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                        $amount = $positionOverride->override_limit;
                    }
                }
                

                $officeData = [
                    'user_id' => $userId,
                    'product_id' => $officeOverride->product_id,
                    'product_code' => $officeOverride->product_code,
                    'type' => 'Office',
                    'during' => 'm2 update',
                    'sale_user_id' => $saleUserId,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $officeOverride->overrides_amount,
                    'overrides_type' => $officeOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : NULL,
                    'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : NULL,
                    'overrides_settlement_type' => $settlementType,
                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                    'is_stop_payroll' => $stopPayroll,
                    'office_id' => $officeOverride->office_id,
                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                    'user_worker_type' => $officeOverride->userdata->worker_type,
                    'pay_frequency' => isset($payFrequencyOffice->pay_frequency) ? $payFrequencyOffice->pay_frequency : NULL,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Office', 'is_displayed' => '1'])->sum('amount');
                $dueOffice = $amount - $userOverride;
                if ($dueOffice >= 0.1 || $dueOffice <= -0.1) {
                    // Fix: Define $lastOverride before using it to prevent fatal error
                    $lastOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Office', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                    if ($lastOverride->overrides_settlement_type == 'during_m2') {
                        if ($lastOverride->status == '3') {
                            $officeData['amount'] = $dueOffice;
                            if ($during) {
                                $officeData['during'] = $during;
                            }
                            // Check if Office override was previously deleted (archived) before creating
                            // Use $saleUserId (not null) because Office normal overrides store sale_user_id
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Office', $saleUserId, false)) {
               
                               $userOverride = UserOverrides::create($officeData);
                             }
                            if ($officeData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyOffice);
                            }
                        } else {
                            // Fix: Update existing record with new amount data
                            $officeData['during'] = $lastOverride->during;
                            $officeData['amount'] = $amount;
                            $lastOverride->update($officeData);
                            if ($officeData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyOffice);
                            }
                        }
                    } else if ($lastOverride->overrides_settlement_type == 'reconciliation') {
                        if ($lastOverride->recon_status == '3') {
                            if ($during) {
                                $officeData['during'] = $during;
                            }
                            // Check if Office override was previously deleted (archived) before creating
                            // Use $saleUserId (not null) because Office normal overrides store sale_user_id
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Office', $saleUserId, false)) {
                // Apply removal status from pivot table before creating Office override
                                $userOverride = UserOverrides::create($officeData);
                             }

                            if ($officeData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyOffice);
                            }
                        } else {
                            unset($officeData['overrides_settlement_type']);
                            unset($officeData['pay_period_from']);
                            unset($officeData['pay_period_to']);
                            unset($officeData['status']);
                            // Fix: Update existing record with accumulated amount (previous + due)
                            $officeData['during'] = $lastOverride->during;
                            $officeData['amount'] = $lastOverride->amount + $dueOffice;
                            $lastOverride->update($officeData);
                        }
                    }
                }
            }
        }
        // END OFFICE OVERRIDE

        // DIRECT OVERRIDE
        $directOverrides = UserOverrides::with('userdata')->where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Direct', 'is_displayed' => '1'])->groupBy('user_id')->get();
        foreach ($directOverrides as $directOverride) {
            $userId = $directOverride->userdata->id;
            $check = checkSalesReps($userId, $approvedDate, '');
            if (!$check['status']) {
                continue;
            }
            $stopPayroll = ($directOverride->userdata->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $organizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $positionId = $directOverride->userdata->sub_position_id;
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                $settlementType = 'reconciliation';
                $payFrequencyDirect = NULL;
            } else {
                $settlementType = 'during_m2';
                $payFrequencyDirect = $this->payFrequencyNew($date, $positionId, $userId);
            }

            if ($directOverride->overrides_amount && $directOverride->overrides_type) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($directOverride->overrides_type == 'percent') {
                        $amount = (($kw * $directOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $directOverride->overrides_amount;
                    }
                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                        // For frdmturf domain
                        if ($directOverride->overrides_type == 'percent') {
                            $amount = (($saleMaster->gross_account_value * $directOverride->overrides_amount * $x) / 100);
                        } else if ($directOverride->overrides_type == 'per kw') {
                            $amount = $directOverride->overrides_amount * $saleMaster->kw;
                        } else {
                            $amount = $directOverride->overrides_amount;
                        }
                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    if ($directOverride->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $directOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $directOverride->overrides_amount;
                    }
                } else {
                    if ($directOverride->overrides_type == 'per kw') {
                        $amount = $directOverride->overrides_amount * $kw;
                    } else if ($directOverride->overrides_type == 'percent') {
                        $amount = $finalCommission * ($directOverride->overrides_amount / 100);
                    } else {
                        $amount = $directOverride->overrides_amount;
                    }
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->whereNull('effective_date')->first();
                }
                //Code to calculate override limit from percentage to normal value for mortgage only company type
                $overrideLimitType = $positionOverride->override_limit_type;
                if ($overrideLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                    // Calculate the amount as percentage of original amount
                    $amount = $amount * ($positionOverride->override_limit / 100);
                }else{
                    if ($positionOverride && $positionOverride->status == 1 && $positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                        $amount = $positionOverride->override_limit;
                    }
                }
               

                $directData = [
                    'user_id' => $userId,
                    'product_id' => $directOverride->product_id,
                    'product_code' => $directOverride->product_code,
                    'type' => 'Direct',
                    'during' => 'm2 update',
                    'sale_user_id' => $saleUserId,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $directOverride->overrides_amount,
                    'overrides_type' => $directOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyDirect->pay_period_from) ? $payFrequencyDirect->pay_period_from : NULL,
                    'pay_period_to' => isset($payFrequencyDirect->pay_period_to) ? $payFrequencyDirect->pay_period_to : NULL,
                    'overrides_settlement_type' => $settlementType,
                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                    'is_stop_payroll' => $stopPayroll,
                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                    'user_worker_type' => $directOverride->userdata->worker_type,
                    'pay_frequency' => isset($payFrequencyDirect->pay_frequency) ? $payFrequencyDirect->pay_frequency : NULL,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Direct', 'is_displayed' => '1'])->sum('amount');
                $dueDirect = $amount - $userOverride;
                if ($dueDirect >= 0.1 || $dueDirect <= -0.1) {
                    $lastOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Direct', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                    if ($lastOverride->overrides_settlement_type == 'during_m2') {
                        if ($lastOverride->status == '3') {
                            $directData['amount'] = $dueDirect;
                            if ($during) {
                                $directData['during'] = $during;
                            }
                            // Check if Direct override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Direct', $saleUserId, false)) {
                                $userOverride = UserOverrides::create($directData);
                            }
                            if ($directData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyDirect);
                            }
                        } else {
                            // Fix: Update existing Direct override record with new amount data
                            $directData['during'] = $lastOverride->during;
                            $directData['amount'] = $amount;
                            $lastOverride->update($directData);
                            if ($directData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyDirect);
                            }
                        }
                    } else if ($lastOverride->overrides_settlement_type == 'reconciliation') {
                        if ($lastOverride->recon_status == '3') {
                            $directData['amount'] = $dueDirect;
                            if ($during) {
                                $directData['during'] = $during;
                            }
                            // Check if Direct override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Direct', $saleUserId, false)) {
                                $userOverride = UserOverrides::create($directData);
                            }

                            if ($directData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyDirect);
                            }
                        } else {
                            unset($directData['overrides_settlement_type']);
                            unset($directData['pay_period_from']);
                            unset($directData['pay_period_to']);
                            unset($directData['status']);
                            // Fix: Update existing Direct override with accumulated amount (previous + due)
                            $directData['during'] = $lastOverride->during;
                            $directData['amount'] = $lastOverride->amount + $dueDirect;
                            $lastOverride->update($directData);
                        }
                    }
                }
            }
        }
        // END DIRECT OVERRIDE

        // INDIRECT OVERRIDE
        $indirectOverrides = UserOverrides::with('userdata')->where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Indirect', 'is_displayed' => '1'])->groupBy('user_id')->get();
        foreach ($indirectOverrides as $indirectOverride) {
            $userId = $indirectOverride->userdata->id;
            $check = checkSalesReps($userId, $approvedDate, '');
            if (!$check['status']) {
                continue;
            }
            $stopPayroll = ($indirectOverride->userdata->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $organizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $positionId = $indirectOverride->userdata->sub_position_id;
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                $settlementType = 'reconciliation';
                $payFrequencyDirect = NULL;
            } else {
                $settlementType = 'during_m2';
                $payFrequencyInDirect = $this->payFrequencyNew($date, $positionId, $userId);
            }

            if ($indirectOverride->overrides_amount && $indirectOverride->overrides_type) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($indirectOverride->overrides_type == 'percent') {
                        $amount = (($kw * $indirectOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $indirectOverride->overrides_amount;
                    }
                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                    if(config('app.domain_name') == 'frdmturf') {
                        if ($indirectOverride->overrides_type == 'percent') {
                            $amount = (($saleMaster->gross_account_value * $indirectOverride->overrides_amount * $x) / 100);
                        } else if ($indirectOverride->overrides_type == 'per kw') {
                            $amount = $indirectOverride->overrides_amount * $saleMaster->kw;
                        } else {
                            $amount = $indirectOverride->overrides_amount;
                        }
                    } else {
                        if ($indirectOverride->overrides_type == 'percent') {
                            $amount = (($saleMaster->gross_account_value * $indirectOverride->overrides_amount * $x) / 100);
                        } else if ($indirectOverride->overrides_type == 'per kw') {
                            $amount = $indirectOverride->overrides_amount * $saleMaster->kw;
                        } else {
                            $amount = $indirectOverride->overrides_amount;
                        }
                    }
                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    if ($indirectOverride->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $indirectOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $indirectOverride->overrides_amount;
                    }
                } else {
                    if ($indirectOverride->overrides_type == 'per kw') {
                        $amount = $indirectOverride->overrides_amount * $kw;
                    } else if ($indirectOverride->overrides_type == 'percent') {
                        $amount = $finalCommission * ($indirectOverride->overrides_amount / 100);
                    } else {
                        $amount = $indirectOverride->overrides_amount;
                    }
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->whereNull('effective_date')->first();
                }
                //Code to calculate override limit from percentage to normal value for mortgage only company type
                $overrideLimitType = $positionOverride->override_limit_type;
                if ($overrideLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                    // Calculate the amount as percentage of original amount
                    $amount = $amount * ($positionOverride->override_limit / 100);
                }else{
                    if ($positionOverride && $positionOverride->status == 1 && $positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                        $amount = $positionOverride->override_limit;
                    }  
                }
               

                $inDirectData = [
                    'user_id' => $userId,
                    'product_id' => $indirectOverride->product_id,
                    'product_code' => $indirectOverride->product_code,
                    'type' => 'Indirect',
                    'during' => 'm2 update',
                    'sale_user_id' => $saleUserId,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $indirectOverride->overrides_amount,
                    'overrides_type' => $indirectOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyInDirect->pay_period_from) ? $payFrequencyInDirect->pay_period_from : NULL,
                    'pay_period_to' => isset($payFrequencyInDirect->pay_period_to) ? $payFrequencyInDirect->pay_period_to : NULL,
                    'overrides_settlement_type' => $settlementType,
                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                    'is_stop_payroll' => $stopPayroll,
                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                    'user_worker_type' => $indirectOverride->userdata->worker_type,
                    'pay_frequency' => isset($payFrequencyInDirect->pay_frequency) ? $payFrequencyInDirect->pay_frequency : NULL,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Indirect', 'is_displayed' => '1'])->sum('amount');
                $dueInDirect = $amount - $userOverride;
                if ($dueInDirect >= 0.1 || $dueInDirect <= -0.1) {
                    $lastOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Indirect', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                    if ($lastOverride->overrides_settlement_type == 'during_m2') {
                        if ($lastOverride->status == '3') {
                            $inDirectData['amount'] = $dueInDirect;
                            if ($during) {
                                $inDirectData['during'] = $during;
                            }
                            // Check if Indirect override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Indirect', $saleUserId, false)) {
                              $userOverride = UserOverrides::create($inDirectData);
                            }
                            if ($inDirectData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyInDirect);
                            }
                        } else {
                            // Fix: Update existing Indirect override record with new amount data
                            $inDirectData['during'] = $lastOverride->during;
                            $inDirectData['amount'] = $amount;
                            $lastOverride->update($inDirectData);
                            if ($inDirectData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyInDirect);
                            }
                        }
                    } else if ($lastOverride->overrides_settlement_type == 'reconciliation') {
                        if ($lastOverride->recon_status == '3') {
                            $inDirectData['amount'] = $dueInDirect;
                            if ($during) {
                                $inDirectData['during'] = $during;
                            }
                            // Check if Indirect override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Indirect', $saleUserId, false)) {
                                $userOverride = UserOverrides::create($inDirectData);
                              }

                            if ($inDirectData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyInDirect);
                            }
                        } else {
                            unset($inDirectData['overrides_settlement_type']);
                            unset($inDirectData['pay_period_from']);
                            unset($inDirectData['pay_period_to']);
                            unset($inDirectData['status']);
                            // Fix: Update existing Indirect override with accumulated amount (previous + due)
                            $inDirectData['during'] = $lastOverride->during;
                            $inDirectData['amount'] = $lastOverride->amount + $dueInDirect;
                            $lastOverride->update($inDirectData);
                        }
                    }
                }
            }
        }
        // END INDIRECT OVERRIDE

        // MANUAL OVERRIDE
        $manualOverrides = UserOverrides::with('userdata')->where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'during' => 'm2', 'type' => 'Manual', 'is_displayed' => '1'])->groupBy('user_id')->get();
        foreach ($manualOverrides as $manualOverride) {
            $userId = $manualOverride->userdata->id;
            $check = checkSalesReps($userId, $approvedDate, '');
            if (!$check['status']) {
                continue;
            }
            $stopPayroll = ($manualOverride->userdata->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $organizationHistory = $userOrganizationData['organization'];
            $actualProductId = $userOrganizationData['product']->id;
            $positionId = $manualOverride->userdata->sub_position_id;
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                $settlementType = 'reconciliation';
                $payFrequencyDirect = NULL;
            } else {
                $settlementType = 'during_m2';
                $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $userId);
            }

            if ($manualOverride->overrides_amount && $manualOverride->overrides_type) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($manualOverride->overrides_type == 'percent') {
                        $amount = (($kw * $manualOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $manualOverride->overrides_amount;
                    }
                } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                    if(config('app.domain_name') == 'frdmturf') {
                        if ($manualOverride->overrides_type == 'percent') {
                            $amount = (($saleMaster->gross_account_value * $manualOverride->overrides_amount * $x) / 100);
                        } else if ($manualOverride->overrides_type == 'per kw') {
                            $amount = $manualOverride->overrides_amount * $saleMaster->kw;
                        } else {
                            $amount = $manualOverride->overrides_amount;
                        }
                    } else {
                        if ($manualOverride->overrides_type == 'percent') {
                            $amount = (($saleMaster->gross_account_value * $manualOverride->overrides_amount * $x) / 100);
                        } else if ($manualOverride->overrides_type == 'per kw') {
                            $amount = $manualOverride->overrides_amount * $saleMaster->kw;
                        } else {
                            $amount = $manualOverride->overrides_amount;
                        }
                    }
                } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    if ($manualOverride->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $manualOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $manualOverride->overrides_amount;
                    }
                } else {
                    if ($manualOverride->overrides_type == 'per kw') {
                        $amount = $manualOverride->overrides_amount * $kw;
                    } else if ($manualOverride->overrides_type == 'percent') {
                        $amount = $finalCommission * ($manualOverride->overrides_amount / 100);
                    } else {
                        $amount = $manualOverride->overrides_amount;
                    }
                }
                
                $manualData = [
                    'user_id' => $userId,
                    'product_id' => $manualOverride->product_id,
                    'product_code' => $manualOverride->product_code,
                    'type' => 'Manual',
                    'during' => 'm2 update',
                    'sale_user_id' => $saleUserId,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $manualOverride->overrides_amount,
                    'overrides_type' => $manualOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : NULL,
                    'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : NULL,
                    'overrides_settlement_type' => $settlementType,
                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                    'is_stop_payroll' => $stopPayroll,
                    'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                    'user_worker_type' => $manualOverride->userdata->worker_type,
                    'pay_frequency' => isset($payFrequencyManual->pay_frequency) ? $payFrequencyManual->pay_frequency : NULL,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Manual', 'is_displayed' => '1'])->sum('amount');
                $dueManual = $amount - $userOverride;
                if ($dueManual >= 0.1 || $dueManual <= -0.1) {
                    $lastOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $saleUserId, 'user_id' => $userId, 'type' => 'Manual', 'is_displayed' => '1'])->orderBy('id', 'DESC')->first();
                    if ($lastOverride->overrides_settlement_type == 'during_m2') {
                        if ($lastOverride->status == '3') {
                            $manualData['amount'] = $dueManual;
                            if ($during) {
                                $manualData['during'] = $during;
                            }
                            // Check if Manual override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Manual', $saleUserId, false)) {
                                $userOverride = UserOverrides::create($manualData);
                            }
                            if ($manualData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyManual);
                            }
                        } else {
                            // Fix: Update existing Manual override record with new amount data
                            $manualData['during'] = $lastOverride->during;
                            $manualData['amount'] = $amount;
                            $lastOverride->update($manualData);
                            if ($manualData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyManual);
                            }
                        }
                    } else if ($lastOverride->overrides_settlement_type == 'reconciliation') {
                        if ($lastOverride->recon_status == '3') {
                            $manualData['amount'] = $dueManual;
                            if ($during) {
                                $manualData['during'] = $during;
                            }
                            // Check if Manual override was previously deleted (archived) before creating
                            if ($this->checkAndSkipIfArchived($userId, $pid, 'Manual', $saleUserId, false)) {
                                $userOverride = UserOverrides::create($manualData);
                            }

                            if ($manualData['overrides_settlement_type'] == 'during_m2') {
                                // subroutineCreatePayrollRecord($userId, $positionId, $payFrequencyManual);
                            }
                        } else {
                            unset($manualData['overrides_settlement_type']);
                            unset($manualData['pay_period_from']);
                            unset($manualData['pay_period_to']);
                            unset($manualData['status']);
                            // Fix: Update existing Manual override with accumulated amount (previous + due)
                            $manualData['during'] = $lastOverride->during;
                            $manualData['amount'] = $lastOverride->amount + $dueManual;
                            $lastOverride->update($manualData);
                        }
                    }
                }
            }
        }
        // END MANUAL OVERRIDE
    }

    public function addExternalManualOverride($userId, $type , $overrideAmount, $pid, $kw, $date, $isProjected = false)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        if (!$saleMaster) {
            throw new \Exception("Sales master record not found for PID: {$pid}");
        }
        
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : NULL;
        $productId = $saleMaster->product_id;
        
        // Use projected commissions if isProjected is true, otherwise use actual commissions
        if ($isProjected) {
            $projectedCommission = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $saleMaster->closer1_id])->sum('amount');
            $finalCommission = $projectedCommission;
        } else {
            $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $saleMaster->closer1_id, 'is_displayed' => '1'])->sum('amount');
            $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'user_id' => $saleMaster->closer1_id, 'is_displayed' => '1'])->sum('clawback_amount');
            $finalCommission = $totalCommission - $totalClawBack;
        }
      
        $companyMargin = CompanyProfile::first();
        if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE) || $companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $kw = $saleMaster->gross_account_value;
        }

        $x = 1;
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $marginPercentage = $companyMargin->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        // MANUAL OVERRIDES CODE
        
        $userData = User::where('id', $userId)->first();
            
        $check = checkSalesReps($userData->id, $approvedDate, '');
        if (!$check['status']) {
            return;
        }
        $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
        $organizationHistory = $userOrganizationData['organization'];
        $positionId = $userData->sub_position_id;
       
        $actualProductId = $userOrganizationData['product']->id;
        if ($organizationHistory) {
            $positionId = $organizationHistory->sub_position_id;
        }

        if ($productId) {
            $product = Products::withTrashed()->where('id', $productId)->first();
            $manualProductId = $product->id;
            $manualProductCode = $product->id;
        } else {
            $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
            $manualProductId = $product->id;
            $manualProductCode = $product->id;
        }
        $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;

        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $manualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (!$positionReconciliation) {
            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
        }
        
        $payFrequencyManual = NULL;
        if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
            $settlementType = 'reconciliation';
        } else {
            $settlementType = 'during_m2';
            if(!$isProjected){
                $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $userData->id);
            }
        }

                
        $userData->overrides_amount = $overrideAmount;
        $userData->overrides_type = $type;
        
        if ($userData->overrides_amount && $userData->overrides_type) {
            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($userData->overrides_type == 'percent') {
                    $amount = (((float)$kw * (float)$userData->overrides_amount * $x) / 100);
                } else {
                    $amount = $userData->overrides_amount;
                }
            } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                // For frdmturf domain
                if (config('app.domain_name') == 'frdmturf') {
                    if ($userData->overrides_type == 'percent') {
                        $amount = (($saleMaster->gross_account_value * $userData->overrides_amount * $x) / 100);
                    } else if ($userData->overrides_type == 'per kw') {
                        $amount = (float)$userData->overrides_amount * (float)$saleMaster->kw;
                    } else {
                        $amount = $userData->overrides_amount;
                    }
                } else {
                    // For frdmturf domain
                    if ($userData->overrides_type == 'percent') {
                        $amount = 0;
                    } else if ($userData->overrides_type == 'per kw') {
                        $amount = (float)$userData->overrides_amount * (float)$saleMaster->kw;
                    } else {
                        $amount = $userData->overrides_amount;
                    }
                }
            } else if ($companyMargin->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                if ($userData->overrides_type == 'percent') {
                    $amount = (($saleMaster->gross_account_value * $userData->overrides_amount * $x) / 100);
                } else {
                    $amount = $userData->overrides_amount;
                }
            } else {
                if ($userData->overrides_type == 'per kw') {
                    // Ensure numeric values for KW calculation to prevent string multiplication error
                    $amount = (float)$userData->overrides_amount * (float)$kw;
                } else if ($userData->overrides_type == 'percent') {
                    $amount = $finalCommission * ($userData->overrides_amount / 100);
                } else {
                    $amount = $userData->overrides_amount;
                }
            }
           
        if ($isProjected) {
            // Check if there's an existing actual override with removal status to preserve
            $existingActualOverride = UserOverrides::where([
                'user_id' => $userData->id,
                'pid' => $pid,
                'type' => 'One Time'
            ])->first();

            // Create projected override record (preserve removal status from actual override if exists)
            $dataManual = [
                'user_id' => $userData->id,
                'customer_name' => $saleMaster->customer_name,
                'type' => 'One Time',
                'sale_user_id' => NULL,
                'pid' => $pid,
                'position_id' => $positionId,
                'kw' => $kw,
                'total_override' => $amount,
                'overrides_amount' => $userData->overrides_amount,
                'overrides_type' => $userData->overrides_type,
                'pay_period_from' => NULL,
                'pay_period_to' => NULL,
                'overrides_settlement_type' => $settlementType,
                'status' => 1,
                'is_stop_payroll' => $stopPayroll,
                'office_id' => $userData->office_id,
                'date' => $approvedDate
            ];
            ProjectionUserOverrides::create($dataManual);
        } else {
            // Create actual override record
            $dataManual = [
                'user_id' => $userData->id,
                'product_id' => $manualProductId,
                'type' => 'One Time',
                'sale_user_id' => NULL,
                'pid' => $pid,
                'product_code' => $manualProductCode,
                'kw' => $kw,
                'amount' => $amount,
                'overrides_amount' => $userData->overrides_amount,
                'overrides_type' => $userData->overrides_type,
                'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : NULL,
                'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : NULL,
                'overrides_settlement_type' => $settlementType,
                'status' => $settlementType == 'reconciliation' ? 3 : 1,
                'is_stop_payroll' => $stopPayroll,
                'worker_type' => 'internal',
                'user_worker_type' => $userData->worker_type,
                'pay_frequency' => isset($payFrequencyManual->pay_frequency) ? $payFrequencyManual->pay_frequency : NULL,
            ];

            UserOverrides::create($dataManual);
        }
            
            // Only create payroll record for actual overrides with during_m2 settlement
            if (!$isProjected && isset($dataManual['overrides_settlement_type']) && $dataManual['overrides_settlement_type'] == 'during_m2') {
                // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequencyManual);
            }
        }      
    }

    /**
     * Convert projected overrides to actual overrides when milestone dates are added
     * - Manual overrides (type 'One Time') are moved from projection to actual table
     * - System-generated projected overrides are deleted (not moved)
     */
    public function convertProjectedOverridesToActual($pid, $milestoneDate)
    {
        $projectedOverrides = ProjectionUserOverrides::where('pid', $pid)->get();
        
        foreach ($projectedOverrides as $projectedOverride) {
            if ($projectedOverride->type == 'One Time') {
                // Handle manual overrides: Move from projection to actual table
                $saleMaster = SalesMaster::where('pid', $pid)->first();
                $kw = $saleMaster->kw;
                $companyProfile = CompanyProfile::first();
                $approvedDate = $saleMaster->customer_signoff;
                $productId = $saleMaster->product_id;
                
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $kw = $saleMaster->gross_account_value;
                }
                
                // Get actual commissions for the closer (same as addExternalManualOverride logic)
                // This ensures percentage-based overrides use the closer's commission, not the override recipient's
                $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $saleMaster->closer1_id, 'is_displayed' => '1'])->sum('amount');
                $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'user_id' => $saleMaster->closer1_id, 'is_displayed' => '1'])->sum('clawback_amount');
                $finalCommission = $totalCommission - $totalClawBack;
                
                // Calculate actual amount using the same logic as addExternalManualOverride
                $userData = User::where('id', $projectedOverride->user_id)->first();
                $userData->overrides_amount = $projectedOverride->overrides_amount;
                $userData->overrides_type = $projectedOverride->overrides_type;
                
                // Use the same calculation logic as addExternalManualOverride
                $x = 1;
                if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
                    $marginPercentage = $companyProfile->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }
                
                $actualAmount = 0;
                if ($userData->overrides_amount && $userData->overrides_type) {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        if ($userData->overrides_type == 'percent') {
                            $actualAmount = (($kw * $userData->overrides_amount * $x) / 100);
                        } else {
                            $actualAmount = $userData->overrides_amount;
                        }
                    } else if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                        // For frdmturf domain
                        if (config('app.domain_name') == 'frdmturf') {
                            if ($userData->overrides_type == 'percent') {
                                $actualAmount = (($saleMaster->gross_account_value * $userData->overrides_amount * $x) / 100);
                            } else if ($userData->overrides_type == 'per kw') {
                                $actualAmount = $userData->overrides_amount * $saleMaster->kw;
                            } else {
                                $actualAmount = $userData->overrides_amount;
                            }
                        } else {
                            // For frdmturf domain
                            if ($userData->overrides_type == 'percent') {
                                $actualAmount = 0;
                            } else if ($userData->overrides_type == 'per kw') {
                                $actualAmount = $userData->overrides_amount * $saleMaster->kw;
                            } else {
                                $actualAmount = $userData->overrides_amount;
                            }
                        }
                    } else if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ($userData->overrides_type == 'percent') {
                            $actualAmount = (($saleMaster->gross_account_value * $userData->overrides_amount * $x) / 100);
                        } else {
                            $actualAmount = $userData->overrides_amount;
                        }
                    } else {
                        if ($userData->overrides_type == 'per kw') {
                            $actualAmount = $userData->overrides_amount * $kw;
                        } else if ($userData->overrides_type == 'percent') {
                            $actualAmount = $finalCommission * ($userData->overrides_amount / 100);
                        } else {
                            $actualAmount = $userData->overrides_amount;
                        }
                    }
                }
                
                // Calculate pay period and settlement type like in manual override creation
                $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                $organizationHistory = $userOrganizationData['organization'];
                $positionId = $userData->sub_position_id;
                
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                }
                
                $actualProductId = $userOrganizationData['product']->id;
                if ($productId) {
                    $product = Products::withTrashed()->where('id', $productId)->first();
                    $manualProductId = $product->id;
                    $manualProductCode = $product->id;
                } else {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $manualProductId = $product->id;
                    $manualProductCode = $product->id;
                }
                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $manualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (!$positionReconciliation) {
                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                }
                
                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                
                // Determine settlement type and pay period
                if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                    $settlementType = 'reconciliation';
                    $payFrequencyManual = NULL;
                } else {
                    $settlementType = 'during_m2';
                    $payFrequencyManual = $this->payFrequencyNew($milestoneDate, $positionId, $userData->id);
                }
                
                // Create actual override record (preserve removal status from projection)
                $actualOverrideData = [
                    'user_id' => $projectedOverride->user_id,
                    'product_id' => $manualProductId,
                    'type' => 'One Time',
                    'sale_user_id' => NULL,
                    'pid' => $pid,
                    'product_code' => $manualProductCode,
                    'kw' => $kw,
                    'amount' => $actualAmount,
                    'overrides_amount' => $projectedOverride->overrides_amount,
                    'overrides_type' => $projectedOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : NULL,
                    'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : NULL,
                    'overrides_settlement_type' => $settlementType,
                    'status' => $settlementType == 'reconciliation' ? 3 : 1,
                    'is_stop_payroll' => $projectedOverride->is_stop_payroll,
                    'worker_type' => 'internal',
                    // Preserve display status from projection override  
                    'is_displayed' => $projectedOverride->is_displayed ?? 1
                ];

                UserOverrides::create($actualOverrideData);
                
                // Create payroll record if needed (same as manual override creation)
                if ($settlementType == 'during_m2') {
                    // subroutineCreatePayrollRecord($userData->id, $positionId, $payFrequencyManual);
                }
                
                // Delete the projected override (move it to actual)
                $projectedOverride->delete();
            } else {
                // Handle system-generated projected overrides: Just delete them (don't move)
                $projectedOverride->delete();
            }
        }
        
        // Update SalesMaster to indicate no longer projected
        SalesMaster::where('pid', $pid)->update(['projected_override' => 0]);
    }

}
