<?php

namespace App\Core\Traits;

use App\Models\AdditionalLocations;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\PayRoll;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Services\SalesCalculationContext;

trait PestSubroutineTrait
{
    use OverrideCommissionTrait, PayFrequencyTrait, PayRollClawbackTrait, PayRollCommissionTrait, ReconciliationPeriodTrait;

    public function pestSubroutineProcess($pid)
    {
        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        if (!$checked) {
            return;
        }

        $dateCancelled = $checked->date_cancelled;
        $m1Date = $checked->m1_date;
        $m2Date = $checked->m2_date;
        $closer1Id = $checked->salesMasterProcess->closer1_id;

        if ($closer1Id) {
            // Set context for custom field conversion (Trick Subroutine approach)
            // This enables auto-conversion of 'custom field' to 'per sale' in model events
            // Context stacking is supported - if context already exists, it will be preserved
            $companyProfile = SalesCalculationContext::getCachedCompanyProfile() ?? CompanyProfile::first();

            // Check if Custom Sales Fields feature is enabled for this company
            $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

            try {
                // Only set context when Custom Sales Fields feature is enabled
                // This ensures zero impact on companies without the feature
                if ($isCustomFieldsEnabled) {
                    SalesCalculationContext::set($checked, $companyProfile);
                }

                // WHEN CANCEL DATE
                if ($dateCancelled) {
                    // CLAWBACK CALCULATION
                    $this->generateClawbackWhileCancelDate($checked);
                } else {
                    // IF M1 & M2 BOTH DATE IS PRESENT
                    if ($m1Date && $m2Date) {
                        if ($m1Date != $m2Date) {
                            // CHECK M1 IS PAID OR NOT
                            $this->generateM1ForCloser($checked);
                        }

                        $this->generateCommissionForCloser($checked);
                        $this->generateM2ForCloser($checked);

                        $m2Paid = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
                        if (isset($m2Paid->kw) && $m2Paid->kw != $checked->gross_account_value) {
                            $this->generateM2Update($checked);
                        } else {
                            $this->removeGeneratedM2Update($checked);
                        }
                    } elseif ($m1Date) { // IF ONLY M1 DATE IS PRESENT
                        // CHECK M1 IS PAID OR NOT
                        $this->generateM1ForCloser($checked);
                    }
                }
            } finally {
                // Only clear the context if it was set (feature is enabled)
                if ($isCustomFieldsEnabled) {
                    SalesCalculationContext::clear();
                }
            }
        }
    }

    // GENERATES M1 WHEN M1 DATE IS PRESENT
    public function generateM1ForCloser($saleMasterData)
    {
        $pid = $saleMasterData->pid;
        $m1Date = $saleMasterData->m1_date;
        $customerSignOff = $saleMasterData->customer_signoff;
        $closer1 = $saleMasterData->salesMasterProcess->closer1_id;
        $closer2 = $saleMasterData->salesMasterProcess->closer2_id;

        if ($m1Date && ($closer1 || $closer2)) {
            if ($closer1 && $closer2) {
                $closer = User::where('id', $closer1)->first();
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $closerUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();

                $upfrontAmount = '';
                $upfrontType = '';
                if ($closerUpfront) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;
                }

                $closer2User = User::where('id', $closer2)->first();
                $stop2Payroll = ($closer2User->stop_payroll == 1) ? 1 : 0;
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
                $closer2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
                $upfrontAmount2 = '';
                $upfrontType2 = '';
                if ($closer2Upfront) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;
                }

                if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
                    if ($closer2Upfront) {
                        if ($upfrontType == 'per sale') {
                            $amount = ($upfrontAmount / 2);
                        }
                    } else {
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        }
                    }

                    if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    }

                    $updateData = SaleMasterProcess::where('pid', $pid)->first();
                    $updateData->closer1_m1 = $amount;
                    $updateData->closer1_m1_paid_status = 4;
                    $updateData->save();

                    $payFrequency = $this->payFrequencyNew($m1Date, $subPositionId, $closer1);
                    $userCommission = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if (isset($userCommission) && $userCommission->is_next_payroll == 1) {
                        $data = [
                            'user_id' => $closer1,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                            'redline' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m1Date,
                            'customer_signoff' => $customerSignOff,
                        ];
                    } else {
                        $data = [
                            'user_id' => $closer1,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                            'redline' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m1Date,
                            'pay_period_from' => $payFrequency->pay_period_from,
                            'pay_period_to' => $payFrequency->pay_period_to,
                            'customer_signoff' => $customerSignOff,
                            'is_stop_payroll' => $stopPayroll,
                            'status' => 1,
                        ];
                    }

                    if ($userCommission) {
                        $clawbackSettle = ClawbackSettlement::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->first();
                        if ($clawbackSettle) {
                            if ($userCommission->status == 3 && $clawbackSettle->status == 3) {
                                if (! empty($amount)) {
                                    $commissionExist = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1', 'status' => '1'])->first();
                                    if ($commissionExist) {
                                        $commissionExist->update($data);
                                    } else {
                                        UserCommission::create($data);
                                    }
                                }
                            } elseif ($userCommission->status == 3 && $clawbackSettle->status == 1) {
                                ClawbackSettlement::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->whereNotIn('status', [2, 3])->delete();
                            }
                        }

                        if ($userCommission->status != 3) {
                            UserCommission::where(['id' => $userCommission->id, 'is_displayed' => '1'])->whereNotIn('status', [2, 3])->update($data);
                        }

                        if ($userCommission->is_mark_paid == 0) {
                            $this->updateCommission($closer1, $subPositionId, $amount, $m1Date);
                        }
                    } else {
                        if (! empty($amount)) {
                            UserCommission::create($data);
                            $this->updateCommission($closer1, $subPositionId, $amount, $m1Date);
                        }
                    }
                } else {
                    if (UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->first()) {
                        $updateData = SaleMasterProcess::where('pid', $pid)->first();
                        $updateData->closer1_m1 = 0;
                        $updateData->closer1_m1_paid_status = 4;
                        $updateData->save();
                        UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->delete();
                    }
                }

                if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
                    if ($closerUpfront) {
                        if ($upfrontType2 == 'per sale') {
                            $amount2 = ($upfrontAmount2 / 2);
                        }
                    } else {
                        if ($upfrontType2 == 'per sale') {
                            $amount2 = $upfrontAmount2;
                        }
                    }

                    if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                        $amount2 = $closer2Upfront->upfront_limit;
                    }

                    $updateData = SaleMasterProcess::where('pid', $pid)->first();
                    $updateData->closer2_m1 = $amount2;
                    $updateData->closer2_m1_paid_status = 4;
                    $updateData->save();

                    $payFrequency = $this->payFrequencyNew($m1Date, $subPositionId2, $closer2);
                    $userCommission = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if (isset($userCommission) && $userCommission->is_next_payroll == 1) {
                        $data = [
                            'user_id' => $closer2,
                            'position_id' => $subPositionId2,
                            'pid' => $pid,
                            'amount_type' => 'm1',
                            'amount' => $amount2,
                            'redline' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m1Date,
                            'customer_signoff' => $customerSignOff,
                        ];
                    } else {
                        $data = [
                            'user_id' => $closer2,
                            'position_id' => $subPositionId2,
                            'pid' => $pid,
                            'amount_type' => 'm1',
                            'amount' => $amount2,
                            'redline' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m1Date,
                            'pay_period_from' => $payFrequency->pay_period_from,
                            'pay_period_to' => $payFrequency->pay_period_to,
                            'customer_signoff' => $customerSignOff,
                            'is_stop_payroll' => $stop2Payroll,
                            'status' => 1,
                        ];
                    }

                    if ($userCommission) {
                        $clawbackSettle = ClawbackSettlement::where(['user_id' => $closer2, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->first();
                        if ($clawbackSettle) {
                            if ($userCommission->status == 3 && $clawbackSettle->status == 3) {
                                if (! empty($amount)) {
                                    $commissionExist = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1', 'status' => '1'])->first();
                                    if ($commissionExist) {
                                        $commissionExist->update($data);
                                    } else {
                                        UserCommission::create($data);
                                    }
                                }
                            } elseif ($userCommission->status == 3 && $clawbackSettle->status == 1) {
                                ClawbackSettlement::where(['user_id' => $closer2, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->whereNotIn('status', [2, 3])->delete();
                            }
                        }

                        if ($userCommission->status != 3) {
                            UserCommission::where(['id' => $userCommission->id, 'is_displayed' => '1'])->whereNotIn('status', [2, 3])->update($data);
                        }

                        if ($userCommission->is_mark_paid == 0) {
                            $this->updateCommission($closer2, $subPositionId, $amount, $m1Date);
                        }
                    } else {
                        if (! empty($amount)) {
                            UserCommission::create($data);
                            $this->updateCommission($closer2, $subPositionId, $amount, $m1Date);
                        }
                    }
                } else {
                    if (UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->first()) {
                        $updateData = SaleMasterProcess::where('pid', $pid)->first();
                        $updateData->closer2_m1 = 0;
                        $updateData->closer2_m1_paid_status = 4;
                        $updateData->save();
                        UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->delete();
                    }
                }
            } elseif ($closer1) {
                $closer = User::where('id', $closer1)->first();
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $closerUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                if ($closerUpfront) {
                    $subPositionId = @$userOrganizationHistory['sub_position_id'];
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer1])
                        ->where('upfront_effective_date', '<=', $customerSignOff)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;

                    if ($upfrontAmount && $upfrontType) {
                        $amount = 0;
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        }

                        if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        }
                        $updateData = SaleMasterProcess::where('pid', $pid)->first();
                        $updateData->closer1_m1 = $amount;
                        $updateData->closer1_m1_paid_status = 4;
                        $updateData->save();

                        $userCommission = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        $payFrequency = $this->payFrequencyNew($m1Date, $subPositionId, $closer1);
                        if (isset($userCommission) && $userCommission->is_next_payroll == 1) {
                            $data = [
                                'user_id' => $closer1,
                                'position_id' => $subPositionId,
                                'pid' => $pid,
                                'amount_type' => 'm1',
                                'amount' => $amount,
                                'redline' => 0,
                                'kw' => $saleMasterData->gross_account_value,
                                'date' => $m1Date,
                                'customer_signoff' => $customerSignOff,
                            ];
                        } else {
                            $data = [
                                'user_id' => $closer1,
                                'position_id' => $subPositionId,
                                'pid' => $pid,
                                'amount_type' => 'm1',
                                'amount' => $amount,
                                'redline' => 0,
                                'kw' => $saleMasterData->gross_account_value,
                                'date' => $m1Date,
                                'pay_period_from' => $payFrequency->pay_period_from,
                                'pay_period_to' => $payFrequency->pay_period_to,
                                'customer_signoff' => $customerSignOff,
                                'is_stop_payroll' => $stopPayroll,
                                'status' => 1,
                            ];
                        }

                        if ($userCommission) {
                            $clawbackSettle = ClawbackSettlement::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->first();
                            if ($clawbackSettle) {
                                if ($userCommission->status == 3 && $clawbackSettle->status == 3) {
                                    if (! empty($amount)) {
                                        $commissionExist = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1', 'status' => '1'])->first();
                                        if ($commissionExist) {
                                            $commissionExist->update($data);
                                        } else {
                                            UserCommission::create($data);
                                        }
                                    }
                                } elseif ($userCommission->status == 3 && $clawbackSettle->status == 1) {
                                    ClawbackSettlement::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'type' => 'commission'])->whereNotIn('status', [2, 3])->delete();
                                }
                            }

                            if ($userCommission->status != 3) {
                                UserCommission::where(['id' => $userCommission->id, 'is_displayed' => '1'])->whereNotIn('status', [2, 3])->update($data);
                            }

                            if ($userCommission->is_mark_paid == 0) {
                                $this->updateCommission($closer1, $subPositionId, $amount, $m1Date);
                            }
                        } else {
                            if (! empty($amount)) {
                                UserCommission::create($data);
                                $this->updateCommission($closer1, $subPositionId, $amount, $m1Date);
                            }
                        }
                    } else {
                        if (UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->first()) {
                            $updateData = SaleMasterProcess::where('pid', $pid)->first();
                            $updateData->closer1_m1 = 0;
                            $updateData->closer1_m1_paid_status = 4;
                            $updateData->save();
                            UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->delete();
                        }
                    }
                } else {
                    if (UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->first()) {
                        $updateData = SaleMasterProcess::where('pid', $pid)->first();
                        $updateData->closer1_m1 = 0;
                        $updateData->closer1_m1_paid_status = 4;
                        $updateData->save();
                        UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm1'])->where('status', '!=', 3)->delete();
                    }
                }
            }
        }
    }

    // GENERATES COMMISSION WHEN M2 DATE IS PRESENT
    public function generateCommissionForCloser($saleMasterData)
    {
        $pid = $saleMasterData->pid;
        $closer1 = $saleMasterData->salesMasterProcess->closer1_id;
        $closer2 = $saleMasterData->salesMasterProcess->closer2_id;
        $m2date = $saleMasterData->m2_date;
        $grossAmountValue = $saleMasterData->gross_account_value;
        $approvedDate = $saleMasterData->customer_signoff;
        $companyMargin = CompanyProfile::where('id', 1)->first();
        $overrideSetting = CompanySetting::where('type', 'overrides')->first();

        $closerCommission = 0;
        if ($closer1 && $closer2) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            $commissionPercentage2 = 0;
            $commission2History = UserCommissionHistory::where('user_id', $closer2)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                }
            } else {
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                }
            }

            $updateData = SaleMasterProcess::where('pid', $pid)->first();
            $commission = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first();
            $updateData->closer1_commission = $closer1Commission;
            $updateData->closer2_commission = $closer2Commission;
            $updateData->mark_account_status_id = 3;
            $updateData->save();
            if (! $commission) {
                $this->updateCommission($closer1, 2, $closer1Commission, $m2date);
                $this->updateCommission($closer2, 2, $closer2Commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $this->generateUserOverrides($closer1, $pid);
                    $this->generateUserOverrides($closer2, $pid);
                }
            }
            $closerCommission = ($closer1Commission + $closer2Commission);
        } elseif ($closer1) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
            } else {
                $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
            }

            $updateData = SaleMasterProcess::where('pid', $pid)->first();
            $commission = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->first();
            $updateData->closer1_commission = $closerCommission;
            $updateData->mark_account_status_id = 3;
            $updateData->save();
            if (! $commission) {
                $this->updateCommission($closer1, 2, $closerCommission, $m2date);

                $userCommission = UserCommission::where('pid', $pid)->where('amount_type', 'm2')->where('is_displayed', '1')->first();
                if ($userCommission) {
                    $userCommissionM2Paid = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->where('is_displayed', '1')->first();
                    if ($overrideSetting->status == '1' && empty($userCommissionM2Paid)) {
                        $this->generateUserOverrides($closer1, $pid);
                    }
                } else {
                    $this->generateUserOverrides($closer1, $pid);
                }
            }
        }

        $userCommissionM2Paid = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
        if (! empty($closer1) && empty($userCommissionM2Paid)) {
            $this->generateStackUserOverride($pid);
        }

        $commissionData['closer_commission'] = $closerCommission;

        return $commissionData;
    }

    // GENERATES M2 AFTER COMMISSION WHEN M2 DATE IS PRESENT
    public function generateM2ForCloser($saleMasterData)
    {
        $pid = $saleMasterData->pid;
        $closer1 = $saleMasterData->salesMasterProcess->closer1_id;
        $closer2 = $saleMasterData->salesMasterProcess->closer2_id;
        $customerSignOff = $saleMasterData->customer_signoff;
        $m2date = $saleMasterData->m2_date;

        if ($closer1) {
            $closer1ReconciliationWithholding = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $closer1])->sum('withhold_amount');
            $saleData = SaleMasterProcess::where('pid', $pid)->first();
            $closer1DueM2 = ($saleData->closer1_commission - $saleData->closer1_m1 - $closer1ReconciliationWithholding);

            $saleData->closer1_m2 = $closer1DueM2;
            $saleData->closer1_m2_paid_status = 5;
            $saleData->save();

            $closer = User::where('id', $closer1)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $closerM2 = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $subPositionId = $userOrganizationHistory ? $userOrganizationHistory->sub_position_id : $closer->sub_position_id;
            if (! $closerM2) {
                $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closer1);
                $data = [
                    'user_id' => $closer1,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $closer1DueM2,
                    'redline' => 0,
                    'redline_type' => null,
                    'net_epc' => 0,
                    'kw' => $saleMasterData->gross_account_value,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencyCloser->pay_period_from,
                    'pay_period_to' => $payFrequencyCloser->pay_period_to,
                    'customer_signoff' => $customerSignOff,
                    'is_stop_payroll' => $stopPayroll,
                    'status' => 1,
                ];
                UserCommission::create($data);
            } else {
                $closerM2Paid = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->first();
                if (! $closerM2Paid) {
                    $closerM2UnPaid = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '1'])->first();
                    if ($closerM2UnPaid && $closerM2UnPaid->is_next_payroll == 1) {
                        $data = [
                            'user_id' => $closer1,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm2',
                            'amount' => $closer1DueM2,
                            'redline' => '0',
                            'redline_type' => null,
                            'net_epc' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m2date,
                            'customer_signoff' => $customerSignOff,
                        ];
                    } else {
                        $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closer1);
                        $data = [
                            'user_id' => $closer1,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm2',
                            'amount' => $closer1DueM2,
                            'redline' => 0,
                            'redline_type' => null,
                            'net_epc' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m2date,
                            'pay_period_from' => $payFrequencyCloser->pay_period_from,
                            'pay_period_to' => $payFrequencyCloser->pay_period_to,
                            'customer_signoff' => $customerSignOff,
                            'is_stop_payroll' => $stopPayroll,
                            'status' => 1,
                        ];
                    }
                    if ($closerM2UnPaid) {
                        UserCommission::where(['id' => $closerM2UnPaid->id, 'status' => '1', 'is_displayed' => '1'])->update($data);
                    } else {
                        UserCommission::create($data);
                    }
                }
            }
        }

        if ($closer2) {
            $closer2ReconciliationWithholding = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $closer2])->sum('withhold_amount');
            $saleData = SaleMasterProcess::where('pid', $pid)->first();
            $closer2DueM2 = ($saleData->closer2_commission - $saleData->closer2_m1 - $closer2ReconciliationWithholding);

            $saleData->closer2_m2 = $closer2DueM2;
            $saleData->closer2_m2_paid_status = 5;
            $saleData->save();

            $closer = User::where('id', $closer2)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $closerM2 = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer1)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $subPositionId = $userOrganizationHistory ? $userOrganizationHistory->sub_position_id : $closer->sub_position_id;
            if (! $closerM2) {
                $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closer2);
                UserCommission::create([
                    'user_id' => $closer2,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $closer2DueM2,
                    'redline' => 0,
                    'redline_type' => null,
                    'net_epc' => 0,
                    'kw' => $saleMasterData->gross_account_value,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencyCloser->pay_period_from,
                    'pay_period_to' => $payFrequencyCloser->pay_period_to,
                    'customer_signoff' => $customerSignOff,
                    'is_stop_payroll' => $stopPayroll,
                    'status' => 1,
                ]);
            } else {
                $closerM2Paid = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->first();
                if (! $closerM2Paid) {
                    $closerM2UnPaid = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '1'])->first();
                    if ($closerM2UnPaid && $closerM2UnPaid->is_next_payroll == 1) {
                        $data = [
                            'user_id' => $closer2,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm2',
                            'amount' => $closer2DueM2,
                            'redline' => '0',
                            'redline_type' => null,
                            'net_epc' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m2date,
                            'customer_signoff' => $customerSignOff,
                        ];
                    } else {
                        $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closer2);
                        $data = [
                            'user_id' => $closer2,
                            'position_id' => $subPositionId,
                            'pid' => $pid,
                            'amount_type' => 'm2',
                            'amount' => $closer2DueM2,
                            'redline' => 0,
                            'redline_type' => null,
                            'net_epc' => 0,
                            'kw' => $saleMasterData->gross_account_value,
                            'date' => $m2date,
                            'pay_period_from' => $payFrequencyCloser->pay_period_from,
                            'pay_period_to' => $payFrequencyCloser->pay_period_to,
                            'customer_signoff' => $customerSignOff,
                            'is_stop_payroll' => $stopPayroll,
                            'status' => 1,
                        ];
                    }
                    if ($closerM2UnPaid) {
                        UserCommission::where(['id' => $closerM2UnPaid->id, 'status' => '1', 'is_displayed' => '1'])->update($data);
                    } else {
                        UserCommission::create($data);
                    }
                }
            }
        }
    }

    // GENERATES M2 UPDATE AFTER M2 GOT PAID & GROSS MAOUNT CHANGES
    public function generateM2Update($saleMasterData)
    {
        $pid = $saleMasterData->pid;
        $closer1 = $saleMasterData->salesMasterProcess->closer1_id;
        $closer2 = $saleMasterData->salesMasterProcess->closer2_id;
        $customerSignOff = $saleMasterData->customer_signoff;

        $saleData = SaleMasterProcess::where('pid', $pid)->first();
        if ($closer1) {
            $closer1ReconciliationWithholding = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $closer1])->sum('withhold_amount');
            $commissionss = UserCommission::where(['pid' => $pid, 'user_id' => $closer1, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
            $clawbackss = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closer1, 'clawback_type' => 'next payroll', 'type' => 'commission', 'status' => 3, 'is_displayed' => '1'])->first();
            if (! empty($commissionss) && ! empty($clawbackss)) {
                $saleData->closer1_m1 = 0;
            }

            $closer1DueM2 = ($saleData->closer1_commission - $saleData->closer1_m1 - $closer1ReconciliationWithholding);
            $commissionsm2 = UserCommission::where(['pid' => $pid, 'user_id' => $closer1, 'status' => 3, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');

            if ($commissionsm2 && $closer1DueM2 != $commissionsm2) {
                if ($commissionsm2 > $closer1DueM2) {
                    UserCommission::where(['pid' => $pid, 'is_displayed' => '1', 'amount_type' => 'm2 update'])->where('status', '!=', 3)->delete();
                    $amount = ($commissionsm2 - $closer1DueM2);
                    $this->generateAddersClawback($closer1, $pid, $amount, $customerSignOff);
                } else {
                    ClawbackSettlement::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('adders_type', ['m2 update', 'Stack m2 update'])->where('status', '!=', 3)->delete();
                    $amount = ($closer1DueM2 - $commissionsm2);
                    $this->generateAddersCommission($closer1, $pid, $amount, $customerSignOff);
                }
            }

            $this->generateOverrideUpdate($closer1, $pid);
        }
        if ($closer2) {
            $closer2ReconciliationWithholding = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $closer2])->sum('withhold_amount');

            $commissionss = UserCommission::where(['pid' => $pid, 'user_id' => $closer2, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
            $clawbackss = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closer2, 'clawback_type' => 'next payroll', 'type' => 'commission', 'status' => 3, 'is_displayed' => '1'])->first();
            if (! empty($commissionss) && ! empty($clawbackss)) {
                $saleData->closer2_m1 = 0;
            }

            $closer2DueM2 = ($saleData->closer2_commission - $saleData->closer2_m1 - $closer2ReconciliationWithholding);
            $commissionsm2 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2, 'status' => 3, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');

            if ($commissionsm2 && $closer2DueM2 != $commissionsm2) {
                if ($commissionsm2 > $closer2DueM2) {
                    $amount = ($commissionsm2 - $closer2DueM2);
                    $this->generateAddersClawback($closer2, $pid, $amount, $customerSignOff);
                } else {
                    $amount = ($closer2DueM2 - $commissionsm2);
                    $this->generateAddersCommission($closer2, $pid, $amount, $customerSignOff);
                }
            }

            $this->generateOverrideUpdate($closer2, $pid);
        }
        if ($closer1) {
            $this->generateStackOverrideUpdate($pid);
        }
    }

    // GENERATES CLAWBACK WHEN CANCEL DATE IS PRESENT
    public function generateClawbackWhileCancelDate($saleMasterData)
    {
        $pid = $saleMasterData->pid;
        $cancelDate = $saleMasterData->date_cancelled;
        $closer1 = $saleMasterData->salesMasterProcess->closer1_id;
        $closer2 = $saleMasterData->salesMasterProcess->closer2_id;
        $companySetting = CompanySetting::where('type', 'reconciliation')->first();

        SaleMasterProcess::where('pid', $pid)->update([
            'closer1_m1' => 0,
            'closer2_m1' => 0,
            'setter1_m1' => 0,
            'setter2_m1' => 0,
            'closer1_m2' => 0,
            'closer2_m2' => 0,
            'setter1_m2' => 0,
            'setter2_m2' => 0,
            'closer1_commission' => 0,
            'closer2_commission' => 0,
            'setter1_commission' => 0,
            'setter2_commission' => 0,
            'closer1_m1_paid_status' => null,
            'closer2_m1_paid_status' => null,
            'setter1_m1_paid_status' => null,
            'setter2_m1_paid_status' => null,
            'closer1_m2_paid_status' => null,
            'closer2_m2_paid_status' => null,
            'setter1_m2_paid_status' => null,
            'setter2_m2_paid_status' => null,
        ]);

        if ($closer1) {
            $closer = User::where('id', $closer1)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $closer1Withheld = UserReconciliationWithholding::where('pid', $pid)->where('closer_id', $closer1)->first();
            $closer1WithheldAmount = 0;
            if ($closer1Withheld) {
                if ($closer1Withheld->status == 'paid') {
                    $closer1WithheldAmount = $closer1Withheld->withhold_amount;
                } else {
                    $closer1Withheld->withhold_amount = 0;
                    $closer1Withheld->status = 'clawdback';
                    $closer1Withheld->save();
                }
            }
            $positionReconciliation = PositionReconciliations::where(['position_id' => $closer->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($cancelDate);
                $payPeriodFrom = $payFrequency->pay_period_from;
                $payPeriodTo = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($cancelDate, $closer->sub_position_id, $closer1);
                $payPeriodFrom = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $payPeriodTo = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->delete();
            $closer1Amount = UserCommission::where(['user_id' => $closer1, 'pid' => $pid, 'status' => 3])->sum('amount') ?? 0;
            $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $closer1, 'pid' => $pid])->sum('clawback_amount') ?? 0;
            $clawback = (($closer1Amount - $closer1PaidClawback) + $closer1WithheldAmount);
            if (! empty($clawback)) {
                ClawbackSettlement::create([
                    'user_id' => $closer1,
                    'position_id' => 2,
                    'pid' => $pid,
                    'clawback_amount' => $clawback,
                    'clawback_type' => $clawbackType,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($closer1, 2, $clawback, $payFrequency, $pid);
                }
            }
        }

        if ($closer2) {
            $closer = User::where('id', $closer2)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $closer2Withheld = UserReconciliationWithholding::where('pid', $pid)->where('closer_id', $closer2)->first();
            $closer2WithheldAmount = 0;
            if ($closer2Withheld) {
                if ($closer2Withheld->status == 'paid') {
                    $closer2WithheldAmount = $closer2Withheld->withhold_amount;
                } else {
                    $closer2Withheld->withhold_amount = 0;
                    $closer2Withheld->status = 'clawdback';
                    $closer2Withheld->save();
                }
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $closer->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($cancelDate);
                $payPeriodFrom = $payFrequency->pay_period_from;
                $payPeriodTo = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($cancelDate, $closer->sub_position_id, $closer2);
                $payPeriodFrom = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $payPeriodTo = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->delete();
            $closer2Amount = UserCommission::where(['user_id' => $closer2, 'pid' => $pid, 'status' => 3])->sum('amount') ?? 0;
            $closer2PaidClawback = ClawbackSettlement::where(['user_id' => $closer2, 'pid' => $pid])->sum('clawback_amount') ?? 0;
            $clawback = (($closer2Amount - $closer2PaidClawback) + $closer2WithheldAmount);
            if (! empty($clawback)) {
                ClawbackSettlement::create([
                    'user_id' => $closer2,
                    'position_id' => 2,
                    'pid' => $pid,
                    'clawback_amount' => $clawback,
                    'clawback_type' => $clawbackType,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($closer2, 2, $clawback, $payFrequency, $pid);
                }
            }
        }

        $this->generateOveridesClawback($pid, $cancelDate);

        $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
        $saleMasterProcess->mark_account_status_id = 1;
        $saleMasterProcess->save();
    }

    public function generateOveridesClawback($pid, $date)
    {
        ClawbackSettlement::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'type' => 'overrides'])->delete();
        UserOverrides::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->delete();
        $data = UserOverrides::with('userdata')->where(['pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->get();
        if (count($data) > 0) {
            $data->transform(function ($data) use ($date, $pid) {
                $companySetting = CompanySetting::where('type', 'reconciliation')->first();
                $positionReconciliation = PositionReconciliations::where(['position_id' => $data->userdata->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
                if ($companySetting->status == '1' && $positionReconciliation) {
                    $clawbackType = 'reconciliation';
                    $payFrequency = $this->reconciliationPeriod($date);
                    $pay_period_from = $payFrequency->pay_period_from;
                    $pay_period_to = $payFrequency->pay_period_to;
                } else {
                    $clawbackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $data->userdata->sub_position_id, $data->user_id);
                    $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                    $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
                }

                $clawbackSettlement = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'type' => 'overrides', 'is_displayed' => '1'])->first();
                if (! $clawbackSettlement) {
                    ClawbackSettlement::create([
                        'user_id' => $data->user_id,
                        'position_id' => $data->userdata->sub_position_id,
                        'sale_user_id' => $data->sale_user_id,
                        'pid' => $pid,
                        'clawback_amount' => $data->amount,
                        'clawback_type' => $clawbackType,
                        'type' => 'overrides',
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]);
                } else {
                    $clawbackSettlement->clawback_amount += $data->amount;
                    $clawbackSettlement->save();
                }

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($data->user_id, $data->userdata->sub_position_id, $data->amount, $payFrequency, $pid);
                }
            });
        }
    }

    // DURING CLOSER CHANGE TO CREATE COMMISSION CLAWBACK
    public function pestClawbackSalesData($closerId, $checked)
    {
        $closer1Withheld_amount = 0;
        $date = date('Y-m-d');
        $pid = $checked->pid;
        $companySetting = CompanySetting::where('type', 'reconciliation')->first();

        if ($closerId) {
            UserCommission::where(['user_id' => $closerId, 'pid' => $pid])->where('is_displayed', '1')->where('status', '!=', 3)->delete();

            $closer = User::where('id', $closerId)->first();
            $closer1Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
            if ($closer1Withheld) {
                if ($closer1Withheld->status == 'paid') {
                    $closer1Withheld_amount = $closer1Withheld->withhold_amount;
                } else {
                    $closer1Withheld->withhold_amount = 0;
                    $closer1Withheld->status = 'clawdback';
                    $closer1Withheld->save();
                }
            }
            $positionReconciliation = PositionReconciliations::where(['position_id' => $closer->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($date);
                $pay_period_from = $payFrequency->pay_period_from;
                $pay_period_to = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $closer->sub_position_id, $closerId);
                $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $closer1Amount = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'status' => 3])->where('is_displayed', '1')->sum('amount');
            $clawback = ($closer1Amount + $closer1Withheld_amount);
            if (! empty($clawback)) {
                ClawbackSettlement::create([
                    'user_id' => $closerId,
                    'position_id' => 2,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawback,
                    'clawback_type' => $clawbackType,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($closerId, 2, $clawback, $payFrequency, $pid);
                }
            }
        }

        $this->pestOveridesClawbackData($closerId, $pid, $date);
    }

    // DURING CLOSER CHANGE TO CREATE OVERRIDES CLAWBACK
    public function pestOveridesClawbackData($userId, $pid, $date)
    {
        UserOverrides::where(['sale_user_id' => $userId, 'is_displayed' => '1', 'pid' => $pid])->where('status', '!=', '3')->delete();
        $data = UserOverrides::with('userdata')->where(['sale_user_id' => $userId, 'is_displayed' => '1', 'pid' => $pid, 'status' => '3'])->get();
        if (count($data) > 0) {
            $data->transform(function ($data) use ($date, $pid) {
                $companySetting = CompanySetting::where('type', 'reconciliation')->first();
                $positionReconciliation = PositionReconciliations::where(['position_id' => $data->userdata->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
                if ($companySetting->status == '1' && $positionReconciliation) {
                    $clawbackType = 'reconciliation';
                    $payFrequency = $this->reconciliationPeriod($date);
                    $pay_period_from = $payFrequency->pay_period_from;
                    $pay_period_to = $payFrequency->pay_period_to;
                } else {
                    $clawbackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $data->userdata->sub_position_id, $data->user_id);
                    $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                    $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
                }

                $clawbackSettlement = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'type' => 'overrides', 'is_displayed' => '1'])->first();
                if (empty($clawbackSettlement)) {
                    ClawbackSettlement::create([
                        'user_id' => $data->user_id,
                        'position_id' => $data->userdata->sub_position_id,
                        'sale_user_id' => $data->sale_user_id,
                        'pid' => $pid,
                        'clawback_amount' => $data->amount,
                        'clawback_type' => $clawbackType,
                        'type' => 'overrides',
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]);
                } else {
                    $clawbackSettlement->clawback_amount += $data->amount;
                    $clawbackSettlement->save();
                }

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($data->user_id, $data->userdata->sub_position_id, $data->amount, $payFrequency, $pid);
                }
            });
        }
    }

    // GENERATE USER OVERRIDES
    public function generateUserOverrides($saleUserId, $pid)
    {
        UserOverrides::where(['sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 1, 'is_displayed' => '1'])->delete();
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $grossAmountValue = $saleMaster->gross_account_value;
        $date = $saleMaster->m2_date;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $recruiterIdData = User::where('id', $saleUserId)->first();
        $companyMargin = CompanyProfile::where('id', 1)->first();
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $marginPercentage = $companyMargin->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        } else {
            $x = 1;
        }

        // office overrides code
        if ($recruiterIdData->office_id) {
            $officeId = $recruiterIdData->office_id;
            $userIdArr1 = User::where('office_id', $officeId)->where('id', '!=', '1')->where('id', '!=', $saleUserId)->pluck('id')->toArray();
            $userIdArr2 = AdditionalLocations::where('office_id', $officeId)->whereHas('user', function ($q) {
                $q->where('id', '!=', '1');
            })->where('user_id', '!=', $saleUserId)->pluck('user_id')->toArray();
            if (count($userIdArr1) > 0) {
                foreach ($userIdArr1 as $value) {
                    $userdata = User::where('id', $value)->first();
                    $stopPayroll = ($userdata->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $userdata->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                    if ($positionReconciliation) {
                        $settlementType = 'reconciliation';
                        $payFrequencyOffice = $this->reconciliationPeriod($date);
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $value);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                    if ($positionOverride) {
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value, 'type' => 'Office', 'status' => '1'])->first();
                        if (empty($overrideStatus) && $userdata->dismiss == 0) {
                            $userdata->office_overrides_amount = 0;
                            $userdata->office_overrides_type = '';
                            $overrideHistory = UserOverrideHistory::where('user_id', $value)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                $userdata->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                $userdata->office_overrides_type = $overrideHistory->office_overrides_type;

                                if ($userdata->office_overrides_amount) {
                                    if ($userdata->office_overrides_type == 'percent') {
                                        $amount = (($grossAmountValue * $userdata->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userdata->office_overrides_amount;
                                    }

                                    $officeData = [
                                        'user_id' => $value,
                                        'type' => 'Office',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $grossAmountValue,
                                        'amount' => $amount,
                                        'overrides_amount' => $userdata->office_overrides_amount,
                                        'overrides_type' => $userdata->office_overrides_type,
                                        'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : null,
                                        'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $stopPayroll,
                                        'office_id' => $officeId,
                                    ];

                                    $officeOverrides = UserOverrides::where(['user_id' => $value, 'type' => 'Office', 'pid' => $pid, 'is_displayed' => '1'])->first();
                                    if ($officeOverrides) {
                                        if ($amount > $officeOverrides->amount) {
                                            UserOverrides::where('id', $officeOverrides->id)->where('status', 1)->where('is_displayed', '1')->delete();
                                            $userOverrode = UserOverrides::create($officeData);
                                        }
                                    } else {
                                        UserOverrides::where(['user_id' => $value, 'pid' => $pid, 'type' => 'Office', 'status' => 1])->where('is_displayed', '1')->delete();
                                        $userOverrode = UserOverrides::create($officeData);
                                    }

                                    if (isset($userOverrode) && $userOverrode && $userOverrode->overrides_settlement_type == 'during_m2') {
                                        if (! PayRoll::where(['user_id' => $value, 'status' => 1, 'pay_period_from' => $payFrequencyOffice->pay_period_from, 'pay_period_to' => $payFrequencyOffice->pay_period_to])->first()) {
                                            PayRoll::create([
                                                'user_id' => $value,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'override' => 0,
                                                'pay_period_from' => $payFrequencyOffice->pay_period_from,
                                                'pay_period_to' => $payFrequencyOffice->pay_period_to,
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (count($userIdArr2) > 0) {
                foreach ($userIdArr2 as $value) {
                    $userdata1 = User::where('id', $value)->first();
                    $stopPayroll = ($userdata1->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $userdata1->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                    if ($positionReconciliation) {
                        $settlementType = 'reconciliation';
                        $payFrequencyOffice = $this->reconciliationPeriod($date);
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $value);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                    if ($positionOverride) {
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value, 'type' => 'Office', 'status' => 1])->first();
                        if (empty($overrideStatus) && $userdata1->dismiss == 0) {
                            $userdata1->office_overrides_amount = 0;
                            $userdata1->office_overrides_type = '';
                            $overrideHistory = UserAdditionalOfficeOverrideHistory::where('user_id', $value)->where('office_id', $officeId)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                $userdata1->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                $userdata1->office_overrides_type = $overrideHistory->office_overrides_type;

                                if ($userdata1->office_overrides_amount) {
                                    if ($userdata1->office_overrides_type == 'percent') {
                                        $amount = (($grossAmountValue * $userdata1->office_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $userdata1->office_overrides_amount;
                                    }

                                    $officeData = [
                                        'user_id' => $value,
                                        'type' => 'Office',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $grossAmountValue,
                                        'amount' => $amount,
                                        'overrides_amount' => $userdata1->office_overrides_amount,
                                        'overrides_type' => $userdata1->office_overrides_type,
                                        'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : null,
                                        'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $stopPayroll,
                                        'office_id' => $officeId,
                                    ];

                                    $officeOverrides = UserOverrides::where(['user_id' => $value, 'type' => 'Office', 'pid' => $pid, 'is_displayed' => '1'])->first();
                                    if ($officeOverrides) {
                                        if ($amount > $officeOverrides->amount) {
                                            UserOverrides::where(['id' => $officeOverrides->id, 'status' => '1', 'is_displayed' => '1'])->delete();
                                            $userOverrode = UserOverrides::create($officeData);
                                        }
                                    } else {
                                        UserOverrides::where(['user_id' => $value, 'pid' => $pid, 'type' => 'Office', 'status' => 1, 'is_displayed' => '1'])->delete();
                                        $userOverrode = UserOverrides::create($officeData);
                                    }

                                    if (isset($userOverrode) && $userOverrode && $userOverrode->overrides_settlement_type == 'during_m2') {
                                        if (! PayRoll::where(['user_id' => $value, 'status' => 1, 'pay_period_from' => $payFrequencyOffice->pay_period_from, 'pay_period_to' => $payFrequencyOffice->pay_period_to])->first()) {
                                            PayRoll::create([
                                                'user_id' => $value,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'override' => 0,
                                                'pay_period_from' => $payFrequencyOffice->pay_period_from,
                                                'pay_period_to' => $payFrequencyOffice->pay_period_to,
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // end office overrides code

        // Direct overrides code
        if ($recruiterIdData->recruiter_id) {
            if (! empty($recruiterIdData->additional_recruiter_id1) && empty($recruiterIdData->additional_recruiter_id2)) {
                $recruiter_ids = $recruiterIdData->recruiter_id.','.$recruiterIdData->additional_recruiter_id1;
            } elseif (! empty($recruiterIdData->additional_recruiter_id1) && ! empty($recruiterIdData->additional_recruiter_id2)) {
                $recruiter_ids = $recruiterIdData->recruiter_id.','.$recruiterIdData->additional_recruiter_id1.','.$recruiterIdData->additional_recruiter_id2;
            } else {
                $recruiter_ids = $recruiterIdData->recruiter_id;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::with('recruiter')->whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
            if (count($directs) > 0) {
                foreach ($directs as $value) {
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $value->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                    if ($positionReconciliation) {
                        $settlementType = 'reconciliation';
                        $payFrequency = $this->reconciliationPeriod($date);
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequency = $this->payFrequencyNew($date, $positionId, $value->id);
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '1', 'status' => '1'])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'type' => 'Direct', 'status' => 1])->first();
                    if ($positionOverride && ! $overrideStatus) {
                        $value->direct_overrides_amount = 0;
                        $value->direct_overrides_type = '';
                        $overrideHistory = UserOverrideHistory::where('user_id', $value->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory) {
                            $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                            $value->direct_overrides_type = $overrideHistory->direct_overrides_type;

                            if ($value->direct_overrides_amount) {
                                if ($value->direct_overrides_type == 'percent') {
                                    $amount = (($grossAmountValue * $value->direct_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }

                                $dataDirect = [
                                    'user_id' => $value->id,
                                    'type' => 'Direct',
                                    'sale_user_id' => $saleUserId,
                                    'pid' => $pid,
                                    'kw' => $grossAmountValue,
                                    'amount' => $amount,
                                    'overrides_amount' => $value->direct_overrides_amount,
                                    'overrides_type' => $value->direct_overrides_type,
                                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                ];

                                $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                if ($overrideSystemSetting) {
                                    $userOverridess = UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('type', ['Indirect', 'Manual'])->first();
                                    if ($userOverridess) {
                                        if ($amount > $userOverridess->amount) {
                                            UserOverrides::where('id', $userOverridess->id)->where('status', 1)->where('is_displayed', '1')->delete();
                                            $userOverrode = UserOverrides::create($dataDirect);
                                        }
                                    } else {
                                        UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Direct', 'status' => 1])->where('is_displayed', '1')->delete();
                                        $userOverrode = UserOverrides::create($dataDirect);
                                    }
                                } else {
                                    $userOverrode = UserOverrides::create($dataDirect);
                                }

                                if (isset($userOverrode) && $userOverrode && $settlementType == 'reconciliation') {
                                    $totalUserOverrides = UserOverrides::where(['user_id' => $value->id, 'status' => 1, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->sum('amount');
                                    $payReconciliation = UserReconciliationCommission::where(['user_id' => $value->id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                    if ($payReconciliation) {
                                        $payReconciliation->overrides = $totalUserOverrides;
                                        $payReconciliation->save();
                                    } else {
                                        UserReconciliationCommission::create([
                                            'user_id' => $value->id,
                                            'overrides' => $totalUserOverrides,
                                            'amount' => 0,
                                            'clawbacks' => 0,
                                            'total_due' => 0,
                                            'period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                            'period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                            'status' => 'pending',
                                        ]);
                                    }
                                } elseif (isset($userOverrode) && $userOverrode && $userOverrode->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $value->id, 'status' => 1, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $value->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'override' => 0,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $stopPayroll,
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                    // indirect
                    $indirect_recruiter = User::with('positionDetail')->where('id', $value->id)->first();
                    if ($indirect_recruiter->recruiter_id) {
                        $recruiter_ids = $indirect_recruiter->recruiter_id;
                        if (! empty($indirect_recruiter->additional_recruiter_id1)) {
                            $recruiter_ids .= ','.$indirect_recruiter->additional_recruiter_id1;
                        }
                        if (! empty($indirect_recruiter->additional_recruiter_id2)) {
                            $recruiter_ids .= ','.$indirect_recruiter->additional_recruiter_id2;
                        }

                        $idsArr = explode(',', $recruiter_ids);
                        $additional = User::with('positionDetail', 'recruiter')->where('id', '!=', '1')->whereIn('id', $idsArr)->get();
                        if (count($additional) > 0) {
                            foreach ($additional as $val) {
                                $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;

                                $organizationHistory = UserOrganizationHistory::where('user_id', $val->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($organizationHistory) {
                                    $positionId = $organizationHistory->sub_position_id;
                                } else {
                                    $positionId = $val->sub_position_id;
                                }

                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                                if ($positionReconciliation) {
                                    $settlementType = 'reconciliation';
                                    $payFrequency1 = $this->reconciliationPeriod($date);
                                } else {
                                    $settlementType = 'during_m2';
                                    $payFrequency1 = $this->payFrequencyNew($date, $positionId, $val->id);
                                }

                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '2', 'status' => '1'])->first();
                                $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $val->id, 'type' => 'Indirect'])->first();
                                if ($positionOverride && ! $overrideStatus) {
                                    $val->indirect_overrides_amount = 0;
                                    $val->indirect_overrides_type = '';
                                    $overrideHistory = UserOverrideHistory::where('user_id', $val->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($overrideHistory) {
                                        $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                        $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                                        if ($val->indirect_overrides_amount) {
                                            if ($val->indirect_overrides_type == 'percent') {
                                                $amount = (($grossAmountValue * $val->indirect_overrides_amount * $x) / 100);
                                            } else {
                                                $amount = $val->indirect_overrides_amount;
                                            }

                                            $dataIndirect = [
                                                'user_id' => $val->id,
                                                'type' => 'Indirect',
                                                'sale_user_id' => $saleUserId,
                                                'pid' => $pid,
                                                'kw' => $grossAmountValue,
                                                'amount' => $amount,
                                                'overrides_amount' => $val->indirect_overrides_amount,
                                                'overrides_type' => $val->indirect_overrides_type,
                                                'pay_period_from' => isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null,
                                                'pay_period_to' => isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null,
                                                'overrides_settlement_type' => $settlementType,
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                            ];

                                            $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                            if ($overrideSystemSetting) {
                                                $userOverridess = UserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'is_displayed' => '1', 'status' => 1])->whereIn('type', ['Direct', 'Manual'])->first();
                                                if ($userOverridess) {
                                                    if ($amount > $userOverridess->amount) {
                                                        UserOverrides::where(['id' => $userOverridess->id, 'status' => 1, 'is_displayed' => '1'])->delete();
                                                        $userOverrode = UserOverrides::create($dataIndirect);
                                                    }
                                                } else {
                                                    UserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'type' => 'Indirect', 'status' => 1, 'is_displayed' => '1'])->delete();
                                                    $userOverrode = UserOverrides::create($dataIndirect);
                                                }
                                            } else {
                                                $userOverrode = UserOverrides::create($dataIndirect);
                                            }

                                            if (isset($userOverrode) && $userOverrode && $settlementType == 'reconciliation') {
                                                $totalUserOverrides = UserOverrides::where(['user_id' => $val->id, 'status' => 1, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->sum('amount');
                                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $val->id, 'period_from' => $payFrequency1->pay_period_from, 'period_to' => $payFrequency1->pay_period_to, 'status' => 'pending'])->first();
                                                if ($payReconciliation) {
                                                    $payReconciliation->overrides = $totalUserOverrides;
                                                    $payReconciliation->save();
                                                } else {
                                                    UserReconciliationCommission::create([
                                                        'user_id' => $val->id,
                                                        'overrides' => $totalUserOverrides,
                                                        'amount' => 0,
                                                        'clawbacks' => 0,
                                                        'total_due' => 0,
                                                        'period_from' => isset($payFrequency1->pay_period_from) ? $payFrequency1->pay_period_from : null,
                                                        'period_to' => isset($payFrequency1->pay_period_to) ? $payFrequency1->pay_period_to : null,
                                                        'status' => 'pending',
                                                    ]);
                                                }
                                            } elseif (isset($userOverrode) && $userOverrode && $userOverrode->overrides_settlement_type == 'during_m2') {
                                                if (! PayRoll::where(['user_id' => $val->id, 'status' => 1, 'pay_period_from' => $payFrequency1->pay_period_from, 'pay_period_to' => $payFrequency1->pay_period_to])->first()) {
                                                    PayRoll::create([
                                                        'user_id' => $val->id,
                                                        'position_id' => isset($positionId) ? $positionId : null,
                                                        'override' => 0,
                                                        'pay_period_from' => $payFrequency1->pay_period_from,
                                                        'pay_period_to' => $payFrequency1->pay_period_to,
                                                        'status' => 1,
                                                        'is_stop_payroll' => $stopPayroll,
                                                    ]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // indirect 2
                        }
                    }
                }
            }
        }
        // end Direct overrides code

        // manual overrides code
        if ($saleUserId) {
            $manualSystemSetting = overrideSystemSetting::where('allow_manual_override_status', 1)->first();
            $manualOverrides = ManualOverrides::where('manual_user_id', $saleUserId)->whereHas('manualUser', function ($q) {
                $q->where('id', '!=', '1');
            })->get();

            if (count($manualOverrides) > 0 && $manualSystemSetting) {
                foreach ($manualOverrides as $value) {
                    $userdata = User::where('id', $value->user_id)->first();
                    $stopPayroll = ($userdata->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value->user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $userdata->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                    if ($positionReconciliation) {
                        $settlementType = 'reconciliation';
                        $payFrequencyManual = $this->reconciliationPeriod($date);
                    } else {
                        $settlementType = 'during_m2';
                        $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $value->user_id);
                    }

                    $value->overrides_amount = 0;
                    $value->overrides_type = '';
                    $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->user_id, 'manual_user_id' => $saleUserId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->overrides_amount = $overrideHistory->overrides_amount;
                        $value->overrides_type = $overrideHistory->overrides_type;
                        if ($value->overrides_amount && $userdata->dismiss == 0) {
                            if ($value->overrides_type == 'percent') {
                                $amount = (($grossAmountValue * ($value->overrides_amount * $x)) / 100);
                            } else {
                                $amount = $value->overrides_amount;
                            }
                            $manOverride = UserOverrides::where(['user_id' => $value->user_id, 'pid' => $pid, 'type' => 'Manual', 'is_displayed' => '1'])->first();
                            if (empty($manOverride)) {
                                $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->user_id, 'type' => 'Manual', 'status' => 1])->first();
                                if (empty($overrideStatus)) {
                                    $dataManual = [
                                        'user_id' => $value->user_id,
                                        'type' => 'Manual',
                                        'sale_user_id' => $saleUserId,
                                        'pid' => $pid,
                                        'kw' => $grossAmountValue,
                                        'amount' => $amount,
                                        'overrides_amount' => $value->overrides_amount,
                                        'overrides_type' => $value->overrides_type,
                                        'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : null,
                                        'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : null,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $stopPayroll,
                                    ];

                                    $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                    if ($overrideSystemSetting) {
                                        $userOverridess = UserOverrides::where(['user_id' => $value->user_id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect'])->first();
                                        if ($userOverridess) {
                                            if ($amount > $userOverridess->amount) {
                                                UserOverrides::where('id', $userOverridess->id)->where('status', 1)->where('is_displayed', '1')->delete();
                                                $userOverrode = UserOverrides::create($dataManual);
                                            }
                                        } else {
                                            UserOverrides::where(['user_id' => $value->user_id, 'pid' => $pid, 'type' => 'Manual', 'status' => 1])->where('is_displayed', '1')->delete();
                                            $userOverrode = UserOverrides::create($dataManual);
                                        }
                                    } else {
                                        $userOverrode = UserOverrides::create($dataManual);
                                    }

                                    if (isset($userOverrode) && $userOverrode && $userOverrode->overrides_settlement_type == 'during_m2') {
                                        if (! PayRoll::where(['user_id' => $value->user_id, 'status' => 1, 'pay_period_from' => $payFrequencyManual->pay_period_from, 'pay_period_to' => $payFrequencyManual->pay_period_to])->first()) {
                                            PayRoll::create([
                                                'user_id' => $value->user_id,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'override' => 0,
                                                'pay_period_from' => $payFrequencyManual->pay_period_from,
                                                'pay_period_to' => $payFrequencyManual->pay_period_to,
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // GENERATE STACK FOR USERS
    public function generateStackUserOverride($pid)
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $saleMaster = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        $closer1 = $saleMaster->salesMasterProcess->closer1_id;
        $closer2 = $saleMaster->salesMasterProcess->closer2_id;

        $userData1 = User::where('id', $closer1)->first();
        $userData2 = User::where('id', $closer2)->first();

        $stackUserIds = [];
        if (($userData1->office_id || $userData2->office_id) && $stackSystemSetting) {
            $approvedDate = $saleMaster->customer_signoff;
            $stackUsers1 = [];
            if ($userData1) {
                $officeId1 = $userData1->office_id;

                $userTransferHistory = UserTransferHistory::where('user_id', $userData1->id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($userTransferHistory) {
                    $officeId1 = $userTransferHistory->office_id;
                }

                $userIdArr1 = User::where('office_id', $officeId1)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $userIds = AdditionalLocations::where('office_id', $officeId1)->where('user_id', '!=', $userData1->id)->pluck('user_id')->toArray();
                $userIdArr2 = User::whereIn('id', $userIds)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $stackUsers1 = array_unique(array_merge($userIdArr1, $userIdArr2));
            }

            $stackUsers2 = [];
            if ($userData2) {
                $officeId2 = $userData2->office_id;

                $userTransferHistory = UserTransferHistory::where('user_id', $userData2->id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($userTransferHistory) {
                    $officeId2 = $userTransferHistory->office_id;
                }

                $userIdArr3 = User::where('office_id', $officeId2)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $userIds = AdditionalLocations::where('office_id', $officeId2)->where('user_id', '!=', $userData2->id)->pluck('user_id')->toArray();
                $userIdArr4 = User::whereIn('id', $userIds)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $stackUsers2 = array_unique(array_merge($userIdArr3, $userIdArr4));
            }
            $stackUsers = array_unique(array_merge($stackUsers1, $stackUsers2));

            if (count($stackUsers) > 0) {
                $commissionArray = [];
                foreach ($stackUsers as $userId) {
                    $userdata = User::where(['id' => $userId])->first();
                    $commission = $this->userStackCommission($userdata, $approvedDate);
                    if ($commission) {
                        $commissionArray[$userId] = $commission;
                    }
                }
                krsort($commissionArray);

                $closerCommission1 = 0;
                $closerCommission2 = 0;
                if ($userData1) {
                    $closerCommission1 = $this->userStackCommission($userData1, $approvedDate);
                }
                if ($userData2) {
                    $closerCommission2 = $this->userStackCommission($userData2, $approvedDate);
                }

                $grossAmountValue = $saleMaster->gross_account_value;
                $date = $saleMaster->m2_date;
                $closer1_commission = $saleMaster->salesMasterProcess->closer1_commission;
                $closer2_commission = $saleMaster->salesMasterProcess->closer2_commission;
                $closer_comm = ($closer1_commission + $closer2_commission);

                $previousValue = 0;
                $lowerStackPay = 0;
                foreach ($commissionArray as $key => $value) {
                    if ($value >= $closerCommission1 && $value >= $closerCommission2) {
                        $userdata = User::where(['id' => $key])->first();
                        $organizationHistory = UserOrganizationHistory::where('user_id', $key)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userdata->sub_position_id;
                        }

                        $saleUserId = null;
                        if (in_array($key, $stackUsers1)) {
                            $saleUserId = $userData1->id;
                            $officeId = $userData1->office_id;
                        } elseif (in_array($key, $stackUsers2)) {
                            $saleUserId = $userData2->id;
                            $officeId = $userData2->office_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1])->first();
                        if (! empty($positionReconciliation) && $positionReconciliation->stack_settlement == 'Reconciliation') {
                            $settlementType = 'reconciliation';
                            $payFrequencyOffice = $this->reconciliationPeriod($date);
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $userdata->id);
                        }

                        $positionOverride = PositionOverride::where('position_id', $positionId)->where(['override_id' => '4', 'status' => 1])->first();
                        if ($positionOverride && $userdata->dismiss == 0) {
                            $overrideHistory = UserOverrideHistory::where('user_id', $userdata->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                $userdata->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userdata->office_stack_overrides_amount) {
                                $totalOverrideCost = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->where('type', '!=', 'Stack')->where('status', '!=', 3)->sum('amount');
                                $stackShare = $userdata->office_stack_overrides_amount;
                                $commission = $value;

                                if ($previousValue == $value) {
                                    $lowerStackPay = $lowerStackPay;
                                } else {
                                    $lowerStackPay = UserOverrides::where(['type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->where('status', '!=', 3)->sum('amount');
                                }

                                $companyMargin = CompanyProfile::first();
                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $marginPercentage = $companyMargin->company_margin;
                                    $x = ((100 - $marginPercentage) / 100);
                                    $amount = (((($value / 100) * $grossAmountValue) * $x) - ($closer_comm + $totalOverrideCost + $lowerStackPay)) * ($stackShare / 100);
                                } else {
                                    $amount = (((($value / 100) * $grossAmountValue)) - ($closer_comm + $totalOverrideCost + $lowerStackPay)) * ($stackShare / 100);
                                }

                                $dataStack = [
                                    'user_id' => $userdata->id,
                                    'type' => 'Stack',
                                    'sale_user_id' => $saleUserId,
                                    'pid' => $pid,
                                    'kw' => $grossAmountValue,
                                    'amount' => $amount,
                                    'overrides_amount' => $userdata->office_stack_overrides_amount,
                                    'overrides_type' => 'per sale',
                                    'calculated_redline' => $commission,
                                    'pay_period_from' => $payFrequencyOffice->pay_period_from,
                                    'pay_period_to' => $payFrequencyOffice->pay_period_to,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $userdata->stop_payroll,
                                    'office_id' => $officeId,
                                ];
                                $stackOverrides = UserOverrides::where(['user_id' => $userdata->id, 'type' => 'Stack', 'pid' => $pid, 'is_displayed' => '1'])->first();
                                if (! $stackOverrides) {
                                    UserOverrides::where(['user_id' => $userdata->user_id, 'pid' => $pid, 'type' => 'Stack', 'status' => 1, 'is_displayed' => '1'])->delete();
                                    $userOverrode = UserOverrides::create($dataStack);
                                }
                            }

                            if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                if (! PayRoll::where(['user_id' => $userdata->id, 'status' => 1, 'pay_period_from' => $payFrequencyOffice->pay_period_from, 'pay_period_to' => $payFrequencyOffice->pay_period_to])->first()) {
                                    PayRoll::create([
                                        'user_id' => $userdata->id,
                                        'position_id' => isset($positionId) ? $positionId : null,
                                        'override' => 0,
                                        'pay_period_from' => $payFrequencyOffice->pay_period_from,
                                        'pay_period_to' => $payFrequencyOffice->pay_period_to,
                                        'status' => 1,
                                        'is_stop_payroll' => $userdata->stop_payroll,
                                    ]);
                                }
                            }
                            array_push($stackUserIds, $userdata->id);
                        }
                        $previousValue = $value;
                    }
                }
            }
        }
        UserOverrides::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'type' => 'Stack'])->where('status', '!=', 3)->whereNotIn('user_id', $stackUserIds)->delete();
    }

    public function userStackCommission($userdata, $approvedDate)
    {
        return @UserCommissionHistory::where('user_id', $userdata->id)->where('commission_effective_date', '<=', $approvedDate)->first()->commission;
    }

    // GENERATE ADDERS CLAWBACK
    public function generateAddersClawback($userId, $pid, $amount, $customerSignOff)
    {
        if ($userId) {
            $user = User::where('id', $userId)->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($userOrganizationHistory) {
                $subPositionId = $userOrganizationHistory['sub_position_id'];
            } else {
                $subPositionId = $user->sub_position_id;
            }

            $clawbackType = 'next payroll';
            $payFrequency = $this->payFrequencyNew(date('Y-m-d'), $subPositionId, $userId);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            if (! empty($amount)) {
                $data = [
                    'user_id' => $userId,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'clawback_amount' => $amount,
                    'clawback_type' => $clawbackType,
                    'adders_type' => 'm2 update',
                    'is_stop_payroll' => $stopPayroll,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                ];

                $clawbackSettlement = ClawbackSettlement::where(['user_id' => $userId, 'pid' => $pid, 'adders_type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                if ($clawbackSettlement) {
                    ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                } else {
                    ClawbackSettlement::create($data);
                }

                if ($clawbackType == 'next payroll') {
                    updateExistingPayroll($userId, $pay_period_from, $pay_period_to, $amount, 'clawback', $subPositionId, 0);
                }
            }
        }
    }

    // GENERATE ADDERS COMMISSION / M2 UPDATE
    public function generateAddersCommission($userId, $pid, $amount, $customerSignOff)
    {
        $date = date('Y-m-d');
        if ($userId) {
            $user = User::where('id', $userId)->first();
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $customerSignOff)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($userOrganizationHistory) {
                $subPositionId = $userOrganizationHistory['sub_position_id'];
            } else {
                $subPositionId = $user->sub_position_id;
            }
            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;
            if (! empty($amount)) {
                $userCommission = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
                $data = [
                    'user_id' => $userId,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2 update',
                    'amount' => $amount,
                    'redline' => '0',
                    'redline_type' => null,
                    'date' => $date,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'customer_signoff' => $customerSignOff,
                    'is_stop_payroll' => $stopPayroll,
                    'status' => 1,
                ];

                if ($userCommission) {
                    UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    UserCommission::create($data);
                }

                updateExistingPayroll($userId, $pay_period_from, $pay_period_to, $amount, 'commission', $subPositionId, 0);
            }
        }
    }

    // REMOVE M2 UPDATES
    public function removeGeneratedM2Update($saleMasterData)
    {
        UserCommission::where(['pid' => $saleMasterData->pid, 'amount_type' => 'm2 update', 'status' => 1, 'is_displayed' => '1'])->delete();
        UserOverrides::where(['pid' => $saleMasterData->pid, 'status' => 1, 'is_displayed' => '1'])->where(function ($q) {
            $q->where('type', 'm2 update')->orWhere('type', 'Stack m2 update');
        })->delete();
        ClawbackSettlement::where(['pid' => $saleMasterData->pid, 'status' => 1, 'is_displayed' => '1'])->where(function ($q) {
            $q->where('adders_type', 'm2 update')->orWhere('adders_type', 'Stack m2 update');
        })->delete();
    }

    // GENERATE M2 UPDATED OVERRIDES
    public function generateOverrideUpdate($saleUserId, $pid)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $grossAmountValue = $saleMaster->gross_account_value;
        $date = $saleMaster->m2_date;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $recruiterIdData = User::where('id', $saleUserId)->first();
        $companyMargin = CompanyProfile::where('id', 1)->first();
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $marginPercentage = $companyMargin->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        } else {
            $x = 1;
        }

        // office overrides code
        if ($recruiterIdData->office_id) {
            $officeId = $recruiterIdData->office_id;
            $userIdArr1 = User::where('office_id', $officeId)->where('id', '!=', '1')->where('id', '!=', $saleUserId)->pluck('id')->toArray();
            $userIdArr2 = AdditionalLocations::where('office_id', $officeId)->whereHas('user', function ($q) {
                $q->where('id', '!=', '1');
            })->where('user_id', '!=', $saleUserId)->pluck('user_id')->toArray();
            if (count($userIdArr1) > 0) {
                foreach ($userIdArr1 as $value) {
                    $paidOfficeOverride = UserOverrides::where(['user_id' => $value, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->whereIn('type', ['Office', 'm2 update'])->sum('amount');
                    if ($paidOfficeOverride) {
                        $userdata = User::where('id', $value)->first();
                        $stopPayroll = ($userdata->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $value)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userdata->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            // $settlementType = 'reconciliation';
                            $payFrequencyOffice = $this->reconciliationPeriod($date);
                        } else {
                            // $settlementType = 'during_m2';
                            $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $value);
                        }

                        $payPeriodFrom = $payFrequencyOffice->pay_period_from;
                        $payPeriodTo = $payFrequencyOffice->pay_period_to;

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value, 'type' => 'Office', 'status' => '1'])->first();
                            if (empty($overrideStatus) && $userdata->dismiss == 0) {
                                $userdata->office_overrides_amount = 0;
                                $userdata->office_overrides_type = '';
                                $overrideHistory = UserOverrideHistory::where('user_id', $value)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    $userdata->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                    $userdata->office_overrides_type = $overrideHistory->office_overrides_type;

                                    if ($userdata->office_overrides_amount) {
                                        if ($userdata->office_overrides_type == 'percent') {
                                            $amount = (($grossAmountValue * $userdata->office_overrides_amount * $x) / 100);
                                        } else {
                                            $amount = $userdata->office_overrides_amount;
                                        }

                                        $adderAmounts = number_format((float) ($paidOfficeOverride * 1), 2, '.', '');
                                        $amount = number_format($amount, 2, '.', '');

                                        if ($adderAmounts != $amount) {
                                            if ($adderAmounts > $amount) {
                                                UserOverrides::where(['user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                $amount = ($adderAmounts - $amount);

                                                $clawbackType = 'next payroll';
                                                $data = [
                                                    'user_id' => $value,
                                                    'position_id' => isset($positionId) ? $positionId : null,
                                                    'sale_user_id' => $saleUserId,
                                                    'pid' => $pid,
                                                    'clawback_amount' => $amount,
                                                    'clawback_type' => $clawbackType,
                                                    'adders_type' => 'm2 update',
                                                    'type' => 'overrides',
                                                    'pay_period_from' => $payPeriodFrom,
                                                    'pay_period_to' => $payPeriodTo,
                                                ];
                                                $clawbackSettlement = ClawbackSettlement::where('user_id', $value)->where('pid', $pid)->where(['adders_type' => 'm2 update', 'type' => 'overrides'])->where('is_displayed', '1')->where('status', '!=', 3)->first();
                                                if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                                    ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                                } else {
                                                    ClawbackSettlement::create($data);
                                                    updateExistingPayroll($value, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                                }
                                            } else {
                                                ClawbackSettlement::where(['pid' => $pid, 'adders_type' => 'm2 update', 'user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                $amount = ($amount - $adderAmounts);

                                                $dataDirect = [
                                                    'user_id' => $value,
                                                    'type' => 'm2 update',
                                                    'sale_user_id' => $saleUserId,
                                                    'pid' => $pid,
                                                    'kw' => $grossAmountValue,
                                                    'amount' => $amount,
                                                    'overrides_amount' => $userdata->office_overrides_amount,
                                                    'overrides_type' => $userdata->office_overrides_type,
                                                    'pay_period_from' => $payPeriodFrom,
                                                    'pay_period_to' => $payPeriodTo,
                                                    'overrides_settlement_type' => 'during_m2',
                                                    'status' => 1,
                                                    'is_stop_payroll' => $stopPayroll,
                                                    'office_id' => $officeId,
                                                ];

                                                $adderOverrid = UserOverrides::where(['user_id' => $value, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                                if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                                    UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                                } else {
                                                    UserOverrides::create($dataDirect);
                                                    updateExistingPayroll($value, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (count($userIdArr2) > 0) {
                foreach ($userIdArr2 as $value) {
                    $paidOfficeOverride = UserOverrides::where(['user_id' => $value, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->whereIn('type', ['Office', 'm2 update'])->sum('amount');
                    if ($paidOfficeOverride) {
                        $userdata1 = User::where('id', $value)->first();
                        $stopPayroll = ($userdata1->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $value)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userdata1->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            // $settlementType = 'reconciliation';
                            $payFrequencyOffice = $this->reconciliationPeriod($date);
                        } else {
                            // $settlementType = 'during_m2';
                            $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $value);
                        }

                        $payPeriodFrom = $payFrequencyOffice->pay_period_from;
                        $payPeriodTo = $payFrequencyOffice->pay_period_to;

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value, 'type' => 'Office', 'status' => 1])->first();
                            if (empty($overrideStatus) && $userdata1->dismiss == 0) {
                                $userdata1->office_overrides_amount = 0;
                                $userdata1->office_overrides_type = '';
                                $overrideHistory = UserAdditionalOfficeOverrideHistory::where('user_id', $value)->where('office_id', $officeId)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    $userdata1->office_overrides_amount = $overrideHistory->office_overrides_amount;
                                    $userdata1->office_overrides_type = $overrideHistory->office_overrides_type;

                                    if ($userdata1->office_overrides_amount) {
                                        if ($userdata1->office_overrides_type == 'percent') {
                                            $amount = (($grossAmountValue * $userdata1->office_overrides_amount * $x) / 100);
                                        } else {
                                            $amount = $userdata1->office_overrides_amount;
                                        }

                                        $adderAmounts = number_format((float) ($paidOfficeOverride * 1), 2, '.', '');
                                        $amount = number_format($amount, 2, '.', '');

                                        if ($adderAmounts != $amount) {
                                            if ($adderAmounts > $amount) {
                                                UserOverrides::where(['user_id' => $userdata1->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                $amount = ($adderAmounts - $amount);

                                                $clawbackType = 'next payroll';
                                                $data = [
                                                    'user_id' => $value,
                                                    'position_id' => isset($positionId) ? $positionId : null,
                                                    'sale_user_id' => $saleUserId,
                                                    'pid' => $pid,
                                                    'clawback_amount' => $amount,
                                                    'clawback_type' => $clawbackType,
                                                    'adders_type' => 'm2 update',
                                                    'type' => 'overrides',
                                                    'pay_period_from' => $payPeriodFrom,
                                                    'pay_period_to' => $payPeriodTo,
                                                ];
                                                $clawbackSettlement = ClawbackSettlement::where(['user_id' => $value, 'pid' => $pid, 'adders_type' => 'm2 update', 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                                if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                                    ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                                } else {
                                                    ClawbackSettlement::create($data);
                                                    updateExistingPayroll($value, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                                }
                                            } else {
                                                ClawbackSettlement::where(['pid' => $pid, 'adders_type' => 'm2 update', 'user_id' => $userdata1->id, 'sale_user_id' => $saleUserId, 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                $amount = ($amount - $adderAmounts);

                                                $dataDirect = [
                                                    'user_id' => $value,
                                                    'type' => 'm2 update',
                                                    'sale_user_id' => $saleUserId,
                                                    'pid' => $pid,
                                                    'kw' => $grossAmountValue,
                                                    'amount' => $amount,
                                                    'overrides_amount' => $userdata1->office_overrides_amount,
                                                    'overrides_type' => $userdata1->office_overrides_type,
                                                    'pay_period_from' => $payPeriodFrom,
                                                    'pay_period_to' => $payPeriodTo,
                                                    'overrides_settlement_type' => 'during_m2',
                                                    'status' => 1,
                                                    'is_stop_payroll' => $stopPayroll,
                                                    'office_id' => $officeId,
                                                ];

                                                $adderOverrid = UserOverrides::where(['user_id' => $value, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                                if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                                    UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                                } else {
                                                    UserOverrides::create($dataDirect);
                                                    updateExistingPayroll($value, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // end office overrides code

        // Direct overrides code
        if ($recruiterIdData->recruiter_id) {
            if (! empty($recruiterIdData->additional_recruiter_id1) && empty($recruiterIdData->additional_recruiter_id2)) {
                $recruiter_ids = $recruiterIdData->recruiter_id.','.$recruiterIdData->additional_recruiter_id1;
            } elseif (! empty($recruiterIdData->additional_recruiter_id1) && ! empty($recruiterIdData->additional_recruiter_id2)) {
                $recruiter_ids = $recruiterIdData->recruiter_id.','.$recruiterIdData->additional_recruiter_id1.','.$recruiterIdData->additional_recruiter_id2;
            } else {
                $recruiter_ids = $recruiterIdData->recruiter_id;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::with('recruiter')->whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
            if (count($directs) > 0) {
                foreach ($directs as $value) {
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $value->sub_position_id;
                    }

                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                    if ($positionReconciliation) {
                        // $settlementType = 'reconciliation';
                        $payFrequency = $this->reconciliationPeriod($date);
                    } else {
                        // $settlementType = 'during_m2';
                        $payFrequency = $this->payFrequencyNew($date, $positionId, $value->id);
                    }

                    $payPeriodFrom = $payFrequency->pay_period_from;
                    $payPeriodTo = $payFrequency->pay_period_to;

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '1', 'status' => '1'])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'type' => 'Direct', 'status' => 1])->first();
                    if ($positionOverride && ! $overrideStatus) {
                        $paidDirectOfficeOverride = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->whereIn('type', ['Direct', 'm2 update'])->sum('amount');
                        if ($paidDirectOfficeOverride) {
                            $value->direct_overrides_amount = 0;
                            $value->direct_overrides_type = '';
                            $overrideHistory = UserOverrideHistory::where('user_id', $value->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                                $value->direct_overrides_type = $overrideHistory->direct_overrides_type;

                                if ($value->direct_overrides_amount) {
                                    if ($value->direct_overrides_type == 'percent') {
                                        $amount = (($grossAmountValue * $value->direct_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $value->direct_overrides_amount;
                                    }

                                    $adderAmounts = number_format((float) ($paidDirectOfficeOverride * 1), 2, '.', '');
                                    $amount = number_format($amount, 2, '.', '');

                                    if ($adderAmounts != $amount) {
                                        if ($adderAmounts > $amount) {
                                            UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                            $amount = ($adderAmounts - $amount);

                                            $clawbackType = 'next payroll';
                                            $data = [
                                                'user_id' => $value->id,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'sale_user_id' => $saleUserId,
                                                'pid' => $pid,
                                                'clawback_amount' => $amount,
                                                'clawback_type' => $clawbackType,
                                                'adders_type' => 'm2 update',
                                                'type' => 'overrides',
                                                'pay_period_from' => $payPeriodFrom,
                                                'pay_period_to' => $payPeriodTo,
                                            ];
                                            $clawbackSettlement = ClawbackSettlement::where('user_id', $value->id)->where('is_displayed', '1')->where('pid', $pid)->where(['adders_type' => 'm2 update', 'type' => 'overrides'])->where('status', '!=', 3)->first();
                                            if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                                ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                            } else {
                                                ClawbackSettlement::create($data);
                                                updateExistingPayroll($value->id, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                            }
                                        } else {
                                            ClawbackSettlement::where(['pid' => $pid, 'adders_type' => 'm2 update', 'user_id' => $value->id, 'sale_user_id' => $saleUserId, 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                            $amount = ($amount - $adderAmounts);

                                            $dataDirect = [
                                                'user_id' => $value->id,
                                                'type' => 'm2 update',
                                                'sale_user_id' => $saleUserId,
                                                'pid' => $pid,
                                                'kw' => $grossAmountValue,
                                                'amount' => $amount,
                                                'overrides_amount' => $value->direct_overrides_amount,
                                                'overrides_type' => $value->direct_overrides_type,
                                                'pay_period_from' => $payPeriodFrom,
                                                'pay_period_to' => $payPeriodTo,
                                                'overrides_settlement_type' => 'during_m2',
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                                'office_id' => $officeId,
                                            ];

                                            $adderOverrid = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                            if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                                UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                            } else {
                                                UserOverrides::create($dataDirect);
                                                updateExistingPayroll($value->id, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // indirect
                    $indirect_recruiter = User::with('positionDetail')->where('id', $value->id)->first();
                    if ($indirect_recruiter->recruiter_id) {
                        $recruiter_ids = $indirect_recruiter->recruiter_id;
                        if (! empty($indirect_recruiter->additional_recruiter_id1)) {
                            $recruiter_ids .= ','.$indirect_recruiter->additional_recruiter_id1;
                        }
                        if (! empty($indirect_recruiter->additional_recruiter_id2)) {
                            $recruiter_ids .= ','.$indirect_recruiter->additional_recruiter_id2;
                        }

                        $idsArr = explode(',', $recruiter_ids);
                        $additional = User::with('positionDetail', 'recruiter')->where('id', '!=', '1')->whereIn('id', $idsArr)->get();
                        if (count($additional) > 0) {
                            foreach ($additional as $val) {
                                $paidInDirectOfficeOverride = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->whereIn('type', ['Indirect', 'm2 update'])->sum('amount');
                                if ($paidInDirectOfficeOverride) {
                                    $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;

                                    $organizationHistory = UserOrganizationHistory::where('user_id', $val->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($organizationHistory) {
                                        $positionId = $organizationHistory->sub_position_id;
                                    } else {
                                        $positionId = $val->sub_position_id;
                                    }

                                    $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                                    if ($positionReconciliation) {
                                        // $settlementType = 'reconciliation';
                                        $payFrequency1 = $this->reconciliationPeriod($date);
                                    } else {
                                        // $settlementType = 'during_m2';
                                        $payFrequency1 = $this->payFrequencyNew($date, $positionId, $val->id);
                                    }

                                    $payPeriodFrom = $payFrequency1->pay_period_from;
                                    $payPeriodTo = $payFrequency1->pay_period_to;

                                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '2', 'status' => '1'])->first();
                                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $val->id, 'type' => 'Indirect'])->first();
                                    if ($positionOverride && ! $overrideStatus) {
                                        $val->indirect_overrides_amount = 0;
                                        $val->indirect_overrides_type = '';
                                        $overrideHistory = UserOverrideHistory::where('user_id', $val->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                        if ($overrideHistory) {
                                            $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                            $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                                            if ($val->indirect_overrides_amount) {
                                                if ($val->indirect_overrides_type == 'percent') {
                                                    $amount = (($grossAmountValue * $val->indirect_overrides_amount * $x) / 100);
                                                } else {
                                                    $amount = $val->indirect_overrides_amount;
                                                }

                                                $adderAmounts = number_format((float) ($paidInDirectOfficeOverride * 1), 2, '.', '');
                                                $amount = number_format($amount, 2, '.', '');

                                                if ($adderAmounts != $amount) {
                                                    if ($adderAmounts > $amount) {
                                                        UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                        $amount = ($adderAmounts - $amount);

                                                        $clawbackType = 'next payroll';
                                                        $data = [
                                                            'user_id' => $val->id,
                                                            'position_id' => isset($positionId) ? $positionId : null,
                                                            'sale_user_id' => $saleUserId,
                                                            'pid' => $pid,
                                                            'clawback_amount' => $amount,
                                                            'clawback_type' => $clawbackType,
                                                            'adders_type' => 'm2 update',
                                                            'type' => 'overrides',
                                                            'pay_period_from' => $payPeriodFrom,
                                                            'pay_period_to' => $payPeriodTo,
                                                        ];
                                                        $clawbackSettlement = ClawbackSettlement::where('user_id', $val->id)->where('is_displayed', '1')->where('pid', $pid)->where(['adders_type' => 'm2 update', 'type' => 'overrides'])->where('status', '!=', 3)->first();
                                                        if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                                            ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                                        } else {
                                                            ClawbackSettlement::create($data);
                                                            updateExistingPayroll($val->id, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                                        }
                                                    } else {
                                                        ClawbackSettlement::where(['pid' => $pid, 'adders_type' => 'm2 update', 'user_id' => $val->id, 'sale_user_id' => $saleUserId, 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                                        $amount = ($amount - $adderAmounts);

                                                        $dataDirect = [
                                                            'user_id' => $val->id,
                                                            'type' => 'm2 update',
                                                            'sale_user_id' => $saleUserId,
                                                            'pid' => $pid,
                                                            'kw' => $grossAmountValue,
                                                            'amount' => $amount,
                                                            'overrides_amount' => $val->indirect_overrides_amount,
                                                            'overrides_type' => $val->indirect_overrides_type,
                                                            'pay_period_from' => $payPeriodFrom,
                                                            'pay_period_to' => $payPeriodTo,
                                                            'overrides_settlement_type' => 'during_m2',
                                                            'status' => 1,
                                                            'is_stop_payroll' => $stopPayroll,
                                                            'office_id' => $officeId,
                                                        ];

                                                        $adderOverrid = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                                        if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                                            UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                                        } else {
                                                            UserOverrides::create($dataDirect);
                                                            updateExistingPayroll($val->id, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // indirect 2
                        }
                    }
                }
            }
        }
        // end Direct overrides code

        // manual overrides code
        if ($saleUserId) {
            $manualSystemSetting = overrideSystemSetting::where('allow_manual_override_status', 1)->first();
            $manualOverrides = ManualOverrides::where('manual_user_id', $saleUserId)->whereHas('manualUser', function ($q) {
                $q->where('id', '!=', '1');
            })->get();

            if (count($manualOverrides) > 0 && $manualSystemSetting) {
                foreach ($manualOverrides as $value) {
                    $paidManualOfficeOverride = UserOverrides::where(['user_id' => $value->user_id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 3, 'is_displayed' => '1'])->whereIn('type', ['Manual', 'm2 update'])->sum('amount');
                    if ($paidManualOfficeOverride) {
                        $userdata = User::where('id', $value->user_id)->first();
                        $stopPayroll = ($userdata->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $value->user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userdata->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            // $settlementType = 'reconciliation';
                            $payFrequencyManual = $this->reconciliationPeriod($date);
                        } else {
                            // $settlementType = 'during_m2';
                            $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $userdata->id);
                        }

                        $payPeriodFrom = $payFrequencyManual->pay_period_from;
                        $payPeriodTo = $payFrequencyManual->pay_period_to;

                        $value->overrides_amount = 0;
                        $value->overrides_type = '';
                        $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->user_id, 'manual_user_id' => $saleUserId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory) {
                            $value->overrides_amount = $overrideHistory->overrides_amount;
                            $value->overrides_type = $overrideHistory->overrides_type;
                            if ($value->overrides_amount && $userdata->dismiss == 0) {
                                if ($value->overrides_type == 'percent') {
                                    $amount = (($grossAmountValue * $value->overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->overrides_amount;
                                }

                                $adderAmounts = number_format((float) ($paidManualOfficeOverride * 1), 2, '.', '');
                                $amount = number_format($amount, 2, '.', '');

                                if ($adderAmounts != $amount) {
                                    if ($adderAmounts > $amount) {
                                        UserOverrides::where(['user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                        $amount = ($adderAmounts - $amount);

                                        $clawbackType = 'next payroll';
                                        $data = [
                                            'user_id' => $userdata->id,
                                            'position_id' => isset($positionId) ? $positionId : null,
                                            'sale_user_id' => $saleUserId,
                                            'pid' => $pid,
                                            'clawback_amount' => $amount,
                                            'clawback_type' => $clawbackType,
                                            'adders_type' => 'm2 update',
                                            'type' => 'overrides',
                                            'pay_period_from' => $payPeriodFrom,
                                            'pay_period_to' => $payPeriodTo,
                                        ];
                                        $clawbackSettlement = ClawbackSettlement::where('user_id', $userdata->id)->where('is_displayed', '1')->where('pid', $pid)->where(['adders_type' => 'm2 update', 'type' => 'overrides'])->where('status', '!=', 3)->first();
                                        if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                            ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                        } else {
                                            ClawbackSettlement::create($data);
                                            updateExistingPayroll($userdata->id, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                        }
                                    } else {
                                        ClawbackSettlement::where(['pid' => $pid, 'adders_type' => 'm2 update', 'user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'type' => 'overrides', 'is_displayed' => '1'])->where('status', '!=', 3)->delete();
                                        $amount = ($amount - $adderAmounts);

                                        $dataDirect = [
                                            'user_id' => $userdata->id,
                                            'type' => 'm2 update',
                                            'sale_user_id' => $saleUserId,
                                            'pid' => $pid,
                                            'kw' => $grossAmountValue,
                                            'amount' => $amount,
                                            'overrides_amount' => $value->overrides_amount,
                                            'overrides_type' => $value->overrides_type,
                                            'pay_period_from' => $payPeriodFrom,
                                            'pay_period_to' => $payPeriodTo,
                                            'overrides_settlement_type' => 'during_m2',
                                            'status' => 1,
                                            'is_stop_payroll' => $stopPayroll,
                                            'office_id' => $officeId,
                                        ];

                                        $adderOverrid = UserOverrides::where(['user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                        if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                            UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                        } else {
                                            UserOverrides::create($dataDirect);
                                            updateExistingPayroll($userdata->id, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // GENERATE M2 UPDATED STACK OVERRIDES
    public function generateStackOverrideUpdate($pid)
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $saleMaster = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        $closer1 = $saleMaster->salesMasterProcess->closer1_id;
        $closer2 = $saleMaster->salesMasterProcess->closer2_id;

        $userData1 = User::where('id', $closer1)->first();
        $userData2 = User::where('id', $closer2)->first();

        $stackUserIds = [];
        if (($userData1->office_id || $userData2->office_id) && $stackSystemSetting) {
            $approvedDate = $saleMaster->customer_signoff;
            $stackUsers1 = [];
            if ($userData1) {
                $officeId1 = $userData1->office_id;

                $userTransferHistory = UserTransferHistory::where('user_id', $userData1->id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($userTransferHistory) {
                    $officeId1 = $userTransferHistory->office_id;
                }

                $userIdArr1 = User::where('office_id', $officeId1)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $userIds = AdditionalLocations::where('office_id', $officeId1)->where('user_id', '!=', $userData1->id)->pluck('user_id')->toArray();
                $userIdArr2 = User::whereIn('id', $userIds)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $stackUsers1 = array_unique(array_merge($userIdArr1, $userIdArr2));
            }

            $stackUsers2 = [];
            if ($userData2) {
                $officeId2 = $userData2->office_id;

                $userTransferHistory = UserTransferHistory::where('user_id', $userData2->id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($userTransferHistory) {
                    $officeId2 = $userTransferHistory->office_id;
                }

                $userIdArr3 = User::where('office_id', $officeId2)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $userIds = AdditionalLocations::where('office_id', $officeId2)->where('user_id', '!=', $userData2->id)->pluck('user_id')->toArray();
                $userIdArr4 = User::whereIn('id', $userIds)->whereNotNull('office_stack_overrides_amount')->orderBy('commission', 'asc')->pluck('id')->toArray();
                $stackUsers2 = array_unique(array_merge($userIdArr3, $userIdArr4));
            }
            $stackUsers = array_unique(array_merge($stackUsers1, $stackUsers2));

            if (count($stackUsers) > 0) {
                $commissionArray = [];
                foreach ($stackUsers as $userId) {
                    $userdata = User::where(['id' => $userId])->first();
                    $commission = $this->userStackCommission($userdata, $approvedDate);
                    if ($commission) {
                        $commissionArray[$userId] = $commission;
                    }
                }
                krsort($commissionArray);

                $closerCommission1 = 0;
                $closerCommission2 = 0;
                if ($userData1) {
                    $closerCommission1 = $this->userStackCommission($userData1, $approvedDate);
                }
                if ($userData2) {
                    $closerCommission2 = $this->userStackCommission($userData2, $approvedDate);
                }

                $grossAmountValue = $saleMaster->gross_account_value;
                $date = $saleMaster->m2_date;
                $closer1_commission = $saleMaster->salesMasterProcess->closer1_commission;
                $closer2_commission = $saleMaster->salesMasterProcess->closer2_commission;
                $closer_comm = ($closer1_commission + $closer2_commission);

                $previousValue = 0;
                $lowerStackPay = 0;
                $userIds = [];
                $i = 0;
                foreach ($commissionArray as $key => $value) {
                    if ($value >= $closerCommission1 && $value >= $closerCommission2) {
                        $userdata = User::where(['id' => $key])->first();
                        $stopPayroll = ($userdata->stop_payroll == 1) ? 1 : 0;
                        $paidStackOverride = UserOverrides::where(['user_id' => $userdata->id, 'pid' => $pid, 'type' => 'Stack', 'status' => 3, 'is_displayed' => '1'])->first();
                        if ($paidStackOverride) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $key)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            } else {
                                $positionId = $userdata->sub_position_id;
                            }

                            $saleUserId = null;
                            if (in_array($key, $stackUsers1)) {
                                $saleUserId = $userData1->id;
                                $officeId = $userData1->office_id;
                            } elseif (in_array($key, $stackUsers2)) {
                                $saleUserId = $userData2->id;
                                $officeId = $userData2->office_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1])->first();
                            if (! empty($positionReconciliation) && $positionReconciliation->stack_settlement == 'Reconciliation') {
                                $payFrequencyStack = $this->reconciliationPeriod($date);
                            } else {
                                $payFrequencyStack = $this->payFrequencyNew($date, $positionId, $userdata->id);
                            }

                            $payPeriodFrom = $payFrequencyStack->pay_period_from;
                            $payPeriodTo = $payFrequencyStack->pay_period_to;

                            $positionOverride = PositionOverride::where('position_id', $positionId)->where(['override_id' => '4', 'status' => 1])->first();
                            if ($positionOverride && $userdata->dismiss == 0) {
                                $overrideHistory = UserOverrideHistory::where('user_id', $userdata->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    $userdata->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                                }

                                if ($userdata->office_stack_overrides_amount) {
                                    $totalOverrideCost = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->whereNotIn('type', ['Stack', 'Stack m2 update'])->sum('amount');
                                    $stackShare = $userdata->office_stack_overrides_amount;
                                    $commission = $value;

                                    if ($previousValue == $value) {
                                        $userIds[] = $key;
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        if ($i == 0) {
                                            $userIds[] = $key;
                                            $lowerStackPay = 0;
                                        } else {
                                            $lowerStackPay = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])
                                                ->where(function ($q) {
                                                    $q->where('type', 'Stack')->orWhere('type', 'Stack m2 update');
                                                })->whereIn('user_id', $userIds)->sum('amount');

                                            $lowerStackClawBackPay = ClawbackSettlement::where(['pid' => $pid, 'is_displayed' => '1', 'type' => 'overrides'])
                                                ->where(function ($q) {
                                                    $q->where('adders_type', 'Stack')->orWhere('adders_type', 'Stack m2 update');
                                                })->whereIn('user_id', $userIds)->sum('clawback_amount');

                                            $lowerStackPay = $lowerStackPay - $lowerStackClawBackPay;
                                        }
                                    }

                                    $companyMargin = CompanyProfile::first();
                                    if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                        $marginPercentage = $companyMargin->company_margin;
                                        $x = ((100 - $marginPercentage) / 100);
                                        $amount = (((($value / 100) * $grossAmountValue) * $x) - ($closer_comm - $totalOverrideCost - $lowerStackPay)) * ($stackShare / 100);
                                    } else {
                                        $amount = ((($value / 100) * $grossAmountValue) - ($closer_comm - $totalOverrideCost - $lowerStackPay)) * ($stackShare / 100);
                                    }

                                    $adderAmounts = number_format((float) ($paidStackOverride->amount * 1), 2, '.', '');
                                    $amount = number_format($amount, 2, '.', '');

                                    if ($adderAmounts != $amount) {
                                        if ($adderAmounts > $amount) {
                                            UserOverrides::where(['pid' => $pid, 'is_displayed' => '1', 'user_id' => $userdata->id, 'type' => 'Stack m2 update', 'sale_user_id' => $saleUserId])->where('status', '!=', 3)->delete();
                                            $amount = ($adderAmounts - $amount);

                                            $clawbackType = 'next payroll';
                                            $data = [
                                                'user_id' => $userdata->id,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'sale_user_id' => $saleUserId,
                                                'pid' => $pid,
                                                'clawback_amount' => $amount,
                                                'clawback_type' => $clawbackType,
                                                'adders_type' => 'Stack m2 update',
                                                'type' => 'overrides',
                                                'pay_period_from' => $payPeriodFrom,
                                                'pay_period_to' => $payPeriodTo,
                                            ];
                                            $clawbackSettlement = ClawbackSettlement::where('user_id', $userdata->id)->where('is_displayed', '1')->where('pid', $pid)->where(['adders_type' => 'Stack m2 update', 'type' => 'overrides'])->where('status', '!=', 3)->first();
                                            if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                                                ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                                            } else {
                                                ClawbackSettlement::create($data);
                                                updateExistingPayroll($userdata->id, $payPeriodFrom, $payPeriodTo, $amount, 'clawback', $positionId, $stopPayroll);
                                            }
                                        } else {
                                            ClawbackSettlement::where(['pid' => $pid, 'is_displayed' => '1', 'user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'adders_type' => 'Stack m2 update', 'type' => 'overrides'])->where('status', '!=', 3)->delete();
                                            $amount = ($amount - $adderAmounts);

                                            $dataDirect = [
                                                'user_id' => $userdata->id,
                                                'type' => 'Stack m2 update',
                                                'sale_user_id' => $saleUserId,
                                                'pid' => $pid,
                                                'kw' => $grossAmountValue,
                                                'calculated_redline' => $commission,
                                                'amount' => $amount,
                                                'overrides_amount' => $userdata->office_stack_overrides_amount,
                                                'overrides_type' => 'percent',
                                                'pay_period_from' => $payPeriodFrom,
                                                'pay_period_to' => $payPeriodTo,
                                                'overrides_settlement_type' => 'during_m2',
                                                'status' => 1,
                                                'is_stop_payroll' => $stopPayroll,
                                                'office_id' => $officeId,
                                            ];

                                            $adderOverrid = UserOverrides::where(['user_id' => $userdata->id, 'sale_user_id' => $saleUserId, 'pid' => $pid, 'type' => 'Stack m2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                                            if (isset($adderOverrid) && ! empty($adderOverrid)) {
                                                UserOverrides::where('id', $adderOverrid->id)->update($dataDirect);
                                            } else {
                                                UserOverrides::create($dataDirect);
                                                updateExistingPayroll($userdata->id, $payPeriodFrom, $payPeriodTo, $amount, 'override', $positionId, $stopPayroll);
                                            }
                                        }
                                    }
                                    $i++;
                                    $stackUserIds[] = $userdata->id;
                                }
                            }
                            $previousValue = $value;
                        }
                    }
                }
            }
        }
        UserOverrides::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'type' => 'Stack m2 update'])->where('status', '!=', 3)->whereNotIn('user_id', $stackUserIds)->delete();
        ClawbackSettlement::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'type' => 'overrides', 'adders_type' => 'Stack m2 update'])->where('status', '!=', 3)->whereNotIn('user_id', $stackUserIds)->delete();
    }
}
