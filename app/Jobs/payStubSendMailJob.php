<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\API\ManagerReport\ManagerReportsControllerV1;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\CustomFieldHistory;
use App\Models\GetPayrollData;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\PositionReconciliations;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
use App\Models\W2PayrollTaxDeduction;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class payStubSendMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailNotificationTrait, EvereeTrait, PayFrequencyTrait, PushNotificationTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $data;

    public $start_date;

    public $end_date;

    public $timeout = 600; // 10 minutes

    public $tries = 3;

    public function __construct($data, $start_date, $end_date)
    {
        $this->data = $data;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = $this->data;
        $start_date = $this->start_date;
        $end_date = $this->end_date;

        $userId = $data;
        if (! empty($userId)) {
            $allUsersDetails = $this->generatePdfAndSendMail($userId, $start_date, $end_date);
        }

    }

    // create pdf
    public function generatePdfAndSendMail($userId, $start_date = '2025-09-23', $end_date = '2025-09-29')
    {
        // ---------------  Genrete pdf -----------------------
        $newData['CompanyProfile'] = CompanyProfile::first();
        $managerReportsController = new ManagerReportsControllerV1;
        $getTotalCalculations = $managerReportsController->getTotalnetPayAmount($userId, $start_date, $end_date);
        $newData['pay_stub']['net_ytd'] = $getTotalCalculations['net_ytd'];
        $newData['pay_stub']['net_pay'] = $getTotalCalculations['net_pay'];

        $newData['earnings']['commission']['period_total'] = $getTotalCalculations['userCommissionSum'];
        $newData['earnings']['commission']['ytd_total'] = $getTotalCalculations['userCommissionSumYtd'];

        $newData['earnings']['overrides']['ytd_total'] = $getTotalCalculations['userOverrideSumYtd'];
        $newData['earnings']['overrides']['period_total'] = $getTotalCalculations['userOverrideSum'];

        $newData['miscellaneous']['adjustment']['ytd_total'] = $getTotalCalculations['adjustmentYtd'];
        $newData['miscellaneous']['adjustment']['period_total'] = $getTotalCalculations['adjustment'];

        $newData['miscellaneous']['reimbursement']['ytd_total'] = $getTotalCalculations['reimbursementYtd'];
        $newData['miscellaneous']['reimbursement']['period_total'] = $getTotalCalculations['reimbursement'];

        $newData['miscellaneous']['Total additional values']['period_total'] = $getTotalCalculations['customFieldSum'];
        $newData['miscellaneous']['Total additional values']['ytd_total'] = $getTotalCalculations['customFieldSumYtd'];

        $newData['pay_stub']['periodeCustomeFieldsSum'] = $getTotalCalculations['customFieldSum'];
        $newData['pay_stub']['ytdCustomeFieldsSum'] = $getTotalCalculations['customFieldSumYtd'];

        $newData['pay_stub']['net_pay_original'] = $getTotalCalculations['net_pay'] - $getTotalCalculations['customFieldSum'];
        $newData['pay_stub']['net_ytd_original'] = $getTotalCalculations['net_ytd'] - $getTotalCalculations['customFieldSumYtd'];

        $newData['deduction']['standard_deduction']['period_total'] = $getTotalCalculations['deductionSum'];
        $newData['deduction']['standard_deduction']['ytd_total'] = $getTotalCalculations['deductionSumYtd'];

        $newData['id'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('id');
        $payroll_id = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('payroll_id');
        $newData['pay_stub']['pay_date'] = date('Y-m-d', strtotime(PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('created_at')));
        $newData['pay_stub']['pay_period_from'] = $start_date;
        $newData['pay_stub']['pay_period_to'] = $end_date;
        $newData['pay_stub']['period_sale_count'] = UserCommissionLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $newData['pay_stub']['ytd_sale_count'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id', 'entity_type', 'business_ein', 'business_name')->first();
        $newData['employee'] = $user;
        $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');
        $newData['earnings']['reconciliation']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->sum('reconciliation');
        $newData['earnings']['reconciliation']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('reconciliation');

        $hourlySalarySum = PayrollHourlySalaryLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('total');
        $overtimeSum = PayrollOvertimeLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('total');
        $hourlySalarySumYtd = PayrollHourlySalaryLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('payroll_id', '!=', 0)->sum('total');
        $overtimeSumYtd = PayrollOvertimeLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('payroll_id', '!=', 0)->sum('total');
        $newData['earnings']['wages']['period_total'] = ($hourlySalarySum + $overtimeSum);
        $newData['earnings']['wages']['ytd_total'] = ($hourlySalarySumYtd + $overtimeSumYtd);

        // Commented out old fica tax and added new taxes section to show all 5 taxes.
        // $w2taxDeduction = W2PayrollTaxDeduction::select(DB::raw('SUM(fica_tax) as fica_tax, SUM(medicare_withholding) as medicare_withholding, SUM(social_security_withholding) as social_security_withholding'))->where(['user_id'=>$userId, 'pay_period_from'=>$start_date, 'pay_period_to'=>$end_date])->first();
        // $w2taxDeductionytd = W2PayrollTaxDeduction::select(DB::raw('SUM(fica_tax) as fica_tax, SUM(medicare_withholding) as medicare_withholding, SUM(social_security_withholding) as social_security_withholding'))->where(['user_id'=> $userId])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->first();

        // $newData['deduction']['fica_tax']['period_total'] = isset($w2taxDeduction->fica_tax) ? $w2taxDeduction->fica_tax : 0;
        // $newData['deduction']['fica_tax']['ytd_total'] = isset($w2taxDeductionytd->fica_tax) ? $w2taxDeductionytd->fica_tax : 0;

        $w2taxDeduction = W2PayrollTaxDeduction::select(DB::raw('SUM(state_income_tax) as state_income_tax, SUM(federal_income_tax) as federal_income_tax, SUM(medicare_tax) as medicare_tax, SUM(social_security_tax) as social_security_tax, SUM(additional_medicare_tax) as additional_medicare_tax'))->where(['user_id' => $userId, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->first();
        $w2taxDeductionytd = W2PayrollTaxDeduction::select(DB::raw('SUM(state_income_tax) as state_income_tax, SUM(federal_income_tax) as federal_income_tax, SUM(medicare_tax) as medicare_tax, SUM(social_security_tax) as social_security_tax, SUM(additional_medicare_tax) as additional_medicare_tax'))->where(['user_id' => $userId])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->first();

        $newData['taxes']['state_income_tax']['period_total'] = isset($w2taxDeduction->state_income_tax) ? $w2taxDeduction->state_income_tax : 0;
        $newData['taxes']['state_income_tax']['ytd_total'] = isset($w2taxDeductionytd->state_income_tax) ? $w2taxDeductionytd->state_income_tax : 0;

        $newData['taxes']['federal_income_tax']['period_total'] = isset($w2taxDeduction->federal_income_tax) ? $w2taxDeduction->federal_income_tax : 0;
        $newData['taxes']['federal_income_tax']['ytd_total'] = isset($w2taxDeductionytd->federal_income_tax) ? $w2taxDeduction->federal_income_tax : 0;

        $newData['taxes']['medicare_tax']['period_total'] = isset($w2taxDeduction->medicare_tax) ? $w2taxDeduction->medicare_tax : 0;
        $newData['taxes']['medicare_tax']['ytd_total'] = isset($w2taxDeductionytd->medicare_tax) ? $w2taxDeduction->medicare_tax : 0;

        $newData['taxes']['social_security_tax']['period_total'] = isset($w2taxDeduction->social_security_tax) ? $w2taxDeduction->social_security_tax : 0;
        $newData['taxes']['social_security_tax']['ytd_total'] = isset($w2taxDeductionytd->social_security_tax) ? $w2taxDeduction->social_security_tax : 0;

        $newData['taxes']['additional_medicare_tax']['period_total'] = isset($w2taxDeduction->additional_medicare_tax) ? $w2taxDeduction->additional_medicare_tax : 0;
        $newData['taxes']['additional_medicare_tax']['ytd_total'] = isset($w2taxDeductionytd->additional_medicare_tax) ? $w2taxDeduction->additional_medicare_tax : 0;

        // ------------------------------------

        $requestData = [
            'id' => $payroll_id,
            'user_id' => $userId,
            'pay_period_from' => $start_date,
            'pay_period_to' => $end_date,
        ];
        $commission_details_lock = $this->payStubCommissionDetails($requestData);
        $override_details_lock = $this->payStubOverrideDetails($requestData);
        $adjustment_details_lock = $this->payStubAdjustmentDetails($requestData);
        $reimbursement_details_lock = $this->payStubReimbursementDetails($requestData);

        $deductions_details_lock = $this->payStubDeductionsDetails($requestData);
        $additional_value_details_lock = $this->additionalValueDetails($requestData);
        $wages_details_lock = $this->payStubWagesDetails($requestData);

        // ----------------- create pdf of user information--------------------------
        $uniqueTime = time();
        $pdfPath = public_path('/template/'.$user->first_name.'_'.$user->last_name.'_'.$uniqueTime.'_pay_stub.pdf');
        $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
        $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
        if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
            $image_file_path = $s3_bucket_public_url.config('app.domain_name');
            $file_link = $image_file_path.'/'.$newData['CompanyProfile']->logo;
            $newData['CompanyProfile']['logo'] = $file_link;
        } else {
            $newData['CompanyProfile']['logo'] = $newData['CompanyProfile']->logo;
        }
        $data = [
            'user' => $user,
            'email' => $user->email,
            'start_date' => $user->startDate,
            'end_date' => $user->endDate,
            'path' => $pdfPath,
            'data' => $newData,
            'commission_details' => $commission_details_lock,
            'override_details' => $override_details_lock,
            'adjustment_details' => $adjustment_details_lock,
            'reimbursement_details' => $reimbursement_details_lock,
            'deductions_details' => $deductions_details_lock,
            'additional_value_details' => $additional_value_details_lock,
            'wages_value_details' => $wages_details_lock,
        ];

        $pdf = Pdf::loadView('mail.paystub_available', [
            'user' => $user,
            'email' => $user->email,
            'start_date' => $user->startDate,
            'end_date' => $user->endDate,
            'path' => $pdfPath,
            'data' => $newData,
            'commission_details' => $commission_details_lock,
            'override_details' => $override_details_lock,
            'adjustment_details' => $adjustment_details_lock,
            'reimbursement_details' => $reimbursement_details_lock,
            'deductions_details' => $deductions_details_lock,
            'additional_value_details' => $additional_value_details_lock,
            'wages_value_details' => $wages_details_lock,
        ]);
        /* return view('mail.paystub_available', [
            'user' => $user,
            'email' => $user->email,
            'start_date' => $user->startDate,
            'end_date' => $user->endDate,
            'path' => $pdfPath,
            'data' => $newData,
            'commission_details' => $commission_details_lock,
            'override_details' => $override_details_lock,
            'adjustment_details' => $adjustment_details_lock,
            'reimbursement_details' => $reimbursement_details_lock,
        ]); */

        $pdf->save($pdfPath);

        $filePath = config('app.domain_name').'/'.'paystyb/'.$user->first_name.'_'.$user->last_name.'_'.time().'_pay_stub.pdf';
        $s3Data = s3_upload($filePath, $pdfPath, true, 'public');
        $s3filePath = config('app.aws_s3bucket_url').'/'.$filePath;
        // ----------------- end create pdf of user information--------------------------

        // ------------------- email sending to the users ---------------------
        $userMailName = preg_replace('/[^a-zA-Z0-9\s]/', '', $user->first_name).'-'.preg_replace('/[^a-zA-Z0-9\s]/', '', $user->last_name);
        $finalize['email'] = $user->email;
        $finalize['subject'] = 'New Paystub Available';
        $finalize['template'] = view('mail.executeUser', compact('newData', 'user', 'start_date', 'end_date', 's3filePath'));
        if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod') {
            $mailSent = $this->sendEmailNotification($finalize);
        }
        // ---------------------- End email sending to the users-------------------------
    }

    // breckdown methods start
    private function payStubCommissionDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($Payroll)) {

            if ($Payroll->status == 3) {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', $Payroll->status)->where(['user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            } else {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', '<', '3')->where(['user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            }

            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' => 'commission', 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            // return $clawbackSettlement;
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->first();

                    if ($value->amount_type == 'm1') {
                        $date = isset($value->saledata->m1_date) ? $value->saledata->m1_date : '';
                    } else {
                        $date = isset($value->saledata->m2_date) ? $value->saledata->m2_date : '';
                    }
                    $data[] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'is_mark_paid' => $value->is_mark_paid,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                        'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
                        // 'rep_redline' => isset($value->userdata->redline) ? $value->userdata->redline : null,
                        'rep_redline' => isset($value->redline) ? $value->redline : null,
                        'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                        'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        // 'date' => isset($value->date) ? $value->date : null,
                        'date' => isset($date) ? $date : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'amount_type' => isset($value->amount_type) ? $value->amount_type : null,
                        'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

                    ];
                }
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $data[] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'is_mark_paid' => $val->is_mark_paid,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }
    }

    private function payStubOverrideDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            if ($Payroll->status == 3) {
                $userdata = UserOverridesLock::where('status', $Payroll->status)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            } else {
                $userdata = UserOverridesLock::where('status', '<', '3')->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            }

            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' => 'overrides', 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();

            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                    $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                    $sale = SalesMaster::where(['pid' => $value->pid])->first();
                    $sub_total = ($sub_total + $value->amount);
                    $data[] = [
                        'id' => $value->sale_user_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'pid' => $value->pid,
                        'first_name' => isset($user->first_name) ? $user->first_name : null,
                        'last_name' => isset($user->last_name) ? $user->last_name : null,
                        'position_id' => isset($user->position_id) ? $user->position_id : null,
                        'sub_position_id' => isset($user->sub_position_id) ? $user->sub_position_id : null,
                        'is_super_admin' => isset($user->is_super_admin) ? $user->is_super_admin : null,
                        'is_manager' => isset($user->is_manager) ? $user->is_manager : null,
                        'image' => isset($user->image) ? $user->image : null,
                        'type' => isset($value->type) ? $value->type : null,
                        'accounts' => 1,
                        'kw_installed' => $value->kw,
                        'total_amount' => $value->amount,
                        'override_type' => $value->overrides_type,
                        'override_amount' => $value->overrides_amount,
                        'calculated_redline' => $value->calculated_redline,
                        'state' => isset($user->state) ? $user->state->state_code : null,
                        'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
                        'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                        'amount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    ];
                }
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $data['data'][] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'is_mark_paid' => $val->is_mark_paid,

                        'first_name' => isset($val->users->first_name) ? $val->users->first_name : null,
                        'last_name' => isset($val->users->last_name) ? $val->users->last_name : null,
                        'position_id' => isset($val->users->position_id) ? $val->users->position_id : null,
                        'sub_position_id' => isset($val->users->sub_position_id) ? $val->users->sub_position_id : null,
                        'is_super_admin' => isset($val->users->is_super_admin) ? $val->users->is_super_admin : null,
                        'is_manager' => isset($val->users->is_manager) ? $val->users->is_manager : null,
                        'image' => isset($val->users->image) ? $val->users->image : null,
                        'type' => 'clawback',

                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'product' => isset($value->salesDetail->product) ? $value->salesDetail->product : null,
                        'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
                        'service_schedule' => isset($value->salesDetail->service_schedule) ? $value->salesDetail->service_schedule : null,
                    ];
                    // $subtotal = ($sub_total - $val->clawback_amount);
                }
                // $data['sub_total'] = $subtotal;
            }

            return $data;

        } else {
            return $data;
        }
    }

    private function payStubAdjustmentDetails($request)
    {
        // echo"asd";die;
        $data = [];

        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($payroll)) {
            $adjustment = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
            $adjustmentNegative = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

            if (count($adjustment) > 0) {
                foreach ($adjustment as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            if (count($adjustmentNegative) > 0) {
                foreach ($adjustmentNegative as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            //  /// Added by Gorakh
            $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->pluck('payroll_id');
            $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::whereIn('payroll_id', $PayrollHistoryPayrollIDs)->where(['user_id' => $payroll->user_id])->get();
            // dd($PayrollAdjustmentDetail);
            if (count($PayrollAdjustmentDetail) > 0) {
                foreach ($PayrollAdjustmentDetail as $key => $value) {
                    $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                        $is_mark_paid = 1;

                    } else {
                        $is_mark_paid = 0;
                    }
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => 'Super',
                        'last_name' => 'Admin',
                        'image' => null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => $value->payroll_type,
                        'description' => $value->comment,
                        'is_mark_paid' => $is_mark_paid,

                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }

    }

    public function payStubReimbursementDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequestLock::with('user', 'approvedBy')->where('status', 'Paid')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }

    }

    public function payStubDeductionsDetails($request)
    {

        $data = [];
        $id = $request['id']; // payroll_id
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollDeductionLock::with('costcenter')
                ->leftjoin('payroll_adjustment_details_lock', function ($join) {
                    $join->on('payroll_adjustment_details_lock.payroll_id', '=', 'payroll_deduction_locks.payroll_id')
                        ->on('payroll_adjustment_details_lock.cost_center_id', '=', 'payroll_deduction_locks.cost_center_id');
                })
                ->where('payroll_deduction_locks.user_id', $user_id)
                ->where('payroll_deduction_locks.payroll_id', $id)
                ->where('payroll_deduction_locks.is_next_payroll', 0)
                ->select('payroll_deduction_locks.*', 'payroll_adjustment_details_lock.amount as adjustment_amount')
                ->get();

            $subtotal = 0;
            foreach ($paydata as $d) {
                if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0) {
                    $subtotal += $d->total;
                }
                $subtotal = $d->subtotal;
                $data[] = [
                    'id' => $d->id,
                    'payroll_id' => $d->payroll_id,
                    'is_mark_paid' => $d->is_mark_paid,
                    'is_next_payroll' => $d->is_next_payroll,
                    'Type' => $d->costcenter->name,
                    'Amount' => $d->amount,
                    'Limit' => $d->limit,
                    'Total' => $d->total,
                    'Outstanding' => $d->outstanding,
                    'cost_center_id' => $d->cost_center_id,
                    'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                ];
            }

            return $data;
        } else {
            return $data;
        }
    }

    public function additionalValueDetails($request)
    {
        $customeFields = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];
        $custom_field_id = $request['custom_field_id'] ?? null;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {
            if ($custom_field_id != null && $custom_field_id != 0) {
                $customeFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->where('column_id', $custom_field_id)->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            } else {
                $customeFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->get();
            }
            $customeFields->transform(function ($customeFields) {
                $date = $customeFields->updated_at != null ? \Carbon\Carbon::parse($customeFields->updated_at)->format('m/d/Y') : \Carbon\Carbon::parse($customeFields->created_at)->format('m/d/Y');

                $approved_by_detail = [];
                if ($customeFields->getApprovedBy != null) {
                    if (isset($customeFields->getApprovedBy->image) && $customeFields->getApprovedBy->image != null) {
                        $image = s3_getTempUrl(config('app.domain_name').'/'.$customeFields->getApprovedBy->image);
                    } else {
                        $image = null;
                    }
                    $approved_by_detail = [
                        'first_name' => $customeFields->getApprovedBy->first_name,
                        'middle_name' => $customeFields->getApprovedBy->middle_name,
                        'last_name' => $customeFields->getApprovedBy->last_name,
                        'image' => $image,
                    ];
                }

                return [
                    'id' => $customeFields->id,
                    'custom_field_id' => $customeFields->column_id,
                    'amount' => isset($customeFields->value) ? ($customeFields->value) : 0,
                    'type' => $customeFields->getColumn->field_name ?? '',
                    'date' => $date,
                    'comment' => $customeFields->comment,
                    'adjustment_by' => $customeFields->approved_by,
                    'adjustment_by_detail' => $approved_by_detail,
                ];
            });

            return $customeFields;
        } else {
            return $customeFields;
        }
    }

    public function payStubWagesDetails($request)
    {
        $data = [];
        $payroll_id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = PayrollHistory::where(['payroll_id' => $payroll_id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollHourlySalaryLock::with('userdata')
                ->leftjoin('payroll_overtimes_lock', function ($join) {
                    $join->on('payroll_overtimes_lock.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                        ->on('payroll_overtimes_lock.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                })
                ->leftjoin('payroll_adjustment_details', function ($join) {
                    $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                        ->on('payroll_adjustment_details.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                })
                ->where('payroll_hourly_salary_lock.user_id', $payroll->user_id)
                ->where('payroll_hourly_salary_lock.payroll_id', $payroll_id)
                ->where('payroll_hourly_salary_lock.is_next_payroll', 0)
                ->select('payroll_hourly_salary_lock.*', 'payroll_overtimes_lock.overtime', 'payroll_overtimes_lock.total as overtime_total', 'payroll_adjustment_details.amount as adjustment_amount')
                ->get();

            $total = 0;
            $subtotal = 0;
            $totalSeconds = 0;
            $totalHours = 0;
            $totalOvertime = 0;
            foreach ($paydata as $d) {

                $total += ($d->total + $d->overtime_total);
                $subtotal += $total;

                // if (!empty($d->overtime)) {
                //     $totalOvertime = $totalOvertime + $d->overtime;
                // }

                $data[] = [
                    'id' => $d->id,
                    'payroll_id' => $d->payroll_id,
                    'is_mark_paid' => $d->is_mark_paid,
                    'is_next_payroll' => $d->is_next_payroll,
                    'date' => $d->date,
                    'hourly_rate' => $d->hourly_rate,
                    'overtime_rate' => $d->overtime_rate,
                    'salary' => $d->salary,
                    'regular_hours' => $d->regular_hours,
                    'overtime' => $d->overtime,
                    'total' => $total,
                    'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                ];
            }

            return $data;

        } else {

            return $data;

        }

    }
}
