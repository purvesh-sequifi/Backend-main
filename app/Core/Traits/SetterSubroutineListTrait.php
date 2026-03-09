<?php

namespace App\Core\Traits;

use App\Core\Traits\ReconTraits\ReconRoutineClawbackTraits;
use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Models\BackendSetting;
use App\Models\ClawbackSettlement;
use App\Models\CloserIdentifyAlert;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\DeductionAlert;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SetterIdentifyAlert;
use App\Models\State;
use App\Models\upfrontSystemSetting;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;

trait SetterSubroutineListTrait
{
    use OverrideCommissionTrait;
    use OverrideStackTrait;
    use PayFrequencyTrait;
    use PayRollClawbackTrait;
    use PayRollCommissionTrait, ReconRoutineClawbackTraits, ReconRoutineTraits;
    use PayRollDeductionTrait;
    use ReconciliationPeriodTrait;

    protected $commissionData = [];

    public function subroutineOne($checked)
    {
        $rep_email = isset($checked->sales_rep_email) ? $checked->sales_rep_email : null;
        $setterId = isset($checked->salesMasterProcess->setter1_id) ? $checked->salesMasterProcess->setter1_id : null;

        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }

        if ($closer) {
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $UpdateData->closer1_id = isset($closer->id) ? $closer->id : 0;
            $UpdateData->save();

            // Identify Setter
            if ($setterId != null) {
                // $setterId = '565656';
                $setterIdCheck = User::where('id', $setterId)->first();
                if (isset($setterIdCheck) && $setterIdCheck != '') {
                    //  Setter Data is updated
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    $UpdateData->setter1_id = isset($setterId) ? $setterId : 0;
                    $UpdateData->save();
                } else {
                    $setter['pid'] = $checked->pid;
                    $setter['sales_rep_email'] = $checked->sales_rep_email;
                    $saleMasterProcess = SetterIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
                    if (empty($saleMasterProcess)) {

                        SetterIdentifyAlert::create($setter);

                        return false;
                    }
                }
            } else {
                $setter['pid'] = $checked->pid;
                $setter['sales_rep_email'] = $checked->sales_rep_email;
                $saleMasterProcess = SetterIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
                if (empty($saleMasterProcess)) {
                    SetterIdentifyAlert::create($setter);

                    return false;
                }
            }
        } else {
            $closers['pid'] = $checked->pid;
            $closers['sales_rep_email'] = $checked->sales_rep_email;

            $saleMasterProcess = CloserIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
            if (empty($saleMasterProcess)) {
                $close = CloserIdentifyAlert::create($closers);

                return false;
            }
        }

        return true;
    }

    public function subroutineTwo($val, $lid)
    {
        $user = User::where('email', $val->rep_email)->first();
        if (empty($user)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $val->rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $user = User::where('id', $additional_user_id)->first();
            }
        }

        if (! empty($val->prospect_id) && ! empty($val->customer_name) && ! empty($val->kw) && ! empty($val->customer_state) && ! empty($val->rep_name) && ! empty($val->rep_email) && $user != '') {

            $data['legacy_data_id'] = isset($val->id) ? $val->id : null;
            $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            $data['page'] = isset($lid['pageid']) ? $lid['pageid'] : null;
            $data['pid'] = isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = isset($val->customer_email) ? $val->customer_email : null;
            $data['customer_phone'] = isset($val->customer_phone) ? $val->customer_phone : null;
            $data['setter_id'] = isset($val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = isset($val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = isset($val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = isset($val->rep_email) ? $val->rep_email : null;
            $data['install_partner'] = isset($val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = isset($val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = isset($val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['m1_date'] = isset($val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = isset($val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['scheduled_install'] = isset($val->scheduled_install) ? $val->scheduled_install : null;
            // $data['m2_date'] = isset($val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['m2_date'] = isset($val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = isset($val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $data['date_cancelled'] = isset($val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = isset($val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            // $data['return_sales_date'] = isset($val->return_sales_date) ? $val->return_sales_date : null;
            $data['return_sales_date'] = null;
            $data['gross_account_value'] = isset($val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = isset($val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = isset($val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = isset($val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            $data['adders'] = isset($val->adders) ? $val->adders : null;
            $data['cancel_fee'] = isset($val->cancel_fee) ? $val->cancel_fee : null;
            $data['adders_description'] = isset($val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = isset($val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = isset($val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = isset($val->financing_term) ? $val->financing_term : null;
            $data['product'] = isset($val->product) ? $val->product : null;
            $data['epc'] = isset($val->epc) ? $val->epc : null;
            $data['net_epc'] = isset($val->net_epc) ? $val->net_epc : null;
            $inserted = LegacyApiRowData::create($data);
        } else {

            // Insert null data in table for alert admin...............................................
            $data['legacy_data_id'] = isset($val->id) ? $val->id : null;
            $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            $data['pid'] = isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = isset($val->customer_email) ? $val->customer_email : null;
            $data['customer_phone'] = isset($val->customer_phone) ? $val->customer_phone : null;
            $data['setter_id'] = isset($val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = isset($val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = isset($val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = isset($val->rep_email) ? $val->rep_email : null;
            $data['install_partner'] = isset($val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = isset($val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = isset($val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['m1_date'] = isset($val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = isset($val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['scheduled_install'] = isset($val->scheduled_install) ? $val->scheduled_install : null;
            // $data['m2_date'] = isset($val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['m2_date'] = isset($val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = isset($val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $data['date_cancelled'] = isset($val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = isset($val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            // $data['return_sales_date'] = isset($val->return_sales_date) ? $val->return_sales_date : null;
            $data['return_sales_date'] = null;
            $data['gross_account_value'] = isset($val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = isset($val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = isset($val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = isset($val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            $data['adders'] = isset($val->adders) ? $val->adders : null;
            $data['adders_description'] = isset($val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = isset($val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = isset($val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = isset($val->financing_term) ? $val->financing_term : null;
            $data['product'] = isset($val->product) ? $val->product : null;
            $data['epc'] = isset($val->epc) ? $val->epc : null;
            $data['net_epc'] = isset($val->net_epc) ? $val->net_epc : null;
            $inserted = LegacyApiNullData::create($data);
        }
    }

    public function subroutineThree($val)
    {
        $closerId = $val->salesMasterProcess->closer1_id;
        $closer2Id = $val->salesMasterProcess->closer2_id;
        $setterId = $val->salesMasterProcess->setter1_id;
        $setter2Id = $val->salesMasterProcess->setter2_id;
        $m1date = $val->m1_date;
        $customer_signoff = $val->customer_signoff;
        $kw = $val->kw;
        $pid = $val->pid;
        $companyProfile = CompanyProfile::first();
        $commission = $this->upfrontTypePercentCalculation($val);

        $updateData = SaleMasterProcess::where('pid', $val->pid)->first();
        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $closerUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();
            $upfrontAmount = '';
            $upfrontType = '';
            $redLine = '';

            $isM2Paid1 = false;
            $m2 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
            if ($m2) {
                if ($m2->settlement_type == 'during_m2') {
                    if ($m2->status == '3') {
                        $isM2Paid1 = true;
                    }
                } elseif ($m2->settlement_type == 'reconciliation') {
                    if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                        $isM2Paid1 = true;
                    }
                }
            } else {
                $withheld = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                if ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $isM2Paid1 = true;
                    }
                }
            }
            if ($closerUpfront) {
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;

                    $redLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                    $redLine = @$redLineHistory->redline;
                    $subPositionId = @$userOrganizationHistory['sub_position_id'];
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;

                    $redLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                    $redLine = @$redLineHistory->redline;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $stop2Payroll = ($closer2->stop_payroll == 1) ? 1 : 0;
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
            $closer2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
            $upfrontAmount2 = '';
            $upfrontType2 = '';
            $redLine2 = '';

            $isM2Paid2 = false;
            $m2 = UserCommission::where(['user_id' => $closer2Id, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
            if ($m2) {
                if ($m2->settlement_type == 'during_m2') {
                    if ($m2->status == '3') {
                        $isM2Paid2 = true;
                    }
                } elseif ($m2->settlement_type == 'reconciliation') {
                    if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                        $isM2Paid2 = true;
                    }
                }
            } else {
                $withheld = UserCommission::where(['user_id' => $closer2Id, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                if ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $isM2Paid2 = true;
                    }
                }
            }
            if ($closer2Upfront) {
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;

                    $redLineHistory = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                    $redLine2 = @$redLineHistory->redline;
                    $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
                } else {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType2 = @$upfrontHistory->upfront_sale_type;

                    $redLineHistory = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                    $redLine2 = @$redLineHistory->redline;
                }
            }

            if (! empty($closerUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
                $amount = 0;
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } elseif ($upfrontType == 'percent') {
                        $amount = ($commission['closer_commission'] * ($upfrontAmount / 100));
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } elseif ($upfrontType == 'percent') {
                        $amount = ($commission['closer_commission'] * ($upfrontAmount / 100));
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }
                }

                if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                    $amount = $closerUpfront->upfront_limit;
                }

                $payFrequency = $this->payFrequencyNew($m1date, $subPositionId, $closerId);
                $data = [
                    'user_id' => $closerId,
                    'position_id' => $subPositionId,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                    'redline' => $redLine,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                if (! $isM2Paid1) {
                    $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if ($m1) {
                        if ($m1->settlement_type == 'during_m2') {
                            if ($m1->status == '1') {
                                $m1->update($data);
                                $updateData->closer1_m1 = $amount;
                                $updateData->closer1_m1_paid_status = 4;
                            }
                        } elseif ($m1->settlement_type == 'reconciliation') {
                            if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                $isUpdate = true;
                                if ($m1->recon_status == '2') {
                                    $paidRecon = ReconCommissionHistory::where(['user_id' => $closerId, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                    $updateData->closer1_m1 = $amount;
                                    $updateData->closer1_m1_paid_status = 4;
                                }
                            }
                        }
                    } else {
                        UserCommission::create($data);
                        $this->updateCommission($closerId, $subPositionId, $amount, $m1date);
                        $updateData->closer1_m1 = $amount;
                        $updateData->closer1_m1_paid_status = 4;
                    }
                }
            } else {
                if (! $isM2Paid1) {
                    $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if ($m1) {
                        $isDelete = false;
                        if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                            $isDelete = true;
                        } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                            $isDelete = true;
                        }

                        if ($isDelete) {
                            $m1->delete();
                            $updateData->closer1_m1 = 0;
                            $updateData->closer1_m1_paid_status = 4;
                        }
                    }
                }
            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
                $amount2 = 0;
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } elseif ($upfrontType2 == 'percent') {
                        $amount2 = ($commission['closer2_commission'] * ($upfrontAmount2 / 100));
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } elseif ($upfrontType2 == 'percent') {
                        $amount2 = ($commission['closer2_commission'] * ($upfrontAmount2 / 100));
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }
                }

                if (! empty($closer2Upfront->upfront_limit) && $amount2 > $closer2Upfront->upfront_limit) {
                    $amount2 = $closer2Upfront->upfront_limit;
                }

                $payFrequency = $this->payFrequencyNew($m1date, $subPositionId2, $closer2Id);
                $data = [
                    'user_id' => $closer2Id,
                    'position_id' => $subPositionId2,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                    'redline' => $redLine2,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stop2Payroll,
                ];

                if (! $isM2Paid2) {
                    $m1 = UserCommission::where(['user_id' => $closer2Id, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if ($m1) {
                        if ($m1->settlement_type == 'during_m2') {
                            if ($m1->status == '1') {
                                $m1->update($data);
                                $updateData->closer2_m1 = $amount;
                                $updateData->closer2_m1_paid_status = 4;
                            }
                        } elseif ($m1->settlement_type == 'reconciliation') {
                            if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                $isUpdate = true;
                                if ($m1->recon_status == '2') {
                                    $paidRecon = ReconCommissionHistory::where(['user_id' => $closer2Id, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                    $updateData->closer2_m1 = $amount;
                                    $updateData->closer2_m1_paid_status = 4;
                                }
                            }
                        }
                    } else {
                        UserCommission::create($data);
                        $this->updateCommission($closer2Id, $subPositionId, $amount, $m1date);
                        $updateData->closer2_m1 = $amount;
                        $updateData->closer2_m1_paid_status = 4;
                    }
                }
            } else {
                if (! $isM2Paid2) {
                    $m1 = UserCommission::where(['user_id' => $closer2Id, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                    if ($m1) {
                        $isDelete = false;
                        if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                            $isDelete = true;
                        } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                            $isDelete = true;
                        }

                        if ($isDelete) {
                            $m1->delete();
                            $updateData->closer2_m1 = 0;
                            $updateData->closer2_m1_paid_status = 4;
                        }
                    }
                }
            }
        } elseif ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();

            $isM2Paid = false;
            $m2 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
            if ($m2) {
                if ($m2->settlement_type == 'during_m2') {
                    if ($m2->status == '3') {
                        $isM2Paid = true;
                    }
                } elseif ($m2->settlement_type == 'reconciliation') {
                    if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                        $isM2Paid = true;
                    }
                }
            } else {
                $withheld = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                if ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $isM2Paid = true;
                    }
                }
            }
            // IN THE CASE OF SELF-GEN THERE SHOULD BE RESTRICTION ONLY ON PRIMARY POSITION
            if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == '1') {
                $primaryUpfront = PositionCommissionUpfronts::where(['position_id' => $userOrganizationHistory->sub_position_id, 'upfront_status' => 1])->first();
                $amount1 = 0;
                if ($primaryUpfront) {
                    $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                        ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                    $upfrontType = @$upfrontHistory->upfront_sale_type;

                    if ($upfrontAmount) {
                        if ($upfrontType == 'per sale') {
                            $amount1 = $upfrontAmount;
                        } elseif ($upfrontType == 'percent') {
                            $amount1 = $commission['closer_commission'] * ($upfrontAmount / 100);
                        } else {
                            $amount1 = ($upfrontAmount * $kw);
                        }
                    }
                }

                $amount2 = 0;
                $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                    ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                $selfUpFrontAmount = @$upfrontHistory->upfront_pay_amount;
                $selfUpFrontType = @$upfrontHistory->upfront_sale_type;

                if ($selfUpFrontAmount) {
                    if ($selfUpFrontType == 'per sale') {
                        $amount2 = $selfUpFrontAmount;
                    } elseif ($selfUpFrontType == 'percent') {
                        $amount2 = $commission['closer_commission'] * ($selfUpFrontAmount / 100);
                    } else {
                        $amount2 = ($selfUpFrontAmount * $kw);
                    }
                }

                $upfrontSetting = upfrontSystemSetting::first();
                if ($upfrontSetting && $upfrontSetting->upfront_for_self_gen == 'Pay sum of setter and closer upfront') {
                    $amount = $amount1 + $amount2;
                } else {
                    $amount = max($amount1, $amount2);
                }

                $subPositionId = $userOrganizationHistory->sub_position_id;
                if ($amount) {
                    $redLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                    $selfGenRedLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                    $redLine = null;
                    if ($redLineHistory && $selfGenRedLineHistory) {
                        $redLine = min($redLineHistory->redline, $selfGenRedLineHistory->redline);
                    } elseif ($redLineHistory) {
                        $redLine = $redLineHistory->redline;
                    } elseif ($selfGenRedLineHistory) {
                        $redLine = $selfGenRedLineHistory->redline;
                    }

                    $payFrequency = $this->payFrequencyNew($m1date, $subPositionId, $closerId);
                    $data = [
                        'user_id' => $closerId,
                        'position_id' => $subPositionId,
                        'pid' => $val->pid,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                        'redline' => $redLine,
                        'date' => $m1date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $customer_signoff,
                        'is_stop_payroll' => $stopPayroll,
                    ];

                    if (! $isM2Paid) {
                        $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            if ($m1->settlement_type == 'during_m2') {
                                if ($m1->status == '1') {
                                    $m1->update($data);
                                    $updateData->closer1_m1 = $amount;
                                    $updateData->closer1_m1_paid_status = 4;
                                }
                            } elseif ($m1->settlement_type == 'reconciliation') {
                                if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                    $isUpdate = true;
                                    if ($m1->recon_status == '2') {
                                        $paidRecon = ReconCommissionHistory::where(['user_id' => $closerId, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                        $updateData->closer1_m1 = $amount;
                                        $updateData->closer1_m1_paid_status = 4;
                                    }
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommission($closerId, $subPositionId, $amount, $m1date);
                            $updateData->closer1_m1 = $amount;
                            $updateData->closer1_m1_paid_status = 4;
                        }
                    }
                } else {
                    if (! $isM2Paid) {
                        $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            $isDelete = false;
                            if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                $isDelete = true;
                            } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                $isDelete = true;
                            }

                            if ($isDelete) {
                                $m1->delete();
                                $updateData->closer1_m1 = 0;
                                $updateData->closer1_m1_paid_status = 4;
                            }
                        }
                    }
                }
            } else {
                $closerUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();
                if ($closerUpfront) {
                    $subPositionId = @$userOrganizationHistory['sub_position_id'];
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                        $redLine = @$redLineHistory->redline;
                        $subPositionId = @$userOrganizationHistory['sub_position_id'];
                    } else {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                        $redLine = @$redLineHistory->redline;
                    }

                    if ($upfrontAmount && $upfrontType) {
                        $amount = 0;
                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                            if ($upfrontType == 'per sale') {
                                $amount = $upfrontAmount;
                            } elseif ($upfrontType == 'percent') {
                                $amount = $commission['closer_commission'] * ($upfrontAmount / 100);
                            }
                        } else {
                            if ($upfrontType == 'per sale') {
                                $amount = $upfrontAmount;
                            } elseif ($upfrontType == 'percent') {
                                $amount = $commission['closer_commission'] * ($upfrontAmount / 100);
                            } else {
                                $amount = ($upfrontAmount * $kw);
                            }
                        }

                        if (! empty($closerUpfront->upfront_limit) && $amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        }

                        $payFrequency = $this->payFrequencyNew($m1date, $subPositionId, $closerId);
                        $data = [
                            'user_id' => $closerId,
                            'position_id' => $subPositionId,
                            'pid' => $val->pid,
                            'amount_type' => 'm1',
                            'amount' => $amount,
                            'redline' => $redLine,
                            'date' => $m1date,
                            'pay_period_from' => $payFrequency->pay_period_from,
                            'pay_period_to' => $payFrequency->pay_period_to,
                            'customer_signoff' => $customer_signoff,
                            'is_stop_payroll' => $stopPayroll,
                        ];

                        if (! $isM2Paid) {
                            $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                            if ($m1) {
                                if ($m1->settlement_type == 'during_m2') {
                                    if ($m1->status == '1') {
                                        $m1->update($data);
                                        $updateData->closer1_m1 = $amount;
                                        $updateData->closer1_m1_paid_status = 4;
                                    }
                                } elseif ($m1->settlement_type == 'reconciliation') {
                                    if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                        $isUpdate = true;
                                        if ($m1->recon_status == '2') {
                                            $paidRecon = ReconCommissionHistory::where(['user_id' => $closerId, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                            $updateData->closer1_m1 = $amount;
                                            $updateData->closer1_m1_paid_status = 4;
                                        }
                                    }
                                }
                            } else {
                                UserCommission::create($data);
                                $this->updateCommission($closerId, $subPositionId, $amount, $m1date);
                                $updateData->closer1_m1 = $amount;
                                $updateData->closer1_m1_paid_status = 4;
                            }
                        }
                    } else {
                        if (! $isM2Paid) {
                            $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                            if ($m1) {
                                $isDelete = false;
                                if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                    $isDelete = true;
                                } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                    $isDelete = true;
                                }

                                if ($isDelete) {
                                    $m1->delete();
                                    $updateData->closer1_m1 = 0;
                                    $updateData->closer1_m1_paid_status = 4;
                                }
                            }
                        }
                    }
                } else {
                    if (! $isM2Paid) {
                        $m1 = UserCommission::where(['user_id' => $closerId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            $isDelete = false;
                            if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                $isDelete = true;
                            } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                $isDelete = true;
                            }

                            if ($isDelete) {
                                $m1->delete();
                                $updateData->closer1_m1 = 0;
                                $updateData->closer1_m1_paid_status = 4;
                            }
                        }
                    }
                }
            }
        }

        if (! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($setterId != null && $setter2Id != null) {
                $setter = User::where('id', $setterId)->first();
                $stopPayroll = ($setter->stop_payroll == 1) ? 1 : 0;
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                $subPositionId = @$userOrganizationHistory['sub_position_id'];
                $setterUpfront = PositionCommissionUpfronts::where('position_id', $subPositionId)->where('upfront_status', 1)->first();
                $upfrontAmount = '';
                $upfrontType = '';
                $redLine = '';

                $isM2Paid1 = false;
                $m2 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $isM2Paid1 = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $isM2Paid1 = true;
                        }
                    }
                } else {
                    $withheld = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                    if ($withheld) {
                        if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                            $isM2Paid1 = true;
                        }
                    }
                }
                if ($setterUpfront) {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                        $redLine = @$redLineHistory->redline;
                        $subPositionId = @$userOrganizationHistory['sub_position_id'];
                    } else {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                        $redLine = @$redLineHistory->redline;
                    }
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $stop2Payroll = ($setter2->stop_payroll == 1) ? 1 : 0;
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
                $setter2Upfront = PositionCommissionUpfronts::where('position_id', $subPositionId2)->where('upfront_status', 1)->first();
                $upfrontAmount2 = '';
                $upfrontType2 = '';
                $redLine2 = '';

                $isM2Paid2 = false;
                $m2 = UserCommission::where(['user_id' => $setter2Id, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $isM2Paid2 = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $isM2Paid2 = true;
                        }
                    }
                } else {
                    $withheld = UserCommission::where(['user_id' => $setter2Id, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                    if ($withheld) {
                        if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                            $isM2Paid2 = true;
                        }
                    }
                }
                if ($setter2Upfront) {
                    if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType2 = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                        $redLine2 = @$redLineHistory->redline;
                        $subPositionId2 = @$userOrganizationHistory['sub_position_id'];
                    } else {
                        $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '0'])
                            ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfrontAmount2 = @$upfrontHistory->upfront_pay_amount;
                        $upfrontType2 = @$upfrontHistory->upfront_sale_type;

                        $redLineHistory = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                        $redLine2 = @$redLineHistory->redline;
                    }
                }

                if (! empty($setterUpfront) && ! empty($upfrontAmount) && ! empty($upfrontType)) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } elseif ($upfrontType == 'percent') {
                        $amount = ($commission['setter_commission'] * ($upfrontAmount / 100));
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }

                    if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                        $amount = $setterUpfront->upfront_limit;
                    }

                    $payFrequency = $this->payFrequencyNew($m1date, $subPositionId, $setterId);
                    $data = [
                        'user_id' => $setterId,
                        'position_id' => $subPositionId,
                        'pid' => $val->pid,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                        'redline' => $redLine,
                        'date' => $m1date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $customer_signoff,
                        'is_stop_payroll' => $stopPayroll,
                    ];

                    if (! $isM2Paid1) {
                        $m1 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            if ($m1->settlement_type == 'during_m2') {
                                if ($m1->status == '1') {
                                    $m1->update($data);
                                    $updateData->setter1_m1 = $amount;
                                    $updateData->setter1_m1_paid_status = 4;
                                }
                            } elseif ($m1->settlement_type == 'reconciliation') {
                                if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                    $isUpdate = true;
                                    if ($m1->recon_status == '2') {
                                        $paidRecon = ReconCommissionHistory::where(['user_id' => $setterId, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                        $updateData->setter1_m1 = $amount;
                                        $updateData->setter1_m1_paid_status = 4;
                                    }
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommission($setterId, $subPositionId, $amount, $m1date);
                            $updateData->setter1_m1 = $amount;
                            $updateData->setter1_m1_paid_status = 4;
                        }
                    }
                } else {
                    if (! $isM2Paid1) {
                        $m1 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            $isDelete = false;
                            if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                $isDelete = true;
                            } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                $isDelete = true;
                            }

                            if ($isDelete) {
                                $m1->delete();
                                $updateData->setter1_m1 = 0;
                                $updateData->setter1_m1_paid_status = 4;
                            }
                        }
                    }
                }

                if (! empty($setter2Upfront) && ! empty($upfrontAmount2) && ! empty($upfrontType2)) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } elseif ($upfrontType2 == 'percent') {
                        $amount2 = ($commission['setter2_commission'] * ($upfrontAmount2 / 100));
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }

                    if (! empty($setter2Upfront->upfront_limit) && $amount2 > $setter2Upfront->upfront_limit) {
                        $amount2 = $setter2Upfront->upfront_limit;
                    }

                    $payFrequency = $this->payFrequencyNew($m1date, $subPositionId2, $setter2Id);
                    $data = [
                        'user_id' => $setter2Id,
                        'position_id' => $subPositionId2,
                        'pid' => $val->pid,
                        'amount_type' => 'm1',
                        'amount' => $amount2,
                        'redline' => $redLine2,
                        'date' => $m1date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $customer_signoff,
                        'is_stop_payroll' => $stop2Payroll,
                    ];

                    if (! $isM2Paid2) {
                        $m1 = UserCommission::where(['user_id' => $setter2Id, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            if ($m1->settlement_type == 'during_m2') {
                                if ($m1->status == '1') {
                                    $m1->update($data);
                                    $updateData->setter2_m1 = $amount;
                                    $updateData->setter2_m1_paid_status = 4;
                                }
                            } elseif ($m1->settlement_type == 'reconciliation') {
                                if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                    $isUpdate = true;
                                    if ($m1->recon_status == '2') {
                                        $paidRecon = ReconCommissionHistory::where(['user_id' => $setter2Id, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                        $updateData->setter2_m1 = $amount;
                                        $updateData->setter2_m1_paid_status = 4;
                                    }
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommission($setter2Id, $subPositionId, $amount, $m1date);
                            $updateData->setter2_m1 = $amount;
                            $updateData->setter2_m1_paid_status = 4;
                        }
                    }
                } else {
                    if (! $isM2Paid2) {
                        $m1 = UserCommission::where(['user_id' => $setter2Id, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                        if ($m1) {
                            $isDelete = false;
                            if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                $isDelete = true;
                            } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                $isDelete = true;
                            }

                            if ($isDelete) {
                                $m1->delete();
                                $updateData->setter2_m1 = 0;
                                $updateData->setter2_m1_paid_status = 4;
                            }
                        }
                    }
                }
            } elseif ($setterId) {
                $setter = User::where('id', $setterId)->first();
                if ($setter && $setterId != $closerId) {
                    $stopPayroll = ($setter->stop_payroll == 1) ? 1 : 0;
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                    $setterUpfront = PositionCommissionUpfronts::where('position_id', @$userOrganizationHistory->sub_position_id)->where('upfront_status', 1)->first();

                    $isM2Paid = false;
                    $m2 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                    if ($m2) {
                        if ($m2->settlement_type == 'during_m2') {
                            if ($m2->status == '3') {
                                $isM2Paid = true;
                            }
                        } elseif ($m2->settlement_type == 'reconciliation') {
                            if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                                $isM2Paid = true;
                            }
                        }
                    } else {
                        $withheld = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                        if ($withheld) {
                            if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                                $isM2Paid = true;
                            }
                        }
                    }

                    if ($setterUpfront) {
                        $subPositionId = @$userOrganizationHistory['sub_position_id'];
                        if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                            $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])
                                ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                            $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                            $upfrontType = @$upfrontHistory->upfront_sale_type;

                            $redLineHistory = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '1')->orderBy('start_date', 'DESC')->first();
                            $redLine = @$redLineHistory->redline;
                            $subPositionId = @$userOrganizationHistory['sub_position_id'];
                        } else {
                            $upfrontHistory = UserUpfrontHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])
                                ->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                            $upfrontAmount = @$upfrontHistory->upfront_pay_amount;
                            $upfrontType = @$upfrontHistory->upfront_sale_type;

                            $redLineHistory = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $customer_signoff)->where('self_gen_user', '0')->orderBy('start_date', 'DESC')->first();
                            $redLine = @$redLineHistory->redline;
                        }

                        if ($upfrontAmount && $upfrontType) {
                            if ($upfrontType == 'per sale') {
                                $amount = $upfrontAmount;
                            } elseif ($upfrontType == 'percent') {
                                $amount = $commission['setter_commission'] * ($upfrontAmount / 100);
                            } else {
                                $amount = ($upfrontAmount * $kw);
                            }

                            if (! empty($setterUpfront->upfront_limit) && $amount > $setterUpfront->upfront_limit) {
                                $amount = $setterUpfront->upfront_limit;
                            }

                            $payFrequency = $this->payFrequencyNew($m1date, $subPositionId, $setterId);
                            $data = [
                                'user_id' => $setterId,
                                'position_id' => $subPositionId,
                                'pid' => $val->pid,
                                'amount_type' => 'm1',
                                'amount' => $amount,
                                'redline' => $redLine,
                                'date' => $m1date,
                                'pay_period_from' => $payFrequency->pay_period_from,
                                'pay_period_to' => $payFrequency->pay_period_to,
                                'customer_signoff' => $customer_signoff,
                                'is_stop_payroll' => $stopPayroll,
                            ];

                            if (! $isM2Paid) {
                                $m1 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                                if ($m1) {
                                    if ($m1->settlement_type == 'during_m2') {
                                        if ($m1->status == '1') {
                                            $m1->update($data);
                                            $updateData->setter1_m1 = $amount;
                                            $updateData->setter1_m1_paid_status = 4;
                                        }
                                    } elseif ($m1->settlement_type == 'reconciliation') {
                                        if ($m1->recon_status == '1' || $m1->recon_status == '2') {
                                            $isUpdate = true;
                                            if ($m1->recon_status == '2') {
                                                $paidRecon = ReconCommissionHistory::where(['user_id' => $setterId, 'pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
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
                                                $updateData->setter1_m1 = $amount;
                                                $updateData->setter1_m1_paid_status = 4;
                                            }
                                        }
                                    }
                                } else {
                                    UserCommission::create($data);
                                    $this->updateCommission($setterId, $subPositionId, $amount, $m1date);
                                    $updateData->setter1_m1 = $amount;
                                    $updateData->setter1_m1_paid_status = 4;
                                }
                            }
                        }
                    } else {
                        if (! $isM2Paid) {
                            $m1 = UserCommission::where(['user_id' => $setterId, 'pid' => $val->pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->first();
                            if ($m1) {
                                $isDelete = false;
                                if ($m1->settlement_type == 'during_m2' && $m1->status == '1') {
                                    $isDelete = true;
                                } elseif ($m1->settlement_type == 'reconciliation' && $m1->recon_status == '1') {
                                    $isDelete = true;
                                }

                                if ($isDelete) {
                                    $m1->delete();
                                    $updateData->setter1_m1 = 0;
                                    $updateData->setter1_m1_paid_status = 4;
                                }
                            }
                        }
                    }
                } else {
                    $updateData->setter1_m1 = 0;
                }
            }
        }
        $updateData->save();
    }

    public function subroutineFour($checked)
    {
        // echo"subroutineFour";die;
        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();

        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting
        $positionOverrideCloser = PositionOverride::where('position_id', 2)->first();

        if ($positionOverrideCloser->settlement_id == 1) {

            $closer1_id = $saleMasterProcess->closer1_id;
            $closer2_id = $saleMasterProcess->closer2_id;
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer1_id)->where('status', 'pending')->sum('withhold_amount');
            }
            if ($closer2_id) {

                $closer2ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer2_id)->where('status', 'pending')->sum('withhold_amount');
            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);
        } else {

            // $sattlement_type = "During M2";
            $closer1_id = $saleMasterProcess->closer1_id;
            $closer2_id = $saleMasterProcess->closer2_id;
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer1_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer1_id', $closer1_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->closer1_m1 = 0;
                $saleMasterProcess->closer1_m2 = 0;
                $saleMasterProcess->save();
            }
            if ($closer2_id) {

                $closer2ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer2_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer2_id', $closer2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->closer2_m1 = 0;
                $saleMasterProcess->closer2_m2 = 0;
                $saleMasterProcess->save();
            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);
        }
        // for setter
        $positionOverrideSettlementSetter = PositionOverride::where('position_id', 3)->first();
        if ($positionOverrideSettlementSetter->settlement_id == 1) {

            $setter1_id = $saleMasterProcess->setter1_id;
            $setter2_id = $saleMasterProcess->setter2_id;

            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $setter1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'pending')->sum('withhold_amount');
            }
            if ($setter2_id) {

                $setter2ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter2_id)->where('status', 'pending')->sum('withhold_amount');
            }
            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);
        } else {
            // $sattlement_type = "During M2";
            $setter1_id = $saleMasterProcess->setter1_id;
            $setter2_id = $saleMasterProcess->setter2_id;

            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $setter1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter1_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->setter1_m1 = 0;
                $saleMasterProcess->setter1_m2 = 0;
                $saleMasterProcess->save();
            }
            if ($setter2_id) {

                $setter2ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter2_id)->where('status', 'pending')->sum('withhold_amount');
                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->setter2_m1 = 0;
                $saleMasterProcess->setter2_m2 = 0;
                $saleMasterProcess->save();
            }

            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);
        }

        $total_m1_m2_amount = $total_closers_ReconciliationWithholding_amount + $total_setters_ReconciliationWithholding_amount;

        $backendSetting = BackendSetting::first();
        if ($backendSetting) {

            $maximum_withheld = $backendSetting->maximum_withheld;
            if ($total_m1_m2_amount <= $maximum_withheld) {
                $total_deduct = $maximum_withheld - $total_m1_m2_amount;
            } else {
                $total_deduct = $maximum_withheld - $total_m1_m2_amount;
            }

            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
            // System adds total of clawback (deduction as a negative amount) to reconciliation
            // $saleMasterProcess->mark_account_status_id = 1;
            $saleMasterProcess->save();

            $userReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->first();
            if ($userReconciliationWithholding) {
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $userReconciliationWithholding->status = 'Clawed Back';
                $userReconciliationWithholding->save();
            }
        }

        return 'Data';
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

        $date = $checked->date_cancelled;
        $approvedDate = isset($checked->customer_signoff) ? $checked->customer_signoff : null;
        $pid = $checked->pid;
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $saleUsers = [];
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

        UserCommission::where(['pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->delete();
        UserCommission::where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->whereIn('user_id', $saleUsers)->delete();

        $userCommissions = UserCommission::with('userdata')->where(['pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closer->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => '1', 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
                $pay_period_from = null;
                $pay_period_to = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                $pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
            $commission = $userCommission->amount;
            $clawbackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawbackAmount,
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'during' => $during,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'is_stop_payroll' => $stopPayroll,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($userCommission->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        }

        $userCommissions = UserCommission::with('userdata')->where(['pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closer->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => '1', 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
                $pay_period_from = null;
                $pay_period_to = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                $pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $clawbackAmount = 0;
            $reconPaid = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $userCommission->user_id, 'type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if ($reconPaid) {
                $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
                $commission = $reconPaid;
                $clawbackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');
            } else {
                $userCommission->delete();
            }

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawbackAmount,
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'during' => $during,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'is_stop_payroll' => $stopPayroll,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($userCommission->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        }
        $this->overides_clawback($pid, $date);

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $saleMasterProcess->mark_account_status_id = 1;
        $saleMasterProcess->save();
    }

    public function subroutineSix($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // $customerRedline = $checked->redline;
        $approvedDate = $checked->customer_signoff;
        // $saleState = $checked->customer_state;

        if (config('app.domain_name') == 'flex') {
            $saleState = $checked->customer_state;
        } else {
            $saleState = $checked->location_code;
        }

        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = $generalCode->redline_standard;
            }
        } else {
            // customer state Id..................................................
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

        if ($approvedDate != null) {
            $data['closer1_redline'] = '0';
            $data['closer1_redline_type'] = '';
            $data['closer2_redline'] = '0';
            $data['closer2_redline_type'] = '';
            $data['setter1_redline'] = '0';
            $data['setter1_redline_type'] = '';
            $data['setter2_redline'] = '0';
            $data['setter2_redline_type'] = '';

            if ($setterId && $setter2Id) {
                $setter1 = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $setterRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $setterRedLine = $setter1->redline;
                    $redLineAmountType = $setter1->redline_amount_type;
                }

                $setterOfficeId = $setter1->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter1_redline'] = $setterRedLine;
                    $data['setter1_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $setterOfficeId = $userTransferHistory->office_id;
                    }

                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter1_redline'] = $redLine;
                    $data['setter1_redline_type'] = 'Shift Based on Location';
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $setterRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $setterRedLine = $setter2->redline;
                    $redLineAmountType = $setter2->redline_amount_type;
                }

                $setterOfficeId = $setter2->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter2_redline'] = $setterRedLine;
                    $data['setter2_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $setter2Id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $setterOfficeId = $userTransferHistory->office_id;
                    }

                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter2_redline'] = $redLine;
                    $data['setter2_redline_type'] = 'Shift Based on Location';
                }
            } elseif ($setterId) {
                $setter = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == 1) {
                    if ($userOrganizationHistory->position_id == '3') {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $setterRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $setterRedLine = $setter->redline;
                        $redLineAmountType = $setter->redline_amount_type;
                    }
                } else {
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $setterRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $setterRedLine = $setter->redline;
                        $redLineAmountType = $setter->redline_amount_type;
                    }
                }

                $setterOfficeId = $setter->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter1_redline'] = $setterRedLine;
                    $data['setter1_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $setterOfficeId = $userTransferHistory->office_id;
                    }

                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter1_redline'] = $redLine;
                    $data['setter1_redline_type'] = 'Shift Based on Location';
                }
            }

            if ($closerId && $closer2Id) {
                $closer1 = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $closerRedLine = $closer1->redline;
                    $redLineAmountType = $closer1->redline_amount_type;
                }

                $closerOfficeId = $closer1->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer1_redline'] = $closerRedLine;
                    $data['closer1_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }

                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer1_redline'] = $redLine;
                    $data['closer1_redline_type'] = 'Shift Based on Location';
                }

                $closer2 = User::where('id', $closer2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $closerRedLine = $closer2->redline;
                    $redLineAmountType = $closer2->redline_amount_type;
                }

                $closerOfficeId = $closer2->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer2_redline'] = $closerRedLine;
                    $data['closer2_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $closer2Id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }

                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer2_redline'] = $redLine;
                    $data['closer2_redline_type'] = 'Shift Based on Location';
                }
            } elseif ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($userOrganizationHistory->position_id == '3') {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $closerRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $closerRedLine = $closer->redline;
                        $redLineAmountType = $closer->redline_amount_type;
                    }
                } else {
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $closerRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $closerRedLine = $closer->redline;
                        $redLineAmountType = $closer->redline_amount_type;
                    }
                }

                $closerOfficeId = $closer->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer1_redline'] = $closerRedLine;
                    $data['closer1_redline_type'] = 'Fixed';
                } else {
                    $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($userTransferHistory) {
                        $closerOfficeId = $userTransferHistory->office_id;
                    }

                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer1_redline'] = $redLine;
                    $data['closer1_redline_type'] = 'Shift Based on Location';
                }

                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                    $redLine1 = $data['setter1_redline'];
                    $redLine2 = $data['closer1_redline'];
                    if ($redLine1 > $redLine2) {
                        $data['closer1_redline'] = $redLine2;
                        $data['closer1_redline_type'] = $data['closer1_redline_type'];
                    } else {
                        $data['closer1_redline'] = $redLine1;
                        $data['closer1_redline_type'] = $data['setter1_redline_type'];
                    }
                }
            }

            return $data;
        }
    }

    public function subroutineEight($checked)
    {
        $companyProfile = CompanyProfile::where('id', 1)->first();
        $commission = [
            'closer_commission' => 0,
            'setter_commission' => 0,
        ];

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $commission = $this->subroutineEightForFlex($checked);
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $commission = $this->subroutineEightForPest($checked);
        }

        return $commission;
    }

    public function subroutineEightForFlex($checked)
    {
        $this->commissionData = [
            'setter_commission' => 0,
            'setter_commission_type' => null,
            'setter2_commission' => 0,
            'setter2_commission_type' => null,
            'closer_commission' => 0,
            'closer_commission_type' => null,
            'closer2_commission' => 0,
            'closer2_commission_type' => null,
        ];

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $approvedDate = $checked->customer_signoff;

        $saleUsers = [];
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

        if (! $setterId) {
            return [
                'closer_commission' => 0,
                'setter_commission' => 0,
            ];
        }

        $companyMargin = CompanyProfile::first();
        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        $updateData = SaleMasterProcess::where('pid', $checked->pid)->first();
        if ($setterId && $setter2Id) {
            $setter = User::where('id', $setterId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $setter = $organizationHistory;
            }
            if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                $commission_percentage = 0;
                $commission_type = null;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0;
                $commission_type = null;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $setter2 = $organizationHistory2;
            }
            if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);

                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $setter1_commission = $commission_percentage * $x * 0.5;
                } else {
                    $setter1_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $setter2_commission = $commission_percentage2 * $x * 0.5;
                } else {
                    $setter2_commission = ((($netEpc - $redline['setter2_redline']) * $x) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $setter1_commission = $commission_percentage * 0.5;
                } else {
                    $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $setter2_commission = $commission_percentage2 * 0.5;
                } else {
                    $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            }

            // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();

            $updateData->setter1_commission = $setter1_commission;
            $updateData->setter2_commission = $setter2_commission;
            $updateData->mark_account_status_id = 3;

            // if (!$paidM2) {
            //     $this->updateCommission($setterId, 3, $setter1_commission, $m2date);
            //     $this->updateCommission($setter2Id, 3, $setter2_commission, $m2date);
            // }
            $setter_commission = ($setter1_commission + $setter2_commission);
        } elseif ($setterId) {
            if ($closerId != $setterId) {
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory && $organizationHistory->self_gen_accounts == 1 && $organizationHistory->position_id == 2) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $setter_commission = (($kw * $commission_percentage) * $x);
                    } elseif ($commission_type == 'per sale') {
                        $setter_commission = $commission_percentage * $x;
                    } else {
                        $setter_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $setter_commission = ($kw * $commission_percentage);
                    } elseif ($commission_type == 'per sale') {
                        $setter_commission = $commission_percentage;
                    } else {
                        $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);
                    }
                }

                // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
                $updateData->setter1_commission = $setter_commission;
                $updateData->mark_account_status_id = 3;
                // if (!$paidM2) {
                //     $this->updateCommission($setterId, 3, $setter_commission, $m2date);
                // }
            } else {
                $updateData->setter1_commission = 0;
                $updateData->mark_account_status_id = 3;
            }
        }

        $closer_commission = 0;
        $setter_commission = 0;
        if ($closerId && $closer2Id) {
            $closer = User::where('id', $closerId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                $commission_percentage = 0;
                $commission_type = null;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0; // percenge
                $commission_type = null;
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $closer2 = $organizationHistory2;
            }
            if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0; // percenge
                $commission_type2 = null;
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $closer1_commission = $commission_percentage * $x * 0.5;
                } else {
                    $closer1_commission = (((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $closer2_commission = $commission_percentage2 * $x * 0.5;
                } else {
                    $closer2_commission = (((($netEpc - $redline['closer2_redline']) * $x) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $closer1_commission = $commission_percentage * 0.5;
                } else {
                    $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $closer2_commission = $commission_percentage2 * 0.5;
                } else {
                    $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            }

            // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
            $updateData->closer1_commission = $closer1_commission;
            $updateData->closer2_commission = $closer2_commission;
            $updateData->mark_account_status_id = 3;
            // if (!$paidM2) {
            //     $this->updateCommission($closerId, 2, $closer1_commission, $m2date);
            //     $this->updateCommission($closer2Id, 2, $closer2_commission, $m2date);
            // }
            $closer_commission = ($closer1_commission + $closer2_commission);
        } elseif ($closerId) {
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory && $organizationHistory->self_gen_accounts == '1' && $closerId == $setterId) {
                $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                    $commission_type = $commissionSelfgen->commission_type;
                    $commission_percentage = $commissionSelfgen->commission;
                } else {
                    $commission_percentage = 100;
                    $commission_type = null;
                }
            } else {
                if ($organizationHistory && $organizationHistory->self_gen_accounts == 1 && $organizationHistory->position_id == 3) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0;
                    $commission_type = null;
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closer_commission = (($kw * $commission_percentage) * $x);
                } elseif ($commission_type == 'per sale') {
                    $closer_commission = $commission_percentage * $x;
                } else {
                    $closer_commission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closer_commission = ($kw * $commission_percentage);
                } elseif ($commission_type == 'per sale') {
                    $closer_commission = $commission_percentage;
                } else {
                    $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);
                }
            }

            // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
            $updateData->closer1_commission = $closer_commission;
            $updateData->mark_account_status_id = 3;
            // if (!$paidM2) {
            //     $this->updateCommission($closerId, 2, $closer_commission, $m2date);
            // }
        }
        $updateData->save();

        return [
            'closer_commission' => $closer_commission,
            'setter_commission' => $setter_commission,
        ];
    }

    public function subroutineEightForPest($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $m2date = $checked->m2_date;
        $approvedDate = $checked->customer_signoff;
        $grossAmountValue = $checked->gross_account_value;

        $saleUsers = [];
        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }

        $closer_commission = 0;
        $companyMargin = CompanyProfile::first();
        $updateData = SaleMasterProcess::where('pid', $checked->pid)->first();
        if ($closerId && $closer2Id) {
            $commissionPercentage = 0;
            $commissionPercentageType = '';
            $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commissionPercentageType = $commissionHistory->commission_type;
            }

            $commissionPercentage2 = 0;
            $commissionPercentageType2 = '';
            $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
                $commissionPercentageType2 = $commission2History->commission_type;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentageType == 'per sale') {
                    $closer1Commission = (($commissionPercentage * $x) / 2);
                } else {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                }

                if ($commissionPercentageType2 == 'per sale') {
                    $closer2Commission = (($commissionPercentage2 * $x) / 2);
                } else {
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                }

                // if ($commissionPercentage && $commissionPercentage2) {
                //     $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                //     $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                // } else if ($commissionPercentage) {
                //     $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                // } else if ($commissionPercentage2) {
                //     $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                // }
            } else {
                if ($commissionPercentageType == 'per sale') {
                    $closer1Commission = $commissionPercentage / 2;
                } else {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                }

                if ($commissionPercentageType2 == 'per sale') {
                    $closer2Commission = $commissionPercentage2 / 2;
                } else {
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                }

                // if ($commissionPercentage && $commissionPercentage2) {
                //     $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                //     $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                // } else if ($commissionPercentage) {
                //     $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                // } else if ($commissionPercentage2) {
                //     $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                // }
            }

            // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
            $updateData->closer1_commission = $closer1Commission;
            $updateData->closer2_commission = $closer2Commission;
            $updateData->mark_account_status_id = 3;
            // if (!$paidM2) {
            //     $this->updateCommission($closerId, 2, $closer1Commission, $m2date);
            //     $this->updateCommission($closer2Id, 2, $closer2Commission, $m2date);
            // }
            $closer_commission = ($closer1Commission + $closer2Commission);
        } elseif ($closerId) {
            $commissionPercentage = 0;
            $commissionPercentageType = '';
            $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commissionPercentageType = $commissionHistory->commission_type;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
            } else {
                $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
            }

            // $paidM2 = UserCommission::where(['pid' => $checked->pid, 'amount_type' => 'm2', 'is_displayed' => '1', 'status' => '3'])->whereIn('user_id', $saleUsers)->first();
            $updateData->closer1_commission = $closer1Commission;
            $updateData->mark_account_status_id = 3;
            // if (!$paidM2) {
            //     $this->updateCommission($closerId, 2, $closer1Commission, $m2date);
            // }
        }
        $updateData->save();

        return [
            'closer_commission' => $closer_commission,
            'setter_commission' => 0,
        ];
    }

    public function subroutineNine($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $customer_signoff = $checked->customer_signoff;
        $m2date = $checked->m2_date;
        $net_epc = $checked->net_epc;
        $kw = $checked->kw;
        $pid = $checked->pid;

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $checked->gross_account_value;
        }
        $withholding = $this->userWithHoldingAmounts($checked);

        $saleData = SaleMasterProcess::where('pid', $pid)->first();
        if ($setterId || in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $overrideSetting = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
            $redline = $this->subroutineSix($checked);
            if ($setterId && $closerId != $setterId) {
                $setter1WithHolding = $withholding['setter1']['amount'];
                if ($setter1WithHolding < 0) {
                    $setter1WithHolding = 0;
                }

                $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $setter1DueM2 = ($saleData->setter1_commission - $totalM1);
                if ($setter1DueM2 <= 0) {
                    $setter1WithHolding = 0;
                } else {
                    $setter1DueM2 = $setter1DueM2 - $setter1WithHolding;
                    if ($setter1DueM2 < 0) {
                        $setter1WithHolding = ($saleData->setter1_commission - $totalM1);
                        $setter1DueM2 = 0;
                    }
                }

                $setter = User::where('id', $setterId)->first();
                $stopPayroll = ($setter->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $setter->position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $setterRedLine = $redline['setter1_redline'];
                $setterRedLineType = ($redline['setter1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['setter_commission_type'] == 'per sale' || $this->commissionData['setter_commission_type'] == 'per kw') {
                    $setterRedLine = $this->commissionData['setter_commission'];
                    $setterRedLineType = $this->commissionData['setter_commission_type'];
                }

                $payFrequencySetter = $this->payFrequencyNew($m2date, $subPositionId, $setterId);
                $data = [
                    'user_id' => $setterId,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $setter1DueM2,
                    'redline' => $redline['setter1_redline'],
                    'redline_type' => ($redline['setter1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                    'net_epc' => $net_epc,
                    'kw' => $kw,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencySetter->pay_period_from,
                    'pay_period_to' => $payFrequencySetter->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $m2 = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                $withheld = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();

                $paid = false;
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $paid = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }
                } elseif ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $paid = true;
                    }
                }

                if (! $paid) {
                    if ($setter1DueM2) {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->update($data);
                                    $saleData->setter1_m2 = $setter1DueM2;
                                    $saleData->setter1_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $m2->update($data);
                                    $saleData->setter1_m2 = $setter1DueM2;
                                    $saleData->setter1_m2_paid_status = 5;
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommissionNew($setterId, $subPositionId, $setter1DueM2, $payFrequencySetter);
                            $saleData->setter1_m2 = $setter1DueM2;
                            $saleData->setter1_m2_paid_status = 5;
                        }
                    } else {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->delete();
                                    $saleData->setter1_m2 = 0;
                                    $saleData->setter1_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    $m2->delete();
                                    $saleData->setter1_m2 = 0;
                                    $saleData->setter1_m2_paid_status = 5;
                                }
                            }
                        }
                    }

                    // WITHHELD CALCULATION
                    $withheld = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                    if ($setter1WithHolding) {
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $setter1WithHolding;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = @$withholding['setter1']['recon_amount'];
                        $data['recon_amount_type'] = @$withholding['setter1']['recon_amount_type'];
                        $data['status'] = 3;

                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                            UserCommission::where(['user_id' => $setterId, 'pid' => $checked->pid, 'is_displayed' => '1'])->update(['redline' => $setterRedLine]);
                        }
                    } else {
                        if ($withheld && $withheld->recon_status == '1') {
                            $withheld->delete();
                        }
                    }
                }
            } else {
                $saleData->setter1_m2 = 0;
                $saleData->setter1_commission = 0;
            }

            if ($setter2Id) {
                $setter2WithHolding = $withholding['setter2']['amount'];
                if ($setter2WithHolding < 0) {
                    $setter2WithHolding = 0;
                }

                $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $setter2DueM2 = ($saleData->setter2_commission - $totalM1);
                if ($setter2DueM2 <= 0) {
                    $setter2WithHolding = 0;
                } else {
                    $setter2DueM2 = $setter2DueM2 - $setter2WithHolding;
                    if ($setter2DueM2 < 0) {
                        $setter2WithHolding = ($saleData->setter2_commission - $totalM1);
                        $setter2DueM2 = 0;
                    }
                }

                $setter = User::where('id', $setter2Id)->first();
                $stopPayroll = ($setter->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $setter->position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $setter2RedLine = $redline['setter2_redline'];
                $setter2RedLineType = ($redline['setter2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['setter2_commission_type'] == 'per sale' || $this->commissionData['setter2_commission_type'] == 'per kw') {
                    $setter2RedLine = $this->commissionData['setter2_commission'];
                    $setter2RedLineType = $this->commissionData['setter2_commission_type'];
                }

                $payFrequencySetter = $this->payFrequencyNew($m2date, $subPositionId, $setter2Id);
                $data = [
                    'user_id' => $setter2Id,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $setter2DueM2,
                    'redline' => $setter2RedLine,
                    'redline_type' => $setter2RedLineType,
                    'net_epc' => $net_epc,
                    'kw' => $kw,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencySetter->pay_period_from,
                    'pay_period_to' => $payFrequencySetter->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $m2 = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                $withheld = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();

                $paid = false;
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $paid = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }
                } elseif ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $paid = true;
                    }
                }

                if (! $paid) {
                    if ($setter2DueM2) {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->update($data);
                                    $saleData->setter2_m2 = $setter2DueM2;
                                    $saleData->setter2_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $m2->update($data);
                                    $saleData->setter2_m2 = $setter2DueM2;
                                    $saleData->setter2_m2_paid_status = 5;
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommissionNew($setter2Id, $subPositionId, $setter2DueM2, $payFrequencySetter);
                            $saleData->setter2_m2 = $setter2DueM2;
                            $saleData->setter2_m2_paid_status = 5;
                        }
                    } else {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->delete();
                                    $saleData->setter2_m2 = 0;
                                    $saleData->setter2_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    $m2->delete();
                                    $saleData->setter2_m2 = 0;
                                    $saleData->setter2_m2_paid_status = 5;
                                }
                            }
                        }
                    }

                    // WITHHELD CALCULATION
                    if ($setter2WithHolding) {
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $setter2WithHolding;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = @$withholding['setter2']['recon_amount'];
                        $data['recon_amount_type'] = @$withholding['setter2']['recon_amount_type'];
                        $data['status'] = 3;

                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                            UserCommission::where(['user_id' => $setter2Id, 'pid' => $checked->pid, 'is_displayed' => '1'])->update(['redline' => $setter2RedLine]);
                        }
                    } else {
                        if ($withheld && $withheld->recon_status == '1') {
                            $withheld->delete();
                        }
                    }
                }
            } else {
                $saleData->setter2_m2 = 0;
                $saleData->setter2_commission = 0;
            }

            if ($closerId) {
                $closer1WithHolding = $withholding['closer1']['amount'];
                if ($closer1WithHolding < 0) {
                    $closer1WithHolding = 0;
                }

                $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $closer1DueM2 = ($saleData->closer1_commission - $totalM1);
                if ($closer1DueM2 <= 0) {
                    $closer1WithHolding = 0;
                } else {
                    $closer1DueM2 = $closer1DueM2 - $closer1WithHolding;
                    if ($closer1DueM2 < 0) {
                        $closer1WithHolding = ($saleData->closer1_commission - $totalM1);
                        $closer1DueM2 = 0;
                    }
                }

                $closer = User::where('id', $closerId)->first();
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $closer->position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $closerRedLine = $redline['closer1_redline'];
                $closerRedLineType = ($redline['closer1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['closer_commission_type'] == 'per sale' || $this->commissionData['closer_commission_type'] == 'per kw') {
                    $closerRedLine = $this->commissionData['closer_commission'];
                    $closerRedLineType = $this->commissionData['closer_commission_type'];
                }

                $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closerId);
                $data = [
                    'user_id' => $closerId,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $closer1DueM2,
                    'redline' => $closerRedLine,
                    'redline_type' => $closerRedLineType,
                    'net_epc' => $net_epc,
                    'kw' => $kw,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencyCloser->pay_period_from,
                    'pay_period_to' => $payFrequencyCloser->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $m2 = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                $withheld = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();

                $paid = false;
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $paid = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }
                } elseif ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $paid = true;
                    }
                }

                if (! $paid) {
                    if ($closer1DueM2) {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->update($data);
                                    $saleData->closer1_m2 = $closer1DueM2;
                                    $saleData->closer1_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $m2->update($data);
                                    $saleData->closer1_m2 = $closer1DueM2;
                                    $saleData->closer1_m2_paid_status = 5;
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommissionNew($closerId, $subPositionId, $closer1DueM2, $payFrequencyCloser);
                            $saleData->closer1_m2 = $closer1DueM2;
                            $saleData->closer1_m2_paid_status = 5;
                        }
                    } else {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->delete();
                                    $saleData->closer1_m2 = 0;
                                    $saleData->closer1_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    $m2->delete();
                                    $saleData->closer1_m2 = 0;
                                    $saleData->closer1_m2_paid_status = 5;
                                }
                            }
                        }
                    }

                    // WITHHELD CALCULATION
                    if ($closer1WithHolding) {
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $closer1WithHolding;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = @$withholding['closer1']['recon_amount'];
                        $data['recon_amount_type'] = @$withholding['closer1']['recon_amount_type'];
                        $data['status'] = 3;

                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                            UserCommission::where(['user_id' => $closerId, 'pid' => $checked->pid, 'is_displayed' => '1'])->update(['redline' => $closerRedLine]);
                        }
                    } else {
                        if ($withheld && $withheld->recon_status == '1') {
                            $withheld->delete();
                        }
                    }
                }
            } else {
                $saleData->closer1_m2 = 0;
                $saleData->closer1_commission = 0;
            }

            if ($closer2Id) {
                $closer2WithHolding = $withholding['closer2']['amount'];
                if ($closer2WithHolding < 0) {
                    $closer2WithHolding = 0;
                }

                $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $closer2DueM2 = ($saleData->closer2_commission - $totalM1);
                if ($closer2DueM2 <= 0) {
                    $closer2WithHolding = 0;
                } else {
                    $closer2DueM2 = $closer2DueM2 - $closer2WithHolding;
                    if ($closer2DueM2 < 0) {
                        $closer2WithHolding = ($saleData->closer2_commission - $totalM1);
                        $closer2DueM2 = 0;
                    }
                }

                $closer = User::where('id', $closer2Id)->first();
                $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
                $subPositionId = $closer->position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $closer2RedLine = $redline['closer2_redline'];
                $closer2RedLineType = ($redline['closer2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['closer2_commission_type'] == 'per sale' || $this->commissionData['closer2_commission_type'] == 'per kw') {
                    $closer2RedLine = $this->commissionData['closer2_commission'];
                    $closer2RedLineType = $this->commissionData['closer2_commission_type'];
                }

                $payFrequencyCloser = $this->payFrequencyNew($m2date, $subPositionId, $closer2Id);
                $data = [
                    'user_id' => $closer2Id,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2',
                    'amount' => $closer2DueM2,
                    'redline' => $closer2RedLine,
                    'redline_type' => $closer2RedLineType,
                    'net_epc' => $net_epc,
                    'kw' => $kw,
                    'date' => $m2date,
                    'pay_period_from' => $payFrequencyCloser->pay_period_from,
                    'pay_period_to' => $payFrequencyCloser->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                $m2 = UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'amount_type' => 'm2', 'is_displayed' => '1'])->first();
                $withheld = UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();

                $paid = false;
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2') {
                        if ($m2->status == '3') {
                            $paid = true;
                        }
                    } elseif ($m2->settlement_type == 'reconciliation') {
                        if ($m2->recon_status == '2' || $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }
                } elseif ($withheld) {
                    if ($withheld->recon_status == '2' || $withheld->recon_status == '3') {
                        $paid = true;
                    }
                }

                if (! $paid) {
                    if ($closer2DueM2) {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->update($data);
                                    $saleData->closer2_m2 = $closer2DueM2;
                                    $saleData->closer2_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    unset($data['pay_period_from']);
                                    unset($data['pay_period_to']);
                                    $m2->update($data);
                                    $saleData->closer2_m2 = $closer2DueM2;
                                    $saleData->closer2_m2_paid_status = 5;
                                }
                            }
                        } else {
                            UserCommission::create($data);
                            $this->updateCommissionNew($closer2Id, $subPositionId, $closer2DueM2, $payFrequencyCloser);
                            $saleData->closer2_m2 = $closer2DueM2;
                            $saleData->closer2_m2_paid_status = 5;
                        }
                    } else {
                        if ($m2) {
                            if ($m2->settlement_type == 'during_m2') {
                                if ($m2->status == '1') {
                                    $m2->delete();
                                    $saleData->closer2_m2 = 0;
                                    $saleData->closer2_m2_paid_status = 5;
                                }
                            } elseif ($m2->settlement_type == 'reconciliation') {
                                if ($m2->recon_status == '1') {
                                    $m2->delete();
                                    $saleData->closer2_m2 = 0;
                                    $saleData->closer2_m2_paid_status = 5;
                                }
                            }
                        }
                    }

                    // WITHHELD CALCULATION
                    if ($closer2WithHolding) {
                        unset($data['pay_period_from']);
                        unset($data['pay_period_to']);
                        $data['amount'] = $closer2WithHolding;
                        $data['amount_type'] = 'reconciliation';
                        $data['settlement_type'] = 'reconciliation';
                        $data['recon_amount'] = @$withholding['closer2']['recon_amount'];
                        $data['recon_amount_type'] = @$withholding['closer2']['recon_amount_type'];
                        $data['status'] = 3;

                        if ($withheld) {
                            if ($withheld->recon_status == '1') {
                                $withheld->update($data);
                            }
                        } else {
                            UserCommission::create($data);
                            UserCommission::where(['user_id' => $closer2Id, 'pid' => $checked->pid, 'is_displayed' => '1'])->update(['redline' => $closer2RedLine]);
                        }
                    } else {
                        if ($withheld && $withheld->recon_status == '1') {
                            $withheld->delete();
                        }
                    }
                }
            } else {
                $saleData->closer2_m2 = 0;
                $saleData->closer2_commission = 0;
            }

            if ($overrideSetting) {
                // REMOVE UNPAID DURING M2 OVERRIDES
                if ($setterId) {
                    UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                    UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                }
                if ($setter2Id) {
                    UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                    UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                }
                if ($closerId) {
                    UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1'])->where('type', '!=', 'Stack')->delete();
                    UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1'])->where('type', '!=', 'Stack')->delete();
                }
                if ($closer2Id) {
                    UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                    UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_move_to_recon' => '0', 'during' => 'm2', 'is_displayed' => '1'])->delete();
                }

                // GENERATE OVERRIDES
                if ($setterId) {
                    $this->UserOverride($setterId, $pid, $kw, $m2date, $redline['setter1_redline']);
                }
                if ($setter2Id) {
                    $this->UserOverride($setter2Id, $pid, $kw, $m2date, $redline['setter2_redline']);
                }
                if ($closerId) {
                    $this->UserOverride($closerId, $pid, $kw, $m2date, $redline['closer1_redline']);
                }
                if ($closer2Id) {
                    $this->UserOverride($closer2Id, $pid, $kw, $m2date, $redline['closer2_redline']);
                }
            }
        } else {
            $saleData->setter1_m2 = 0;
            $saleData->setter1_commission = 0;
            $saleData->setter1_m2_paid_status = 5;
            $saleData->setter2_m2 = 0;
            $saleData->setter2_commission = 0;
            $saleData->setter2_m2_paid_status = 5;
            $saleData->closer1_m2 = 0;
            $saleData->closer1_commission = 0;
            $saleData->closer1_m2_paid_status = 5;
            $saleData->closer2_m2 = 0;
            $saleData->closer2_commission = 0;
            $saleData->closer2_m2_paid_status = 5;
        }
        $saleData->save();
    }

    public function subroutineTen($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $approvedDate = $checked->customer_signoff;

        $companySetting = CompanySetting::where('type', 'reconciliation')->first();
        if ($companySetting->status == '1') {
            $payFrequency = $this->reconciliationPeriod($m2date);

            $closerWithHeldType = '';
            $closerWithHeldAmount = 0;
            $closerWithHeldType2 = '';
            $closerWithHeldAmount2 = 0;
            if ($closerId != null && $closer2Id != null) {
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $closerId, 'self_gen_user' => 1])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $closerWithHeldType = $userWithheldHistory->withheld_type;
                        $closerWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                } else {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $closerId, 'self_gen_user' => 0])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $closerWithHeldType = $userWithheldHistory->withheld_type;
                        $closerWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                $userOrganizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory2['self_gen_accounts'] == 1 && $userOrganizationHistory2['position_id'] == 3) {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $closer2Id, 'self_gen_user' => 1])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $closerWithHeldType2 = $userWithheldHistory->withheld_type;
                        $closerWithHeldAmount2 = $userWithheldHistory->withheld_amount;
                    }
                } else {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $closer2Id, 'self_gen_user' => 0])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $closerWithHeldType2 = $userWithheldHistory->withheld_type;
                        $closerWithHeldAmount2 = $userWithheldHistory->withheld_amount;
                    }
                }

                $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
                $closerWithheldForMax2 = PositionReconciliations::where(['position_id' => $userOrganizationHistory2->sub_position_id, 'status' => '1'])->first();
                $closerMaxWithHeldAmount = $closerWithheldForMax2->maximum_withheld;
                $closerMaxWithHeldAmount2 = $closerWithheldForMax->maximum_withheld;
                if (! empty($closerMaxWithHeldAmount)) {
                    $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closerId)->sum('withhold_amount');
                    if ($closerReconciliationWithholdAmount >= $closerMaxWithHeldAmount) {
                        // No withholding calculations required.  System proceeds to following steps
                    } else {
                        if ($closerWithHeldType == 'per kw') {
                            $commissionSettingAmount = $closerWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $closerWithHeldAmount;
                        }

                        $closerWithheldCheck = ($closerReconciliationWithholdAmount + $commissionSettingAmount);
                        if ($closerWithheldCheck > $closerMaxWithHeldAmount) {
                            $commissionSettingAmount = ($closerMaxWithHeldAmount - $closerReconciliationWithholdAmount);
                        }

                        $closerWithheld = $commissionSettingAmount;

                        // Total is added to reconciliation withholdings and system proceeds to following steps
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $closerWithheld;
                            $reconData->save();
                        } else {
                            if ($closerWithheld > 0) {
                                UserReconciliationWithholding::create([
                                    'pid' => $checked->pid,
                                    'closer_id' => $closerId,
                                    'withhold_amount' => $closerWithheld,
                                ]);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
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
                    }
                } else {
                    if ($closerWithHeldType == 'per kw') {
                        $commissionSettingAmount = $closerWithHeldAmount * $kw;
                    } else {
                        $commissionSettingAmount = $closerWithHeldAmount;
                    }

                    $closerWithheld = $commissionSettingAmount;
                    $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                    if (isset($reconData) && $reconData != '') {
                        $reconData->withhold_amount = $closerWithheld;
                        $reconData->save();
                    } else {
                        if ($closerWithheld > 0) {
                            UserReconciliationWithholding::create([
                                'pid' => $checked->pid,
                                'closer_id' => $closerId,
                                'withhold_amount' => $closerWithheld,
                            ]);
                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
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
                }

                if (! empty($closerMaxWithHeldAmount2)) {
                    $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closer2Id)->sum('withhold_amount');
                    if ($closerReconciliationWithholdAmount >= $closerMaxWithHeldAmount2) {
                        // No withholding calculations required.  System proceeds to following steps
                    } else {
                        if ($closerWithHeldType2 == 'per kw') {
                            $commissionSettingAmount = $closerWithHeldAmount2 * $kw;
                        } else {
                            $commissionSettingAmount = $closerWithHeldAmount2;
                        }

                        $closerWithheldCheck = ($closerReconciliationWithholdAmount + $commissionSettingAmount);
                        if ($closerWithheldCheck > $closerMaxWithHeldAmount2) {
                            $commissionSettingAmount = ($closerMaxWithHeldAmount2 - $closerReconciliationWithholdAmount);
                        }

                        $closerWithheld = $commissionSettingAmount;

                        // Total is added to reconciliation withholdings and system proceeds to following steps
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $closerWithheld;
                            $reconData->save();
                        } else {
                            if ($closerWithheld > 0) {
                                UserReconciliationWithholding::create([
                                    'pid' => $checked->pid,
                                    'closer_id' => $closer2Id,
                                    'withhold_amount' => $closerWithheld,
                                ]);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $closer2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create([
                                        'user_id' => $closer2Id,
                                        'amount' => $closerWithheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    if ($closerWithHeldType2 == 'per kw') {
                        $commissionSettingAmount = $closerWithHeldAmount2 * $kw;
                    } else {
                        $commissionSettingAmount = $closerWithHeldAmount2;
                    }

                    $closerWithheld = $commissionSettingAmount;
                    $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
                    if (isset($reconData) && $reconData != '') {
                        $reconData->withhold_amount = $closerWithheld;
                        $reconData->save();
                    } else {
                        if ($closerWithheld > 0) {
                            UserReconciliationWithholding::create([
                                'pid' => $checked->pid,
                                'closer_id' => $closer2Id,
                                'withhold_amount' => $closerWithheld,
                            ]);
                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $closer2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create([
                                    'user_id' => $closer2Id,
                                    'amount' => $closerWithheld,
                                    'period_from' => $payFrequency->pay_period_from,
                                    'period_to' => $payFrequency->pay_period_to,
                                    'status' => 'pending',
                                ]);
                            }
                        }
                    }
                }
            } elseif ($closerId != null) {
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == '1') {
                    if ($userOrganizationHistory->position_id == '2') {
                        $selfSubGenPositionId = 3;
                    } else {
                        $selfSubGenPositionId = 2;
                    }
                    $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
                    $setterWithheldForMax = PositionReconciliations::where(['position_id' => $selfSubGenPositionId, 'status' => '1'])->first();
                    if ($closerWithheldForMax || $setterWithheldForMax) {
                        $closerWithheldHistory = UserWithheldHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                        $closerWithHeldType = 0;
                        $closerWithHeldAmount = 0;
                        if ($closerWithheldHistory && $closerWithheldHistory->withheld_amount > 0) {
                            $closerWithHeldType = $closerWithheldHistory->withheld_type;
                            $closerWithHeldAmount = $closerWithheldHistory->withheld_amount;
                        }

                        $amount1 = 0;
                        if ($closerWithheldHistory) {
                            if ($closerWithHeldType == 'per sale') {
                                $amount1 = $closerWithHeldAmount;
                            } else {
                                $amount1 = ($closerWithHeldAmount * $kw);
                            }
                        }

                        $setterWithHeldType = 0;
                        $setterWithHeldAmount = 0;
                        $setterWithheldHistory = UserWithheldHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                        if ($setterWithheldHistory && $setterWithheldHistory->withheld_amount > 0) {
                            $setterWithHeldType = $setterWithheldHistory->withheld_type;
                            $setterWithHeldAmount = $setterWithheldHistory->withheld_amount;
                        }

                        $amount2 = 0;
                        if ($setterWithheldHistory) {
                            if ($setterWithHeldType == 'per sale') {
                                $amount2 = $setterWithHeldAmount;
                            } else {
                                $amount2 = ($setterWithHeldAmount * $kw);
                            }
                        }

                        if (! empty(@$setterWithheldHistory->upfront_limit) && $amount1 > @$setterWithheldHistory->upfront_limit) {
                            $amount1 = $setterWithheldHistory->upfront_limit;
                        }

                        if (! empty(@$closerWithheldHistory->upfront_limit) && $amount2 > @$closerWithheldHistory->upfront_limit) {
                            $amount2 = $closerWithheldHistory->upfront_limit;
                        }

                        $amount = max($amount1, $amount2);
                        if (isset($amount)) {
                            $closerWithheld = $amount;
                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $closerWithheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'closer_id' => $closerId,
                                    'withhold_amount' => $closerWithheld,
                                ];

                                if ($closerWithheld > 0) {
                                    UserReconciliationWithholding::create($data);
                                    $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                    if ($payReconciliation) {
                                        $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
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
                        }
                    }
                } else {
                    $closerWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
                    if ($closerWithheldForMax) {
                        $userWithheldHistory = UserWithheldHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                        if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                            $closerWithHeldType = $userWithheldHistory->withheld_type;
                            $closerWithHeldAmount = $userWithheldHistory->withheld_amount;
                        }
                        $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closerId)->sum('withhold_amount');
                        $closerMaxWithHeldAmount = @$closerWithheldForMax->maximum_withheld ? $closerWithheldForMax->maximum_withheld : null;

                        if ($closerMaxWithHeldAmount) {
                            if ($closerReconciliationWithholdAmount < $closerMaxWithHeldAmount) {
                                if ($closerWithHeldType == 'per kw') {
                                    $commissionSettingAmount = $closerWithHeldAmount * $kw;
                                } else {
                                    $commissionSettingAmount = $closerWithHeldAmount;
                                }

                                $closerWithheldCheck = ($closerReconciliationWithholdAmount + $commissionSettingAmount);
                                if ($closerWithheldCheck > $closerMaxWithHeldAmount) {
                                    $commissionSettingAmount = ($closerMaxWithHeldAmount - $closerReconciliationWithholdAmount);
                                }
                            }
                        } else {
                            if ($closerWithHeldType == 'per kw') {
                                $commissionSettingAmount = $closerWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $closerWithHeldAmount;
                            }
                        }

                        if (isset($commissionSettingAmount)) {
                            $closerWithheld = $commissionSettingAmount;
                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $closerWithheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'closer_id' => $closerId,
                                    'withhold_amount' => $closerWithheld,
                                ];

                                if ($closerWithheld > 0) {
                                    UserReconciliationWithholding::create($data);
                                    $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                    if ($payReconciliation) {
                                        $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
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
                        }
                    }
                }
            }

            $setterWithHeldType = '';
            $setterWithHeldAmount = 0;
            $setterWithHeldType2 = '';
            $setterWithHeldAmount2 = 0;
            if ($setterId != null && $setter2Id != null) {
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory['self_gen_accounts'] == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $setterId, 'self_gen_user' => 1])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                } else {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $setterId, 'self_gen_user' => 0])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                $userOrganizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory2['self_gen_accounts'] == 1 && $userOrganizationHistory2['position_id'] == 2) {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $setter2Id, 'self_gen_user' => 1])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType2 = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount2 = $userWithheldHistory->withheld_amount;
                    }
                } else {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $setter2Id, 'self_gen_user' => 0])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType2 = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount2 = $userWithheldHistory->withheld_amount;
                    }
                }

                $setterWithheldForMax = PositionReconciliations::where(['position_id' => $userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
                $setterMaxWithHeldAmount = $setterWithheldForMax->maximum_withheld;
                $setterWithheldForMax2 = PositionReconciliations::where(['position_id' => $userOrganizationHistory2->sub_position_id, 'status' => '1'])->first();
                $setterMaxWithHeldAmount2 = $setterWithheldForMax2->maximum_withheld;
                $setterReconciliationWithholdAmount = UserReconciliationWithholding::where(['setter_id' => $setterId, 'status' => 'unpaid'])->sum('withhold_amount');
                if (! empty($setterMaxWithHeldAmount)) {
                    if ($setterReconciliationWithholdAmount >= $setterMaxWithHeldAmount) {
                        // No withholding calculations required.  System proceeds to following steps
                    } else {
                        if ($setterWithHeldType == 'per kw') {
                            $commissionSettingAmount = $setterWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $setterWithHeldAmount;
                        }

                        $setterWithheldCheck = ($setterReconciliationWithholdAmount + $commissionSettingAmount);
                        if ($setterWithheldCheck > $setterMaxWithHeldAmount) {
                            $commissionSettingAmount = ($setterMaxWithHeldAmount - $setterReconciliationWithholdAmount);
                        }

                        $setterWithheld = $commissionSettingAmount;

                        // Total is added to reconciliation withholdings and system proceeds to following steps
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $setterWithheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'setter_id' => $setterId,
                                'withhold_amount' => $setterWithheld,
                            ];

                            if ($setterWithheld > 0) {
                                UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $setterId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
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
                    }
                } else {
                    if ($setterWithHeldType == 'per kw') {
                        $commissionSettingAmount = $setterWithHeldAmount * $kw;
                    } else {
                        $commissionSettingAmount = $setterWithHeldAmount;
                    }

                    $setterWithheld = $commissionSettingAmount;
                    $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
                    if (isset($reconData) && $reconData != '') {
                        $reconData->withhold_amount = $setterWithheld;
                        $reconData->save();
                    } else {
                        $data = [
                            'pid' => $checked->pid,
                            'setter_id' => $setterId,
                            'withhold_amount' => $setterWithheld,
                        ];

                        if ($setterWithheld > 0) {
                            UserReconciliationWithholding::create($data);
                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $setterId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
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
                }

                $setterReconciliationWithholdAmount = UserReconciliationWithholding::where(['setter_id' => $setter2Id, 'status' => 'unpaid'])->sum('withhold_amount');
                if (! empty($setterMaxWithHeldAmount2)) {
                    if ($setterReconciliationWithholdAmount >= $setterMaxWithHeldAmount2) {
                        // No withholding calculations required.  System proceeds to following steps
                    } else {
                        if ($setterWithHeldType2 == 'per kw') {
                            $commissionSettingAmount = $setterWithHeldAmount2 * $kw;
                        } else {
                            $commissionSettingAmount = $setterWithHeldAmount2;
                        }

                        $setterWithheldCheck = ($setterReconciliationWithholdAmount + $commissionSettingAmount);
                        if ($setterWithheldCheck > $setterMaxWithHeldAmount2) {
                            $commissionSettingAmount = ($setterMaxWithHeldAmount2 - $setterReconciliationWithholdAmount);
                        }

                        $setterWithheld = $commissionSettingAmount;

                        // Total is added to reconciliation withholdings and system proceeds to following steps
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $setterWithheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'setter_id' => $setter2Id,
                                'withhold_amount' => $setterWithheld,
                            ];

                            if ($setterWithheld > 0) {
                                UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $setter2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create([
                                        'user_id' => $setter2Id,
                                        'amount' => $setterWithheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]);
                                }
                            }
                        }
                    }
                } else {
                    if ($setterWithHeldType2 == 'per kw') {
                        $commissionSettingAmount = $setterWithHeldAmount2 * $kw;
                    } else {
                        $commissionSettingAmount = $setterWithHeldAmount2;
                    }

                    $setterWithheld = $commissionSettingAmount;
                    $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
                    if (isset($reconData) && $reconData != '') {
                        $reconData->withhold_amount = $setterWithheld;
                        $reconData->save();
                    } else {
                        $data = [
                            'pid' => $checked->pid,
                            'setter_id' => $setter2Id,
                            'withhold_amount' => $setterWithheld,
                        ];

                        if ($setterWithheld > 0) {
                            UserReconciliationWithholding::create($data);
                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $setter2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create([
                                    'user_id' => $setter2Id,
                                    'amount' => $setterWithheld,
                                    'period_from' => $payFrequency->pay_period_from,
                                    'period_to' => $payFrequency->pay_period_to,
                                    'status' => 'pending',
                                ]);
                            }
                        }
                    }
                }
            } elseif ($setterId != null && $closerId != $setterId) {
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $setterWithheldForMax = PositionReconciliations::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'status' => '1'])->first();
                if ($setterWithheldForMax) {
                    $userWithheldHistory = UserWithheldHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($userWithheldHistory && $userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                    $setterReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setterId)->sum('withhold_amount');
                    $setterMaxWithHeldAmount = @$setterWithheldForMax->maximum_withheld ? $setterWithheldForMax->maximum_withheld : null;

                    if ($setterMaxWithHeldAmount) {
                        if ($setterReconciliationWithholdAmount < $setterMaxWithHeldAmount) {
                            if ($setterWithHeldType == 'per kw') {
                                $commissionSettingAmount = $setterWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $setterWithHeldAmount;
                            }

                            $setterWithheldCheck = ($setterReconciliationWithholdAmount + $commissionSettingAmount);
                            if ($setterWithheldCheck > $setterMaxWithHeldAmount) {
                                $commissionSettingAmount = ($setterMaxWithHeldAmount - $setterReconciliationWithholdAmount);
                            }
                        }
                    } else {
                        if ($setterWithHeldType == 'per kw') {
                            $commissionSettingAmount = $setterWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $setterWithHeldAmount;
                        }
                    }

                    if (isset($commissionSettingAmount)) {
                        $setterWithheld = $commissionSettingAmount;
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $setterWithheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'setter_id' => $setterId,
                                'withhold_amount' => $setterWithheld,
                            ];

                            if ($setterWithheld > 0) {
                                UserReconciliationWithholding::create($data);
                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $setterId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
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
                    }
                }
            }
        }
    }

    public function subroutineEleven($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $customer_signoff = $checked->customer_signoff;
        $pid = $checked->pid;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $date = $checked->m2_date;

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $redline = [
                'setter1_redline' => null,
                'setter2_redline' => null,
                'closer1_redline' => null,
                'closer2_redline' => null,
            ];
            $kw = $checked->gross_account_value;
            $netEpc = null;
        } else {
            $redline = $this->subroutineSix($checked);
        }

        $saleData = SaleMasterProcess::where('pid', $checked->pid)->first();
        if (! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($setterId != null && $closerId != $setterId) {
                $setter = User::where('id', $setterId)->first();
                $subPositionId = $setter->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $reconAmount = 0;
                $withHeldAmount = null;
                $withHeldAmountType = null;
                $withHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setterId, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                    if ($withHeld->recon_amount_type == 'per sale') {
                        $reconAmount = $withHeld->recon_amount;
                    } elseif ($withHeld->recon_amount_type == 'percent') {
                        $withheldPercent = $withHeld->recon_amount;
                        $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $setterId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                        $totalM2 = $saleData->setter1_commission - $totalM1;
                        $reconAmount = ($totalM2 * ($withheldPercent / 100));
                    } else {
                        $reconAmount = ($withHeld->recon_amount * $kw);
                    }
                    $withHeldAmount = $withHeld->recon_amount;
                    $withHeldAmountType = $withHeld->recon_amount_type;
                }

                $totalM1 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setterId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $totalCommission = ($saleData->setter1_commission - $totalM1);
                $paidM2 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setterId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $dueM2Amount = $totalCommission - $paidM2 - $reconAmount;
                // Round to 2 decimals to match database DECIMAL(10,2) precision
                $dueM2Amount = round((float) $dueM2Amount, 2);

                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $setterId);
                $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
                $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

                $stopPayroll = ($setter->stop_payroll == 1) ? 1 : 0;
                $data = [
                    'user_id' => $setterId,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2 update',
                    'amount' => $dueM2Amount,
                    'redline' => @$redline['setter1_redline'],
                    'redline_type' => ($redline['setter1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                    'net_epc' => $netEpc,
                    'kw' => $kw,
                    'date' => $date,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                if ($dueM2Amount >= 0.1 || $dueM2Amount <= -0.1) {
                    $paid = false;
                    $m2 = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    if ($m2) {
                        if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                            $paid = true;
                        } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }

                    if ($paid) {
                        UserCommission::create($data);
                        $this->updateCommissionNew($setterId, $subPositionId, $dueM2Amount, $payFrequency);
                    } else {
                        if ($m2) {
                            unset($data['amount_type']);
                            $m2->update($data);
                        }
                    }
                } else {
                    UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->delete();
                }

                $totalWithHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setterId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                $withHeldDue = $reconAmount - $totalWithHeld;
                if ($withHeldDue) {
                    $paid = false;
                    $withheld = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
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
                            $data['amount_type'] = $withheld->amount_type;
                            $data['settlement_type'] = 'reconciliation';
                            $withheld->update($data);
                        }
                    }
                } else {
                    UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->delete();
                }
            }

            if ($setter2Id != null) {
                $setter2 = User::where('id', $setter2Id)->first();
                $subPositionId = $setter2->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $reconAmount = 0;
                $withHeldAmount = null;
                $withHeldAmountType = null;
                $withHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setter2Id, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
                if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                    if ($withHeld->recon_amount_type == 'per sale') {
                        $reconAmount = $withHeld->recon_amount;
                    } elseif ($withHeld->recon_amount_type == 'percent') {
                        $withheldPercent = $withHeld->recon_amount;
                        $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $setter2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                        $totalM2 = $saleData->setter2_commission - $totalM1;
                        $reconAmount = ($totalM2 * ($withheldPercent / 100));
                    } else {
                        $reconAmount = ($withHeld->recon_amount * $kw);
                    }
                    $withHeldAmount = $withHeld->recon_amount;
                    $withHeldAmountType = $withHeld->recon_amount_type;
                }

                $totalM1 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setter2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                $totalCommission = ($saleData->setter2_commission - $totalM1);
                $paidM2 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setter2Id, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
                $dueM2Amount = $totalCommission - $paidM2 - $reconAmount;
                // Round to 2 decimals to match database DECIMAL(10,2) precision
                $dueM2Amount = round((float) $dueM2Amount, 2);

                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $setter2Id);
                $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
                $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

                $stopPayroll = ($setter2->stop_payroll == 1) ? 1 : 0;
                $data = [
                    'user_id' => $setter2Id,
                    'position_id' => $subPositionId,
                    'pid' => $pid,
                    'amount_type' => 'm2 update',
                    'amount' => $dueM2Amount,
                    'redline' => @$redline['setter2_redline'],
                    'redline_type' => ($redline['setter2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                    'net_epc' => $netEpc,
                    'kw' => $kw,
                    'date' => $date,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                ];

                // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
                if ($dueM2Amount >= 0.1 || $dueM2Amount <= -0.1) {
                    $paid = false;
                    $m2 = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                    if ($m2) {
                        if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                            $paid = true;
                        } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                            $paid = true;
                        }
                    }

                    if ($paid) {
                        UserCommission::create($data);
                        $this->updateCommissionNew($setter2Id, $subPositionId, $dueM2Amount, $payFrequency);
                    } else {
                        if ($m2) {
                            unset($data['amount_type']);
                            $m2->update($data);
                        }
                    }
                } else {
                    UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->delete();
                }

                $totalWithHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $setter2Id, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
                $withHeldDue = $reconAmount - $totalWithHeld;
                if ($withHeldDue) {
                    $paid = false;
                    $withheld = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
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
                        }
                    }
                } else {
                    UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->delete();
                }
            }
        }

        if ($closerId != null) {
            $closer = User::where('id', $closerId)->first();
            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $reconAmount = 0;
            $withHeldAmount = null;
            $withHeldAmountType = null;
            $withHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closerId, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
            if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                if ($withHeld->recon_amount_type == 'per sale') {
                    $reconAmount = $withHeld->recon_amount;
                } elseif ($withHeld->recon_amount_type == 'percent') {
                    $withheldPercent = $withHeld->recon_amount;
                    $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                    $totalM2 = $saleData->closer1_commission - $totalM1;
                    $reconAmount = ($totalM2 * ($withheldPercent / 100));
                } else {
                    $reconAmount = ($withHeld->recon_amount * $kw);
                }
                $withHeldAmount = $withHeld->recon_amount;
                $withHeldAmountType = $withHeld->recon_amount_type;
            }

            $totalM1 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closerId, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
            $totalCommission = ($saleData->closer1_commission - $totalM1);
            $paidM2 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closerId, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
            $dueM2Amount = $totalCommission - $paidM2 - $reconAmount;
            // Round to 2 decimals to match database DECIMAL(10,2) precision
            $dueM2Amount = round((float) $dueM2Amount, 2);

            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $closerId);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;
            $data = [
                'user_id' => $closerId,
                'position_id' => $subPositionId,
                'pid' => $pid,
                'amount_type' => 'm2 update',
                'amount' => $dueM2Amount,
                'redline' => @$redline['closer1_redline'],
                'redline_type' => ($redline['closer1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'net_epc' => $netEpc,
                'kw' => $kw,
                'date' => $date,
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
                'customer_signoff' => $customer_signoff,
                'is_stop_payroll' => $stopPayroll,
            ];

            // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
            if ($dueM2Amount >= 0.1 || $dueM2Amount <= -0.1) {
                $paid = false;
                $m2 = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                        $paid = true;
                    } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                        $paid = true;
                    }
                }

                if ($paid) {
                    UserCommission::create($data);
                    $this->updateCommissionNew($closerId, $subPositionId, $dueM2Amount, $payFrequency);
                } else {
                    if ($m2) {
                        unset($data['amount_type']);
                        $m2->update($data);
                    }
                }
            } else {
                UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->delete();
            }

            $totalWithHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closerId, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
            $withHeldDue = $reconAmount - $totalWithHeld;
            if ($withHeldDue) {
                $paid = false;
                $withheld = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
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
                    }
                }
            } else {
                UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->delete();
            }
        }

        if ($closer2Id != null) {
            $closer2 = User::where('id', $closer2Id)->first();
            $subPositionId = $closer2->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $customer_signoff)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $reconAmount = 0;
            $withHeldAmount = null;
            $withHeldAmountType = null;
            $withHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closer2Id, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
            if ($withHeld && $withHeld->recon_amount && $withHeld->recon_amount_type) {
                if ($withHeld->recon_amount_type == 'per sale') {
                    $reconAmount = $withHeld->recon_amount;
                } elseif ($withHeld->recon_amount_type == 'percent') {
                    $withheldPercent = $withHeld->recon_amount;
                    $totalM1 = UserCommission::where(['pid' => $pid, 'user_id' => $closer2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
                    $totalM2 = $saleData->closer2_commission - $totalM1;
                    $reconAmount = ($totalM2 * ($withheldPercent / 100));
                } else {
                    $reconAmount = ($withHeld->recon_amount * $kw);
                }
                $withHeldAmount = $withHeld->recon_amount;
                $withHeldAmountType = $withHeld->recon_amount_type;
            }

            $totalM1 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closer2Id, 'amount_type' => 'm1', 'is_displayed' => '1'])->sum('amount');
            $totalCommission = ($saleData->closer2_commission - $totalM1);
            $paidM2 = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closer2Id, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->sum('amount');
            $dueM2Amount = $totalCommission - $paidM2 - $reconAmount;
            // Round to 2 decimals to match database DECIMAL(10,2) precision
            $dueM2Amount = round((float) $dueM2Amount, 2);

            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $closer2Id);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

            $stopPayroll = ($closer2->stop_payroll == 1) ? 1 : 0;
            $data = [
                'user_id' => $closer2Id,
                'position_id' => $subPositionId,
                'pid' => $pid,
                'amount_type' => 'm2 update',
                'amount' => $dueM2Amount,
                'redline' => @$redline['closer2_redline'],
                'redline_type' => ($redline['closer2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'net_epc' => $netEpc,
                'kw' => $kw,
                'date' => $date,
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
                'customer_signoff' => $customer_signoff,
                'is_stop_payroll' => $stopPayroll,
            ];

            // Only create commission if amount >= $0.10 or <= -$0.10 (skip penny amounts)
            if ($dueM2Amount >= 0.1 || $dueM2Amount <= -0.1) {
                $paid = false;
                $m2 = UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'm2 update'])->orderBy('id', 'DESC')->first();
                if ($m2) {
                    if ($m2->settlement_type == 'during_m2' && $m2->status == '3') {
                        $paid = true;
                    } elseif ($m2->settlement_type == 'reconciliation' && $m2->recon_status == '3') {
                        $paid = true;
                    }
                }

                if ($paid) {
                    UserCommission::create($data);
                    $this->updateCommissionNew($closer2Id, $subPositionId, $dueM2Amount, $payFrequency);
                } else {
                    if ($m2) {
                        unset($data['amount_type']);
                        $m2->update($data);
                    }
                }
            } else {
                UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->delete();
            }

            $totalWithHeld = UserCommission::where(['pid' => $checked->pid, 'user_id' => $closer2Id, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->whereIn('recon_status', ['2', '3'])->sum('amount');
            $withHeldDue = $reconAmount - $totalWithHeld;
            if ($withHeldDue) {
                $paid = false;
                $withheld = UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['reconciliation', 'reconciliation update'])->orderBy('id', 'DESC')->first();
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
                    }
                }
            } else {
                UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid, 'amount_type' => 'reconciliation update', 'recon_status' => '1', 'is_displayed' => '1'])->delete();
            }
        }
    }

    public function SubroutineTwelve($checked)
    {
        $subroutineEight = $this->subroutineEight($checked);
        $closerCommission = $subroutineEight['closer_commission'];
        $setterCommission = $subroutineEight['setter_commission'];

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // Calculate difference between 2 previous steps
        $dataSale = SaleMasterProcess::where('pid', $checked->pid)->first();

        if ($closerId != null && $closer2Id != null) {
            if ($closerId != null) {
                $closer1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closerId])->first();
                $closer1Commission = ($closerCommission / 2);
                $closerValue = ($closer1Commission - $dataSale->closer1_commission);
                // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
                $data1 = [
                    'pid' => $checked->pid,
                    'user_id' => $closerId,
                    'position_id' => 2,
                    'amount' => $closerValue,
                    'status' => ($closerValue >= 0) ? 'Positive' : 'Negative',
                ];
                if ($closer1Deduction) {
                    $deduction = ($closerValue - $closer1Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $closer1Deduction->id)->update($data1);
                } else {
                    $deduction = $closerValue;
                    $backendSettings = DeductionAlert::create($data1);
                }
                // $this->updateDeduction($closerId,2,$deduction);

            }

            if ($closer2Id != null) {
                $closer2Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closer2Id])->first();
                $closer2Commission = ($closerCommission / 2);
                $closer2Value = ($closer2Commission - $dataSale->closer2_commission);
                $data2 = [
                    'pid' => $checked->pid,
                    'user_id' => $closer2Id,
                    'position_id' => 2,
                    'amount' => $closer2Value,
                    'status' => ($closer2Value >= 0) ? 'Positive' : 'Negative',
                ];
                if ($closer2Deduction) {
                    $deduction = ($closer2Value - $closer2Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $closer2Deduction->id)->update($data2);
                } else {
                    $deduction = $closer2Value;
                    $backendSettings = DeductionAlert::create($data2);
                }
                // $this->updateDeduction($closer2Id,2,$deduction);

            }
        } elseif ($closerId) {
            $closer1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closerId])->first();
            $closer1Commission = $closerCommission;
            $closerValue = ($closer1Commission - $dataSale->closer1_commission);
            // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
            $data1 = [
                'pid' => $checked->pid,
                'user_id' => $closerId,
                'position_id' => 2,
                'amount' => $closerValue,
                'status' => ($closerValue >= 0) ? 'Positive' : 'Negative',
            ];
            if ($closer1Deduction) {
                $deduction = ($closerValue - $closer1Deduction->amount);
                $updateDeduction = DeductionAlert::where('id', $closer1Deduction->id)->update($data1);
            } else {
                $deduction = $closerValue;
                $backendSettings = DeductionAlert::create($data1);
            }
            // $this->updateDeduction($closerId,2,$deduction);

        }

        if ($setterId != null && $setter2Id != null) {
            if ($setterId != null) {
                $setter1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setterId])->first();
                $setter1Commission = ($setterCommission / 2);
                $setterValue = ($setter1Commission - $dataSale->setter1_commission);
                $data3 = [
                    'pid' => $checked->pid,
                    'user_id' => $setterId,
                    'position_id' => 3,
                    'amount' => $setterValue,
                    'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
                ];
                if ($setter1Deduction) {
                    $deduction = ($setterValue - $setter1Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $setter1Deduction->id)->update($data3);
                } else {
                    $deduction = $setterValue;
                    $backendSettings = DeductionAlert::create($data3);
                }
                // $this->updateDeduction($setterId,3,$deduction);

            }

            if ($setter2Id != null) {
                $setter2Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setter2Id])->first();
                $setter2Commission = ($setterCommission / 2);
                $setter2Value = ($setter2Commission - $dataSale->setter2_commission);
                $data4 = [
                    'pid' => $checked->pid,
                    'user_id' => $setter2Id,
                    'position_id' => 3,
                    'amount' => $setter2Value,
                    'status' => ($setter2Value >= 0) ? 'Positive' : 'Negative',
                ];
                if ($setter2Deduction) {
                    $deduction = ($setter2Value - $setter2Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $setter2Deduction->id)->update($data4);
                } else {
                    $deduction = $setter2Value;
                    $backendSettings = DeductionAlert::create($data4);
                }
                // $this->updateDeduction($setter2Id,3,$deduction);
            }
        } elseif ($setterId) {
            $setter1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setterId])->first();
            $setter1Commission = $setterCommission;
            $setterValue = ($setter1Commission - $dataSale->setter1_commission);
            $data3 = [
                'pid' => $checked->pid,
                'user_id' => $setterId,
                'position_id' => 3,
                'amount' => $setterValue,
                'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
            ];
            if ($setter1Deduction) {
                $deduction = ($setterValue - $setter1Deduction->amount);
                $updateDeduction = DeductionAlert::where('id', $setter1Deduction->id)->update($data3);
            } else {
                $deduction = $setterValue;
                $backendSettings = DeductionAlert::create($data3);
            }
            // $this->updateDeduction($setterId,3,$deduction);

        }
    }

    // Only For Clawback Commission Updates
    public function addersClawback($userId, $pid, $amount)
    {
        $date = date('Y-m-d');
        if ($userId != null) {
            $user = User::where('id', $userId)->first();

            $clawbackType = 'next payroll';
            $payFrequency = $this->payFrequencyNew($date, $user->sub_position_id, $userId);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

            if (! empty($amount)) {
                $data = [
                    'user_id' => $userId,
                    'position_id' => $user->position_id,
                    'pid' => $pid,
                    'clawback_amount' => $amount,
                    'clawback_type' => $clawbackType,
                    'adders_type' => 'm2 update',
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                ];

                $clawbackSettlement = ClawbackSettlement::where(['user_id' => $userId, 'pid' => $pid, 'adders_type' => 'm2 update', 'is_displayed' => '1', 'type' => 'commission'])->where('status', '!=', 3)->first();
                if (isset($clawbackSettlement) && ! empty($clawbackSettlement)) {
                    ClawbackSettlement::where('id', $clawbackSettlement->id)->update($data);
                } else {
                    ClawbackSettlement::create($data);
                }

                if ($clawbackType == 'next payroll') {
                    updateExistingPayroll($userId, $pay_period_from, $pay_period_to, $amount, 'clawback', $user->position_id, 0);
                }
            }
        }
    }

    public function addersCommission($userId, $pid, $amount, $redline, $customer_signoff, $type)
    {
        $saleData = SalesMaster::where('pid', $pid)->first();
        $companyProfile = CompanyProfile::first();
        $kw = $saleData->kw;
        $netEpc = $saleData->net_epc;
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = $saleData->gross_account_value;
            $netEpc = null;
        }
        $date = $saleData->m2_date;
        if ($userId != null) {
            $user = User::where('id', $userId)->first();
            $payFrequency = $this->payFrequencyNew($date, $user->sub_position_id, $userId);
            $pay_period_from = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_from : $payFrequency->next_pay_period_from;
            $pay_period_to = isset($payFrequency) && ($payFrequency->closed_status == 0) ? $payFrequency->pay_period_to : $payFrequency->next_pay_period_to;

            $redLine = 0;
            $redLineType = null;
            if ($type == 'setter') {
                $redLine = $redline['setter1_redline'];
                $redLineType = ($redline['setter1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['setter_commission_type'] == 'per sale' || $this->commissionData['setter_commission_type'] == 'per kw') {
                    $redLine = $this->commissionData['setter_commission'];
                    $redLineType = $this->commissionData['setter_commission_type'];
                }
            } elseif ($type == 'setter2') {
                $redLine = $redline['setter2_redline'];
                $redLineType = ($redline['setter2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['setter2_commission_type'] == 'per sale' || $this->commissionData['setter2_commission_type'] == 'per kw') {
                    $redLine = $this->commissionData['setter2_commission'];
                    $redLineType = $this->commissionData['setter2_commission_type'];
                }
            } elseif ($type == 'closer') {
                $redLine = $redline['closer1_redline'];
                $redLineType = ($redline['closer1_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['closer_commission_type'] == 'per sale' || $this->commissionData['closer_commission_type'] == 'per kw') {
                    $redLine = $this->commissionData['closer_commission'];
                    $redLineType = $this->commissionData['closer_commission_type'];
                }
            } elseif ($type == 'closer2') {
                $redLine = $redline['closer2_redline'];
                $redLineType = ($redline['closer2_redline_type'] == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed';
                if ($this->commissionData['closer2_commission_type'] == 'per sale' || $this->commissionData['closer2_commission_type'] == 'per kw') {
                    $redLine = $this->commissionData['closer2_commission'];
                    $redLineType = $this->commissionData['closer2_commission_type'];
                }
            }

            if (! empty($amount)) {
                $stopPayroll = ($user->stop_payroll == 1) ? 1 : 0;
                $data = [
                    'user_id' => $userId,
                    'position_id' => $user->position_id,
                    'pid' => $pid,
                    'amount_type' => 'm2 update',
                    'amount' => $amount,
                    'redline' => $redLine,
                    'redline_type' => $redLineType,
                    'net_epc' => $netEpc,
                    'kw' => $kw,
                    'date' => $date,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'is_stop_payroll' => $stopPayroll,
                    'status' => 1,
                ];

                $userCommission = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2 update', 'is_displayed' => '1'])->where('status', '!=', 3)->first();
                if (isset($userCommission) && ! empty($userCommission)) {
                    UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    UserCommission::create($data);
                }
                updateExistingPayroll($userId, $pay_period_from, $pay_period_to, $amount, 'commission', $user->position_id, 0);
            }
        }
    }

    public function overides_clawback($pid, $date, $checkedStatus = 0, $userId = '')
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->when(! empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->delete();
        UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->when(! empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->delete();

        $data = UserOverrides::with('userdata')->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->when(! empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->get();
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        $data->transform(function ($data) use ($date, $pid, $companySetting, $checkedStatus, $approvedDate) {
            $stopPayroll = ($data->userdata->stop_payroll == 1) ? 1 : 0;
            $subPositionId = $data->userdata->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $data->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }
            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $data->user_id);
            }

            $clawbackSettlement = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'during' => $data->during, 'is_displayed' => '1'])->sum('clawback_amount');
            $userOverride = $data->amount;
            $clawbackAmount = number_format($userOverride, 3, '.', '') - number_format($clawbackSettlement, 3, '.', '');

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $data->user_id,
                    'position_id' => $subPositionId,
                    'sale_user_id' => $data->sale_user_id,
                    'pid' => $pid,
                    'clawback_amount' => number_format($clawbackAmount, 3, '.', ''),
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'type' => 'overrides',
                    'adders_type' => $data->type,
                    'during' => $data->during,
                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => $checkedStatus,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($data->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        });

        $data = UserOverrides::with('userdata')->where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->when(! empty($userId), function ($q) use ($userId) {
            $q->where('sale_user_id', $userId);
        })->get();
        $data->transform(function ($data) use ($date, $pid, $companySetting, $checkedStatus, $approvedDate) {
            $stopPayroll = ($data->userdata->stop_payroll == 1) ? 1 : 0;
            $subPositionId = $data->userdata->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $data->userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }
            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $data->user_id);
            }

            $clawbackAmount = 0;
            $reconPaid = ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $data->user_id, 'overrider' => $data->sale_user_id, 'type' => $data->type, 'during' => $data->during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
            if ($reconPaid) {
                $closer1PaidClawback = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'sale_user_id' => $data->sale_user_id, 'type' => 'overrides', 'adders_type' => $data->type, 'during' => $data->during, 'is_displayed' => '1'])->sum('clawback_amount');
                $userOverride = $reconPaid;
                $clawbackAmount = number_format($userOverride, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');
            } else {
                $data->delete();
            }

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $data->user_id,
                    'position_id' => $subPositionId,
                    'sale_user_id' => $data->sale_user_id,
                    'pid' => $pid,
                    'clawback_amount' => number_format($clawbackAmount, 3, '.', ''),
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'type' => 'overrides',
                    'adders_type' => $data->type,
                    'during' => $data->during,
                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => $checkedStatus,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($data->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        });
    }

    public function clawbackSalesData($closerId, $checked)
    {
        $date = date('Y-m-d');
        $approvedDate = isset($checked->customer_signoff) ? $checked->customer_signoff : null;
        $pid = $checked->pid;
        $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->delete();
        $userCommissions = UserCommission::with('userdata')->where(['pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closer->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => '1', 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
                $pay_period_from = null;
                $pay_period_to = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                $pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
            $commission = $userCommission->amount;
            $clawbackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawbackAmount,
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'during' => $during,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => 1,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($userCommission->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        }

        UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->delete();
        $userCommissions = UserCommission::where(['pid' => $pid, 'user_id' => $closerId, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->get();
        foreach ($userCommissions as $userCommission) {
            $closer = $userCommission->userdata;
            $stopPayroll = ($closer->stop_payroll == 1) ? 1 : 0;

            $subPositionId = $closer->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $closer->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $subPositionId, 'status' => '1', 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
                $pay_period_from = null;
                $pay_period_to = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $userCommission->user_id);
                $pay_period_from = isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null;
                $pay_period_to = isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null;
            }

            $during = $userCommission->amount_type;
            if ($userCommission->amount_type == 'm1') {
                $during = 'm2';
            }

            $clawbackAmount = 0;
            $reconPaid = ReconCommissionHistory::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if ($reconPaid) {
                $closer1PaidClawback = ClawbackSettlement::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => 'commission', 'adders_type' => $userCommission->amount_type, 'during' => $during, 'is_displayed' => '1'])->sum('clawback_amount');
                $commission = $reconPaid;
                $clawbackAmount = number_format($commission, 3, '.', '') - number_format($closer1PaidClawback, 3, '.', '');
            } else {
                $userCommission->delete();
            }

            if ($clawbackAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $subPositionId,
                    'pid' => $checked->pid,
                    'clawback_amount' => $clawbackAmount,
                    'clawback_type' => $clawbackType,
                    'status' => $clawbackType == 'reconciliation' ? 3 : 1,
                    'adders_type' => $userCommission->amount_type,
                    'during' => $during,
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                    'is_stop_payroll' => $stopPayroll,
                    'clawback_status' => 1,
                ]);

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($userCommission->user_id, $subPositionId, $clawbackAmount, $payFrequency, $pid);
                }
            }
        }
        $this->overides_clawback_data($closerId, $pid, $date);

        $clawbackCommissions = \App\Models\ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closerId, 'type' => 'commission', 'is_displayed' => '1', 'clawback_status' => '1'])->get();
        foreach ($clawbackCommissions as $clawbackCommission) {
            $clawbackCommission->is_displayed = '0';
            $clawbackCommission->save();

            $commission = UserCommission::where(['user_id' => $clawbackCommission->user_id, 'amount_type' => $clawbackCommission->adders_type, 'pid' => $pid, 'is_displayed' => '1'])->first();
            if ($commission) {
                if ($commission->settlement_type == 'reconciliation') {
                    $reconPaid = ReconCommissionHistory::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => $userCommission->amount_type, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
                    if ($reconPaid) {
                        UserCommission::where(['user_id' => $clawbackCommission->user_id, 'amount_type' => $clawbackCommission->adders_type, 'pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->update(['is_displayed' => '0']);
                        ReconCommissionHistory::where(['user_id' => $userCommission->user_id, 'pid' => $pid, 'type' => $userCommission->amount_type, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                    }
                } else {
                    UserCommission::where(['user_id' => $clawbackCommission->user_id, 'amount_type' => $clawbackCommission->adders_type, 'pid' => $pid, 'settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                }
            }
        }

        $clawbackOverrides = \App\Models\ClawbackSettlement::where(['pid' => $pid, 'sale_user_id' => $closerId, 'type' => 'overrides', 'is_displayed' => '1', 'clawback_status' => '1'])->get();
        foreach ($clawbackOverrides as $clawbackOverride) {
            $clawbackOverride->is_displayed = '0';
            $clawbackOverride->save();

            $override = UserOverrides::where(['user_id' => $clawbackOverride->user_id, 'sale_user_id' => $clawbackOverride->sale_user_id, 'pid' => $pid, 'type' => $clawbackOverride->adders_type, 'is_displayed' => '1'])->first();
            if ($override) {
                if ($override->overrides_settlement_type == 'reconciliation') {
                    $reconPaid = ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $clawbackOverride->user_id, 'overrider' => $clawbackOverride->sale_user_id, 'type' => $clawbackOverride->adders_type, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
                    if ($reconPaid) {
                        UserOverrides::where(['user_id' => $clawbackOverride->user_id, 'sale_user_id' => $clawbackOverride->sale_user_id, 'pid' => $pid, 'type' => $clawbackOverride->adders_type, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->update(['is_displayed' => '0']);
                        ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $clawbackOverride->user_id, 'overrider' => $clawbackOverride->sale_user_id, 'type' => $clawbackOverride->adders_type, 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                    }
                } else {
                    UserOverrides::where(['user_id' => $clawbackOverride->user_id, 'sale_user_id' => $clawbackOverride->sale_user_id, 'pid' => $pid, 'type' => $clawbackOverride->adders_type, 'overrides_settlement_type' => 'during_m2', 'status' => '3', 'is_displayed' => '1'])->update(['is_displayed' => '0']);
                }
            }
        }
    }

    public function overides_clawback_data($userId, $pid, $date)
    {
        $this->overides_clawback($pid, $date, 1, $userId);
    }

    public function m2updateRemoved($checked)
    {
        $pid = $checked->pid;
        UserCommission::where(['pid' => $pid, 'amount_type' => 'm2 update', 'status' => '1', 'is_displayed' => '1'])->delete();
        UserOverrides::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'type' => 'Stack', 'during' => 'm2 update'])->delete();
        ClawbackSettlement::where(['pid' => $pid, 'status' => '1', 'is_displayed' => '1', 'adders_type' => 'Stack', 'during' => 'm2 update'])->delete();
    }

    public function upfrontTypePercentCalculation($checked)
    {
        $companyProfile = CompanyProfile::where('id', 1)->first();
        $commission = [
            'setter_commission' => 0,
            'setter2_commission' => 0,
            'closer_commission' => 0,
            'closer2_commission' => 0,
        ];

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            return $this->upfrontTypePercentCalculationForFlex($checked);
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            return $this->upfrontTypePercentCalculationForPest($checked);
        }

        return $commission;
    }

    public function upfrontTypePercentCalculationForFlex($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $approvedDate = $checked->customer_signoff;

        $saleUsers = [];
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

        $companyMargin = CompanyProfile::first();
        $redline = $this->subroutineSix($checked);

        $setterCommission = 0;
        $setter2Commission = 0;
        $closerCommission = 0;
        $closer2Commission = 0;
        if ($setterId != null && $setter2Id != null) {
            $setter = User::where('id', $setterId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $setter = $organizationHistory;
            }

            $commission_percentage = 0;
            $commission_type = null;
            if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $setter2 = $organizationHistory2;
            }

            $commission_percentage2 = 0;
            $commission_type2 = null;
            if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);

                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $setter1_commission = $commission_percentage * $x * 0.5;
                } else {
                    $setter1_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $setter2_commission = $commission_percentage2 * $x * 0.5;
                } else {
                    $setter2_commission = ((($netEpc - $redline['setter2_redline']) * $x) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $setter1_commission = $commission_percentage * 0.5;
                } else {
                    $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $setter2_commission = $commission_percentage2 * 0.5;
                } else {
                    $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            }
            $setterCommission = $setter1_commission;
            $setter2Commission = $setter2_commission;
        } elseif ($setterId) {
            if ($closerId != $setterId) {
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $commission_percentage = 0;
                $commission_type = null;
                if ($organizationHistory && $organizationHistory->self_gen_accounts == 1 && $organizationHistory->position_id == 2) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $setterCommission = (($kw * $commission_percentage) * $x);
                    } elseif ($commission_type == 'per sale') {
                        $setterCommission = $commission_percentage * $x;
                    } else {
                        $setterCommission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $setterCommission = ($kw * $commission_percentage);
                    } elseif ($commission_type == 'per sale') {
                        $setterCommission = $commission_percentage;
                    } else {
                        $setterCommission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);
                    }
                }
            }
        }

        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            $commission_percentage = 0;
            $commission_type = null;
            if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $closer2 = $organizationHistory2;
            }

            $commission_percentage2 = 0;
            $commission_type2 = null;
            if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $closer1_commission = $commission_percentage * $x * 0.5;
                } else {
                    $closer1_commission = (((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $closer2_commission = $commission_percentage2 * $x * 0.5;
                } else {
                    $closer2_commission = (((($netEpc - $redline['closer2_redline']) * $x) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * 0.5);
                } elseif ($commission_type == 'per sale') {
                    $closer1_commission = $commission_percentage * 0.5;
                } else {
                    $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * 0.5);
                } elseif ($commission_type2 == 'per sale') {
                    $closer2_commission = $commission_percentage2 * 0.5;
                } else {
                    $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            }

            $closerCommission = $closer1_commission;
            $closer2Commission = $closer2_commission;
        } elseif ($closerId) {
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory && $organizationHistory->self_gen_accounts == '1' && $closerId == $setterId) {
                $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                    $commission_type = $commissionSelfgen->commission_type;
                    $commission_percentage = $commissionSelfgen->commission;
                } else {
                    $commission_percentage = 100;
                    $commission_type = null;
                }
            } else {
                $commission_percentage = 0;
                $commission_type = null;
                if ($organizationHistory && $organizationHistory->self_gen_accounts == 1 && $organizationHistory->position_id == 3) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closerCommission = (($kw * $commission_percentage) * $x);
                } elseif ($commission_type == 'per sale') {
                    $closerCommission = $commission_percentage * $x;
                } else {
                    $closerCommission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closerCommission = ($kw * $commission_percentage);
                } elseif ($commission_type == 'per sale') {
                    $closerCommission = $commission_percentage;
                } else {
                    $closerCommission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);
                }
            }
        }

        return [
            'setter_commission' => $setterCommission,
            'setter2_commission' => $setter2Commission,
            'closer_commission' => $closerCommission,
            'closer2_commission' => $closer2Commission,
        ];
    }

    public function upfrontTypePercentCalculationForPest($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $approvedDate = $checked->customer_signoff;
        $grossAmountValue = $checked->gross_account_value;

        $saleUsers = [];
        if ($closerId) {
            $saleUsers[] = $closerId;
        }
        if ($closer2Id) {
            $saleUsers[] = $closer2Id;
        }

        $closerCommission = 0;
        $closer2Commission = 0;
        $companyMargin = CompanyProfile::first();
        if ($closerId && $closer2Id != null) {
            $commissionPercentage = 0;
            $commissionPercentageType = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commissionPercentageType = $commissionHistory->commission_type;
            }

            $commissionPercentage2 = 0;
            $commissionPercentageType2 = null;
            $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
                $commissionPercentageType2 = $commission2History->commission_type;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);

                if ($commissionPercentageType == 'percent') {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100 / 2);
                } elseif ($commissionPercentageType == 'per sale') {
                    $closer1Commission = ($commissionPercentage * $x) / 2;
                }

                if ($commissionPercentageType2 == 'percent') {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100 / 2);
                } elseif ($commissionPercentageType2 == 'per sale') {
                    $closer2Commission = ($commissionPercentage2 * $x) / 2;
                }
            } else {
                if ($commissionPercentageType == 'percent') {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100 / 2);
                } elseif ($commissionPercentageType == 'per sale') {
                    $closer1Commission = ($commissionPercentage) / 2;
                }

                if ($commissionPercentageType2 == 'percent') {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100 / 2);
                } elseif ($commissionPercentageType2 == 'per sale') {
                    $closer2Commission = ($commissionPercentage2) / 2;
                }
            }

            $closerCommission = $closer1Commission;
            $closer2Commission = $closer2Commission;
        } elseif ($closerId) {
            $commissionPercentage = 0;
            $commissionPercentageType = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
                $commissionPercentageType = $commissionHistory->commission_type;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);

                if ($commissionPercentageType == 'percent') {
                    $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } elseif ($commissionPercentageType == 'per sale') {
                    $closerCommission = $commissionPercentage * $x;
                }
            } else {
                if ($commissionPercentageType == 'percent') {
                    $closerCommission = $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
                } elseif ($commissionPercentageType == 'per sale') {
                    $closerCommission = $commissionPercentage;
                }
            }
        }

        return [
            'setter_commission' => 0,
            'setter2_commission' => 0,
            'closer_commission' => $closerCommission,
            'closer2_commission' => $closer2Commission,
        ];
    }
}
