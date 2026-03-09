<?php

namespace App\Core\Traits\SaleTraits;

use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\ReconciliationPeriodTrait;
use App\Core\Traits\ReconTraits\ReconRoutineClawbackTraits;
use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\ExternalSaleWorker;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionReconciliations;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SaleTiersDetail;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use Illuminate\Support\Facades\Log;

trait SubroutineTrait
{
    use EditSaleTrait, OverrideStackTrait, PayFrequencyTrait, ReconciliationPeriodTrait, ReconRoutineClawbackTraits, ReconRoutineTraits, SubroutineOverrideTrait;

    public function subroutineThree($sale, $schema, $info, $commission, $redLine, $redLineType, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $productId = $sale->product_id;
        $productCode = $sale?->productInfo?->product_code;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }

        $isHalf = false;
        $isSelfGen = false;

        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $companyProfile = CompanyProfile::first();
        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $organization = UserOrganizationHistory::where('effective_date', '<=', $approvalDate)->where('user_id', $userId)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId, 'effective_date' => $organization?->effective_date])->first();
            if (! $userOrganizationHistory) {
                $product = Products::withTrashed()->with('productCodes')->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
                $productCode = $product->productCodes->first()?->product_code;
                $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                    $triggerIndex = (preg_replace('/\D/', '', $type) - 1);
                    $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex];
                    $schemaId = @$trigger->id;
                    $schemaName = @$trigger->name;
                    $schemaTrigger = @$trigger->on_trigger;
                    $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            $subPositionId = @$userOrganizationHistory->sub_position_id;
            $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $upfront) {
                $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->orderBy('id', 'DESC')->first();
            }

            if ($upfront && $upfront->upfront_status == 1) {
                $upfrontAmount = 0;
                $upfrontType = null;
                $upfrontHistory = null;
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

                if ($upfrontHistory) {
                    if ($upfrontHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Upfront', 'sub_type' => $type])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $upFrontTier = UserUpfrontHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_upfront_history_id' => $upfrontHistory->id])->first();
                            if ($upFrontTier) {
                                $upfrontAmount = $upFrontTier->value;
                                $upfrontType = $upfrontHistory->upfront_sale_type;
                            } else {
                                $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                                $upfrontType = $upfrontHistory->upfront_sale_type;
                            }
                        } else {
                            $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                            $upfrontType = $upfrontHistory->upfront_sale_type;
                        }
                    } else {
                        $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                        $upfrontType = $upfrontHistory->upfront_sale_type;
                    }
                }

                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $upfrontType) {
                    $amount = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        // Whiteknight domain-specific calculation logic for PEST companies
                        if (config('app.domain_name') === 'whiteknight') {
                            $amount = $this->calculateWhiteknightCommissionAmount($commission, $info, $forExternal, $type, $upfrontType, $upfrontAmount, $pid, $userId, $schemaId);
                        } else {
                            if ($upfrontType == 'per sale') {
                                $amount = $upfrontAmount;
                            } elseif ($upfrontType == 'percent') {
                                $value = @$commission[$info['type']] ? $commission[$info['type']] : 0;
                                if ($forExternal) {
                                    $value = @$commission[$info['id']] ? $commission[$info['id']] : 0;
                                }
                                if ($upfront->deductible_from_prior == 1) {
                                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId])->where('schema_type', '<', $type)->sum('amount');
                                    $value = (($value * $upfrontAmount) / 100);
                                    $amount = $value - $milestone;
                                } else {
                                    $amount = ($value * $upfrontAmount) / 100;
                                }
                                $isHalf = false;
                            }
                        }
                    } else {
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        } elseif ($upfrontType == 'per kw') {
                            $amount = ($upfrontAmount * $kw);
                        } elseif ($upfrontType == 'percent') {
                            $value = @$commission[$info['type']] ? $commission[$info['type']] : 0;
                            if ($forExternal) {
                                $value = @$commission[$info['id']] ? $commission[$info['id']] : 0;
                            }
                            if ($upfront->deductible_from_prior == 1) {
                                $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId])->where('schema_type', '<', $type)->sum('amount');
                                $value = (($value * $upfrontAmount) / 100);
                                $amount = $value - $milestone;
                            } else {
                                $amount = ($value * $upfrontAmount) / 100;
                            }
                            $isHalf = false;
                        }
                    }

                    // If the amount is half, divide it by 2 this is the feature to give half upfront in case of 2 closers.
                    if ($isHalf) {
                        $amount = ($amount / 2);
                    }

                    $upfrontLimitType = $upfront->upfront_limit_type;
                    if ($upfrontLimitType == 'percent' && ! is_null($upfront->upfront_limit) && $upfront->upfront_limit > 0) {
                        // Calculate the amount as percentage of original amount
                        $amount = $amount * ($upfront->upfront_limit / 100);
                    } else {
                        if ($upfront->upfront_limit && $amount > $upfront->upfront_limit) {
                            $amount = $upfront->upfront_limit;
                        }
                    }

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $schema->pid,
                        'amount_type' => 'm1',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'amount' => $amount,
                        'redline' => $redLine,
                        'redline_type' => $redLineType,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $upfrontAmount,
                        'commission_type' => $upfrontType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    $m1 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_displayed' => '1'])->first();
                    if ($m1) {
                        if ($m1->settlement_type == 'during_m2') {
                            if ($m1->status == '1') {
                                $m1->update($data);
                            }
                        } elseif ($m1->settlement_type == 'reconciliation') {
                            if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                $isUpdate = true;
                                if ($m1->recon_status == '2') {
                                    $paidRecon = ReconCommissionHistory::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
                                    if ($paidRecon >= $amount) {
                                        $isUpdate = false;
                                    }
                                    // WHEN PAID RECON & CURRENT AMOUNT IS SAME THEN MARK AS PAID
                                    if ($paidRecon == $amount) {
                                        $data['recon_status'] = 3;
                                    }
                                }

                                if ($isUpdate) {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $m1->update($data);
                                }
                            }
                        }
                    } else {
                        UserCommission::create($data);
                        // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                    }
                } else {
                    $m1 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_displayed' => '1'])->first();
                    if ($m1) {
                        $isDelete = false;
                        if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                            $isDelete = true;
                        } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                            $isDelete = true;
                        }

                        if ($isDelete) {
                            $m1->delete();
                        }
                    }
                }
            } else {
                $m1 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_displayed' => '1'])->first();
                if ($m1) {
                    $isDelete = false;
                    if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                        $isDelete = true;
                    } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                        $isDelete = true;
                    }

                    if ($isDelete) {
                        $m1->delete();
                    }
                }
            }
        }
    }

    public function subroutineFive($checked)
    {
        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        if ($saleMasterProcess) {
            SaleMasterProcess::where('pid', $checked->pid)->update([
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
        }

        $pid = $checked->pid;
        $date = $checked->date_cancelled;
        $productId = $checked->product_id;
        $approvedDate = isset($checked->customer_signoff) ? $checked->customer_signoff : null;
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        $closerId = isset($checked->salesMasterProcess->closer1_id) ? $checked->salesMasterProcess->closer1_id : null;
        $closer2Id = isset($checked->salesMasterProcess->closer2_id) ? $checked->salesMasterProcess->closer2_id : null;
        $setterId = isset($checked->salesMasterProcess->setter1_id) ? $checked->salesMasterProcess->setter1_id : null;
        $setter2Id = isset($checked->salesMasterProcess->setter2_id) ? $checked->salesMasterProcess->setter2_id : null;
        $saleUsers = [];

        // Added external sale worker
        $existWorker = ExternalSaleWorker::where('pid', $pid)->pluck('user_id')->toArray();
        if ($existWorker) {
            $saleUsers = $existWorker;
        }

        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }
        if ($setterId) {
            $saleUsers[] = $setterId;
        }
        if ($setter2Id) {
            $saleUsers[] = $setter2Id;
        }

        $userCommissions = UserCommission::where(['pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
        $userReconCommissions = UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->get();
        $userCommissions = $userCommissions->merge($userReconCommissions);
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }

        $userCommissions = UserCommission::with('userdata')->whereHas('userdata')->where(['pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where(['user_id' => $closer->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                $clawBackType = 'reconciliation';
                $payFrequency = NULL;
                $payPeriodFrom = NULL;
                $payPeriodTo = NULL;
                $frequency = NULL;
            } else {
                $clawBackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $payPeriodFrom = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : NULL;
                $payPeriodTo = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : NULL;
                $frequency = isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $closer1PaidClawBack = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'schema_type' => $userCommission->schema_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
            $commission = $userCommission->amount;
            $clawBackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawBack, 3, '.', '');

            if ($clawBackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'milestone_schema_id' => $userCommission->milestone_schema_id,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawBackAmount,
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'schema_type' => $userCommission->schema_type,
                    'schema_name' => $userCommission->schema_name,
                    'schema_trigger' => $userCommission->schema_trigger,
                    'is_last' => $userCommission->is_last,
                    'during' => $during,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_cal_amount' => $userCommission->commission_amount,
                    'clawback_cal_type' => $userCommission->commission_type,
                    'redline' => $userCommission->redline,
                    'redline_type' => $userCommission->redline_type,
                    'user_worker_type' => $userCommission->user_worker_type,
                    'pay_frequency' => $frequency,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($userCommission->user_id, $subPositionId, $payFrequency);
                }
            }
        }

        $userCommissions = UserCommission::with('userdata')->whereHas('userdata')->where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where(['user_id' => $closer->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionReconciliation) {
                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                $clawBackType = 'reconciliation';
                $payFrequency = NULL;
                $payPeriodFrom = NULL;
                $payPeriodTo = NULL;
                $frequency = NULL;
            } else {
                $clawBackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $payPeriodFrom = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : NULL;
                $payPeriodTo = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : NULL;
                $frequency = isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $clawBackAmount = 0;
            $reconPaid = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $userCommission->user_id, 'type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if ($reconPaid) {
                $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'schema_type' => $userCommission->schema_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
                $commission = $reconPaid;
                $clawBackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');
            } else {
                $userCommission->delete();
            }

            if ($clawBackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'milestone_schema_id' => $userCommission->milestone_schema_id,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawBackAmount,
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'schema_type' => $userCommission->schema_type,
                    'schema_name' => $userCommission->schema_name,
                    'schema_trigger' => $userCommission->schema_trigger,
                    'is_last' => $userCommission->is_last,
                    'during' => $during,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_cal_amount' => $userCommission->commission_amount,
                    'clawback_cal_type' => $userCommission->commission_type,
                    'redline' => $userCommission->redline,
                    'redline_type' => $userCommission->redline_type,
                    'user_worker_type' => $userCommission->user_worker_type,
                    'pay_frequency' => $frequency,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($userCommission->user_id, $subPositionId, $payFrequency);
                }
            }
        }
        $this->overridesClawBack($pid, $date);

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $saleMasterProcess->mark_account_status_id = 1;
        $saleMasterProcess->save();
    }

    public function subroutineEightForSolar($sale, $schema, $info, $redLine, $redLineType, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                $triggerIndex = count($milestones->milestone->milestone_trigger);
                $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex - 1];
                $schemaId = @$trigger->id;
                $schemaName = @$trigger->name;
                $schemaTrigger = @$trigger->on_trigger;
            }
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = null;
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

                $commissionType = null;
                $commissionPercentage = 0;
                if ($commissionHistory) {
                    if ($commissionHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                            if ($commissionTier) {
                                $commissionPercentage = $commissionTier->value;
                                $commissionType = $commissionHistory->commission_type;
                            }
                        } else {
                            $commissionPercentage = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } elseif ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        $amount = ((($netEpc - $redLine) * $x) * $kw * 1000 * $commissionPercentage / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $amount = $amount - $milestone;

                    $withholding = [
                        'recon_amount' => null,
                        'recon_amount_type' => null,
                        'recon_limit' => 0,
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } elseif ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } elseif ($withholding['recon_amount_type'] == 'percent') {
                                $withheldPercent = $withholding['recon_amount'];
                                if ($amount > 0) {
                                    $reconAmount = ($amount * ($withheldPercent / 100));
                                }
                            }

                            if ($withholding['recon_limit'] && $reconAmount > $withholding['recon_limit']) {
                                $reconAmount = $withholding['recon_limit'];
                            }
                        }

                        if ($amount > 0) {
                            $reconDue = $amount - $reconAmount;
                            if ($reconDue <= 0) {
                                $reconAmount = $amount;
                                $amount = 0;
                            } else {
                                $amount = $reconDue;
                            }
                        } else {
                            $amount = $amount - $reconAmount;
                        }
                    }

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // if ($amount) {
                        $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->first();
                        if ($finalPayment) {
                            $finalPayment->update($data);
                        } else {
                            $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->first();
                            if ($finalPayment) {
                                if ($finalPayment->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $finalPayment->update($data);
                                }
                            } else {
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    // } else {
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->delete();
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->delete();
                    // }
                    if ($reconAmount) {
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $reconAmount;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = $withholding['recon_amount'];
                        $data['recon_amount_type'] = $withholding['recon_amount_type'];
                        $data['status'] = 3;
                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                        }
                    } else {
                        $recon = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->first();
                        if ($recon) {
                            $recon->delete();
                        }
                    }
                } else {
                    $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                    $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                    $userCommissions = $userCommissions->merge($userReconCommissions);
                    foreach ($userCommissions as $userCommission) {
                        $userCommission->delete();
                    }
                }
            } else {
                $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                $userCommissions = $userCommissions->merge($userReconCommissions);
                foreach ($userCommissions as $userCommission) {
                    $userCommission->delete();
                }
            }
        } else {
            $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
            $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
            $userCommissions = $userCommissions->merge($userReconCommissions);
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
        }
    }

    public function subroutineEightForTurf($sale, $schema, $info, $redLine, $redLineType, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = 0;
        // $redLineType = NULL;

        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                $triggerIndex = count($milestones->milestone->milestone_trigger);
                $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex - 1];
                $schemaId = @$trigger->id;
                $schemaName = @$trigger->name;
                $schemaTrigger = @$trigger->on_trigger;
            }
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = null;
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

                $commissionType = null;
                $commissionPercentage = 0;
                if ($commissionHistory) {
                    if ($commissionHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                            if ($commissionTier) {
                                $commissionPercentage = $commissionTier->value;
                                $commissionType = $commissionHistory->commission_type;
                            }
                        } else {
                            $commissionPercentage = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                if ($commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } elseif ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        $amount = (($grossAmountValue + (($netEpc - $redLine) * $x) * $kw * 1000) * $commissionPercentage / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $amount = $amount - $milestone;

                    $withholding = [
                        'recon_amount' => null,
                        'recon_amount_type' => null,
                        'recon_limit' => 0,
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } elseif ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } elseif ($withholding['recon_amount_type'] == 'percent') {
                                $withheldPercent = $withholding['recon_amount'];
                                if ($amount > 0) {
                                    $reconAmount = ($amount * ($withheldPercent / 100));
                                }
                            }

                            if ($withholding['recon_limit'] && $reconAmount > $withholding['recon_limit']) {
                                $reconAmount = $withholding['recon_limit'];
                            }
                        }

                        if ($amount > 0) {
                            $reconDue = $amount - $reconAmount;
                            if ($reconDue <= 0) {
                                $reconAmount = $amount;
                                $amount = 0;
                            } else {
                                $amount = $reconDue;
                            }
                        } else {
                            $amount = $amount - $reconAmount;
                        }
                    }

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'gross_account_value' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // if ($amount) {
                        $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->first();
                        if ($finalPayment) {
                            $finalPayment->update($data);
                        } else {
                            $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->first();
                            if ($finalPayment) {
                                if ($finalPayment->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $finalPayment->update($data);
                                }
                            } else {
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    // } else {
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->delete();
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->delete();
                    // }
                    if ($reconAmount) {
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $reconAmount;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = $withholding['recon_amount'];
                        $data['recon_amount_type'] = $withholding['recon_amount_type'];
                        $data['status'] = 3;
                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                        }
                    } else {
                        $recon = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->first();
                        if ($recon) {
                            $recon->delete();
                        }
                    }
                } else {
                    $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_last' => 1, 'is_displayed' => '1'])->first();
                    if ($finalPayment) {
                        $isDelete = false;
                        if ($finalPayment->settlement_type == 'during_m2' && $finalPayment->status == '1') {
                            $isDelete = true;
                        } elseif ($finalPayment->settlement_type == 'reconciliation' && $finalPayment->recon_status == '1') {
                            $isDelete = true;
                        }

                        if ($isDelete) {
                            $finalPayment->delete();
                        }
                    }
                }
            } else {
                $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_last' => 1, 'is_displayed' => '1'])->first();
                if ($finalPayment) {
                    $isDelete = false;
                    if ($finalPayment->settlement_type == 'during_m2' && $finalPayment->status == '1') {
                        $isDelete = true;
                    } elseif ($finalPayment->settlement_type == 'reconciliation' && $finalPayment->recon_status == '1') {
                        $isDelete = true;
                    }

                    if ($isDelete) {
                        $finalPayment->delete();
                    }
                }
            }
        } else {
            $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'is_last' => 1, 'is_displayed' => '1'])->first();
            if ($finalPayment) {
                $isDelete = false;
                if ($finalPayment->settlement_type == 'during_m2' && $finalPayment->status == '1') {
                    $isDelete = true;
                } elseif ($finalPayment->settlement_type == 'reconciliation' && $finalPayment->recon_status == '1') {
                    $isDelete = true;
                }

                if ($isDelete) {
                    $finalPayment->delete();
                }
            }
        }
    }

    public function subroutineEightForMortgage($sale, $schema, $info, $companyProfile, $redLine = 0, $redLineType = null, $forExternal = 0)
    {
        // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
            $redLine = 0;
        }

        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc; // ? $sale->net_epc / 100 : 0; // Convert netEpc from percent to decimal
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = $redLine ? $redLine / 100 : 0; // Convert redLine from percent to decimal
        $redLineType = null;
        $compRate = 0;

        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }

        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                $triggerIndex = count($milestones->milestone->milestone_trigger);
                $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex - 1];
                $schemaId = @$trigger->id;
                $schemaName = @$trigger->name;
                $schemaTrigger = @$trigger->on_trigger;
            }
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = null;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->where('self_gen_user', '1')->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $commission) {
                            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->where('self_gen_user', '1')->first();
                        }
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

                $commissionType = null;
                $commissionPercentage = 0;
                if ($commissionHistory) {
                    if ($commissionHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                            if ($commissionTier) {
                                $commissionPercentage = $commissionTier->value;
                                $commissionType = $commissionHistory->commission_type;
                            }
                        } else {
                            $commissionPercentage = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                if ($commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        // Percentage-based commission: Different formula based on domain
                        if (strtolower(config('app.domain_name')) == 'firstcoast') {
                            // Firstcoast: Keep original formula using netEpc, redLine, and kw
                            $redLine = $redLine ? $redLine / 100 : $redLine;
                            $amount = (((($netEpc - $redLine) * $x) * $kw) * $commissionPercentage / 100);
                        } else {
                            // Other mortgage domains: Use gross_account_value based formula
                            $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                        }
                        $compRate = ($amount / $grossAmountValue) * 100;
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $kw * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                        if ($compRate > $commission->commission_limit) {
                            $compRate = $commission->commission_limit;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $amount = $amount - $milestone;

                    $withholding = [
                        'recon_amount' => null,
                        'recon_amount_type' => null,
                        'recon_limit' => 0,
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } elseif ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } elseif ($withholding['recon_amount_type'] == 'percent') {
                                $withheldPercent = $withholding['recon_amount'];
                                if ($amount > 0) {
                                    $reconAmount = ($amount * ($withheldPercent / 100));
                                }
                            }

                            if ($withholding['recon_limit'] && $reconAmount > $withholding['recon_limit']) {
                                $reconAmount = $withholding['recon_limit'];
                            }
                        }

                        if ($amount > 0) {
                            $reconDue = $amount - $reconAmount;
                            if ($reconDue <= 0) {
                                $reconAmount = $amount;
                                $amount = 0;
                            } else {
                                $amount = $reconDue;
                            }
                        } else {
                            $amount = $amount - $reconAmount;
                        }
                    }

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'gross_account_value' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'comp_rate' => number_format($compRate, 4, '.', ''),
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // if ($amount) {
                        $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->first();
                        if ($finalPayment) {
                            $finalPayment->update($data);
                        } else {
                            $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->first();
                            if ($finalPayment) {
                                if ($finalPayment->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $finalPayment->update($data);
                                }
                            } else {
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    // } else {
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->delete();
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->delete();
                    // }
                    if ($reconAmount) {
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $reconAmount;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = $withholding['recon_amount'];
                        $data['recon_amount_type'] = $withholding['recon_amount_type'];
                        $data['status'] = 3;
                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                        }
                    } else {
                        $recon = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->first();
                        if ($recon) {
                            $recon->delete();
                        }
                    }
                } else {
                    $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                    $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                    $userCommissions = $userCommissions->merge($userReconCommissions);
                    foreach ($userCommissions as $userCommission) {
                        $userCommission->delete();
                    }
                }
            } else {
                $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                $userCommissions = $userCommissions->merge($userReconCommissions);
                foreach ($userCommissions as $userCommission) {
                    $userCommission->delete();
                }
            }
        } else {
            $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
            $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
            $userCommissions = $userCommissions->merge($userReconCommissions);
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
        }
    }

    public function subroutineEightForPest($sale, $schema, $info, $companyProfile, $forExternal = 0)
    {
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $productId = $sale->product_id;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }
        if (UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            return false;
        }

        $isHalf = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                $triggerIndex = count($milestones->milestone->milestone_trigger);
                $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex - 1];
                $schemaId = @$trigger->id;
                $schemaName = @$trigger->name;
                $schemaTrigger = @$trigger->on_trigger;
            }
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionType = null;
                $commissionPercentage = 0;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($commissionHistory) {
                    if ($commissionHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                            if ($commissionTier) {
                                $commissionPercentage = $commissionTier->value;
                                $commissionType = $commissionHistory->commission_type;
                            }
                        } else {
                            $commissionPercentage = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                }

                if ($commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $amount = $amount - $milestone;

                    $withholding = [
                        'recon_amount' => null,
                        'recon_amount_type' => null,
                        'recon_limit' => 0,
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } elseif ($withholding['recon_amount_type'] == 'percent') {
                                $withheldPercent = $withholding['recon_amount'];
                                if ($amount > 0) {
                                    $reconAmount = ($amount * ($withheldPercent / 100));
                                }
                            }

                            if ($withholding['recon_limit'] && $reconAmount > $withholding['recon_limit']) {
                                $reconAmount = $withholding['recon_limit'];
                            }
                        }

                        if ($amount > 0) {
                            $reconDue = $amount - $reconAmount;
                            if ($reconDue <= 0) {
                                $reconAmount = $amount;
                                $amount = 0;
                            } else {
                                $amount = $reconDue;
                            }
                        } else {
                            $amount = $amount - $reconAmount;
                        }
                    }

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionPercentage,
                        'redline_type' => $commissionType,
                        'kw' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external':'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // if ($amount) {
                        $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->first();
                        if ($finalPayment) {
                            $finalPayment->update($data);
                        } else {
                            $finalPayment = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->first();
                            if ($finalPayment) {
                                if ($finalPayment->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $finalPayment->update($data);
                                }
                            } else {
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    // } else {
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->delete();
                    //     UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->where('amount_type', '!=', 'reconciliation')->delete();
                    // }
                    if ($reconAmount) {
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $reconAmount;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = $withholding['recon_amount'];
                        $data['recon_amount_type'] = $withholding['recon_amount_type'];
                        $data['status'] = 3;
                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                        }
                    } else {
                        $recon = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'amount_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->first();
                        if ($recon) {
                            $recon->delete();
                        }
                    }
                } else {
                    $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                    $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                    $userCommissions = $userCommissions->merge($userReconCommissions);
                    foreach ($userCommissions as $userCommission) {
                        $userCommission->delete();
                    }
                }
            } else {
                $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                $userCommissions = $userCommissions->merge($userReconCommissions);
                foreach ($userCommissions as $userCommission) {
                    $userCommission->delete();
                }
            }
        } else {
            $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
            $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
            $userCommissions = $userCommissions->merge($userReconCommissions);
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
        }
    }

    public function subroutineElevenForTurf($sale, $schema, $info, $redLine, $redLineType, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = 0;
        // $redLineType = NULL;

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $commissionPercentage = 0;
                $commissionType = null;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
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
                            $commissionPercentage = @$commissionHistory->commission;
                            $commissionType = @$commissionHistory->commission_type;
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
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } elseif ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        $amount = (($grossAmountValue + (($netEpc - $redLine) * $x) * $kw * 1000) * $commissionPercentage / 100);
                    }

                    if ($isHalf) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $reconAmount = 0;
                    $withHeldAmount = null;
                    $withHeldAmountType = null;
                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $withHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->first();
                    if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                        if ($withHeld->recon_amount_type == 'per sale') {
                            $reconAmount = $withHeld->recon_amount;
                        } elseif ($withHeld->recon_amount_type == 'percent') {
                            $withheldPercent = $withHeld->recon_amount;
                            $totalM2 = $amount - $milestone;
                            $reconAmount = ($totalM2 * ($withheldPercent / 100));
                        } else {
                            $reconAmount = ($withHeld->recon_amount * $kw);
                        }
                        $withHeldAmount = $withHeld->recon_amount;
                        $withHeldAmountType = $withHeld->recon_amount_type;
                    }

                    $lastPaid = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                    $amount = number_format($amount - $milestone - $lastPaid - $reconAmount, 3, '.', '');
                    // Round to 2 decimals to match database DECIMAL(10,2) precision
                    $amount = round((float) $amount, 2);

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2 update',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'gross_account_value' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                    if ($amount >= 0.1 || $amount <= -0.1) {
                        $paid = false;
                        $m2 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                                $paid = true;
                            } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                                $paid = true;
                            }
                        }

                        if ($paid) {
                            UserCommission::create($data);
                            // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                        } else {
                            if ($m2) {
                                unset($data['amount_type']);
                                $m2->update($data);
                            } else {
                                $data['amount_type'] = 'm2';
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }

                    $totalWithHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                    $withHeldDue = number_format($reconAmount - $totalWithHeld, 3, '.', '');
                    if ((float) $withHeldDue) {
                        $paid = false;
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
                        if ($withheld && $withheld->recon_status == '3') {
                            $paid = true;
                        }

                        if ($paid) {
                            unset($data['pay_period_from']);
                            unset($data['pay_period_to']);
                            $data['amount'] = $withHeldDue;
                            $data['amount_type'] = 'reconciliation update';
                            $data['settlement_type'] = 'reconciliation';
                            $data['recon_amount'] = $withHeldAmount;
                            $data['recon_amount_type'] = $withHeldAmountType;
                            $data['status'] = 3;

                            UserCommission::create($data);
                        } else {
                            if ($withheld) {
                                if ($withheld->recon_status == '2') {
                                    $due = $withHeldDue + $withheld->amount;
                                } else {
                                    $due = $withHeldDue;
                                }
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $due;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $withheld->update($data);
                            } else {
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $withHeldDue;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $data['recon_amount'] = $withHeldAmount;
                                $data['recon_amount_type'] = $withHeldAmountType;
                                $data['status'] = 3;
                                UserCommission::create($data);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }
                } else {
                    $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                    $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                    $userCommissions = $userCommissions->merge($userReconCommissions);
                    foreach ($userCommissions as $userCommission) {
                        $userCommission->delete();
                    }
                }
            } else {
                $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
                $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
                $userCommissions = $userCommissions->merge($userReconCommissions);
                foreach ($userCommissions as $userCommission) {
                    $userCommission->delete();
                }
            }
        } else {
            $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'schema_type' => $type, 'settlement_type' => 'during_m2', 'is_last' => 1, 'status' => 1, 'is_displayed' => '1'])->get();
            $userReconCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_last' => 1, 'recon_status' => 1, 'is_displayed' => '1'])->get();
            $userCommissions = $userCommissions->merge($userReconCommissions);
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
        }
    }

    public function subroutineElevenForMortgage($sale, $schema, $info, $companyProfile, $redLine = 0, $redLineType = null, $forExternal = 0)
    {
        // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
            $redLine = 0;
        }

        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc; // Here taking net_epc from sale table where it is already divided by 100 //? $sale->net_epc / 100 : 0; // Convert netEpc from percent to decimal
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = $redLine ? $redLine / 100 : 0; // Convert redLine from percent to decimal
        $redLineType = null;
        $compRate = 0;

        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $commissionPercentage = 0;
                $commissionType = null;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
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
                            $commissionPercentage = @$commissionHistory->commission;
                            $commissionType = @$commissionHistory->commission_type;
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
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } elseif ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        // Percentage-based commission: Different formula based on domain
                        if (strtolower(config('app.domain_name')) == 'firstcoast') {
                            // Firstcoast: Keep original formula using netEpc, redLine, and kw
                            $redLine = $redLine ? $redLine / 100 : 0;
                            $amount = (((($netEpc - $redLine) * $x) * $kw) * $commissionPercentage / 100);
                        } else {
                            // Other mortgage domains: Use gross_account_value based formula
                            $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                        }
                        $compRate = ($amount / $grossAmountValue) * 100;

                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $kw * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }

                        if ($compRate > $commission->commission_limit) {
                            $compRate = $commission->commission_limit;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $reconAmount = 0;
                    $withHeldAmount = null;
                    $withHeldAmountType = null;
                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $withHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->first();
                    if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                        if ($withHeld->recon_amount_type == 'per sale') {
                            $reconAmount = $withHeld->recon_amount;
                        } elseif ($withHeld->recon_amount_type == 'percent') {
                            $withheldPercent = $withHeld->recon_amount;
                            $totalM2 = $amount - $milestone;
                            $reconAmount = ($totalM2 * ($withheldPercent / 100));
                        } else {
                            $reconAmount = ($withHeld->recon_amount * $kw);
                        }
                        $withHeldAmount = $withHeld->recon_amount;
                        $withHeldAmountType = $withHeld->recon_amount_type;
                    }

                    $lastPaid = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                    $amount = number_format($amount - $milestone - $lastPaid - $reconAmount, 3, '.', '');
                    // Round to 2 decimals to match database DECIMAL(10,2) precision
                    $amount = round((float) $amount, 2);

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2 update',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'gross_account_value' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'comp_rate' => number_format($compRate, 4, '.', ''),
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1)? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                    if ($amount >= 0.1 || $amount <= -0.1) {
                        $paid = false;
                        $m2 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                                $paid = true;
                            } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                                $paid = true;
                            }
                        }

                        if ($paid) {
                            UserCommission::create($data);
                            // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                        } else {
                            if ($m2) {
                                unset($data['amount_type']);
                                $m2->update($data);
                            } else {
                                $data['amount_type'] = 'm2';
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }

                    $totalWithHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                    $withHeldDue = number_format($reconAmount - $totalWithHeld, 3, '.', '');
                    if ((float) $withHeldDue) {
                        $paid = false;
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
                        if ($withheld && $withheld->recon_status == '3') {
                            $paid = true;
                        }

                        if ($paid) {
                            unset($data['pay_period_from']);
                            unset($data['pay_period_to']);
                            $data['amount'] = $withHeldDue;
                            $data['amount_type'] = 'reconciliation update';
                            $data['settlement_type'] = 'reconciliation';
                            $data['recon_amount'] = $withHeldAmount;
                            $data['recon_amount_type'] = $withHeldAmountType;
                            $data['status'] = 3;

                            UserCommission::create($data);
                        } else {
                            if ($withheld) {
                                if ($withheld->recon_status == '2') {
                                    $due = $withHeldDue + $withheld->amount;
                                } else {
                                    $due = $withHeldDue;
                                }
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $due;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $withheld->update($data);
                            } else {
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $withHeldDue;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $data['recon_amount'] = $withHeldAmount;
                                $data['recon_amount_type'] = $withHeldAmountType;
                                $data['status'] = 3;
                                UserCommission::create($data);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }
                }
            }
        }
    }

    public function subroutineElevenForSolar($sale, $schema, $info, $redLine, $redLineType, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $commissionPercentage = 0;
                $commissionType = null;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
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
                            $commissionPercentage = @$commissionHistory->commission;
                            $commissionType = @$commissionHistory->commission_type;
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
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

                $commissionRedLine = $redLine;
                $commissionRedLineType = $redLineType;
                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } elseif ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                        $commissionRedLine = $commissionPercentage;
                        $commissionRedLineType = $commissionType;
                    } else {
                        $amount = ((($netEpc - $redLine) * $x) * $kw * 1000 * $commissionPercentage / 100);
                    }

                    if ($isHalf) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $reconAmount = 0;
                    $withHeldAmount = null;
                    $withHeldAmountType = null;
                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $withHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->first();
                    if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                        if ($withHeld->recon_amount_type == 'per sale') {
                            $reconAmount = $withHeld->recon_amount;
                        } elseif ($withHeld->recon_amount_type == 'percent') {
                            $withheldPercent = $withHeld->recon_amount;
                            $totalM2 = $amount - $milestone;
                            $reconAmount = ($totalM2 * ($withheldPercent / 100));
                        } else {
                            $reconAmount = ($withHeld->recon_amount * $kw);
                        }
                        $withHeldAmount = $withHeld->recon_amount;
                        $withHeldAmountType = $withHeld->recon_amount_type;
                    }

                    $lastPaid = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                    $amount = number_format($amount - $milestone - $lastPaid - $reconAmount, 3, '.', '');
                    // Round to 2 decimals to match database DECIMAL(10,2) precision
                    $amount = round((float) $amount, 2);

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2 update',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionRedLine,
                        'redline_type' => $commissionRedLineType,
                        'net_epc' => $netEpc,
                        'kw' => $kw,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'worker_type' => ($forExternal == 1) ? 'external' : 'internal',
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                    if ($amount >= 0.1 || $amount <= -0.1) {
                        $paid = false;
                        $m2 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                                $paid = true;
                            } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                                $paid = true;
                            }
                        }

                        if ($paid) {
                            UserCommission::create($data);
                            // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                        } else {
                            if ($m2) {
                                unset($data['amount_type']);
                                $m2->update($data);
                            } else {
                                $data['amount_type'] = 'm2';
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }

                    $totalWithHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                    $withHeldDue = number_format($reconAmount - $totalWithHeld, 3, '.', '');
                    if ((float) $withHeldDue) {
                        $paid = false;
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
                        if ($withheld && $withheld->recon_status == '3') {
                            $paid = true;
                        }

                        if ($paid) {
                            unset($data['pay_period_from']);
                            unset($data['pay_period_to']);
                            $data['amount'] = $withHeldDue;
                            $data['amount_type'] = 'reconciliation update';
                            $data['settlement_type'] = 'reconciliation';
                            $data['recon_amount'] = $withHeldAmount;
                            $data['recon_amount_type'] = $withHeldAmountType;
                            $data['status'] = 3;

                            UserCommission::create($data);
                        } else {
                            if ($withheld) {
                                if ($withheld->recon_status == '2') {
                                    $due = $withHeldDue + $withheld->amount;
                                } else {
                                    $due = $withHeldDue;
                                }
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $due;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $withheld->update($data);
                            } else {
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $withHeldDue;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $data['recon_amount'] = $withHeldAmount;
                                $data['recon_amount_type'] = $withHeldAmountType;
                                $data['status'] = 3;
                                UserCommission::create($data);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }
                }
            }
        }
    }

    public function subroutineElevenForPest($sale, $schema, $info, $companyProfile, $forExternal = 0)
    {
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $date = $schema->milestone_date;
        $productId = $sale->product_id;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;

        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $productCode = $userOrganizationData['product']->productCodes()->first()?->product_code;
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $commissionType = null;
                $commissionPercentage = 0;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($commissionHistory) {
                    $commissionType = @$commissionHistory->commission_type;
                    $commissionPercentage = $commissionHistory->commission;
                }

                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                    }

                    if ($isHalf) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? null;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if ($amount > $commissionAmount) {
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $reconAmount = 0;
                    $withHeldAmount = null;
                    $withHeldAmountType = null;
                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $withHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'amount_type' => 'reconciliation', 'is_last' => '1', 'is_displayed' => '1'])->first();
                    if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                        if ($withHeld->recon_amount_type == 'per sale') {
                            $reconAmount = $withHeld->recon_amount;
                        } elseif ($withHeld->recon_amount_type == 'percent') {
                            $withheldPercent = $withHeld->recon_amount;
                            $totalM2 = $amount - $milestone;
                            $reconAmount = ($totalM2 * ($withheldPercent / 100));
                        } else {
                            $reconAmount = ($withHeld->recon_amount * $grossAmountValue);
                        }
                        $withHeldAmount = $withHeld->recon_amount;
                        $withHeldAmountType = $withHeld->recon_amount_type;
                    }

                    $lastPaid = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                    $amount = number_format($amount - $milestone - $lastPaid - $reconAmount, 3, '.', '');
                    // Round to 2 decimals to match database DECIMAL(10,2) precision
                    $amount = round((float) $amount, 2);

                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userId);
                    $data = [
                        'user_id' => $userId,
                        'position_id' => $subPositionId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $schemaId,
                        'product_code' => $productCode,
                        'pid' => $pid,
                        'amount_type' => 'm2 update',
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'schema_type' => $type,
                        'is_last' => 1,
                        'amount' => $amount,
                        'redline' => $commissionPercentage,
                        'redline_type' => $commissionType,
                        'kw' => $grossAmountValue,
                        'date' => $date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $approvalDate,
                        'is_stop_payroll' => $stopPayroll,
                        'commission_amount' => $commissionPercentage,
                        'commission_type' => $commissionType,
                        'user_worker_type' => $user->worker_type,
                        'pay_frequency' => $payFrequency->pay_frequency,
                    ];

                    // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                    if ($amount >= 0.1 || $amount <= -0.1) {
                        $paid = false;
                        $m2 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                                $paid = true;
                            } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                                $paid = true;
                            }
                        }

                        if ($paid) {
                            UserCommission::create($data);
                            // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                        } else {
                            if ($m2) {
                                unset($data['amount_type']);
                                $m2->update($data);
                            } else {
                                $data['amount_type'] = 'm2';
                                UserCommission::create($data);
                                // subroutineCreatePayrollRecord($userId, $subPositionId, $payFrequency);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }

                    $totalWithHeld = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                    $withHeldDue = number_format($reconAmount - $totalWithHeld, 3, '.', '');
                    if ((float) $withHeldDue) {
                        $paid = false;
                        $withheld = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
                        if ($withheld && $withheld->recon_status == '3') {
                            $paid = true;
                        }

                        if ($paid) {
                            unset($data['pay_period_from']);
                            unset($data['pay_period_to']);
                            $data['amount'] = $withHeldDue;
                            $data['amount_type'] = 'reconciliation update';
                            $data['settlement_type'] = 'reconciliation';
                            $data['recon_amount'] = $withHeldAmount;
                            $data['recon_amount_type'] = $withHeldAmountType;
                            $data['status'] = 3;

                            UserCommission::create($data);
                        } else {
                            if ($withheld) {
                                if ($withheld->recon_status == '2') {
                                    $due = $withHeldDue + $withheld->amount;
                                } else {
                                    $due = $withHeldDue;
                                }
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $due;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $withheld->update($data);
                            } else {
                                unset($data['pay_period_from']);
                                unset($data['pay_period_to']);
                                $data['amount'] = $withHeldDue;
                                $data['amount_type'] = 'reconciliation';
                                $data['settlement_type'] = 'reconciliation';
                                $data['recon_amount'] = $withHeldAmount;
                                $data['recon_amount_type'] = $withHeldAmountType;
                                $data['status'] = 3;
                                UserCommission::create($data);
                            }
                        }
                    } else {
                        $userCommissions = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->get();
                        foreach ($userCommissions as $userCommission) {
                            $userCommission->delete();
                        }
                    }
                }
            }
        }
    }

    public function overridesClawBack($pid, $date, $checkedStatus = 0, $userId = '')
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $productId = $saleMaster->product_id;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        $userOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->when(!empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->get();
        $userReconOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->when(!empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->get();
        $userOverrides = $userOverrides->merge($userReconOverrides);
        foreach ($userOverrides as $userOverride) {
            $userOverride->delete();
        }

        $data = UserOverrides::with('userdata')->whereHas('userdata')
            ->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->when(! empty($userId), function ($q) use ($userId) {
                $q->where('sale_user_id', $userId);
            })->get();
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        $data->transform(function ($data) use ($date, $pid, $companySetting, $checkedStatus, $approvedDate, $productId) {
            $clawBackSettlement = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'during' => $data->during, 'is_displayed' => '1'])->sum('clawback_amount');
            $userOverride = $data->amount;
            $clawBackAmount = number_format($userOverride, 3, '.', '') - number_format($clawBackSettlement, 3, '.', '');

            if ($clawBackAmount) {
                $stopPayroll = ($data->userdata->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $data->userdata->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where(['user_id' => $data->userdata->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }
                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionReconciliation) {
                    $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                }
                if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                    $clawBackType = 'reconciliation';
                    $payFrequency = null;
                } else {
                    $clawBackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $data->user_id);
                }

                ClawbackSettlement::create([
                    'user_id' => $data->user_id,
                    'position_id' => $subPositionId,
                    'sale_user_id' => $data->sale_user_id,
                    'pid' => $pid,
                    'clawback_amount' => number_format($clawBackAmount, 3, '.', ''),
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'type' => 'overrides',
                    'adders_type' => $data->type,
                    'during' => $data->during,
                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => $checkedStatus,
                    'clawback_cal_amount' => $data->overrides_amount,
                    'clawback_cal_type' => $data->overrides_type,
                    'user_worker_type' => $data->userdata->worker_type,
                    'pay_frequency' => isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($data->user_id, $subPositionId, $payFrequency);
                }
            }
        });

        $data = UserOverrides::with('userdata')->whereHas('userdata')
            ->where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->when(! empty($userId), function ($q) use ($userId) {
                $q->where('sale_user_id', $userId);
            })->get();
        $data->transform(function ($data) use ($date, $pid, $companySetting, $checkedStatus, $approvedDate, $productId) {
            $clawBackAmount = 0;
            $reconPaid = ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $data->user_id, 'overrider' => $data->sale_user_id, 'type' => $data->type, 'during' => $data->during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
            if ($reconPaid) {
                $closer1PaidClawBack = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'during' => $data->during, 'is_displayed' => '1'])->sum('clawback_amount');
                $userOverride = $reconPaid;
                $clawBackAmount = number_format($userOverride, 3, '.', '') - number_format($closer1PaidClawBack, 3, '.', '');
            } else {
                $data->delete();
            }

            if ($clawBackAmount) {
                $stopPayroll = ($data->userdata->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $data->userdata->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where(['user_id' => $data->userdata->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }
                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionReconciliation) {
                    $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                }
                if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                    $clawBackType = 'reconciliation';
                    $payFrequency = null;
                } else {
                    $clawBackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $data->user_id);
                }

                ClawbackSettlement::create([
                    'user_id' => $data->user_id,
                    'position_id' => $subPositionId,
                    'sale_user_id' => $data->sale_user_id,
                    'pid' => $pid,
                    'clawback_amount' => number_format($clawBackAmount, 3, '.', ''),
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'type' => 'overrides',
                    'adders_type' => $data->type,
                    'during' => $data->during,
                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => $checkedStatus,
                    'clawback_cal_amount' => $data->overrides_amount,
                    'clawback_cal_type' => $data->overrides_type,
                    'user_worker_type' => $data->userdata->worker_type,
                    'pay_frequency' => isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($data->user_id, $subPositionId, $payFrequency);
                }
            }
        });
    }

    public function clawBackSalesData($closerId, $checked, $check = false)
    {
        if (!$closerId) {
            return;
        }

        $saleCloserId = $closerId;
        $closerId = isset($checked->salesMasterProcess->closer1Detail->id) ? $checked->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($checked->salesMasterProcess->closer2Detail->id) ? $checked->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($checked->salesMasterProcess->setter1Detail->id) ? $checked->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($checked->salesMasterProcess->setter2Detail->id) ? $checked->salesMasterProcess->setter2Detail->id : null;
        if ($check && $check == 'setter' && $setterId == $closerId) {
            return false;
        }
        if ($check && $check == 'setter2' && $setter2Id == $closer2Id) {
            return false;
        }

        $date = date('Y-m-d');
        $pid = $checked->pid;
        $productId = $checked->product_id;
        $approvedDate = isset($checked->customer_signoff) ? $checked->customer_signoff : null;
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        $userCommissions = UserCommission::where(['pid' => $pid, 'user_id' => $saleCloserId, 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }
        $userCommissions = UserCommission::with('userdata')->whereHas('userdata')->where(['pid' => $pid, 'user_id' => $saleCloserId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $clawBack = 0;
            $closer1PaidClawBack = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'schema_type' => $userCommission->schema_type, 'during' => $during, 'is_displayed' => '1'])->first();
            if ($closer1PaidClawBack) {
                $clawBack = $closer1PaidClawBack->clawback_amount;
            }
            $commission = $userCommission->amount;
            $clawBackAmount = number_format($commission, 3, '.', '') - number_format($clawBack, 3, '.', '');

            if ($clawBackAmount) {
                $closer = $userCommission->userdata;
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

                $subPositionId = $closer->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where(['user_id' => $closer->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionReconciliation) {
                    $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                }
                if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                    $clawBackType = 'reconciliation';
                    $payFrequency = null;
                    $payPeriodFrom = null;
                    $payPeriodTo = null;
                } else {
                    $clawBackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                    $payPeriodFrom = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                    $payPeriodTo = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
                }

                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'product_id' => $userCommission->product_id,
                    'product_code' => $userCommission->product_code,
                    'milestone_schema_id' => $userCommission->milestone_schema_id,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawBackAmount,
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'schema_type' => $userCommission->schema_type,
                    'schema_name' => $userCommission->schema_name,
                    'schema_trigger' => $userCommission->schema_trigger,
                    'is_last' => $userCommission->is_last,
                    'during' => $during,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => 1,
                    'clawback_cal_amount' => $userCommission->commission_amount,
                    'clawback_cal_type' => $userCommission->commission_type,
                    'redline' => $userCommission->redline,
                    'redline_type' => $userCommission->redline_type,
                    'user_worker_type' => $userCommission->userdata->worker_type,
                    'pay_frequency' => isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($userCommission->user_id, $subPositionId, $payFrequency);
                }
            }
        }

        $userCommissions = UserCommission::where(['pid' => $pid, 'user_id' => $saleCloserId, 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }
        $userCommissions = UserCommission::with('userdata')->whereHas('userdata')->where(['pid' => $pid, 'user_id' => $saleCloserId, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->get();
        foreach ($userCommissions as $userCommission) {
            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $clawBackAmount = 0;
            $reconPaid = ReconCommissionHistory::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if ($reconPaid) {
                $clawBack = 0;
                $closer1PaidClawBack = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'schema_type' => $userCommission->schema_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
                if ($closer1PaidClawBack) {
                    $clawBack = $closer1PaidClawBack->clawback_amount;
                }
                $commission = $reconPaid;
                $clawBackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawBack, 3, '.', '');
            } else {
                $userCommission->delete();
            }

            if ($clawBackAmount) {
                $closer = $userCommission->userdata;
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

                $subPositionId = $closer->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where(['user_id' => $closer->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionReconciliation) {
                    $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
                }
                if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->clawback_settlement == 'Reconciliation') {
                    $clawBackType = 'reconciliation';
                    $payFrequency = null;
                    $payPeriodFrom = null;
                    $payPeriodTo = null;
                } else {
                    $clawBackType = 'next payroll';
                    $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                    $payPeriodFrom = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                    $payPeriodTo = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
                }

                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'product_id' => $userCommission->product_id,
                    'product_code' => $userCommission->product_code,
                    'milestone_schema_id' => $userCommission->milestone_schema_id,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawBackAmount,
                    'clawback_type' => $clawBackType,
                    'status' => $clawBackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'schema_type' => $userCommission->schema_type,
                    'schema_name' => $userCommission->schema_name,
                    'schema_trigger' => $userCommission->schema_trigger,
                    'is_last' => $userCommission->is_last,
                    'during' => $during,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => 1,
                    'clawback_cal_amount' => $userCommission->commission_amount,
                    'clawback_cal_type' => $userCommission->commission_type,
                    'redline' => $userCommission->redline,
                    'redline_type' => $userCommission->redline_type,
                    'user_worker_type' => $userCommission->userdata->worker_type,
                    'pay_frequency' => isset($payFrequency->pay_frequency) ? $payFrequency->pay_frequency : NULL,
                ]);

                if ($clawBackType == 'next payroll') {
                    // subroutineCreatePayrollRecord($userCommission->user_id, $subPositionId, $payFrequency);
                }
            }
        }
        $this->overridesClawBackData($saleCloserId, $pid, $date);
    }

    public function removeClawBackForNewUser($closerId, $checked)
    {
        $pid = $checked->pid;
        $userClawBacks = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'commission', 'clawback_type' => 'next payroll', 'status' => '1', 'is_displayed' => '1'])->get();
        $userReconClawBacks = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'commission', 'clawback_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->get();
        $userClawBacks = $userClawBacks->merge($userReconClawBacks);
        foreach ($userClawBacks as $userClawBack) {
            $userClawBack->delete();
        }

        $userClawBacks = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $closerId, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'status' => '1', 'is_displayed' => '1'])->get();
        $userReconClawBacks = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $closerId, 'type' => 'overrides', 'clawback_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->get();
        $userClawBacks = $userClawBacks->merge($userReconClawBacks);
        foreach ($userClawBacks as $userClawBack) {
            $userClawBack->delete();
        }
    }

    public function overridesClawBackData($userId, $pid, $date)
    {
        $this->overridesClawBack($pid, $date, 1, $userId);
    }

    public function m2updateRemoved($checked, $forExternal = 0)
    {
        $pid = $checked->pid;
        if($forExternal == 0){
            $userCommissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1', 'worker_type' => 'internal'])->get();
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
            $userOverrides = UserOverrides::where(['pid' => $pid, 'during' => 'm2 update', 'status' => '1', 'is_displayed' => '1', 'worker_type' => 'internal'])->get();
            foreach ($userOverrides as $userOverride) {
                $userOverride->delete();
            }
            $userClawBacks = ClawbackSettlement::where(['pid' => $pid, 'during' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
            foreach ($userClawBacks as $userClawBack) {
                $userClawBack->delete();
            }
        }else{
            $userCommissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1','worker_type' => 'external'])->get();
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
            $userOverrides = UserOverrides::where(['pid' => $pid, 'during' => 'm2 update', 'status' => '1', 'is_displayed' => '1', 'worker_type' => 'external'])->get();
            foreach ($userOverrides as $userOverride) {
                $userOverride->delete();
            }
            $userClawBacks = ClawbackSettlement::where(['pid' => $pid, 'during' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->get();
            foreach ($userClawBacks as $userClawBack) {
                $userClawBack->delete();
            }
        }
    }

    /**
     * Calculate commission amount for whiteknight domain
     */
    private function calculateWhiteknightCommissionAmount(array $commission, array $info, int $forExternal, string $type, string $upfrontType, float $upfrontAmount, string $pid, int $userId, int $schemaId): float
    {
        try {
            // Get total commission for this user
            $totalCommission = $forExternal ?
                (@$commission[$info['id']] ? $commission[$info['id']] : 0) :
                (@$commission[$info['type']] ? $commission[$info['type']] : 0);

            // New whiteknight logic: Upfront + 50/50 split of remaining
            switch (strtolower($type)) {
                case 'm1':
                    // M1 (Upfront): Use upfront amount from user profile
                    if ($upfrontType == 'per sale') {
                        return $upfrontAmount;
                    } elseif ($upfrontType == 'percent') {
                        return ($totalCommission * $upfrontAmount) / 100;
                    }

                    return $upfrontAmount;

                case 'm2':
                case 'm3':
                    // M2/M3: Apply configured percentage to remaining commission after M1
                    $m1Amount = $this->getM1CommissionAmountForUser($userId, $pid, $totalCommission);
                    $remaining = max(0, $totalCommission - $m1Amount); // Prevent negative

                    // Use the configured upfront percentage for this milestone
                    if ($upfrontType == 'per sale') {
                        return $upfrontAmount;
                    } elseif ($upfrontType == 'percent') {
                        return round($remaining * ($upfrontAmount / 100), 2);
                    }

                    return $upfrontAmount;

                default:
                    // Fallback to original logic for other milestone types
                    if ($upfrontType == 'per sale') {
                        return $upfrontAmount;
                    } elseif ($upfrontType == 'percent') {
                        return ($totalCommission * $upfrontAmount) / 100;
                    }

                    return $upfrontAmount;
            }
        } catch (\Exception $e) {
            Log::error('Whiteknight commission calculation error', [
                'error' => $e->getMessage(),
                'pid' => $pid,
                'user_id' => $userId,
                'schema_id' => $schemaId,
            ]);

            // Fallback to original calculation
            if ($upfrontType == 'per sale') {
                return $upfrontAmount;
            } elseif ($upfrontType == 'percent') {
                $value = $forExternal ?
                    (@$commission[$info['id']] ? $commission[$info['id']] : 0) :
                    (@$commission[$info['type']] ? $commission[$info['type']] : 0);

                return ($value * $upfrontAmount) / 100;
            }

            return $upfrontAmount;
        }
    }

    /**
     * Get M1 upfront amount for user from UserUpfrontHistory
     */
    private function getM1CommissionAmountForUser(int $userId, string $pid, float $totalCommission): float
    {
        try {
            $sale = \App\Models\SalesMaster::where('pid', $pid)->first();
            if (! $sale) {
                return 0;
            }

            // First, check user_commission table for M1 amount
            $userCommission = \App\Models\UserCommission::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('schema_type', 'm1')
                ->first();

            if ($userCommission) {
                return round($userCommission->amount, 2);
            }

            // If no M1 amount found in user_commission table
            Log::warning('No M1 amount found in user_commission table for user in commission calculation', [
                'user_id' => $userId,
                'pid' => $pid,
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::error('Error getting M1 amount for user in commission calculation', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'pid' => $pid,
            ]);

            return 0;
        }
    }
}
