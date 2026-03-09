<?php

namespace App\Core\Traits\ReconTraits;

use App\Core\Traits\ReconciliationPeriodTrait;
use App\Models\ClawbackSettlement;
use App\Models\CompanySetting;
use App\Models\PositionReconciliations;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconCommissionHistory;
use App\Models\SaleMasterProcess;
use App\Models\UserCommission;
use App\Models\UserOrganizationHistory;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Models\UserWithheldHistory;
use Illuminate\Support\Facades\Log;

trait ReconRoutineTraits
{
    use ReconciliationPeriodTrait, ReconRoutineClawbackTraits;

    public function callReconRoutine($response, $saleUserType = null, $saleUserId = null)
    {
        Log::channel('reconLog')->info("start recon for {$response->pid}");
        $companySetting = CompanySetting::where('type', 'reconciliation')->first();
        if ($companySetting->status) {
            Log::channel('reconLog')->info("callReconRoute for {$response->pid}");
            $pid = $response->pid;
            $closerId = $response->salesMasterProcess->closer1_id;
            $secondCloserId = $response->salesMasterProcess->closer2_id;
            $setterId = $response->salesMasterProcess->setter1_id;
            $secondSetterId = $response->salesMasterProcess->setter2_id;
            $m2Date = $response->m2_date;
            $kw = $response->kw;
            $net_epc = $response->net_epc;
            $approvedDate = $response->customer_signoff;
            $cancelDate = $response->date_cancelled;

            $saleUsers = [];
            if ($closerId) {
                $saleUsers[] = $closerId;
            }
            if ($secondCloserId) {
                $saleUsers[] = $secondCloserId;
            }
            if ($setterId) {
                $saleUsers[] = $setterId;
            }
            if ($secondSetterId) {
                $saleUsers[] = $secondSetterId;
            }

            if ($cancelDate) {
                Log::channel('reconLog')->info('callReconRoute - with cancel date');
                $this->callReconClawbackRoutine($response, $saleUserType, $saleUserId);
            } else {
                if ($m2Date && $approvedDate) {
                    $paidM2 = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
                    $saleData = SaleMasterProcess::where('pid', $pid)->first();
                    /* closer setting */
                    if ($closerId && $secondCloserId) {
                        /* working with first closer */
                        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)
                            ->where('effective_date', '<=', $approvedDate)
                            ->orderBy('effective_date', 'DESC')
                            ->first();
                        $subPositionId = $userOrganizationHistory->sub_position_id;
                        $closerAmount = PositionReconciliations::where('position_id', $subPositionId)->where('status', 1)->first();
                        /* closer-1 is self gen */
                        if ($closerId == $setterId && $userOrganizationHistory->self_gen_accounts == '1') {

                            $primaryCloserReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $primaryAmount = 0;
                            if ($primaryCloserReconAmount) {
                                $primaryWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $closerId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $primaryReconAmount = @$primaryWithHeldHistory->withheld_amount;
                                $primaryReconType = @$primaryWithHeldHistory->withheld_type;

                                if ($primaryReconAmount) {
                                    if ($primaryReconType == 'per sale') {
                                        $primaryAmount = $primaryReconAmount / 2;
                                    } elseif ($primaryReconType == 'percent') {
                                        $withheldPercent = $primaryReconAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $amount = ($closer1Commission * ($withheldPercent / 100));
                                        $primaryAmount = $amount / 2;
                                    } else {
                                        $primaryAmount = ($primaryReconAmount * $kw) / 2;
                                    }
                                }
                            }

                            $secondaryCloserReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $secondaryAmount = 0;
                            if ($secondaryCloserReconAmount) {
                                $secondaryWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $closerId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $secondaryReconAmount = @$secondaryWithHeldHistory->withheld_amount;
                                $secondaryReconType = @$secondaryWithHeldHistory->withheld_type;

                                if ($secondaryReconAmount) {
                                    if ($secondaryReconType == 'per sale') {
                                        $secondaryAmount = $secondaryReconAmount / 2;
                                    } else {
                                        $secondaryAmount = ($secondaryReconAmount * $kw) / 2;
                                    }
                                }
                            }
                            $amount = max($primaryAmount, $secondaryAmount);

                            // $withHeldData = UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $closerId)->first();
                            $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                            $data = [
                                'pid' => $pid,
                                'user_id' => $closerId,
                                'amount' => $amount,
                                'amount_type' => 'reconciliation',
                                'settlement_type' => 'reconciliation',
                                'kw' => $kw,
                                'net_epc' => $net_epc,
                                'date' => $m2Date,
                                'status' => '3',
                            ];
                            if ($withHeldData) {
                                if (! $paidM2) {
                                    UserCommission::where('id', $withHeldData->id)->update($data);
                                }

                            } else {
                                UserCommission::create($data);
                            }

                            /* update finalize recon commission history status */
                            $clawbackData = ClawbackSettlement::where([
                                'pid' => $pid,
                                'user_id' => $closerId,
                                'type' => 'recon-commission',
                            ])->where('status', '!=', 3)->get();
                            if ($clawbackData) {
                                ReconCommissionHistory::where([
                                    'pid' => $pid,
                                    'user_id' => $closerId,
                                    'status' => 'clawback',
                                ])->update([
                                    'status' => 'payroll',
                                ]);
                            }
                        }

                        /* closer1 and setter-1 is not same */
                        if ($closerId != $setterId) {
                            if ($closerAmount) {
                                if ($userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory->position_id == 3) {
                                    $closerReconHistory = UserWithheldHistory::where([
                                        'user_id' => $closerId,
                                        'self_gen_user' => 1,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $closerReconWithHeldAmount = $closerReconHistory->withheld_amount;
                                    $closerReconWithHeldType = $closerReconHistory->withheld_type;
                                } else {
                                    $closerReconHistory = UserWithheldHistory::where([
                                        'user_id' => $closerId,
                                        'self_gen_user' => 0,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    if ($closerReconHistory) {
                                        $closerReconWithHeldAmount = $closerReconHistory->withheld_amount;
                                        $closerReconWithHeldType = $closerReconHistory->withheld_type;
                                    } else {
                                        $closerReconWithHeldAmount = $closerAmount->commission_withheld;
                                        $closerReconWithHeldType = $closerAmount->commission_type;
                                    }
                                }
                            }
                            /* working with second closer */
                            $secondaryCloserUserOrganizationHistory = UserOrganizationHistory::where('user_id', $secondCloserId)
                                ->where('effective_date', '<=', $approvedDate)
                                ->orderBy('effective_date', 'DESC')
                                ->first();
                            $secondaryCloserSubPositionId = $secondaryCloserUserOrganizationHistory->sub_position_id;
                            $secondaryCloserAmount = PositionReconciliations::where('position_id', $secondaryCloserSubPositionId)->where('status', 1)->first();

                            if ($secondaryCloserAmount) {
                                if ($secondaryCloserUserOrganizationHistory->self_gen_accounts == 1 && $secondaryCloserUserOrganizationHistory->position_id == 3) {
                                    $secondaryCloserReconHistory = UserWithheldHistory::where([
                                        'user_id' => $secondCloserId,
                                        'self_gen_user' => 1,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $secondaryCloserReconWithHeldAmount = $secondaryCloserReconHistory->withheld_amount;
                                    $secondaryCloserReconWithHeldType = $secondaryCloserReconHistory->withheld_type;
                                } else {
                                    $secondaryCloserReconHistory = UserWithheldHistory::where([
                                        'user_id' => $closerId,
                                        'self_gen_user' => 0,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();

                                    if ($secondaryCloserReconHistory) {
                                        $secondaryCloserReconWithHeldAmount = $secondaryCloserReconHistory->withheld_amount;
                                        $secondaryCloserReconWithHeldType = $secondaryCloserReconHistory->withheld_type;
                                    } else {
                                        $secondaryCloserReconWithHeldAmount = $secondaryCloserAmount->commission_withheld;
                                        $secondaryCloserReconWithHeldType = $secondaryCloserAmount->commission_type;
                                    }
                                }
                            }

                            /* first closer amount calculation */
                            if (! empty($closerAmount) && ! empty($closerReconWithHeldAmount) && ! empty($closerReconWithHeldType)) {
                                if ($secondaryCloserAmount) {
                                    if ($closerReconWithHeldType == 'per sale') {
                                        $amount = ($closerReconWithHeldAmount / 2);
                                    } elseif ($closerReconWithHeldType == 'percent') {
                                        $withheldPercent = $closerReconWithHeldAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $amount1 = ($closer1Commission * ($withheldPercent / 100));
                                        $amount = $amount1 / 2;
                                    } else {
                                        $amount = (($closerReconWithHeldAmount * $kw) / 2);
                                    }
                                } else {
                                    if ($closerReconWithHeldType == 'per sale') {
                                        $amount = $closerReconWithHeldAmount;
                                    } elseif ($closerReconWithHeldType == 'percent') {
                                        $withheldPercent = $closerReconWithHeldAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $amount = ($closer1Commission * ($withheldPercent / 100));
                                    } else {
                                        $amount = ($closerReconWithHeldAmount * $kw);
                                    }
                                }

                                if (! empty($closerAmount->maximum_withheld) && $amount > $closerAmount->maximum_withheld) {
                                    $amount = $closerAmount->maximum_withheld;
                                }

                                // $withHeldData = UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $closerId)->first();
                                $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                $data = [
                                    'pid' => $pid,
                                    'user_id' => $closerId,
                                    'amount' => $amount,
                                    'amount_type' => 'reconciliation',
                                    'settlement_type' => 'reconciliation',
                                    'kw' => $kw,
                                    'net_epc' => $net_epc,
                                    'date' => $m2Date,
                                    'status' => '3',
                                ];
                                if ($withHeldData) {
                                    if (! $paidM2) {
                                        UserCommission::where('id', $withHeldData->id)->update($data);
                                    }

                                } else {
                                    UserCommission::create($data);
                                }
                                /* update finalize recon commission history status */
                                $clawbackData = ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $closerId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->get();
                                if ($clawbackData) {
                                    ReconCommissionHistory::where([
                                        'pid' => $pid,
                                        'user_id' => $closerId,
                                        'status' => 'clawback',
                                    ])->update([
                                        'status' => 'payroll',
                                    ]);
                                    ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $closerId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->delete();
                                }
                            }

                        }

                        /* closer-2 is self */
                        $secondUserOrganizationHistory = UserOrganizationHistory::where('user_id', $secondCloserId)
                            ->where('effective_date', '<=', $approvedDate)
                            ->orderBy('effective_date', 'DESC')
                            ->first();
                        $subPositionId = $secondUserOrganizationHistory->sub_position_id;
                        $secondCloserAmount = PositionReconciliations::where('position_id', $subPositionId)->where('status', 1)->first();

                        if ($secondCloserId == $secondSetterId && $secondUserOrganizationHistory->self_gen_accounts == '1') {
                            $primaryCloser2ReconAmount = PositionReconciliations::where('position_id', $secondUserOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $primaryCloser2Amount = 0;
                            if ($primaryCloser2ReconAmount) {
                                $closer2WithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $secondCloserId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $primaryCloser2ReconAmount = $closer2WithHeldHistory->withheld_amount;
                                $primaryCloser2ReconType = $closer2WithHeldHistory->withheld_type;

                                if ($primaryCloser2ReconAmount) {
                                    if ($primaryCloser2ReconType == 'per sale') {
                                        $primaryCloser2Amount = $primaryCloser2ReconAmount / 2;
                                    } elseif ($primaryCloser2ReconType == 'percent') {
                                        $withheldPercent = $primaryCloser2ReconAmount;
                                        $closer2Commission = isset($saleData->closer2_commission) ? $saleData->closer2_commission : 0;
                                        $amount = ($closer2Commission * ($withheldPercent / 100));
                                        $primaryCloser2Amount = $amount / 2;
                                    } else {
                                        $primaryCloser2Amount = ($primaryCloser2ReconAmount * $kw) / 2;
                                    }
                                }
                            }

                            $secondaryCloserReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $secondaryCloser2Amount = 0;
                            if ($secondaryCloserReconAmount) {
                                $secondaryWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $secondCloserId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $secondaryReconAmount = @$secondaryWithHeldHistory->withheld_amount;
                                $secondaryReconType = @$secondaryWithHeldHistory->withheld_type;

                                if ($secondaryReconAmount) {
                                    if ($secondaryReconType == 'per sale') {
                                        $secondaryCloser2Amount = $secondaryReconAmount / 2;
                                    } elseif ($secondaryReconType == 'percent') {
                                        $withheldPercent = $secondaryReconAmount;
                                        $closer2Commission = isset($saleData->closer2_commission) ? $saleData->closer2_commission : 0;
                                        $amount = ($closer2Commission * ($withheldPercent / 100));
                                        $secondaryCloser2Amount = $amount / 2;
                                    } else {
                                        $secondaryCloser2Amount = ($secondaryReconAmount * $kw) / 2;
                                    }
                                }
                            }

                            $amount = max($primaryCloser2Amount, $secondaryCloser2Amount);
                            // $withHeldData = UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $secondCloserId)->first();
                            $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $secondCloserId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                            $data = [
                                'pid' => $pid,
                                'user_id' => $secondCloserId,
                                'amount' => $amount,
                                'amount_type' => 'reconciliation',
                                'settlement_type' => 'reconciliation',
                                'kw' => $kw,
                                'net_epc' => $net_epc,
                                'date' => $m2Date,
                                'status' => '3',
                            ];
                            if ($withHeldData) {
                                if (! $paidM2) {
                                    UserCommission::where('id', $withHeldData->id)->update($data);
                                }

                            } else {
                                UserCommission::create($data);
                            }
                            /* update finalize recon commission history status */
                            $clawbackData = ClawbackSettlement::where([
                                'pid' => $pid,
                                'user_id' => $secondCloserId,
                                'type' => 'recon-commission',
                            ])->where('status', '!=', 3)->get();
                            if ($clawbackData) {
                                ReconCommissionHistory::where([
                                    'pid' => $pid,
                                    'user_id' => $secondCloserId,
                                    'status' => 'clawback',
                                ])->update([
                                    'status' => 'payroll',
                                ]);
                                ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $secondCloserId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->delete();
                            }
                        }

                        /* closer2 and setter2 is not same */
                        if ($secondCloserId != $secondSetterId) {
                            if ($secondCloserAmount) {
                                if ($secondUserOrganizationHistory->self_gen_accounts == 1 && $secondUserOrganizationHistory->position_id == 3) {
                                    $closerReconHistory = UserWithheldHistory::where([
                                        'user_id' => $secondCloserId,
                                        'self_gen_user' => 1,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $closerReconWithHeldAmount = $closerReconHistory->withheld_amount;
                                    $closerReconWithHeldType = $closerReconHistory->withheld_type;
                                } else {
                                    $closerReconHistory = UserWithheldHistory::where([
                                        'user_id' => $secondCloserId,
                                        'self_gen_user' => 0,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $closerReconWithHeldAmount = $closerReconHistory?->withheld_amount;
                                    $closerReconWithHeldType = $closerReconHistory?->withheld_type;
                                }
                            }
                            /* working with second closer */
                            $secondaryCloserUserOrganizationHistory = UserOrganizationHistory::where('user_id', $secondCloserId)
                                ->where('effective_date', '<=', $approvedDate)
                                ->orderBy('effective_date', 'DESC')
                                ->first();
                            $secondaryCloserSubPositionId = $secondaryCloserUserOrganizationHistory->sub_position_id;
                            $secondaryCloserAmount = PositionReconciliations::where('position_id', $secondaryCloserSubPositionId)->where('status', 1)->first();

                            if ($secondaryCloserAmount) {
                                if ($secondaryCloserUserOrganizationHistory->self_gen_accounts == 1 && $secondaryCloserUserOrganizationHistory->position_id == 3) {
                                    $secondaryCloserReconHistory = UserWithheldHistory::where([
                                        'user_id' => $secondCloserId,
                                        'self_gen_user' => 1,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $secondaryCloserReconWithHeldAmount = $secondaryCloserAmount?->withheld_amount;
                                    $secondaryCloserReconWithHeldType = $secondaryCloserAmount?->withheld_type;
                                } else {
                                    $secondaryCloserReconHistory = UserWithheldHistory::where([
                                        'user_id' => $secondCloserId,
                                        'self_gen_user' => 0,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $secondaryCloserReconWithHeldAmount = $secondaryCloserReconHistory?->withheld_amount;
                                    $secondaryCloserReconWithHeldType = $secondaryCloserReconHistory?->withheld_type;
                                }
                            }

                            /* second closer amount calculation */
                            if (! empty($secondaryCloserAmount) && ! empty($secondaryCloserReconWithHeldAmount) && ! empty($secondaryCloserReconWithHeldType)) {
                                if ($secondaryCloserAmount) {
                                    if ($secondaryCloserReconWithHeldType == 'per sale') {
                                        $amount2 = ($secondaryCloserReconWithHeldAmount / 2);
                                    } elseif ($secondaryCloserReconWithHeldType == 'percent') {
                                        $withheldPercent = $secondaryCloserReconWithHeldAmount;
                                        $closer2Commission = isset($saleData->closer2_commission) ? $saleData->closer2_commission : 0;
                                        $amount = ($closer2Commission * ($withheldPercent / 100));
                                        $amount2 = $amount / 2;
                                    } else {
                                        $amount2 = (($secondaryCloserReconWithHeldAmount * $kw) / 2);
                                    }
                                } else {
                                    if ($secondaryCloserReconWithHeldType == 'per sale') {
                                        $amount2 = $secondaryCloserReconWithHeldAmount;
                                    } elseif ($secondaryCloserReconWithHeldType == 'percent') {
                                        $withheldPercent = $secondaryCloserReconWithHeldAmount;
                                        $closer2Commission = isset($saleData->closer2_commission) ? $saleData->closer2_commission : 0;
                                        $amount2 = ($closer2Commission * ($withheldPercent / 100));
                                    } else {
                                        $amount2 = ($secondaryCloserReconWithHeldAmount * $kw);
                                    }
                                }

                                if (! empty($secondaryCloserAmount->maximum_withheld) && $amount2 > $secondaryCloserAmount->maximum_withheld) {
                                    $amount2 = $secondaryCloserAmount->maximum_withheld;
                                }
                                // $withHeldData = UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $secondCloserId)->first();
                                $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $secondCloserId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                $data = [
                                    'pid' => $pid,
                                    'user_id' => $secondCloserId,
                                    'amount' => $amount2,
                                    'amount_type' => 'reconciliation',
                                    'settlement_type' => 'reconciliation',
                                    'kw' => $kw,
                                    'net_epc' => $net_epc,
                                    'date' => $m2Date,
                                    'status' => '3',
                                ];
                                if ($withHeldData) {
                                    if (! $paidM2) {
                                        UserCommission::where('id', $withHeldData->id)->update($data);
                                    }

                                } else {
                                    UserCommission::create($data);
                                }
                                /* update finalize recon commission history status */
                                $clawbackData = ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $secondCloserId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->get();
                                if ($clawbackData) {
                                    ReconCommissionHistory::where([
                                        'pid' => $pid,
                                        'user_id' => $secondCloserId,
                                        'status' => 'clawback',
                                    ])->update([
                                        'status' => 'payroll',
                                    ]);
                                    ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $secondCloserId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->delete();
                                }
                            }
                        }
                    } elseif ($closerId) {
                        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();

                        if ($closerId == $setterId && $userOrganizationHistory->self_gen_accounts == '1') {
                            $primaryCloserReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $primaryAmount = 0;
                            if ($primaryCloserReconAmount) {
                                $primaryWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $closerId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $primaryReconAmount = @$primaryWithHeldHistory?->withheld_amount;
                                $primaryReconType = @$primaryWithHeldHistory?->withheld_type;

                                if ($primaryReconAmount) {
                                    if ($primaryReconType == 'per sale') {
                                        $primaryAmount = $primaryReconAmount;
                                    } elseif ($primaryReconType == 'percent') {
                                        $withheldPercent = $primaryReconAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $primaryAmount = ($closer1Commission * ($withheldPercent / 100));
                                    } else {
                                        $primaryAmount = ($primaryReconAmount * $kw);
                                    }
                                }
                            }

                            $secondaryCloserReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            $secondaryAmount = 0;
                            if ($secondaryCloserReconAmount) {
                                $secondaryWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $closerId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();

                                $secondaryReconAmount = @$secondaryWithHeldHistory?->withheld_amount;
                                $secondaryReconType = @$secondaryWithHeldHistory?->withheld_type;

                                if ($secondaryReconAmount) {
                                    if ($secondaryReconType == 'per sale') {
                                        $secondaryAmount = $secondaryReconAmount;
                                    } elseif ($secondaryReconType == 'percent') {
                                        $withheldPercent = $secondaryReconAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $secondaryAmount = ($closer1Commission * ($withheldPercent / 100));
                                    } else {
                                        $secondaryAmount = ($secondaryReconAmount * $kw);
                                    }
                                }
                            }

                            $amount = max($primaryAmount, $secondaryAmount);
                            // $withHeldData = UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $closerId)->first();
                            $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                            $data = [
                                'pid' => $pid,
                                'user_id' => $closerId,
                                'amount' => $amount,
                                'amount_type' => 'reconciliation',
                                'settlement_type' => 'reconciliation',
                                'kw' => $kw,
                                'net_epc' => $net_epc,
                                'date' => $m2Date,
                                'status' => '3',
                            ];
                            if ($withHeldData) {
                                if (! $paidM2) {
                                    // UserReconciliationWithholding::where("pid", $pid)->where("closer_id", $closerId)->update($data);
                                    UserCommission::where('id', $withHeldData->id)->update($data);
                                }

                            } else {
                                UserCommission::create($data);
                            }

                            /* update finalize recon commission history status */
                            $clawbackData = ClawbackSettlement::where([
                                'pid' => $pid,
                                'user_id' => $closerId,
                                'type' => 'recon-commission',
                            ])->where('status', '!=', 3)->get();
                            if ($clawbackData) {
                                ReconCommissionHistory::where([
                                    'pid' => $pid,
                                    'user_id' => $closerId,
                                    'status' => 'clawback',
                                ])->update([
                                    'status' => 'payroll',
                                ]);
                                ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $closerId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->delete();
                            }
                        } else {
                            $closerReconAmount = PositionReconciliations::where('position_id', $userOrganizationHistory->sub_position_id)->where('status', 1)->first();
                            if ($closerReconAmount) {
                                if ($userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory->position_id == 3) {
                                    $reconHistory = UserWithheldHistory::where([
                                        'user_id' => $closerId,
                                        'self_gen_user' => 1,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $reconWithHeldAmount = $reconHistory?->withheld_amount;
                                    $reconWithHeldType = $reconHistory?->withheld_type;
                                } else {
                                    $reconHistory = UserWithheldHistory::where([
                                        'user_id' => $closerId,
                                        'self_gen_user' => 0,
                                    ])->where('withheld_effective_date', '<=', $approvedDate)
                                        ->orderBy('withheld_effective_date', 'DESC')
                                        ->first();
                                    $reconWithHeldAmount = $reconHistory?->withheld_amount;
                                    $reconWithHeldType = $reconHistory?->withheld_type;
                                }

                                if ($reconWithHeldAmount && $reconWithHeldType) {
                                    if ($reconWithHeldType == 'per sale') {
                                        $amount = $reconWithHeldAmount;
                                    } elseif ($reconWithHeldType == 'percent') {
                                        $withheldPercent = $reconWithHeldAmount;
                                        $closer1Commission = isset($saleData->closer1_commission) ? $saleData->closer1_commission : 0;
                                        $amount = ($closer1Commission * ($withheldPercent / 100));
                                    } else {
                                        $amount = ($reconWithHeldAmount * $kw);
                                    }

                                    if ($closerReconAmount->maximum_withheld && $amount > $closerReconAmount->maximum_withheld) {
                                        $amount = $closerReconAmount->maximum_withheld;
                                    }

                                    $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                    $data = [
                                        'pid' => $pid,
                                        'user_id' => $closerId,
                                        'amount' => $amount,
                                        'amount_type' => 'reconciliation',
                                        'settlement_type' => 'reconciliation',
                                        'kw' => $kw,
                                        'net_epc' => $net_epc,
                                        'date' => $m2Date,
                                        'status' => '3',
                                    ];
                                    if ($withHeldData) {
                                        if (! $paidM2) {
                                            UserCommission::where('id', $withHeldData->id)->update($data);
                                        }

                                    } else {
                                        UserCommission::create($data);
                                    }
                                    /* update finalize recon commission history status */
                                    $clawbackData = ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $closerId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->get();
                                    if ($clawbackData) {
                                        ReconCommissionHistory::where([
                                            'pid' => $pid,
                                            'user_id' => $closerId,
                                            'status' => 'clawback',
                                        ])->update([
                                            'status' => 'payroll',
                                        ]);
                                        ClawbackSettlement::where([
                                            'pid' => $pid,
                                            'user_id' => $closerId,
                                            'type' => 'recon-commission',
                                        ])->where('status', '!=', 3)->delete();
                                    }
                                }
                            }
                        }
                    }

                    /* setter setting */
                    if ($setterId && $secondSetterId) {
                        /* working with setter-1 */
                        $setter1OrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)
                            ->where('effective_date', '<=', $approvedDate)
                            ->orderBy('effective_date', 'DESC')
                            ->first();
                        $subPositionId = $setter1OrganizationHistory->sub_position_id;
                        $primarySetterReconAmount = PositionReconciliations::where('position_id', $subPositionId)->where('status', 1)->first();
                        $primarySetterAmount = 0;
                        if ($primarySetterReconAmount) {
                            if ($setter1OrganizationHistory->self_gen_accounts == 1 && $setter1OrganizationHistory->position_id == 2) {
                                $primarySetterWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $setterId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $primaryReconAmount = $primarySetterWithHeldHistory?->withheld_amount;
                                $primaryReconType = $primarySetterWithHeldHistory?->withheld_type;
                            } else {
                                $primarySetterWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $setterId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $primaryReconAmount = $primarySetterWithHeldHistory?->withheld_amount;
                                $primaryReconType = $primarySetterWithHeldHistory?->withheld_type;
                            }

                            if ($primaryReconAmount) {
                                if ($primaryReconType == 'per sale') {
                                    $primarySetterAmount = $primaryReconAmount;
                                } elseif ($primaryReconType == 'percent') {
                                    $withheldPercent = $primaryReconAmount;
                                    $setter1Commission = isset($saleData->setter1_commission) ? $saleData->setter1_commission : 0;
                                    $primarySetterAmount = ($setter1Commission * ($withheldPercent / 100));
                                } else {
                                    $primarySetterAmount = ($primaryReconAmount * $kw);
                                }
                            }
                        }

                        /* working with setter-2 */
                        $setter2OrganizationHistory = UserOrganizationHistory::where('user_id', $secondSetterId)
                            ->where('effective_date', '<=', $approvedDate)
                            ->orderBy('effective_date', 'DESC')
                            ->first();
                        $subPositionId = $setter2OrganizationHistory->sub_position_id;
                        $secondarySetterReconAmount = PositionReconciliations::where('position_id', $subPositionId)->where('status', 1)->first();
                        $secondarySetterAmount = 0;
                        if ($secondarySetterReconAmount) {
                            if ($setter2OrganizationHistory->self_gen_accounts == 1 && $setter2OrganizationHistory->position_id == 2) {
                                $secondarySetterWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $secondSetterId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $secondaryReconAmount = $secondarySetterWithHeldHistory?->withheld_amount;
                                $secondaryReconType = $secondarySetterWithHeldHistory?->withheld_type;
                            } else {
                                $secondarySetterWithHeldHistory = UserWithheldHistory::where([
                                    'user_id' => $secondSetterId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $secondaryReconAmount = $secondarySetterWithHeldHistory?->withheld_amount;
                                $secondaryReconType = $secondarySetterWithHeldHistory?->withheld_type;
                            }

                            if ($secondaryReconAmount) {
                                if ($secondaryReconType == 'per sale') {
                                    $secondarySetterAmount = $secondaryReconAmount;
                                } elseif ($secondaryReconType == 'percent') {
                                    $withheldPercent = $secondaryReconAmount;
                                    $setter2Commission = isset($saleData->setter2_commission) ? $saleData->setter2_commission : 0;
                                    $secondarySetterAmount = ($setter2Commission * ($withheldPercent / 100));
                                } else {
                                    $secondarySetterAmount = ($secondaryReconAmount * $kw);
                                }
                            }
                            /* $secondarySetterWithHeldHistory = UserWithheldHistory::where([
                                'user_id' => $secondSetterId,
                                'self_gen_user' => 0,
                            ])->where('withheld_effective_date', '<=', $approvedDate)
                                ->orderBy('withheld_effective_date', 'DESC')
                                ->first();

                            $secondaryReconAmount = $secondarySetterWithHeldHistory?->withheld_amount;
                            $secondaryReconType = $secondarySetterWithHeldHistory?->withheld_type;

                            if ($secondaryReconAmount) {
                                if ($secondaryReconType == 'per sale') {
                                    $secondarySetterAmount = $secondaryReconAmount;
                                } else {
                                    $secondarySetterAmount = ($secondaryReconAmount * $kw);
                                }
                            } */
                        }

                        /* first setter amount calculation */
                        if (! empty($primarySetterReconAmount) && ! empty($primarySetterAmount) && ! empty($primaryReconType)) {
                            if ($secondaryReconAmount) {
                                if ($primaryReconType == 'per sale') {
                                    $amount = ($primarySetterAmount / 2);
                                } elseif ($primaryReconType == 'percent') {
                                    $withheldPercent = $primarySetterAmount;
                                    $setter1Commission = isset($saleData->setter1_commission) ? $saleData->setter1_commission : 0;
                                    $primarySetterAmount = ($setter1Commission * ($withheldPercent / 100));
                                    $amount = ($primarySetterAmount / 2);
                                } else {
                                    $amount = (($primarySetterAmount * $kw) / 2);
                                }
                            } else {
                                if ($closerReconWithHeldType == 'per sale') {
                                    $amount = $primarySetterAmount;
                                } elseif ($closerReconWithHeldType == 'percent') {
                                    $withheldPercent = $primarySetterAmount;
                                    $setter1Commission = isset($saleData->setter1_commission) ? $saleData->setter1_commission : 0;
                                    $amount = ($setter1Commission * ($withheldPercent / 100));
                                } else {
                                    $amount = ($primarySetterAmount * $kw);
                                }
                            }

                            if (! empty($primarySetterReconAmount->maximum_withheld) && $amount > $primarySetterReconAmount->maximum_withheld) {
                                $amount = $primarySetterReconAmount->maximum_withheld;
                            }

                            if ($closerId != $setterId) {
                                $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                $data = [
                                    'pid' => $pid,
                                    'user_id' => $setterId,
                                    'amount' => $amount,
                                    'amount_type' => 'reconciliation',
                                    'settlement_type' => 'reconciliation',
                                    'kw' => $kw,
                                    'net_epc' => $net_epc,
                                    'date' => $m2Date,
                                    'status' => '3',
                                ];
                                if ($withHeldData) {
                                    if (! $paidM2) {
                                        UserCommission::where('id', $withHeldData->id)->update($data);
                                    }

                                } else {
                                    UserCommission::create($data);
                                }
                                /* update finalize recon commission history status */
                                $clawbackData = ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $setterId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->get();
                                if ($clawbackData) {
                                    ReconCommissionHistory::where([
                                        'pid' => $pid,
                                        'user_id' => $setterId,
                                        'status' => 'clawback',
                                    ])->update([
                                        'status' => 'payroll',
                                    ]);
                                    ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $setterId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->delete();
                                }
                            }
                        }

                        /* second closer amount calculation */
                        if (! empty($secondarySetterReconAmount) && ! empty($secondaryReconAmount) && ! empty($secondaryReconType)) {
                            if ($primarySetterReconAmount) {
                                if ($secondaryReconType == 'per sale') {
                                    $amount2 = ($secondarySetterAmount / 2);
                                } elseif ($secondaryReconType == 'percent') {
                                    $withheldPercent = $secondarySetterAmount;
                                    $setter2Commission = isset($saleData->setter2_commission) ? $saleData->setter2_commission : 0;
                                    $setter2Amount = ($setter2Commission * ($withheldPercent / 100));
                                    $amount2 = ($setter2Amount / 2);
                                } else {
                                    $amount2 = (($secondarySetterAmount * $kw) / 2);
                                }
                            } else {
                                if ($secondaryReconType == 'per sale') {
                                    $amount2 = $secondarySetterAmount;
                                } elseif ($secondaryReconType == 'percent') {
                                    $withheldPercent = $secondarySetterAmount;
                                    $setter2Commission = isset($saleData->setter2_commission) ? $saleData->setter2_commission : 0;
                                    $amount2 = ($setter2Commission * ($withheldPercent / 100));
                                } else {
                                    $amount2 = ($secondarySetterAmount * $kw);
                                }
                            }

                            if (! empty($secondarySetterReconAmount->maximum_withheld) && $amount2 > $secondarySetterReconAmount->maximum_withheld) {
                                $amount2 = $secondarySetterReconAmount->maximum_withheld;
                            }

                            if ($secondCloserId != $secondSetterId) {
                                $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $secondSetterId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                $data = [
                                    'pid' => $pid,
                                    'user_id' => $secondSetterId,
                                    'amount' => $amount2,
                                    'amount_type' => 'reconciliation',
                                    'settlement_type' => 'reconciliation',
                                    'kw' => $kw,
                                    'net_epc' => $net_epc,
                                    'date' => $m2Date,
                                    'status' => '3',
                                ];
                                if ($withHeldData) {
                                    if (! $paidM2) {
                                        UserCommission::where('id', $withHeldData->id)->update($data);
                                    }

                                } else {
                                    UserCommission::create($data);
                                }
                                /* update finalize recon commission history status */
                                $clawbackData = ClawbackSettlement::where([
                                    'pid' => $pid,
                                    'user_id' => $secondSetterId,
                                    'type' => 'recon-commission',
                                ])->where('status', '!=', 3)->get();
                                if ($clawbackData) {
                                    ReconCommissionHistory::where([
                                        'pid' => $pid,
                                        'user_id' => $secondSetterId,
                                        'status' => 'clawback',
                                    ])->update([
                                        'status' => 'payroll',
                                    ]);
                                    ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $secondSetterId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->delete();
                                }
                            } /* else{
                        UserReconciliationWithholding::where("pid", $pid)->where("setter_id", $secondSetterId)->delete();
                        } */
                        }

                    } elseif ($setterId) {
                        /* working with first closer */
                        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)
                            ->where('effective_date', '<=', $approvedDate)
                            ->orderBy('effective_date', 'DESC')
                            ->first();
                        $subPositionId = $userOrganizationHistory->sub_position_id;
                        $setterAmount = PositionReconciliations::where('position_id', $subPositionId)->where('status', 1)->first();
                        if ($setterAmount) {
                            if ($userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory->position_id == 2) {
                                $setterReconHistory = UserWithheldHistory::where([
                                    'user_id' => $setterId,
                                    'self_gen_user' => 1,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $setterReconWithHeldAmount = floatval($setterReconHistory?->withheld_amount);
                                $setterReconWithHeldType = $setterReconHistory?->withheld_type;
                            } else {
                                $setterReconHistory = UserWithheldHistory::where([
                                    'user_id' => $setterId,
                                    'self_gen_user' => 0,
                                ])->where('withheld_effective_date', '<=', $approvedDate)
                                    ->orderBy('withheld_effective_date', 'DESC')
                                    ->first();
                                $setterReconWithHeldAmount = floatval($setterReconHistory?->withheld_amount);
                                $setterReconWithHeldType = $setterReconHistory?->withheld_type;
                            }
                            if ($setterReconWithHeldAmount && $setterReconWithHeldType) {
                                if ($setterReconWithHeldType == 'per sale') {
                                    $amount = $setterReconWithHeldAmount;
                                } elseif ($setterReconWithHeldType == 'percent') {
                                    $withheldPercent = $setterReconWithHeldAmount;
                                    $setter1Commission = isset($saleData->setter1_commission) ? $saleData->setter1_commission : 0;
                                    $amount = ($setter1Commission * ($withheldPercent / 100));
                                } else {
                                    $amount = ($setterReconWithHeldAmount * $kw);
                                }

                                if ($setterAmount->maximum_withheld && $amount > $setterAmount->maximum_withheld) {
                                    $amount = $setterAmount->maximum_withheld;
                                }

                                if ($setterId != $closerId) {
                                    $withHeldData = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'status' => '3'])->first();
                                    $data = [
                                        'pid' => $pid,
                                        'user_id' => $setterId,
                                        'amount' => $amount,
                                        'amount_type' => 'reconciliation',
                                        'settlement_type' => 'reconciliation',
                                        'kw' => $kw,
                                        'net_epc' => $net_epc,
                                        'date' => $m2Date,
                                        'status' => '3',
                                    ];
                                    if ($withHeldData) {
                                        if (! $paidM2) {
                                            UserCommission::where('id', $withHeldData->id)->update($data);
                                        }

                                    } else {
                                        UserCommission::create($data);
                                    }
                                    /* update finalize recon commission history status */
                                    $clawbackData = ClawbackSettlement::where([
                                        'pid' => $pid,
                                        'user_id' => $setterId,
                                        'type' => 'recon-commission',
                                    ])->where('status', '!=', 3)->get();
                                    if ($clawbackData) {
                                        ReconCommissionHistory::where([
                                            'pid' => $pid,
                                            'user_id' => $setterId,
                                            'status' => 'clawback',
                                        ])->update([
                                            'status' => 'payroll',
                                        ]);
                                        ClawbackSettlement::where([
                                            'pid' => $pid,
                                            'user_id' => $setterId,
                                            'type' => 'recon-commission',
                                        ])->where('status', '!=', 3)->delete();
                                    }
                                } /* else {
                            UserReconciliationWithholding::where("pid", $pid)->where("setter_id", $setterId)->delete();
                            } */
                            } /* else {
                        UserReconciliationWithholding::where("pid", $pid)->where("setter_id", $setterId)->delete();
                        } */
                        }
                    }

                    /* update finalize clawback status */
                    ReconciliationFinalizeHistory::where('pid', $pid)->where('payroll_execute_status', 3)->where('status', 'clawback')?->update(['status' => 'payroll']);
                }
            }
        }
        Log::channel('reconLog')->info("end recon for {$response->pid}");

    }

    public function userWithHoldingAmounts($userId, $productId, $approvalDate)
    {
        $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $subPositionId = @$userOrganizationHistory->sub_position_id;
        $closerAmount = PositionReconciliations::where(['position_id' => $subPositionId, 'product_id' => $productId, 'status' => 1])->first();
        if ($closerAmount) {
            $closerReconHistory = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $approvalDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($closerReconHistory) {
                $reconWithHeldAmount = floatval($closerReconHistory?->withheld_amount);
                $reconWithHeldType = $closerReconHistory?->withheld_type;
                if ($reconWithHeldAmount && $reconWithHeldType) {
                    return [
                        'recon_amount' => $reconWithHeldAmount,
                        'recon_amount_type' => $reconWithHeldType,
                        'recon_limit' => $closerAmount->maximum_withheld,
                    ];
                }
            }
        }

        return [
            'recon_amount' => null,
            'recon_amount_type' => null,
            'recon_limit' => null,
        ];
    }

    private function getCloserWithheldData($closerId, $approvedDate, $kw, $checked, $payFrequency, $halfAmount = false)
    {
        $userOrganizationHistory = $this->getUserOrganizationHistory($closerId, $approvedDate);

        if ($userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
            $userWithheldHistory = $this->getUserWithheldHistory($closerId, /* 1 */ 0, $approvedDate);
        } else {
            $userWithheldHistory = $this->getUserWithheldHistory($closerId, /* 0 */ 1, $approvedDate);
        }

        $closerWithHeldType = $userWithheldHistory->withheld_type ?? null;
        if ($halfAmount) {
            $closerWithHeldAmount = floatval($userWithheldHistory?->withheld_amount) * 0.5;
        } else {
            $closerWithHeldAmount = $userWithheldHistory->withheld_amount ?? 0;
        }

        $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
        $closerMaxWithHeldAmount = $closerWithheldForMax->maximum_withheld ?? null;

        return [
            'closerId' => $closerId,
            'closerWithHeldType' => $closerWithHeldType,
            'closerWithHeldAmount' => $closerWithHeldAmount,
            'closerMaxWithHeldAmount' => $closerMaxWithHeldAmount,
            'kw' => $kw,
            // 'checked' => $checked,
            'payFrequency' => $payFrequency,
            'saleSelfGen' => $userOrganizationHistory['self_gen_accounts'],
        ];
    }

    private function handleWithholding($data, $checked, $payFrequency)
    {
        $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $data['closerId'])->sum('withhold_amount');
        if ($data['closerMaxWithHeldAmount'] !== null && $closerReconciliationWithholdAmount < $data['closerMaxWithHeldAmount']) {
            if ($data['closerWithHeldType'] == 'per kw') {
                $commissionSettingAmount = $data['closerWithHeldAmount'] * $data['kw'];
            } else {
                $commissionSettingAmount = $data['closerWithHeldAmount'];
            }

            $closerWithheldCheck = $closerReconciliationWithholdAmount + $commissionSettingAmount;
            if ($closerWithheldCheck > $data['closerMaxWithHeldAmount']) {
                $commissionSettingAmount = $data['closerMaxWithHeldAmount'] - $closerReconciliationWithholdAmount;
            }

            $closerWithheld = $commissionSettingAmount;
        } else {
            $closerWithheld = $data['closerWithHeldType'] == 'per kw' ? $data['closerWithHeldAmount'] * $data['kw'] : $data['closerWithHeldAmount'];
        }
        if ($data['saleSelfGen'] != 1) {
            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $data['closerId'])->first();
            if ($reconData) {
                $reconData->withhold_amount = $closerWithheld;
                $reconData->save();
            } else {
                if ($closerWithheld > 0) {
                    UserReconciliationWithholding::create([
                        'pid' => $checked->pid,
                        'closer_id' => $data['closerId'],
                        'withhold_amount' => $closerWithheld,
                    ]);

                    $payReconciliation = UserReconciliationCommission::where([
                        'user_id' => $data['closerId'],
                        'period_from' => $payFrequency->pay_period_from,
                        'period_to' => $payFrequency->pay_period_to,
                        'status' => 'pending',
                    ])->first();

                    if ($payReconciliation) {
                        $payReconciliation->amount += $closerWithheld;
                        $payReconciliation->save();
                    } else {
                        UserReconciliationCommission::create([
                            'user_id' => $data['closerId'],
                            'amount' => $closerWithheld,
                            'period_from' => $payFrequency->pay_period_from,
                            'period_to' => $payFrequency->pay_period_to,
                            'status' => 'pending',
                        ]);
                    }
                }
            }
        }
    }

    private function handleSelfGenAccounts($userOrganizationHistory, $closerId, $setterId, $approvedDate, $kw, $response, $payFrequency, $halfAmount = false)
    {
        Log::channel('reconLog')->info('function handleSelfGenAccounts');
        if ($closerId == $setterId && $userOrganizationHistory->self_gen_accounts == '1') {
            Log::channel('reconLog')->info('function handleSelfGenAccounts if');
            $selfSubGenPositionId = $userOrganizationHistory->position_id == '2' ? 3 : 2;
            $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
            $setterWithheldForMax = PositionReconciliations::where(['position_id' => $selfSubGenPositionId, 'status' => '1'])->first();
            $selfGenUser = 0;
            if ($closerWithheldForMax || $setterWithheldForMax) {
                if ($closerId == $setterId) {
                    Log::channel('reconLog')->info('in');
                    $selfGenUser = 1;
                    $closerWithheldHistory = $this->getUserWithheldHistory($closerId, '1', $approvedDate);
                    $amount1 = $this->calculateWithheldAmount($closerWithheldHistory, $kw);

                    Log::channel('reconLog')->info("self-gen amount-1 {$amount1}");
                    $setterWithheldHistory = $this->getUserWithheldHistory($closerId, '0', $approvedDate);
                    $amount2 = $this->calculateWithheldAmount($setterWithheldHistory, $kw);
                    Log::channel('reconLog')->info("self gen amount-2 {$amount2}");
                } else {
                    $closerWithheldHistory = $this->getUserWithheldHistory($closerId, '0', $approvedDate);
                    $amount1 = $this->calculateWithheldAmount($closerWithheldHistory, $kw);
                    Log::channel('reconLog')->info("not same closer-setter self-gen amount-1 {$amount1}");

                    $setterWithheldHistory = $this->getUserWithheldHistory($closerId, '1', $approvedDate);
                    $amount2 = $this->calculateWithheldAmount($setterWithheldHistory, $kw);
                    Log::channel('reconLog')->info("not same closer-setter self-gen amount-1 {$amount2}");
                }

                $amount = max($amount1, $amount2);
                if ($halfAmount) {
                    $amount = $amount * 0.5;
                }
                $this->updateCloserReconciliation($response->pid, $closerId, $amount, $payFrequency, $selfGenUser);
            }
        } else {
            Log::channel('reconLog')->info('function handleSelfGenAccounts else');
            $this->handleNonSelfGenAccounts($userOrganizationHistory, $closerId, $approvedDate, $kw, $response, $payFrequency, $halfAmount);
        }
    }

    protected function handleSetter($setterId, $approvedDate, $checked, $kw, $payFrequency, $halfAmount = false)
    {
        $userOrganizationHistory = $this->getUserOrganizationHistory($setterId, $approvedDate);
        $selfGenUser = ($userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory->position_id == 2) ? 1 : 0;

        $userWithheldHistory = $this->getUserWithheldHistory($setterId, $selfGenUser, $approvedDate);
        $setterWithHeldType = $userWithheldHistory->withheld_type ?? null;
        if ($halfAmount) {
            $setterWithHeldAmount = floatval($userWithheldHistory?->withheld_amount) * 0.5;
        } else {
            $setterWithHeldAmount = $userWithheldHistory->withheld_amount ?? 0;
        }

        $setterMaxWithHeldAmount = $this->getPositionReconciliation($userOrganizationHistory->sub_position_id)->maximum_withheld ?? null;
        $setterReconciliationWithholdAmount = UserReconciliationWithholding::where(['setter_id' => $setterId, 'status' => 'unpaid'])->sum('withhold_amount');

        $commissionSettingAmount = $this->calculateCommissionSettingAmount($setterWithHeldType, $setterWithHeldAmount, $kw);
        $setterWithheld = $this->checkAndUpdateWithholding($setterMaxWithHeldAmount, $setterReconciliationWithholdAmount, $commissionSettingAmount);
        if ($setterWithheld > 0) {
            $this->updateOrCreateReconciliationWithholding($checked, $setterId, $setterWithheld, $payFrequency);
            $this->updateOrCreateReconciliationCommission($setterId, $setterWithheld, $payFrequency);
        }
    }

    protected function checkAndUpdateWithholding($setterMaxWithHeldAmount, $setterReconciliationWithholdAmount, $commissionSettingAmount)
    {
        if (! empty($setterMaxWithHeldAmount)) {
            if ($setterReconciliationWithholdAmount >= $setterMaxWithHeldAmount) {
                return 0;
            } else {
                $setterWithheldCheck = ($setterReconciliationWithholdAmount + $commissionSettingAmount);
                if ($setterWithheldCheck > $setterMaxWithHeldAmount) {
                    return $setterMaxWithHeldAmount - $setterReconciliationWithholdAmount;
                }
            }
        }

        return $commissionSettingAmount;
    }

    protected function calculateCommissionSettingAmount($withheldType, $withheldAmount, $kw)
    {
        return $withheldType == 'per kw' ? $withheldAmount * $kw : $withheldAmount;
    }

    protected function getPositionReconciliation($positionId)
    {
        return PositionReconciliations::where(['position_id' => $positionId, 'status' => '1'])->first();
    }

    private function getUserOrganizationHistory($userId, $approvedDate)
    {
        return UserOrganizationHistory::where('user_id', $userId)
            ->where('effective_date', '<=', $approvedDate)
            ->orderBy('effective_date', 'DESC')
            ->first();
    }

    private function handleNonSelfGenAccounts($userOrganizationHistory, $closerId, $approvedDate, $kw, $response, $payFrequency, $halfAmount = false)
    {
        Log::channel('reconLog')->info('function handleNonSelfGenAccounts');
        $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();

        if ($closerWithheldForMax) {
            Log::channel('reconLog')->info('function handleNonSelfGenAccounts if');

            $userWithheldHistory = $this->getUserWithheldHistory($closerId, $userOrganizationHistory->self_gen_accounts, $approvedDate);
            $closerWithheldAmount = $userWithheldHistory ? $userWithheldHistory->withheld_amount : 0;
            $closerWithHeldType = $userWithheldHistory ? $userWithheldHistory->withheld_type : 'per kw';
            $closerWithHeldType = empty($closerWithHeldType) ? $closerWithheldForMax->commission_type : $closerWithHeldType;

            $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closerId)->sum('withhold_amount');
            $closerMaxWithHeldAmount = $closerWithheldForMax->maximum_withheld ?? null;

            if ($closerMaxWithHeldAmount && $closerReconciliationWithholdAmount < $closerMaxWithHeldAmount) {
                Log::channel('reconLog')->info('function handleNonSelfGenAccounts if if');

                $commissionSettingAmount = $closerWithHeldType == 'per kw' ? $closerWithheldAmount * $kw : $closerWithheldAmount;

                $closerWithheldCheck = $closerReconciliationWithholdAmount + $commissionSettingAmount;
                if ($closerWithheldCheck > $closerMaxWithHeldAmount) {
                    $commissionSettingAmount = $closerMaxWithHeldAmount - $closerReconciliationWithholdAmount;
                }
            } else {
                Log::channel('reconLog')->info('function handleNonSelfGenAccounts if else');

                $commissionSettingAmount = $closerWithHeldType == 'per kw' ? $closerWithheldAmount * $kw : $closerWithheldAmount;
            }
            if ($halfAmount) {
                $commissionSettingAmount = $commissionSettingAmount * 0.5;
            }
            $this->updateCloserReconciliation($response->pid, $closerId, $commissionSettingAmount, $payFrequency);
        }
    }

    private function getUserWithheldHistory($userId, $selfGenUser, $approvedDate)
    {
        return UserWithheldHistory::where(['user_id' => $userId, 'self_gen_user' => $selfGenUser])
            ->where('withheld_effective_date', '<=', $approvedDate)
            ->orderBy('withheld_effective_date', 'DESC')
            ->first();
    }

    private function updateCloserReconciliation($pid, $closerId, $closerWithheld, $payFrequency, $selfGenUser = 0)
    {
        Log::channel('reconLog')->info("function updateReconciliation {$pid} -> {$closerId} -> {$selfGenUser}");
        /* check this sale is clawback exits or not */
        // $this->checkSaleClawbackData($pid, $closerId, $closerWithheld);

        $reconData = UserReconciliationWithholding::where('pid', $pid)->where('closer_id', $closerId)->first();
        if ($reconData) {
            $reconData->withhold_amount = $closerWithheld;
            $reconData->save();
        } else {
            UserReconciliationWithholding::create([
                'pid' => $pid,
                'closer_id' => $closerId,
                'withhold_amount' => $closerWithheld,
            ]);
        }
        if ($selfGenUser == 1) {
            UserReconciliationWithholding::where('pid', $pid)->where('setter_id', $closerId)->delete();
        }

        if (floatval($closerWithheld) > 0) {
            $payReconciliation = UserReconciliationCommission::where([
                'user_id' => $closerId,
                'period_from' => $payFrequency->pay_period_from,
                'period_to' => $payFrequency->pay_period_to,
                'status' => 'pending',
            ])->first();

            if ($payReconciliation) {
                $payReconciliation->amount += $closerWithheld;
                $payReconciliation->save();
            } else {
                UserReconciliationCommission::create([
                    'user_id' => $closerId,
                    'amount' => $closerWithheld,
                    'period_from' => $payFrequency->pay_period_from,
                    'period_to' => $payFrequency->pay_period_to,
                    'status' => 'pending',
                ]);
            }
        }
    }

    private function calculateWithheldAmount($withheldHistory, $kw)
    {
        $amount = 0;
        if ($withheldHistory) {
            if ($withheldHistory->withheld_type == 'per sale') {
                $amount = $withheldHistory->withheld_amount;
            } else {
                $amount = $withheldHistory->withheld_amount * $kw;
            }

            if (! empty($withheldHistory->upfront_limit) && $amount > $withheldHistory->upfront_limit) {
                $amount = $withheldHistory->upfront_limit;
            }
        }

        return $amount;
    }

    protected function handleSingleSetter($setterId, $approvedDate, $checked, $kw, $payFrequency)
    {
        $userOrganizationHistory = $this->getUserOrganizationHistory($setterId, $approvedDate);
        $userWithheldHistory = $this->getUserWithheldHistory($setterId, 0, $approvedDate);

        $setterWithHeldType = $userWithheldHistory->withheld_type ?? null;
        $setterWithHeldAmount = $userWithheldHistory->withheld_amount ?? 0;

        $setterMaxWithHeldAmount = $this->getPositionReconciliation($userOrganizationHistory->sub_position_id)->maximum_withheld ?? null;
        $setterReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setterId)->sum('withhold_amount');

        $commissionSettingAmount = $this->calculateCommission($setterWithHeldType, $setterWithHeldAmount, $kw);
        $setterWithheld = $this->checkAndUpdateWithholding($setterMaxWithHeldAmount, $setterReconciliationWithholdAmount, $commissionSettingAmount);

        if ($setterWithheld > 0) {
            $this->updateOrCreateReconciliationWithholding($checked, $setterId, $setterWithheld, $payFrequency);
            $this->updateOrCreateReconciliationCommission($setterId, $setterWithheld, $payFrequency);
        }
    }

    protected function calculateCommission($setterWithHeldType, $setterWithHeldAmount, $kw)
    {
        if ($setterWithHeldType == 'per kw') {
            return $setterWithHeldAmount * $kw;
        }

        return $setterWithHeldAmount;
    }

    protected function updateOrCreateReconciliationWithholding($checked, $setterId, $setterWithheld, $payFrequency)
    {
        /* check this sale is clawback exits or not */
        // $this->checkSaleClawbackData($checked->pid, $setterId, $setterWithheld);

        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
        if ($reconData) {
            $reconData->withhold_amount = $setterWithheld;
            $reconData->save();
        } else {
            $reconData = UserReconciliationWithholding::firstOrNew(
                ['pid' => $checked->pid, 'setter_id' => $setterId]
            );
            $reconData->withhold_amount = $setterWithheld;
            $reconData->save();
            /* UserReconciliationWithholding::create([
        'pid' => $checked->pid,
        'closer_id' => $setterId,
        'withhold_amount' => $setterWithheld,
        ]); */
        }

        if (floatval($setterWithheld) > 0) {
            $payReconciliation = UserReconciliationCommission::where([
                'user_id' => $setterId,
                'period_from' => $payFrequency->pay_period_from,
                'period_to' => $payFrequency->pay_period_to,
                'status' => 'pending',
            ])->first();

            if ($payReconciliation) {
                $payReconciliation->amount += $setterWithheld;
                $payReconciliation->save();
            } else {
                UserReconciliationCommission::create([
                    'user_id' => $setterId,
                    'amount' => $setterWithheld,
                    'period_from' => $payFrequency->pay_period_from,
                    'period_to' => $payFrequency->pay_period_to,
                    'status' => 'pending',
                ]);
            }
        }
    }

    protected function updateOrCreateReconciliationCommission($setterId, $setterWithheld, $payFrequency)
    {
        $payReconciliation = UserReconciliationCommission::firstOrNew(
            [
                'user_id' => $setterId,
                'period_from' => $payFrequency->pay_period_from,
                'period_to' => $payFrequency->pay_period_to,
                'status' => 'pending',
            ]
        );
        $payReconciliation->amount += $setterWithheld;
        $payReconciliation->save();
    }

    protected function checkSaleClawbackData($pid, $userId)
    {
        $clawbackPaidAmount = ClawbackSettlement::where('user_id', $userId)
            ->where('pid', $pid)
            ->where('clawback_type', 'reconciliation')
            ->where('status', 3)
            ->sum('clawback_amount');

        $salePaidAmount = ReconciliationFinalizeHistory::where('pid', $pid)
            ->where('user_id', $userId)
            ->where('status', 'clawback')
            ->where('payroll_execute_status', 3)
            ->sum('net_amount');
        $amount = $salePaidAmount - $clawbackPaidAmount;

        if (! empty($amount)) {
            ReconciliationFinalizeHistory::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('status', 'clawback')
                ->where('payroll_execute_status', 3)
                ->update([
                    'status' => 'payroll',
                ]);
            ClawbackSettlement::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('clawback_type', 'reconciliation')
                ->where('status', 3)->delete();
        }
    }
}
