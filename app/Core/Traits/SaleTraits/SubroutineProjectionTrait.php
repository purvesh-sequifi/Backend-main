<?php

namespace App\Core\Traits\SaleTraits;

use App\Models\User;
use App\Models\Products;
use App\Models\Positions;
use App\Models\SalesMaster;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\OverrideStatus;
use App\Models\UserCommission;
use App\Models\ManualOverrides;
use App\Models\SaleTiersDetail;
use App\Models\PositionOverride;
use App\Models\ClawbackSettlement;
use App\Models\PositionCommission;
use App\Models\UserUpfrontHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AdditionalLocations;
use App\Models\UserOverrideHistory;
use App\Models\UserTransferHistory;
use App\Models\overrideSystemSetting;
use App\Models\UserCommissionHistory;
use App\Models\ManualOverridesHistory;
use App\Models\PositionReconciliations;
use App\Models\ProjectionUserOverrides;
use App\Models\UserOrganizationHistory;
use App\Models\ProjectionUserCommission;
use App\Models\ProductMilestoneHistories;
use App\Models\PositionCommissionUpfronts;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserCommissionHistoryTiersRange;
use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserAdditionalOfficeOverrideHistoryTiersRange;
use App\Core\Traits\OverrideArchiveCheckTrait;

trait SubroutineProjectionTrait
{
    use ReconRoutineTraits, EditSaleTrait, OverrideArchiveCheckTrait;

    public function subroutineThree($sale, $schema, $info, $commission, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : NULL;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : NULL;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : NULL;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : NULL;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;
        $isSelfGen = false;
        if($forExternal == 0){        
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && !empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && !empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && !empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && !empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        }else{
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }


        $user = User::where(['id' => $userId])->first();
        if ($user) {
            $companyProfile = CompanyProfile::first();
            if (!$productId) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
            }
            $organization = UserOrganizationHistory::where('effective_date', '<=', $approvalDate)->where('user_id', $userId)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId, 'effective_date' => $organization?->effective_date])->orderBy('id', 'DESC')->first();
            $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                $triggerIndex = (preg_replace('/\D/', '', $type) - 1);
                $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex];
                $schemaId = @$trigger->id;
                $schemaName = @$trigger->name;
                $schemaTrigger = @$trigger->on_trigger;
            }

            $subPositionId = @$userOrganizationHistory->sub_position_id;
            $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (!$upfront) {
                $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId])->whereNull('effective_date')->orderBy('id', 'DESC')->first();
            }
            if ($upfront && $upfront->upfront_status == 1) {
                $upfrontAmount = 0;
                $upfrontType = NULL;
                $upfrontHistory = NULL;
                if (@$userOrganizationHistory->self_gen_accounts == 1) {
                    if ($isSelfGen) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId, 'self_gen_user' => '1'])->whereNull('core_position_id')
                            ->where('upfront_effective_date', '<=', $approvalDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    } else if ($userOrganizationHistory->position_id == '2' || $userOrganizationHistory->position_id == '3') {
                        $corePosition = '';
                        if ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                            $corePosition = 2;
                        } else if ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
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
                    } else if ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory->position_id == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory->position_id == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }
                    if ($corePosition) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schemaId, 'self_gen_user' => '0', 'core_position_id' => $corePosition])
                            ->where('upfront_effective_date', '<=', $approvalDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }

                if ($upfrontHistory) {
                    if ($upfrontHistory->tiers_id) {
                        $level = SaleTiersDetail::where(['pid' => $schema->pid, 'user_id' => $userId, 'type' => 'Upfront', 'sub_type' => $type])->whereNotNull('tier_level')->first();
                        if ($level) {
                            $upFrontTier = UserUpfrontHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                                $q->where('level', $level->tier_level);
                            })->with('level')->where(['user_upfront_history_id' => $upfrontHistory->id])->first();
                            if ($upFrontTier) {
                                $upfrontAmount = $upFrontTier->value;
                                $upfrontType = $upfrontHistory->upfront_sale_type;
                            }
                            else{
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
                if ($check['status'] && $upfrontAmount && $upfrontType) {
                    $amount = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        // Special logic for whiteknight domain
                        if (config('app.domain_name') === 'whiteknight') {
                            $amount = $this->calculateWhiteknightProjectionAmount(
                                $commission, $info, $forExternal, $type, $upfrontType, $upfrontAmount, 
                                $pid, $userId, $schemaId
                            );
                        } else {
                            if ($upfrontType == 'per sale') {
                                $amount = $upfrontAmount;
                            } else if ($upfrontType == 'percent') {

                                $value = @$commission[$info['type']] ? $commission[$info['type']] : 0;
                                if($forExternal){
                                    $value = @$commission[$info['id']] ? $commission[$info['id']] : 0;
                                }
                                if ($upfront && $upfront->deductible_from_prior == 1) {
                                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId])->where('schema_type', '<', $type)->sum('amount') ?? 0;
                                    $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->where('type', '<', $type)->sum('amount') ?? 0;
                                    $value = (($value * $upfrontAmount) / 100);
                                    $amount = $value - $milestone - $projectedMilestone;
                                } else {
                                    $amount = ($value * $upfrontAmount) / 100;
                                }
                                $isHalf = false;
                            }
                        }
                    } else {
                        if ($upfrontType == 'per sale') {
                            $amount = $upfrontAmount;
                        } else if ($upfrontType == 'per kw') {
                            $amount = ($upfrontAmount * $kw);
                        } else if ($upfrontType == 'percent') {
                            $value = @$commission[$info['type']] ? $commission[$info['type']] : 0;
                            if($forExternal){
                                $value = @$commission[$info['id']] ? $commission[$info['id']] : 0;
                            }
                            if ($upfront && $upfront->deductible_from_prior == 1) {
                                $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId])->where('schema_type', '<', $type)->sum('amount') ?? 0;
                                $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->where('type', '<', $type)->sum('amount') ?? 0;
                                $value = (($value * $upfrontAmount) / 100);
                                $amount = $value - $milestone - $projectedMilestone;
                            } else {
                                $amount = ($value * $upfrontAmount) / 100;
                            }
                            $isHalf = false;
                        }
                    }

                    if ($companyProfile->company_type != CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ($isHalf) {
                            $amount = ($amount / 2);
                        }
                    }
                    $upfrontLimitType = $upfront->upfront_limit_type;
                    if ($upfrontLimitType == 'percent' && !is_null($upfront->upfront_limit) && $upfront->upfront_limit > 0) {
                        // Calculate the amount as percentage of original amount
                        $amount = $amount * ($upfront->upfront_limit / 100);
                    }else{
                        if ($upfront->upfront_limit && $amount > $upfront->upfront_limit) {
                            $amount = $upfront->upfront_limit;
                        }
                    }
                    
                    
                    ProjectionUserCommission::create([
                        'user_id' => $userId,
                        'milestone_schema_id' => $schemaId,
                        'product_id' => $productId ?? null,
                        'pid' => $schema->pid,
                        'type' => $type,
                        'schema_name' => $schemaName,
                        'schema_trigger' => $schemaTrigger,
                        'amount' => $amount,
                        'customer_signoff' => $approvalDate,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }

    public function subroutineEightForSolar($sale, $schema, $info, $redLine, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : NULL;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : NULL;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : NULL;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : NULL;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;
        $isSelfGen = false;
        if($forExternal == 0){
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && !empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && !empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && !empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && !empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        }else{
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
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
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
            if (!$commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = NULL;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    } else if ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                        $corePosition = '';
                        if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                            $corePosition = 2;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
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
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }
                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

                $commissionType = NULL;
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

                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                    } else if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        $amount = ((($netEpc - $redLine) * $x) * $kw * 1000 * $commissionPercentage / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? NULL;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if($amount > $commissionAmount){
                            $amount = $commissionAmount; // CAPS amount only if higher
                        }

                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }
                    

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->sum('amount');
                    $amount = $amount - $milestone - $projectedMilestone;

                    $withholding = [
                        'recon_amount' => NULL,
                        'recon_amount_type' => NULL,
                        'recon_limit' => 0
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } else if ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } else if ($withholding['recon_amount_type'] == 'percent') {
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
                        }else{
                            $amount = $amount - $reconAmount;
                        }
                    }

                    if ($amount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'product_id' => $productId ?? null,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'amount' => $amount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    if ($reconAmount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'product_id' => $productId ?? null,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'value_type' => 'reconciliation',
                            'amount' => $reconAmount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
    }

    public function subroutineEightForTurf($sale, $schema, $info, $companyProfile, $forExternal = 0)
    {
        $redLine = 0;
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : NULL;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : NULL;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : NULL;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : NULL;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;
        $isSelfGen = false;
        if($forExternal == 0){
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && !empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && !empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && !empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && !empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        }else{
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
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
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
            if (!$commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = NULL;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    } else if ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                        $corePosition = '';
                        if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                            $corePosition = 2;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
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
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }
                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

                $commissionType = NULL;
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

                if ($commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                    } else if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        $amount = (($grossAmountValue + (($netEpc - $redLine) * $x) * $kw * 1000) * $commissionPercentage / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }
                    $commissionLimitType = $commission->commission_limit_type ?? NULL;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if($amount > $commissionAmount){
                            $amount = $commissionAmount;
                        }
                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }   
                  

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->sum('amount');
                    $amount = $amount - $milestone - $projectedMilestone;

                    $withholding = [
                        'recon_amount' => NULL,
                        'recon_amount_type' => NULL,
                        'recon_limit' => 0
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } else if ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } else if ($withholding['recon_amount_type'] == 'percent') {
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
                        }else{
                            $amount = $amount - $reconAmount;
                        }
                    }

                    if ($amount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'amount' => $amount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    if ($reconAmount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'value_type' => 'reconciliation',
                            'amount' => $reconAmount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
    }

    public function subroutineEightForMortgage($sale, $schema, $info, $companyProfile, $redLine = 0, $forExternal = 0)
    {
        // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
            $redLine = 0;
        }
        
        $redLine = $redLine ? $redLine / 100 : 0; // Convert redLine from percent to decimal
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : NULL;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : NULL;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : NULL;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : NULL;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isSelfGen = false;
        if($forExternal == 0){
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        }else{
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
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
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
            if (!$commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionHistory = NULL;
                $commissionPercentage = 0;
                $commissionType = NULL;
                if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($isSelfGen) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    } else if ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                        $corePosition = '';
                        if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                            $corePosition = 2;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                            $corePosition = 3;
                        } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
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
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } else if ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }
                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        $commissionPercentage = @$commissionHistory->commission;
                        $commissionType = @$commissionHistory->commission_type;
                    }
                }

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

                $check = checkSalesReps($userId, $approvalDate, '');
                if ($check['status'] && $commissionPercentage && $commissionType) {
                    $amount = 0;
                   
                    if ($commissionType == 'per kw') {
                        $amount = (($kw * $commissionPercentage) * $x);
                    } else if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        // Percentage-based commission: Different formula based on domain
                        if (strtolower(config('app.domain_name')) == 'firstcoast') {
                            // Firstcoast: Keep original formula using netEpc, redLine, and kw
                            // Note: $redLine already divided by 100 on line 773
                            $amount = (((($netEpc - $redLine) * $x) * $kw ) * $commissionPercentage / 100);
                        } else {
                            // Other mortgage domains: Use gross_account_value based formula
                            $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                        }
                    }
                    
                    $commissionLimitType = $commission->commission_limit_type ?? NULL;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $kw * ($commission->commission_limit / 100);
                        if($amount > $commissionAmount){
                            $amount = $commissionAmount; // CAPS amount only if higher
                        }

                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->sum('amount');
                    $amount = $amount - $milestone - $projectedMilestone;

                    $withholding = [
                        'recon_amount' => NULL,
                        'recon_amount_type' => NULL,
                        'recon_limit' => 0
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } else if ($withholding['recon_amount_type'] == 'per kw') {
                                $reconAmount = ($withholding['recon_amount'] * $kw);
                            } else if ($withholding['recon_amount_type'] == 'percent') {
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
                        }else{
                            $amount = $amount - $reconAmount;
                        }
                    }

                    if ($amount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'amount' => $amount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    if ($reconAmount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'value_type' => 'reconciliation',
                            'amount' => $reconAmount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
    }

    public function subroutineEightForPest($sale, $schema, $info, $companyProfile, $forExternal = 0)
    {
        $pid = $sale->pid;
        $userId = $info['id'];
        $type = $schema->type;
        $productId = $sale->product_id;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : NULL;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : NULL;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $schemaId = $schema->milestone_schema_id;
        $schemaName = $schema->milestoneSchemaTrigger->name;
        $schemaTrigger = $schema->milestoneSchemaTrigger->on_trigger;

        $isHalf = false;
        if($forExternal == 0){
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && !empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && !empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && !empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && !empty($closerId)) {
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
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
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
            if (!$commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }
            if ($commission && $commission->commission_status == 1) {
                $commissionType = NULL;
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

                if ($commissionPercentage && $commissionType) {
                    $amount = 0;
                    if ($commissionType == 'per sale') {
                        $amount = $commissionPercentage * $x;
                    } else {
                        $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                    }

                    if ($isHalf && $amount) {
                        $amount = ($amount / 2);
                    }

                    $commissionLimitType = $commission->commission_limit_type ?? NULL;
                    if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                        // Apply percentage commission
                        $commissionAmount = $amount * ($commission->commission_limit / 100);
                        if($amount > $commissionAmount){
                            $amount = $commissionAmount; // CAPS amount only if higher
                        }

                    } else {
                        if ($commission->commission_limit && $amount > $commission->commission_limit) {
                            $amount = $commission->commission_limit;
                        }
                    }

                    $milestone = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0', 'is_displayed' => '1'])->sum('amount');
                    $projectedMilestone = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'is_last' => '0'])->sum('amount');
                    $amount = $amount - $milestone - $projectedMilestone;

                    $withholding = [
                        'recon_amount' => NULL,
                        'recon_amount_type' => NULL,
                        'recon_limit' => 0
                    ];
                    $reconAmount = 0;
                    if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                        $withholding = $this->userWithHoldingAmounts($userId, $productId, $approvalDate);
                        if ($withholding['recon_amount'] && $withholding['recon_amount_type']) {
                            if ($withholding['recon_amount_type'] == 'per sale') {
                                $reconAmount = $withholding['recon_amount'];
                            } else if ($withholding['recon_amount_type'] == 'percent') {
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
                        }else{
                            $amount = $amount - $reconAmount;
                        }
                    }

                    if ($amount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'product_id' => $productId ?? null,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'amount' => $amount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    if ($reconAmount) {
                        ProjectionUserCommission::create([
                            'user_id' => $userId,
                            'milestone_schema_id' => $schemaId,
                            'product_id' => $productId ?? null,
                            'pid' => $pid,
                            'type' => $type,
                            'schema_name' => $schemaName,
                            'schema_trigger' => $schemaTrigger,
                            'value_type' => 'reconciliation',
                            'amount' => $reconAmount,
                            'is_last' => 1,
                            'customer_signoff' => $approvalDate,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
    }

    public function userOverride($info, $pid, $kw, $commission, $forExternal = 0)
    {
        $saleUserId = $info['id'];
        $saleMaster = SalesMaster::where('pid', $pid)->first();
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

            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
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

                // Main query to get the IDs where rn = 1
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->select('id')
                    ->where('rn', 1);

                // Final query to get the user_transfer_history records with the selected IDs
                $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereNotNull('office_id')->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type')->whereIn('id', $userIdArr)->get();
                foreach ($userIdArr1 as $userData) {
                    $check = checkSalesReps($userData->id, $approvedDate, '');
                    if (!$check['status']) {
                        continue;
                    }
                    $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                    $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                    $organizationHistory = $userOrganizationData['organization'];
                    $actualProductId = $userOrganizationData['product']->id;
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
                    } else {
                        $settlementType = 'during_m2';
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
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $saleMaster->kw;
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                } else {
                                    if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $kw;
                                    } else if ($userData->office_overrides_type == 'percent') {
                                        $amount = $finalCommission * ($userData->office_overrides_amount / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                }

                                //Code to calculate override limit from percentage to normal value for mortgage only company type
                                $overridesLimitType = $positionOverride->override_limit_type;
                                if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    // Calculate the amount as percentage of original amount
                                    $amount = $amount * ($positionOverride->override_limit / 100);
                                }else{
                                    if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                        $amount = $positionOverride->override_limit;
                                    }
                                }
                              

                                $where = [
                                    'user_id' => $userData->id,
                                    'type' => 'Office',
                                    'pid' => $pid,
                                    'sale_user_id' => $saleUserId
                                ];

                                $officeData = [
                                    'customer_name' => $saleMaster->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $userData->office_overrides_amount,
                                    'overrides_type' => $userData->office_overrides_type,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                    'office_id' => $officeId
                                ];

                                $officeOverrides = ProjectionUserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office'])->first();
                                if ($officeOverrides) {
                                    if ($amount > $officeOverrides->total_override) {
                                        ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                        if ($userData->office_overrides_type) {
                                            // Check if projection Office override was previously deleted (archived) before creating
                                            // Use $saleUserId (not null) because Office projection overrides store sale_user_id
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, true)) {
                                               
                                                ProjectionUserOverrides::updateOrCreate($where, $officeData);
                                            }
                                        }
                                    }
                                } else {
                                    ProjectionUserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office', 'status' => 1])->delete();
                                    if ($userData->office_overrides_type) {
                                        // Check if projection Office override was previously deleted (archived) before creating
                                        // Use $saleUserId (not null) because Office projection overrides store sale_user_id
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, true)) {
                                            ProjectionUserOverrides::updateOrCreate($where, $officeData);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $userIdArr2 = AdditionalLocations::with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type')->where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->get();
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
                    } else {
                        $settlementType = 'during_m2';
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionOverride) {
                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                    }
                    if ($positionOverride && $positionOverride->status == 1) {
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'product_id' => $actualProductId, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
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
                                    if ($userData->office_overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $userData->office_overrides_amount * $x) / 100);
                                    } else if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $saleMaster->kw;
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                } else {
                                    if ($userData->office_overrides_type == 'per kw') {
                                        $amount = $userData->office_overrides_amount * $kw;
                                    } else if ($userData->office_overrides_type == 'percent') {
                                        $amount = $finalCommission * ($userData->office_overrides_amount / 100);
                                    } else {
                                        $amount = $userData->office_overrides_amount;
                                    }
                                }
                                //Code to calculate override limit from percentage to normal value for mortgage only company type
                                $overridesLimitType = $positionOverride->override_limit_type;
                                if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                    // Calculate the amount as percentage of original amount
                                    $amount = $amount * ($positionOverride->override_limit / 100);
                                }else{
                                    if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                        $amount = $positionOverride->override_limit;
                                    }
                                }
                               

                                $where = [
                                    'user_id' => $userData->id,
                                    'type' => 'Office',
                                    'pid' => $pid,
                                    'sale_user_id' => $saleUserId
                                ];

                                $officeData = [
                                    'customer_name' => $saleMaster->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $userData->office_overrides_amount,
                                    'overrides_type' => $userData->office_overrides_type,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                    'office_id' => $officeId
                                ];

                                $officeOverrides = ProjectionUserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office'])->first();
                                if ($officeOverrides) {
                                    if ($amount > $officeOverrides->total_override) {
                                        ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                        if ($userData->office_overrides_type) {
                                            // Check if projection Office override was previously deleted (archived) before creating
                                            // Use $saleUserId (not null) because Office projection overrides store sale_user_id
                                            if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, true)) {
                                             
                                                ProjectionUserOverrides::updateOrCreate($where, $officeData);
                                            }
                                        }
                                    }
                                } else {
                                    ProjectionUserOverrides::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'Office', 'status' => 1])->delete();
                                    if ($userData->office_overrides_type) {
                                        // Check if projection Office override was previously deleted (archived) before creating
                                        // Use $saleUserId (not null) because Office projection overrides store sale_user_id
                                        if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Office', $saleUserId, true)) {
                                            ProjectionUserOverrides::updateOrCreate($where, $officeData);
                                        }
                                    }
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
                    } else {
                        $settlementType = 'during_m2';
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (!$positionOverride) {
                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->whereNull('effective_date')->first();
                    }
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $actualProductId, 'type' => 'Direct'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($positionOverride && $positionOverride->status == 1 && (!$overrideStatus || $overrideStatus->status == 0)) {
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
                            } elseif ($companyMargin->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                                if ($value->direct_overrides_type === 'percent') {
                                    $amount = (($saleMaster->gross_account_value * $value->direct_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                            } else if ($companyMargin->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
                                if ($value->direct_overrides_type == 'percent') {
                                    $amount = (($saleMaster->gross_account_value * $value->direct_overrides_amount * $x) / 100);
                                } else if ($value->direct_overrides_type == 'per kw') {
                                    $amount = $value->direct_overrides_amount * $saleMaster->kw;
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                            } else {
                                if ($value->direct_overrides_type == 'per kw') {
                                    $amount = $value->direct_overrides_amount * $kw;
                                } else if ($value->direct_overrides_type == 'percent') {
                                    $amount = $finalCommission * ($value->direct_overrides_amount / 100);
                                } else {
                                    $amount = $value->direct_overrides_amount;
                                }
                            }
                            //Code to calculate override limit from percentage to normal value for mortgage only company type
                            $overridesLimitType = $positionOverride->override_limit_type;
                            if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                // Calculate the amount as percentage of original amount
                                $amount = $amount * ($positionOverride->override_limit / 100);
                            }else{
                                if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                    $amount = $positionOverride->override_limit;
                                }
                            }
                           

                            $where = [
                                'user_id' => $value->id,
                                'type' => 'Direct',
                                'pid' => $pid,
                                'sale_user_id' => $saleUserId
                            ];

                            $dataDirect = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $value->direct_overrides_amount,
                                'overrides_type' => $value->direct_overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll
                            ];

                            $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                            if ($overrideSystemSetting) {
                                $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                if ($userOverrides) {
                                    if ($amount > $userOverrides->total_override) {
                                        ProjectionUserOverrides::where(['id' => $userOverrides->id, 'status' => '1'])->delete();
                                        if ($value->direct_overrides_type) {
                                            // Check if projection Direct override was previously deleted (archived) before creating
                                            // Use $saleUserId (not null) because Direct projection overrides store sale_user_id
                                            if ($this->checkAndSkipIfArchived($value->id, $pid, 'Direct', $saleUserId, true)) {
                                               
                                                ProjectionUserOverrides::updateOrCreate($where, $dataDirect);
                                            }
                                        }
                                    }
                                } else {
                                    ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Direct', 'status' => 1])->delete();
                                    if ($value->direct_overrides_type) {
                                        // Check if projection Direct override was previously deleted (archived) before creating
                                        // Use $saleUserId (not null) because Direct projection overrides store sale_user_id
                                        if ($this->checkAndSkipIfArchived($value->id, $pid, 'Direct', $saleUserId, true)) {
                                            ProjectionUserOverrides::updateOrCreate($where, $dataDirect);
                                        }
                                    }
                                }
                            } else {
                                if ($value->direct_overrides_type) {
                                    // Check if projection Direct override was previously deleted (archived) before creating
                                    // Use $saleUserId (not null) because Direct projection overrides store sale_user_id
                                    if ($this->checkAndSkipIfArchived($value->id, $pid, 'Direct', $saleUserId, true)) {
                                        ProjectionUserOverrides::updateOrCreate($where, $dataDirect);
                                    }
                                }
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
                            $positionId = $val->sub_position_id;
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $actualProductId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                            } else {
                                $settlementType = 'during_m2';
                            }

                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionOverride) {
                                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->whereNull('effective_date')->first();
                            }
                            $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $actualProductId, 'type' => 'Indirect'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($positionOverride && $positionOverride->status == 1 && (!$overrideStatus || $overrideStatus->status == 0)) {
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
                                        if ($val->indirect_overrides_type == 'percent') {
                                            $amount = (($saleMaster->gross_account_value * $val->indirect_overrides_amount * $x) / 100);
                                        } else if ($val->indirect_overrides_type == 'per kw') {
                                            $amount = $val->indirect_overrides_amount * $saleMaster->kw;
                                        } else {
                                            $amount = $val->indirect_overrides_amount;
                                        }
                                    } else {
                                        if ($val->indirect_overrides_type == 'per kw') {
                                            $amount = $val->indirect_overrides_amount * $kw;
                                        } else if ($val->indirect_overrides_type == 'percent') {
                                            $amount = $finalCommission * ($val->indirect_overrides_amount / 100);
                                        } else {
                                            $amount = $val->indirect_overrides_amount;
                                        }
                                    }
                                    //Code to calculate override limit from percentage to normal value for mortgage only company type
                                    $overridesLimitType = $positionOverride->override_limit_type;
                                    if ($overridesLimitType == 'percent' && !is_null($positionOverride->override_limit) && $positionOverride->override_limit > 0) {
                                        // Calculate the amount as percentage of original amount
                                        $amount = $amount * ($positionOverride->override_limit / 100);
                                    }else{
                                        if ($positionOverride->override_limit && $amount > $positionOverride->override_limit) {
                                            $amount = $positionOverride->override_limit;
                                        }        
                                    }
                                   

                                    $where = [
                                        'user_id' => $val->id,
                                        'type' => 'Indirect',
                                        'pid' => $pid,
                                        'sale_user_id' => $saleUserId
                                    ];

                                    $dataIndirect = [
                                        'customer_name' => $saleMaster->customer_name,
                                        'kw' => $kw,
                                        'total_override' => $amount,
                                        'overrides_amount' => $val->indirect_overrides_amount,
                                        'overrides_type' => $val->indirect_overrides_type,
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => $stopPayroll
                                    ];

                                    $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                    if ($overrideSystemSetting) {
                                        $userOverrides = ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                        if ($userOverrides) {
                                            if ($amount > $userOverrides->total_override) {
                                                ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                                if ($val->indirect_overrides_type) {
                                                    // Check if projection Indirect override was previously deleted (archived) before creating
                                                    if ($this->checkAndSkipIfArchived($val->id, $pid, 'Indirect', $saleUserId, true)) {
                                                        ProjectionUserOverrides::updateOrCreate($where, $dataIndirect);
                                                    }
                                                }
                                            }
                                        } else {
                                            ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'type' => 'Indirect', 'status' => 1])->delete();
                                            if ($val->indirect_overrides_type) {
                                                // Check if projection Indirect override was previously deleted (archived) before creating
                                                if ($this->checkAndSkipIfArchived($val->id, $pid, 'Indirect', $saleUserId, true)) {
                                                  
                                                    ProjectionUserOverrides::updateOrCreate($where, $dataIndirect);
                                                }
                                            }
                                        }
                                    } else {
                                        if ($val->indirect_overrides_type) {
                                            // Check if projection Indirect override was previously deleted (archived) before creating
                                            if ($this->checkAndSkipIfArchived($val->id, $pid, 'Indirect', $saleUserId, true)) {
                                                ProjectionUserOverrides::updateOrCreate($where, $dataIndirect);
                                            }
                                        }
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
                        // Get manualProductId - use default product if productId is null (matching SubroutineOverrideTrait logic)
                        if ($productId) {
                            $product = Products::withTrashed()->where('id', $productId)->first();
                            $manualProductId = $product ? $product->id : null;
                        } else {
                            $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                            $manualProductId = $product ? $product->id : null;
                        }
                        
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $manualProductId, 'type' => 'Manual'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (!$overrideStatus || $overrideStatus->status == 0) {
                            $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;
                            $organizationHistory = UserOrganizationHistory::where(['user_id' => $value->id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            $positionId = $value->sub_position_id;
                            if ($organizationHistory) {
                                $positionId = $organizationHistory->sub_position_id;
                            }

                            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $manualProductId])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (!$positionReconciliation) {
                                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'product_id' => $manualProductId])->whereNull('effective_date')->first();
                            }
                            if ($companySetting && $positionReconciliation && $positionReconciliation->status == 1 && $positionReconciliation->override_settlement == 'Reconciliation') {
                                $settlementType = 'reconciliation';
                            } else {
                                $settlementType = 'during_m2';
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
                                    if ($value->overrides_type == 'percent') {
                                        $amount = (($saleMaster->gross_account_value * $value->overrides_amount * $x) / 100);
                                    } else if ($value->overrides_type == 'per kw') {
                                        $amount = $value->overrides_amount * $saleMaster->kw;
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

                                $where = [
                                    'user_id' => $value->id,
                                    'type' => 'Manual',
                                    'pid' => $pid,
                                    'sale_user_id' => $saleUserId
                                ];

                                $dataManual = [
                                    'customer_name' => $saleMaster->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $value->overrides_amount,
                                    'overrides_type' => $value->overrides_type,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll
                                ];

                                $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                if ($overrideSystemSetting) {
                                    $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                    if ($userOverrides) {
                                        if ($amount > $userOverrides->total_override) {
                                            ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                            if ($value->overrides_type) {
                                                // Check if projection override was previously deleted (archived) before creating
                                                // Use $saleUserId (not null) because Manual projection overrides store sale_user_id
                                                if ($this->checkAndSkipIfArchived($value->id, $pid, 'Manual', $saleUserId, true)) {
                                                  
                                                    ProjectionUserOverrides::updateOrCreate($where, $dataManual);
                                                }
                                            }
                                        }
                                    } else {
                                        ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Manual', 'status' => 1])->delete();
                                        if ($value->overrides_type) {
                                            // Check if projection override was previously deleted (archived) before creating
                                            // Use $saleUserId (not null) because Manual projection overrides store sale_user_id
                                            if ($this->checkAndSkipIfArchived($value->id, $pid, 'Manual', $saleUserId, true)) {
                                                ProjectionUserOverrides::updateOrCreate($where, $dataManual);
                                            }
                                        }
                                    }
                                } else {
                                    if ($value->overrides_type) {
                                        // Check if projection override was previously deleted (archived) before creating
                                        // Use $saleUserId (not null) because Manual projection overrides store sale_user_id
                                        if ($this->checkAndSkipIfArchived($value->id, $pid, 'Manual', $saleUserId, true)) {
                                            ProjectionUserOverrides::updateOrCreate($where, $dataManual);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // END MANUAL OVERRIDES CODE
        }
    }

    public function pestStackUserOverride($saleUserId, $pid)
    {
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

                $projectedCommission = ProjectionUserCommission::where(['pid' => $pid])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $finalCommission = $projectedCommission + $totalCommission;
                $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('total_override');

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)->pluck('id');
                foreach ($allUsers as $allUser) {
                    // Skip users whose Stack projection override was previously deleted (archived)
                    if (!$this->shouldCreateOverride($allUser, $pid, 'Stack', $saleUserId, true)) {
                        Log::info("Skipping eligible user - projection Stack override archived", ['user_id' => $allUser, 'pid' => $pid, 'sale_user_id' => $saleUserId]);
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
                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $marginPercentage = $companyMargin->company_margin;
                    $x = ((100 - $marginPercentage) / 100);
                }
                $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
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
                            } else {
                                $settlementType = 'during_m2';
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
                                            $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid,])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
                                        }
                                    }

                                    $amount = 0;
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                    }

                                    $where = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'pid' => $pid,
                                        'sale_user_id' => $saleUserId
                                    ];

                                    $update = [
                                        'customer_name' => $saleData->customer_name,
                                        'kw' => $grossAmountValue,
                                        'total_override' => $amount,
                                       'overrides_amount' =>  $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => 0,
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                    ];
                                    // Check if projection Stack override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, true)) {
                                      
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
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

    public function turfStackUserOverride($saleUserId, $pid, $kw)
    {
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

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
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
                        $closerData = $this->turfUserPerSale($eligibleUser['user_id'], $saleData->customer_state, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'per kw') {
                    foreach ($eligibleUsers as $eligibleUser) {
                        $closerData = $this->turfUserPerKw($eligibleUser['user_id'], $saleData->customer_state, $approvedDate, $commissionHistory, $eligibleUser);
                        if ($closerData) {
                            $stackData[$closerData['type']][] = $closerData;
                        }
                    }
                } else if ($commissionType == 'percent') {
                    $closerRedline = $this->userRedline($userData, $saleData->customer_state, $approvedDate, $actualProductId);
                    if (!$closerRedline['redline_missing']) {
                        foreach ($eligibleUsers as $eligibleUser) {
                            $closerData = $this->turfUserPercentage($eligibleUser['user_id'], $saleData->customer_state, $approvedDate, $closerRedline['redline'], $eligibleUser);
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
                $projectedCommission = ProjectionUserCommission::where(['pid' => $pid])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $finalCommission = $projectedCommission + $totalCommission;
                $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('total_override');
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
                            } else {
                                $settlementType = 'during_m2';
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
                                            $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
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
                                            $amount = (($grossAmountValue + (($netEpc - 0) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        }
                                    }

                                    $where = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'pid' => $pid,
                                        'sale_user_id' => $saleUserId
                                    ];

                                    $update = [
                                        'customer_name' => $saleData->customer_name,
                                        'kw' => $kw,
                                        'total_override' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => 0,
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                    ];
                                    // Check if projection Stack override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, true)) {
                                       
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
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

    public function mortgageStackUserOverride($saleUserId, $pid, $kw)
    {
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

                $subQuery = UserTransferHistory::select(
                    'id',
                    'user_id',
                    'transfer_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
                )->where('transfer_effective_date', '<=', $approvedDate);
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                $userIdArr1 = UserTransferHistory::whereIn('id', $results->pluck('id'))->where('office_id', $officeId)->pluck('user_id')->toArray();
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
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
                $projectedCommission = ProjectionUserCommission::where(['pid' => $pid])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $finalCommission = $projectedCommission + $totalCommission;
                $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('total_override');
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
                            } else {
                                $settlementType = 'during_m2';
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
                                            $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
                                        }
                                    }

                                    $amount = 0;
                                    if ($key == 'per sale') {
                                        $amount = (($value * $x) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    } else if ($key == 'percent') {
                                        //$amount = (($grossAmountValue + (($netEpc - 0) * $x) * $kw * 1000) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                        $amount = (((($netEpc - 0) * $x) * $kw) - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                    }

                                    $where = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'pid' => $pid,
                                        'sale_user_id' => $saleUserId
                                    ];

                                    //Redline 0 for MORTGAGE company type if domain is not firstcoast
                                    if(strtolower(config("app.domain_name")) != 'firstcoast'){
                                        $value = 0;
                                    }

                                    $update = [
                                        'customer_name' => $saleData->customer_name,
                                        'kw' => $kw,
                                        'total_override' => $amount,
                                       'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => 0,
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                    ];
                                    // Check if projection Stack override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, true)) {
                                      
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
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

    public function stackUserOverride($saleUserId, $pid, $kw)
    {
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
                $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->pluck('user_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $eligibleUsers = [];
                $allUsers = User::whereIn('id', $userIdArr)
                    ->when(($closerId == $setterId), function ($q) use ($saleUserId) {
                        $q->where('id', '!=', $saleUserId);
                    })->pluck('id');
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
                $projectedCommission = ProjectionUserCommission::where(['pid' => $pid])->whereIn('user_id', $saleUsers)->sum('amount');
                $totalCommission = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->sum('amount');
                $finalCommission = $projectedCommission + $totalCommission;
                $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->whereIn('sale_user_id', $saleUsers)->sum('total_override');
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
                            } else {
                                $settlementType = 'during_m2';
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
                                            $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
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

                                    $where = [
                                        'user_id' => $userData->id,
                                        'type' => 'Stack',
                                        'pid' => $pid,
                                        'sale_user_id' => $saleUserId
                                    ];

                                    $update = [
                                        'customer_name' => $saleData->customer_name,
                                        'kw' => $kw,
                                        'total_override' => $amount,
                                        'overrides_amount' => $userData->office_stack_overrides_amount,
                                        'overrides_type' => 'per sale',
                                        'overrides_settlement_type' => $settlementType,
                                        'status' => 1,
                                        'is_stop_payroll' => 0,
                                        'calculated_redline' => $value,
                                        'calculated_redline_type' => $valueType,
                                    ];
                                    // Check if projection Stack override was previously deleted (archived) before creating
                                    if ($this->checkAndSkipIfArchived($userData->id, $pid, 'Stack', $saleUserId, true)) {
                                       
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
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

    /**
     * Calculate projection amount for whiteknight domain with new logic
     * 
     * @param array $commission
     * @param array $info
     * @param int $forExternal
     * @param string $type
     * @param string $upfrontType
     * @param float $upfrontAmount
     * @param string $pid
     * @param int $userId
     * @param int $schemaId
     * @return float
     */
    private function calculateWhiteknightProjectionAmount($commission, $info, $forExternal, $type, $upfrontType, $upfrontAmount, $pid, $userId, $schemaId)
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
                    } else if ($upfrontType == 'percent') {
                        return ($totalCommission * $upfrontAmount) / 100;
                    }
                    return $upfrontAmount;

                case 'm2':
                case 'm3':
                    // M2/M3: Apply configured percentage to remaining commission after M1
                    $m1Amount = $this->getM1AmountForUser($userId, $pid, $totalCommission);
                    $remaining = max(0, $totalCommission - $m1Amount); // Prevent negative
                    
                    // Use the configured upfront percentage for this milestone
                    if ($upfrontType == 'per sale') {
                        return $upfrontAmount;
                    } else if ($upfrontType == 'percent') {
                        return round($remaining * ($upfrontAmount / 100), 2);
                    }
                    return $upfrontAmount;

                default:
                    // Fallback to original logic for other milestone types
                    if ($upfrontType == 'per sale') {
                        return $upfrontAmount;
                    } else if ($upfrontType == 'percent') {
                        return ($totalCommission * $upfrontAmount) / 100;
                    }
                    return $upfrontAmount;
            }

        } catch (\Exception $e) {
            Log::error('Whiteknight projection calculation error', [
                'error' => $e->getMessage(),
                'pid' => $pid,
                'user_id' => $userId,
                'schema_id' => $schemaId
            ]);
            
            // Fallback to original calculation
            if ($upfrontType == 'per sale') {
                return $upfrontAmount;
            } else if ($upfrontType == 'percent') {
                $value = $forExternal ? 
                    (@$commission[$info['id']] ? $commission[$info['id']] : 0) : 
                    (@$commission[$info['type']] ? $commission[$info['type']] : 0);
                return ($value * $upfrontAmount) / 100;
            }
            return $upfrontAmount;
        }
    }

    /**
     * Get M1 upfront amount for a specific user
     * 
     * @param int $userId
     * @param string $pid
     * @param float $totalCommission
     * @return float
     */
    private function getM1AmountForUser($userId, $pid, $totalCommission)
    {
        try {
            // Get sale data
            $sale = \App\Models\SalesMaster::where('pid', $pid)->first();
            if (!$sale) {
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

            // If not found in user_commission, check projection_user_commissions table
            $projectionCommission = \App\Models\ProjectionUserCommission::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('type', 'm1')
                ->first();

            if ($projectionCommission) {
                return round($projectionCommission->amount, 2);
            }

            // If no M1 amount found in commission tables
            Log::warning('No M1 amount found in commission tables for user', [
                'user_id' => $userId,
                'pid' => $pid
            ]);
            return 0;

        } catch (\Exception $e) {
            Log::error('Error getting M1 amount for user', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'pid' => $pid
            ]);
            return 0;
        }
    }

}
