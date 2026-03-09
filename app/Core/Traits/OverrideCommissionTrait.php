<?php

namespace App\Core\Traits;

use App\Models\AdditionalLocations;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\Payroll;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommission;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserTransferHistory;
use Illuminate\Support\Facades\DB;

trait OverrideCommissionTrait
{
    use PayFrequencyTrait;
    use ReconciliationPeriodTrait;

    public function UserOverride($sale_user_id, $pid, $kw, $date, $redline)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $netEpc = $saleMaster->net_epc;
        // $grossAmountValue = $saleMaster->gross_account_value;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $recruiter_id_data = User::where('id', $sale_user_id)->first();
        $companyMargin = CompanyProfile::first();
        if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $saleMaster->gross_account_value;
        }
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $margin_percentage = $companyMargin->company_margin;
            $x = ((100 - $margin_percentage) / 100);
        } else {
            $x = 1;
        }
        $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $sale_user_id, 'is_displayed' => '1'])->sum('amount');
        $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'user_id' => $sale_user_id, 'is_displayed' => '1'])->sum('clawback_amount');
        $finalCommission = $totalCommission - $totalClawBack;

        // OFFICE OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->office_id) {
            $office_id = $recruiter_id_data->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $sale_user_id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }

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
            $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereHas('user', function ($q) {
                $q->where(['dismiss' => '0'])->whereNotIn('id', ['1']);
            })->whereNotNull('office_id')->where('office_id', $office_id)->pluck('user_id')->toArray();

            $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type')
                ->whereIn('id', $userIdArr)->get();

            foreach ($userIdArr1 as $userData) {
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'override_settlement' => 'Reconciliation', 'status' => '1'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                    // $payFrequencyOffice = $this->reconciliationPeriod($date);
                } else {
                    $settlementType = 'during_m2';
                    $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $userData->id);
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => 1])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideStatus && $overrideStatus->status == 0 && $userData) {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                if ($userData->office_overrides_type == 'percent') {
                                    $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $userData->office_overrides_amount;
                                }
                                $redline = null;
                            } else {
                                if ($userData->office_overrides_type == 'per kw') {
                                    $amount = $userData->office_overrides_amount * $kw;
                                } elseif ($userData->office_overrides_type == 'percent') {
                                    $amount = ($finalCommission * $x) * ($userData->office_overrides_amount / 100);
                                } else {
                                    $amount = $userData->office_overrides_amount;
                                }
                            }

                            $officeData = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'sale_user_id' => $sale_user_id,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'calculated_redline' => $redline,
                                'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : null,
                                'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : null,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $isCreate = 0;
                            $override = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->first();
                            if ($override) {
                                $isCreate = 1;
                            } else {
                                $userOverride = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                                if ($userOverride) {
                                    $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'overrides', 'adders_type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                                    if ($userOverride == $userClawBack) {
                                        $isCreate = 1;
                                    }
                                } else {
                                    $isCreate = 1;
                                }
                            }
                            if ($isCreate) {
                                $officeOverrides = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->orderByDesc('amount')->first();
                                if ($officeOverrides) {
                                    if ($amount > $officeOverrides->amount) {
                                        UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->delete();
                                        $userOverrode = UserOverrides::create($officeData);
                                    }
                                } else {
                                    $userOverrode = UserOverrides::create($officeData);
                                }
                            }

                            if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequencyOffice->pay_period_from, 'pay_period_to' => $payFrequencyOffice->pay_period_to])->first()) {
                                    PayRoll::create([
                                        'user_id' => $userData->id,
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

            $userIdArr2 = AdditionalLocations::whereHas('user')->with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type')
                ->where(['office_id' => $office_id])->whereNotIn('user_id', ['1', $sale_user_id])->get();
            foreach ($userIdArr2 as $userData) {
                $userData = $userData->user;
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                    // $payFrequencyAdditionalOffice = $this->reconciliationPeriod($date);
                } else {
                    $settlementType = 'during_m2';
                    $payFrequencyAdditionalOffice = $this->payFrequencyNew($date, $positionId, $userData->id);
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => '1'])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideStatus && $overrideStatus->status == 0 && $userData->dismiss == '0') {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'office_id' => $office_id])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                if ($userData->office_overrides_type == 'percent') {
                                    $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $userData->office_overrides_amount;
                                }
                                $redline = null;
                            } else {
                                if ($userData->office_overrides_type == 'per kw') {
                                    $amount = $userData->office_overrides_amount * $kw;
                                } elseif ($userData->office_overrides_type == 'percent') {
                                    $amount = ($finalCommission * $x) * ($userData->office_overrides_amount / 100);
                                } else {
                                    $amount = $userData->office_overrides_amount;
                                }
                            }

                            $officeData = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'sale_user_id' => $sale_user_id,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'calculated_redline' => $redline,
                                'pay_period_from' => isset($payFrequencyAdditionalOffice->pay_period_from) ? $payFrequencyAdditionalOffice->pay_period_from : null,
                                'pay_period_to' => isset($payFrequencyAdditionalOffice->pay_period_to) ? $payFrequencyAdditionalOffice->pay_period_to : null,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $isCreate = 0;
                            $override = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->first();
                            if ($override) {
                                $isCreate = 1;
                            } else {
                                $userOverride = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                                if ($userOverride) {
                                    $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $userData->id, 'type' => 'overrides', 'adders_type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                                    if ($userOverride == $userClawBack) {
                                        $isCreate = 1;
                                    }
                                } else {
                                    $isCreate = 1;
                                }
                            }
                            if ($isCreate) {
                                $officeOverrides = UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->orderByDesc('amount')->first();
                                if ($officeOverrides) {
                                    if ($amount > $officeOverrides->amount) {
                                        UserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => '1', 'is_displayed' => '1'])->delete();
                                        $userOverrode = UserOverrides::create($officeData);
                                    }
                                } else {
                                    $userOverrode = UserOverrides::create($officeData);
                                }
                            }

                            if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                if (! PayRoll::where(['user_id' => $userData->id, 'status' => '1', 'pay_period_from' => $payFrequencyAdditionalOffice->pay_period_from, 'pay_period_to' => $payFrequencyAdditionalOffice->pay_period_to])->first()) {
                                    PayRoll::create([
                                        'user_id' => $userData->id,
                                        'position_id' => isset($positionId) ? $positionId : null,
                                        'override' => 0,
                                        'pay_period_from' => $payFrequencyAdditionalOffice->pay_period_from,
                                        'pay_period_to' => $payFrequencyAdditionalOffice->pay_period_to,
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
        // END OFFICE OVERRIDES CODE

        // DIRECT & INDIRECT OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->recruiter_id) {
            $recruiter_ids = $recruiter_id_data->recruiter_id;
            if (! empty($recruiter_id_data->additional_recruiter_id1)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id1;
            }
            if (! empty($recruiter_id_data->additional_recruiter_id2)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id2;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
            foreach ($directs as $value) {
                $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $value->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                    // $payFrequencyDirect = $this->reconciliationPeriod($date);
                } else {
                    $settlementType = 'during_m2';
                    $payFrequencyDirect = $this->payFrequencyNew($date, $positionId, $value->id);
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Direct', 'status' => 1])->first();
                $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Direct'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($positionOverride && $overrideStatus && $overrideStatus->status == 0) {
                    $value->direct_overrides_amount = 0;
                    $value->direct_overrides_type = '';

                    $overrideHistory = UserOverrideHistory::where('user_id', $value->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                        $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                    }

                    if ($value->direct_overrides_amount) {
                        if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            if ($value->direct_overrides_type == 'percent') {
                                $amount = (($kw * $value->direct_overrides_amount * $x) / 100);
                            } else {
                                $amount = $value->direct_overrides_amount;
                            }
                        } else {
                            if ($value->direct_overrides_type == 'per kw') {
                                $amount = $value->direct_overrides_amount * $kw;
                            } elseif ($value->direct_overrides_type == 'percent') {
                                $amount = ($finalCommission * $x) * ($value->direct_overrides_amount / 100);
                            } else {
                                $amount = $value->direct_overrides_amount;
                            }
                        }

                        $dataDirect = [
                            'user_id' => $value->id,
                            'type' => 'Direct',
                            'sale_user_id' => $sale_user_id,
                            'pid' => $pid,
                            'kw' => $kw,
                            'amount' => $amount,
                            'overrides_amount' => $value->direct_overrides_amount,
                            'overrides_type' => $value->direct_overrides_type,
                            'pay_period_from' => isset($payFrequencyDirect->pay_period_from) ? $payFrequencyDirect->pay_period_from : null,
                            'pay_period_to' => isset($payFrequencyDirect->pay_period_to) ? $payFrequencyDirect->pay_period_to : null,
                            'overrides_settlement_type' => $settlementType,
                            'status' => 1,
                            'is_stop_payroll' => $stopPayroll,
                        ];

                        // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                        if (! UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '3', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first()) {
                            if (overrideSystemSetting::where('pay_type', 2)->first()) {
                                $userOverrides = UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('amount')->first();
                                if ($userOverrides) {
                                    if ($amount > $userOverrides->amount) {
                                        UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->delete();
                                        $userOverrode = UserOverrides::create($dataDirect);
                                    }
                                } else {
                                    $userOverrode = UserOverrides::create($dataDirect);
                                }
                            } else {
                                $userOverrode = UserOverrides::create($dataDirect);
                            }

                            if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                if (! PayRoll::where(['user_id' => $value->id, 'status' => '1', 'pay_period_from' => $payFrequencyDirect->pay_period_from, 'pay_period_to' => $payFrequencyDirect->pay_period_to])->first()) {
                                    PayRoll::create([
                                        'user_id' => $value->id,
                                        'position_id' => isset($positionId) ? $positionId : null,
                                        'override' => 0,
                                        'pay_period_from' => $payFrequencyDirect->pay_period_from,
                                        'pay_period_to' => $payFrequencyDirect->pay_period_to,
                                        'status' => 1,
                                        'is_stop_payroll' => $stopPayroll,
                                    ]);
                                }
                            }
                        }
                    }
                }

                // INDIRECT
                if ($value->recruiter_id) {
                    $recruiter_ids = $value->recruiter_id;
                    if (! empty($value->additional_recruiter_id1)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id1;
                    }
                    if (! empty($value->additional_recruiter_id2)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id2;
                    }
                    $idsArr = explode(',', $recruiter_ids);

                    $additional = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
                    foreach ($additional as $val) {
                        $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $val->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $val->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                            // $payFrequencyInDirect = $this->reconciliationPeriod($date);
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequencyInDirect = $this->payFrequencyNew($date, $positionId, $val->id);
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                        // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $val->id, 'type' => 'Direct', 'status' => 1])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $val->id, 'type' => 'Indirect'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($positionOverride && $overrideStatus && $overrideStatus->status == 0) {
                            $val->indirect_overrides_amount = 0;
                            $val->indirect_overrides_type = '';

                            $overrideHistory = UserOverrideHistory::where('user_id', $val->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                            }

                            if ($val->indirect_overrides_amount) {
                                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                    if ($val->indirect_overrides_type == 'percent') {
                                        $amount = (($kw * $val->indirect_overrides_amount * $x) / 100);
                                    } else {
                                        $amount = $val->indirect_overrides_amount;
                                    }
                                } else {
                                    if ($val->indirect_overrides_type == 'per kw') {
                                        $amount = $val->indirect_overrides_amount * $kw;
                                    } elseif ($val->indirect_overrides_type == 'percent') {
                                        $amount = ($finalCommission * $x) * ($val->indirect_overrides_amount / 100);
                                    } else {
                                        $amount = $val->indirect_overrides_amount;
                                    }
                                }

                                $dataIndirect = [
                                    'user_id' => $val->id,
                                    'type' => 'Indirect',
                                    'sale_user_id' => $sale_user_id,
                                    'pid' => $pid,
                                    'kw' => $kw,
                                    'amount' => $amount,
                                    'overrides_amount' => $val->indirect_overrides_amount,
                                    'overrides_type' => $val->indirect_overrides_type,
                                    'pay_period_from' => isset($payFrequencyInDirect->pay_period_from) ? $payFrequencyInDirect->pay_period_from : null,
                                    'pay_period_to' => isset($payFrequencyInDirect->pay_period_to) ? $payFrequencyInDirect->pay_period_to : null,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                ];

                                // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                                if (! UserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'status' => '3', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first()) {
                                    if (overrideSystemSetting::where('pay_type', 2)->first()) {
                                        $userOverrides = UserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('amount')->first();
                                        if ($userOverrides) {
                                            if ($amount > $userOverrides->amount) {
                                                UserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->delete();
                                                $userOverrode = UserOverrides::create($dataIndirect);
                                            }
                                        } else {
                                            $userOverrode = UserOverrides::create($dataIndirect);
                                        }
                                    } else {
                                        $userOverrode = UserOverrides::create($dataIndirect);
                                    }

                                    if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                        if (! PayRoll::where(['user_id' => $val->id, 'status' => '1', 'pay_period_from' => $payFrequencyInDirect->pay_period_from, 'pay_period_to' => $payFrequencyInDirect->pay_period_to])->first()) {
                                            PayRoll::create([
                                                'user_id' => $val->id,
                                                'position_id' => isset($positionId) ? $positionId : null,
                                                'override' => 0,
                                                'pay_period_from' => $payFrequencyInDirect->pay_period_from,
                                                'pay_period_to' => $payFrequencyInDirect->pay_period_to,
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
        // END DIRECT & INDIRECT OVERRIDES CODE

        // MANUAL OVERRIDES CODE
        if ($sale_user_id) {
            if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
                $manualOverrides = ManualOverrides::where('manual_user_id', $sale_user_id)->whereHas('manualUser', function ($q) {
                    $q->where('id', '!=', '1')->where('dismiss', '0');
                })->pluck('user_id');
                $users = User::whereIn('id', $manualOverrides)->get();

                foreach ($users as $value) {
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;
                    // $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Manual', 'status' => 1])->first();
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Manual'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideStatus && $overrideStatus->status == 0) {
                        $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $value->sub_position_id;
                        }

                        if (PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first()) {
                            $settlementType = 'reconciliation';
                            // $payFrequencyManual = $this->reconciliationPeriod($date);
                        } else {
                            $settlementType = 'during_m2';
                            $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $value->id);
                        }

                        $value->overrides_amount = 0;
                        $value->overrides_type = '';
                        $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->id, 'manual_user_id' => $sale_user_id])
                            ->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $value->overrides_amount = $overrideHistory->overrides_amount;
                            $value->overrides_type = $overrideHistory->overrides_type;
                        }

                        if ($value->overrides_amount) {
                            if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                if ($value->overrides_type == 'percent') {
                                    $amount = (($kw * $value->overrides_amount * $x) / 100);
                                } else {
                                    $amount = $value->overrides_amount;
                                }
                            } else {
                                if ($value->overrides_type == 'per kw') {
                                    $amount = $value->overrides_amount * $kw;
                                } elseif ($value->overrides_type == 'percent') {
                                    $amount = ($finalCommission * $x) * ($value->overrides_amount / 100);
                                } else {
                                    $amount = $value->overrides_amount;
                                }
                            }

                            $dataManual = [
                                'user_id' => $value->id,
                                'type' => 'Manual',
                                'sale_user_id' => $sale_user_id,
                                'pid' => $pid,
                                'kw' => $kw,
                                'amount' => $amount,
                                'overrides_amount' => $value->overrides_amount,
                                'overrides_type' => $value->overrides_type,
                                'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : null,
                                'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : null,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                            ];

                            // IF ANY ONE OF THESE ARE PAID THEN IT CAN NOT BE CHANGED, THEY DEPENDS ON EACH OTHER
                            if (! UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '3', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->first()) {
                                if (overrideSystemSetting::where('pay_type', 2)->first()) {
                                    $userOverrides = UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('amount')->first();
                                    if ($userOverrides) {
                                        if ($amount > $userOverrides->amount) {
                                            UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->delete();
                                            $userOverrode = UserOverrides::create($dataManual);
                                        }
                                    } else {
                                        $userOverrode = UserOverrides::create($dataManual);
                                    }
                                } else {
                                    $userOverrode = UserOverrides::create($dataManual);
                                }

                                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                                    if (! PayRoll::where(['user_id' => $value->id, 'status' => '1', 'pay_period_from' => $payFrequencyManual->pay_period_from, 'pay_period_to' => $payFrequencyManual->pay_period_to])->first()) {
                                        PayRoll::create([
                                            'user_id' => $value->id,
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
        // END MANUAL OVERRIDES CODE
    }

    public function AddersOverrides($sale_user_id, $pid, $kw, $date, $redline)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $companyMargin = CompanyProfile::first();
        if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $saleMaster->gross_account_value;
        }
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $margin_percentage = $companyMargin->company_margin;
            $x = ((100 - $margin_percentage) / 100);
        } else {
            $x = 1;
        }

        $totalCommission = UserCommission::where(['pid' => $pid, 'user_id' => $sale_user_id, 'is_displayed' => '1'])->sum('amount');
        $totalClawBack = ClawbackSettlement::where(['pid' => $pid, 'type' => 'commission', 'user_id' => $sale_user_id, 'is_displayed' => '1'])->sum('clawback_amount');
        $finalCommission = $totalCommission - $totalClawBack;

        // OFFICE OVERRIDE
        $officeOverrides = UserOverrides::with('userdata')->whereHas('userdata', function ($q) {
            $q->where('dismiss', '0');
        })->where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'status' => '3', 'type' => 'Office', 'is_displayed' => '1'])->get();
        foreach ($officeOverrides as $officeOverride) {
            $stopPayroll = ($officeOverride->userdata->stop_payroll == 1) ? 1 : 0;

            $organizationHistory = UserOrganizationHistory::where('user_id', $officeOverride->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            } else {
                $positionId = $officeOverride->userdata->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'override_settlement' => 'Reconciliation', 'status' => '1'])->first();
            if ($positionReconciliation) {
                $settlementType = 'reconciliation';
                // $payFrequencyOffice = $this->reconciliationPeriod($date);
            } else {
                $settlementType = 'during_m2';
                $payFrequencyOffice = $this->payFrequencyNew($date, $positionId, $officeOverride->userdata->id);
            }

            if ($officeOverride->overrides_amount) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($officeOverride->overrides_type == 'percent') {
                        $amount = (($kw * $officeOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                    $redline = null;
                } else {
                    if ($officeOverride->overrides_type == 'per kw') {
                        $amount = $officeOverride->overrides_amount * $kw;
                    } elseif ($officeOverride->overrides_type == 'percent') {
                        $amount = ($finalCommission * $x) * ($officeOverride->overrides_amount / 100);
                    } else {
                        $amount = $officeOverride->overrides_amount;
                    }
                }

                $officeData = [
                    'user_id' => $officeOverride->userdata->id,
                    'type' => 'Office',
                    'during' => 'm2 update',
                    'sale_user_id' => $sale_user_id,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $officeOverride->overrides_amount,
                    'overrides_type' => $officeOverride->overrides_type,
                    'calculated_redline' => $redline,
                    'pay_period_from' => isset($payFrequencyOffice->pay_period_from) ? $payFrequencyOffice->pay_period_from : null,
                    'pay_period_to' => isset($payFrequencyOffice->pay_period_to) ? $payFrequencyOffice->pay_period_to : null,
                    'overrides_settlement_type' => $settlementType,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                    'office_id' => $officeOverride->office_id,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $officeOverride->userdata->id, 'type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $officeOverride->userdata->id, 'type' => 'overrides', 'adders_type' => 'Office', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                if (number_format($userOverride, 2, '.', '') != number_format($userClawBack, 2, '.', '')) {
                    $totalOffice = $userOverride - $userClawBack;
                    $adderAmount = number_format($amount, 2, '.', '');
                    $normalAmount = number_format($totalOffice, 2, '.', '');
                    $amount = $adderAmount - $normalAmount;
                    if ($amount) {
                        $officeData['amount'] = $amount;
                        $userOverrode = UserOverrides::create($officeData);
                    }
                }

                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                    updateExistingPayroll($officeOverride->userdata->id, $payFrequencyOffice->pay_period_from, $payFrequencyOffice->pay_period_to, $amount, 'override', $positionId, $stopPayroll);
                }
            }
        }
        // END OFFICE OVERRIDE

        // DIRECT OVERRIDE
        $directOverrides = UserOverrides::with('userdata')->whereHas('userdata', function ($q) {
            $q->where('dismiss', '0');
        })->where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'status' => '3', 'type' => 'Direct', 'is_displayed' => '1'])->get();
        foreach ($directOverrides as $directOverride) {
            $stopPayroll = ($directOverride->userdata->stop_payroll == 1) ? 1 : 0;

            $organizationHistory = UserOrganizationHistory::where('user_id', $directOverride->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            } else {
                $positionId = $directOverride->userdata->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
            if ($positionReconciliation) {
                $settlementType = 'reconciliation';
                // $payFrequencyDirect = $this->reconciliationPeriod($date);
            } else {
                $settlementType = 'during_m2';
                $payFrequencyDirect = $this->payFrequencyNew($date, $positionId, $directOverride->userdata->id);
            }

            if ($directOverride->overrides_amount) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($directOverride->overrides_type == 'percent') {
                        $amount = (($kw * $directOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $directOverride->overrides_amount;
                    }
                } else {
                    if ($directOverride->overrides_type == 'per kw') {
                        $amount = $directOverride->overrides_amount * $kw;
                    } elseif ($directOverride->overrides_type == 'percent') {
                        $amount = ($finalCommission * $x) * ($directOverride->overrides_amount / 100);
                    } else {
                        $amount = $directOverride->overrides_amount;
                    }
                }

                $directData = [
                    'user_id' => $directOverride->userdata->id,
                    'type' => 'Direct',
                    'during' => 'm2 update',
                    'sale_user_id' => $sale_user_id,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $directOverride->overrides_amount,
                    'overrides_type' => $directOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyDirect->pay_period_from) ? $payFrequencyDirect->pay_period_from : null,
                    'pay_period_to' => isset($payFrequencyDirect->pay_period_to) ? $payFrequencyDirect->pay_period_to : null,
                    'overrides_settlement_type' => $settlementType,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $directOverride->userdata->id, 'type' => 'Direct', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $directOverride->userdata->id, 'type' => 'overrides', 'adders_type' => 'Direct', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                if (number_format($userOverride, 2, '.', '') != number_format($userClawBack, 2, '.', '')) {
                    $totalDirect = $userOverride - $userClawBack;
                    $adderAmount = number_format($amount, 2, '.', '');
                    $normalAmount = number_format($totalDirect, 2, '.', '');
                    $amount = $adderAmount - $normalAmount;
                    if ($amount) {
                        $directData['amount'] = $amount;
                        $userOverrode = UserOverrides::create($directData);
                    }
                }

                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                    updateExistingPayroll($directOverride->userdata->id, $payFrequencyDirect->pay_period_from, $payFrequencyDirect->pay_period_to, $amount, 'override', $positionId, $stopPayroll);
                }
            }
        }
        // END DIRECT OVERRIDE

        // INDIRECT OVERRIDE
        $indirectOverrides = UserOverrides::with('userdata')->whereHas('userdata', function ($q) {
            $q->where('dismiss', '0');
        })->where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'type' => 'Indirect', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($indirectOverrides as $indirectOverride) {
            $stopPayroll = ($indirectOverride->userdata->stop_payroll == 1) ? 1 : 0;

            $organizationHistory = UserOrganizationHistory::where('user_id', $indirectOverride->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            } else {
                $positionId = $indirectOverride->userdata->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
            if ($positionReconciliation) {
                $settlementType = 'reconciliation';
                // $payFrequencyDirect = $this->reconciliationPeriod($date);
            } else {
                $settlementType = 'during_m2';
                $payFrequencyInDirect = $this->payFrequencyNew($date, $positionId, $indirectOverride->userdata->id);
            }

            if ($indirectOverride->overrides_amount) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($indirectOverride->overrides_type == 'percent') {
                        $amount = (($kw * $indirectOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $indirectOverride->overrides_amount;
                    }
                } else {
                    if ($indirectOverride->overrides_type == 'per kw') {
                        $amount = $indirectOverride->overrides_amount * $kw;
                    } elseif ($indirectOverride->overrides_type == 'percent') {
                        $amount = ($finalCommission * $x) * ($indirectOverride->overrides_amount / 100);
                    } else {
                        $amount = $indirectOverride->overrides_amount;
                    }
                }

                $inDirectData = [
                    'user_id' => $indirectOverride->userdata->id,
                    'type' => 'Indirect',
                    'during' => 'm2 update',
                    'sale_user_id' => $sale_user_id,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $indirectOverride->overrides_amount,
                    'overrides_type' => $indirectOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyInDirect->pay_period_from) ? $payFrequencyInDirect->pay_period_from : null,
                    'pay_period_to' => isset($payFrequencyInDirect->pay_period_to) ? $payFrequencyInDirect->pay_period_to : null,
                    'overrides_settlement_type' => $settlementType,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $indirectOverride->userdata->id, 'type' => 'Indirect', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $indirectOverride->userdata->id, 'type' => 'overrides', 'adders_type' => 'Indirect', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                if (number_format($userOverride, 2, '.', '') != number_format($userClawBack, 2, '.', '')) {
                    $totalInDirect = $userOverride - $userClawBack;
                    $adderAmount = number_format($amount, 2, '.', '');
                    $normalAmount = number_format($totalInDirect, 2, '.', '');
                    $amount = $adderAmount - $normalAmount;
                    if ($amount) {
                        $inDirectData['amount'] = $amount;
                        $userOverrode = UserOverrides::create($inDirectData);
                    }
                }

                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                    updateExistingPayroll($indirectOverride->userdata->id, $payFrequencyInDirect->pay_period_from, $payFrequencyInDirect->pay_period_to, $amount, 'override', $positionId, $stopPayroll);
                }
            }
        }
        // END INDIRECT OVERRIDE

        // MANUAL OVERRIDE
        $manualOverrides = UserOverrides::with('userdata')->whereHas('userdata', function ($q) {
            $q->where('dismiss', '0');
        })->where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'during' => 'm2', 'type' => 'Manual', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($manualOverrides as $manualOverride) {
            $stopPayroll = ($manualOverride->userdata->stop_payroll == 1) ? 1 : 0;

            $organizationHistory = UserOrganizationHistory::where('user_id', $manualOverride->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $positionId = $organizationHistory->sub_position_id;
            } else {
                $positionId = $manualOverride->userdata->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
            if ($positionReconciliation) {
                $settlementType = 'reconciliation';
                // $payFrequencyDirect = $this->reconciliationPeriod($date);
            } else {
                $settlementType = 'during_m2';
                $payFrequencyManual = $this->payFrequencyNew($date, $positionId, $manualOverride->userdata->id);
            }

            if ($manualOverride->overrides_amount) {
                if (in_array($companyMargin->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($manualOverride->overrides_type == 'percent') {
                        $amount = (($kw * $manualOverride->overrides_amount * $x) / 100);
                    } else {
                        $amount = $manualOverride->overrides_amount;
                    }
                } else {
                    if ($manualOverride->overrides_type == 'per kw') {
                        $amount = $manualOverride->overrides_amount * $kw;
                    } elseif ($manualOverride->overrides_type == 'percent') {
                        $amount = ($finalCommission * $x) * ($manualOverride->overrides_amount / 100);
                    } else {
                        $amount = $manualOverride->overrides_amount;
                    }
                }

                $manualData = [
                    'user_id' => $manualOverride->userdata->id,
                    'type' => 'Manual',
                    'during' => 'm2 update',
                    'sale_user_id' => $sale_user_id,
                    'pid' => $pid,
                    'kw' => $kw,
                    'amount' => $amount,
                    'overrides_amount' => $manualOverride->overrides_amount,
                    'overrides_type' => $manualOverride->overrides_type,
                    'pay_period_from' => isset($payFrequencyManual->pay_period_from) ? $payFrequencyManual->pay_period_from : null,
                    'pay_period_to' => isset($payFrequencyManual->pay_period_to) ? $payFrequencyManual->pay_period_to : null,
                    'overrides_settlement_type' => $settlementType,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $userOverride = UserOverrides::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $manualOverride->userdata->id, 'type' => 'Manual', 'status' => '3', 'is_displayed' => '1'])->sum('amount');
                $userClawBack = ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $sale_user_id, 'user_id' => $manualOverride->userdata->id, 'type' => 'overrides', 'adders_type' => 'Manual', 'status' => '3', 'is_displayed' => '1'])->sum('clawback_amount');
                if (number_format($userOverride, 2, '.', '') != number_format($userClawBack, 2, '.', '')) {
                    $totalManual = $userOverride - $userClawBack;
                    $adderAmount = number_format($amount, 2, '.', '');
                    $normalAmount = number_format($totalManual, 2, '.', '');
                    $amount = $adderAmount - $normalAmount;
                    if ($amount) {
                        $manualData['amount'] = $amount;
                        $userOverrode = UserOverrides::create($manualData);
                    }
                }

                if (isset($userOverrode) && $userOverrode->overrides_settlement_type == 'during_m2') {
                    updateExistingPayroll($manualOverride->userdata->id, $payFrequencyManual->pay_period_from, $payFrequencyManual->pay_period_to, $amount, 'override', $positionId, $stopPayroll);
                }
            }
        }
        // END MANUAL OVERRIDE
    }
}
