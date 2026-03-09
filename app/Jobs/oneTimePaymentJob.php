<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\API\ManagerReport\ManagerReportsControllerV1;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\Notification;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PositionReconciliations;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\sendEventToPusher;

class oneTimePaymentJob implements ShouldQueue
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

    public $pay_frequency;

    public $open_status_from_bank;

    public $onetimepaymentData;

    public $timeout = 1200; // 10 minutes

    public $tries = 3;

    // Pusher notification properties
    protected int $progress = 0;
    protected string $jobId;
    protected ?string $sessionKey = null;
    protected array $stageBoundaries = [
        'init' => 20,
        'notification' => 50,
        'pdf_generation' => 80,
        'complete' => 100,
    ];

    public function __construct($data, $start_date, $end_date, $pay_frequency, $open_status_from_bank, $onetimepaymentData)
    {
        // $this->onQueue('onetimepayment');
        $this->onQueue('payroll');
        Log::info('Full Payroll Query: ');
        $this->data = json_decode($data);
        $this->onetimepaymentData = $onetimepaymentData;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->pay_frequency = $pay_frequency;
        $this->open_status_from_bank = $open_status_from_bank;
        Log::info('Full Payroll Query: 00000000');

        // Initialize Pusher notification tracking
        $this->jobId = uniqid('onetimepay_', true);
        $this->sessionKey = request()->header('X-Session-Key') ?? session()->getId();
        // 'one_time_payment_id' => $this->onetimepaymentData ? $this->onetimepaymentData->id : null,
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Full Payroll Query new 9990: '.json_encode($this->data));
        
        // Send initial notification
        $this->updateProgress(
            $this->stageBoundaries['init'],
            'started',
            'Starting one-time payment processing...',
            [
                'user_id' => $this->data->user_id,
                'pay_period_from' => $this->start_date,
                'pay_period_to' => $this->end_date,
                'pay_frequency' => $this->pay_frequency,
            ]
        );

        $data = $this->data;
        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $pay_frequency = $this->pay_frequency;
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        Log::info('Full Payroll Query 000: ');
        $worker_type = null;
        $userData = User::where('id', $data->user_id)->first();
        
        // Progress: Creating notification
        $this->updateProgress(
            $this->stageBoundaries['notification'],
            'processing',
            'Creating notifications...',
            ['stage' => 'notification']
        );

        $note = Notification::create([
            'user_id' => $data->user_id,
            'type' => 'Payroll One Time Payment',
            'description' => 'One Time Payment PayRoll Data',
            'is_read' => 0,
        ]);
        $notificationData = [
            'user_id' => $data->user_id,
            'device_token' => $userData->device_token,
            'title' => 'One Time Payment PayRoll Data.',
            'sound' => 'sound',
            'type' => 'One Time Payment PayRoll',
            'body' => 'Updated One Time Payment PayRoll Data',
        ];
        $this->sendNotification($notificationData);
        Log::info('Full Payroll Query 01: ');
        
        // Progress: Generating PDF
        $this->updateProgress(
            $this->stageBoundaries['pdf_generation'],
            'processing',
            'Generating PDF and sending email...',
            ['stage' => 'pdf_generation']
        );

        $this->generatePdfAndSendMail($userData->id, $start_date, $end_date, $pay_frequency);

        // Complete
        $this->updateProgress(
            $this->stageBoundaries['complete'],
            'completed',
            'One-time payment processed successfully',
            [
                'user_name' => $userData->first_name . ' ' . $userData->last_name,
                'completed_at' => now()->toISOString(),
            ]
        );
    }

    // create pdf
    public function generatePdfAndSendMail($userId, $start_date, $end_date, $pay_frequency)
    {
        // ---------------  Genrete pdf -----------------------
        $newData['CompanyProfile'] = CompanyProfile::first();
        $managerReportsController = new ManagerReportsControllerV1;
        Log::info('Full Payroll Query 02: ');
        $getTotalCalculations = $managerReportsController->getTotalnetPayAmountOTP($userId, $start_date, $end_date, $pay_frequency, $this->onetimepaymentData->id);

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

        $newData['id'] = PayrollHistory::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->where('id', '!=', 0)->value('id');
        $payroll_id = PayrollHistory::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->where('id', '!=', 0)->value('payroll_id');
        $newData['pay_stub']['pay_date'] = date('Y-m-d', strtotime(PayrollHistory::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->where('id', '!=', 0)->value('created_at')));
        $newData['pay_stub']['pay_period_from'] = $start_date;
        $newData['pay_stub']['pay_period_to'] = $end_date;
        $newData['pay_stub']['pay_frequency'] = $pay_frequency;
        $newData['pay_stub']['period_sale_count'] = UserCommissionLock::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $newData['pay_stub']['ytd_sale_count'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id', 'entity_type', 'business_ein', 'business_name')->first();
        $newData['employee'] = $user;
        $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');
        $newData['earnings']['reconciliation']['period_total'] = PayrollHistory::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->sum('reconciliation');
        $newData['earnings']['reconciliation']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('reconciliation');

        $requestData = [
            'id' => $payroll_id,
            'user_id' => $userId,
            'pay_period_from' => $start_date,
            'pay_period_to' => $end_date,
            'pay_frequency' => $pay_frequency,
        ];
        $commission_details_lock = $this->payStubCommissionDetails($requestData);
        $override_details_lock = $this->payStubOverrideDetails($requestData);
        $adjustment_details_lock = $this->payStubAdjustmentDetails($requestData);
        $reimbursement_details_lock = $this->payStubReimbursementDetails($requestData);

        $deductions_details_lock = $this->payStubDeductionsDetails($requestData);
        $additional_value_details_lock = $this->additionalValueDetails($requestData);
        $wages_value_details_lock = $this->payStubWagesDetails($requestData);

        // ----------------- create pdf of user information--------------------------

        Log::info('Full Payroll Query commission_details_lock 03 : '.json_encode($commission_details_lock));

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
            'wages_value_details' => $wages_value_details_lock,
        ];
        Log::info('Full Payroll Query 04: ');

        $pdf = Pdf::loadView('mail.paystub_available_one_time', [
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
            'wages_value_details' => $wages_value_details_lock,
        ]);

        $pdf->save($pdfPath);

        Log::info('Full Payroll Query 05: ');
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
        Log::info('Full Payroll Query 06: ');
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
        $pay_frequency = $request['pay_frequency'];

        $Payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })->where(['id' => $id, 'user_id' => $user_id])->first();
        Log::info('Full Payroll Query cpayrolldata: ');
        if (! empty($Payroll)) {
            $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', 3)->where(['user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();
            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' => 'commission', 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();
            // return $clawbackSettlement;
            Log::info('Full Payroll Query usercommission: ');
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->schema_type, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->first();

                    $saleProduct = SaleProductMaster::where(['pid' => $value->pid, 'type' => $value->schema_type])->first();
                    $date = isset($saleProduct->milestone_date) ? $saleProduct->milestone_date : '';
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
                        'amount_type' => isset($value->schema_name) ? $value->schema_name : null,
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
        $pay_frequency = $request['pay_frequency'];

        $Payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })->where(['id' => $id, 'user_id' => $user_id])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            $userdata = UserOverridesLock::where('status', 3)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();
            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' => 'overrides', 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();

            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->first();

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
        $pay_frequency = $request['pay_frequency'];

        $payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })
            ->where(['id' => $id, 'user_id' => $user_id])->first();
        if (! empty($payroll)) {
            $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
            $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->whereIn('adjustment_type_id', [5])->get();

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
            $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->pluck('payroll_id');
            $PayrollAdjustmentDetail = PayrollAdjustmentDetail::whereIn('payroll_id', $PayrollHistoryPayrollIDs)->where(['user_id' => $payroll->user_id, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();

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
        $pay_frequency = $request['pay_frequency'];

        $payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })
            ->where(['id' => $id, 'user_id' => $user_id])->first();

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where('status', 'Paid')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 1, 'one_time_payment_id' => $this->onetimepaymentData->id])->get();
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
        $pay_frequency = $request['pay_frequency'];

        $payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })
            ->where(['id' => $id, 'user_id' => $user_id])->first();

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
                ->where('payroll_deduction_locks.is_onetime_payment', 1)
                ->where('payroll_deduction_locks.one_time_payment_id', $this->onetimepaymentData->id)
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
        $pay_frequency = $request['pay_frequency'];

        $payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($pay_period_from, $pay_period_to) {
            $query->where([
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
            ]);
        })
            ->where(['id' => $id, 'user_id' => $user_id])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {
            $customeFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->whereIn('payroll_id', [$payroll->id])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $this->onetimepaymentData->id)->get();
            if (empty($customeFields->toArray())) {
                $customeFields = CustomField::with(['getColumn', 'getApprovedBy'])->whereIn('payroll_id', [$payroll->id])->where(['user_id' => $user_id])->where('is_onetime_payment', 1)->where('one_time_payment_id', $this->onetimepaymentData->id)->get();
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
        $id = $request['id']; // payroll_id
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];
        $pay_frequency = $request['pay_frequency'];

        if (! empty($user_id)) {
            $payroll = GetPayrollData::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {
                $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                    ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($pay_period_from, $pay_period_to) {
                $query->where([
                    'pay_period_from' => $pay_period_from,
                    'pay_period_to' => $pay_period_to,
                ]);
            })
                ->where(['id' => $id, 'user_id' => $user_id])->first();

            $payroll_status = '';
            $sub_total = 0;

            if (! empty($payroll)) {
                $Payroll_status = 3;
                $adjustmentwithpayroll = PayrollHourlySalaryLock::with(['oneTimePaymentDetail', 'oneTimePaymentDetail.paidBy', 'oneTimePaymentDetail.adjustment'])
                    ->leftjoin('payroll_overtimes_lock', function ($join) {
                        $join->on('payroll_overtimes_lock.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                            ->on('payroll_overtimes_lock.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                    })
                    ->leftjoin('payroll_adjustment_details', function ($join) {
                        $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                            ->on('payroll_adjustment_details.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                    })
                    ->whereIn('payroll_hourly_salary_lock.payroll_id', [$payroll->id])
                    ->where('payroll_hourly_salary_lock.user_id', $user_id)->where('payroll_hourly_salary_lock.is_onetime_payment', 1)->where('payroll_hourly_salary_lock.one_time_payment_id', $this->onetimepaymentData->id)
                    ->where('payroll_hourly_salary_lock.is_next_payroll', 0)
                    ->select('payroll_hourly_salary_lock.*', 'payroll_overtimes_lock.overtime', 'payroll_overtimes_lock.total as overtime_total', 'payroll_adjustment_details.amount as adjustment_amount')
                    ->get();
                if (count($adjustmentwithpayroll) > 0) {
                    $response_arr = [];
                    $total = 0;
                    $subtotal = 0;
                    $totalSeconds = 0;
                    $totalHours = 0;
                    $totalOvertime = 0;
                    foreach ($adjustmentwithpayroll as $d) {
                        // if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0) {
                        //     $subtotal += $d->total;
                        // }
                        $total += ($d->total + $d->overtime_total);
                        $subtotal += $total;

                        if (! empty($d->regular_hours)) {
                            $timeA = Carbon::createFromFormat('H:i', $d->regular_hours);
                            $secondsA = $timeA->hour * 3600 + $timeA->minute * 60;
                            $totalSeconds = $totalSeconds + $secondsA;
                            // $totalHours = $this->hoursformat($totalSeconds);
                            // $totalHours = Carbon::parse($totalHours)->format('H:i');
                        }

                        if (! empty($d->overtime)) {
                            $totalOvertime = $totalOvertime + $d->overtime;
                        }

                        $response_arr[] = [
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

                    $totalHours = ($totalSeconds > 0) ? ($totalSeconds / 3600) : 0;

                    $totalData = [
                        'total_amount' => $subtotal,
                        'total_regular_hour' => number_format($totalHours, 2),
                        'total_overtime' => number_format($totalOvertime, 2),
                    ];

                    $response = ['list' => $response_arr, 'subtotal' => $totalData];

                    return $response_arr;
                }

                return $response_arr = [];
            }

            return $response_arr = [];
        } else {
            return $response_arr = [];
        }

    }

    protected function updateProgress(
        int $targetProgress,
        string $status,
        string $message,
        array $metadata = []
    ): void {
        $this->progress = max($this->progress, $targetProgress);
        $this->progress = min(100, $this->progress);

        $this->sendPusherNotification($status, $this->progress, $message, $metadata);

        Log::info('One-time payment job progress', [
            'job_id' => $this->jobId,
            'progress' => $this->progress,
            'status' => $status,
            'message' => $message,
        ]);
    }

    protected function sendPusherNotification(
        string $status,
        int $progress,
        string $message,
        array $metadata
    ): void {
        try {
            $domainName = config('app.domain_name');

            event(new sendEventToPusher(
                $domainName,
                'onetime-payment-progress',
                $message,
                array_merge($metadata, [
                    'status' => $status,
                    'progress' => $progress,
                    'session_key' => $this->sessionKey,
                    'job_id' => $this->jobId,
                    'user_id' => $this->data->user_id,
                ])
            ));

            Log::debug('Pusher notification sent', [
                'job_id' => $this->jobId,
                'status' => $status,
                'progress' => $progress,
                'session_key' => $this->sessionKey,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Pusher notification for one-time payment job', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Exception $e)
    {
        // Send failure notification
        $this->sendPusherNotification(
            'failed',
            $this->progress,
            "One-time payment processing failed: {$e->getMessage()}",
            [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        );

        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];
        \Illuminate\Support\Facades\Log::error('Failed to one time payment job', $error);
    }

    // public function getTotalnetPayAmount($userId, $startDate, $endDate)
    // {
    //     $payrollHistory = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->first();
    //     $payroll_id = $payrollHistory->payroll_id;

    //     $userCommissionSum = UserCommissionLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
    //     $userOverrideSum = UserOverridesLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
    //     $clawbackSettlementSum = ClawbackSettlementLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('clawback_amount');

    //     $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('commission_amount + overrides_amount + deductions_amount'));
    //     $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));

    //     $adjustmentToAdd = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6])->sum('amount');
    //     $adjustmentToNigative = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [5])->sum('amount');
    //     $reimbursement = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [2])->sum('amount');
    //     $adjustment = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);

    //     $net_pay = ($userCommissionSum + $userOverrideSum + $adjustment + $reimbursement - $clawbackSettlementSum);

    //     return $net_pay;
    // }

}
