<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\Notification;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollCommon;
use App\Models\PayrollHistory;
use App\Models\PositionReconciliations;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationCommissionLock;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class executeNewPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailNotificationTrait, EvereeTrait, PayFrequencyTrait , PushNotificationTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $data;

    public $start_date;

    public $end_date;

    public $currentIndex;

    public $totalIIndex;

    public $new_start_date;

    public $new_end_date;

    public $timeout = 600; // 10 minutes

    public $tries = 3;

    public function __construct($data, $currentIndex, $totalIIndex, $start_date, $end_date, $new_start_date, $new_end_date)
    {
        $this->data = $data;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->currentIndex = $currentIndex;
        $this->totalIIndex = $totalIIndex;
        $this->new_start_date = $new_start_date;
        $this->new_end_date = $new_end_date;

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // DB::beginTransaction();
        // initialize the variables
        $data = $this->data;
        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $currentIndex = $this->currentIndex;
        $totalIIndex = $this->totalIIndex;
        $new_start_date = $this->new_start_date;
        $new_end_date = $this->new_end_date;

        $filePath = public_path('/'.$start_date.'_'.$end_date.'_executePayrollUsers.txt');
        $myArray = [];

        if ($currentIndex == 1) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // file_put_contents($filePath, '[', FILE_APPEND);
        }

        // get CRM setting status
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();

        // $pusher_message = "Executing";
        // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 3);

        $data->transform(function ($data) use ($start_date, $end_date, $CrmData, $filePath, $new_start_date, $new_end_date) {
            file_put_contents($filePath, $data->user_id.',', FILE_APPEND);
            // $external_id = $data['usersdata']['employee_id']."-".$data->id;
            if (! empty($CrmData) && $data['is_mark_paid'] != 1 && $data['net_pay'] > 0 && $data['status'] != 6 && $data['status'] != 7) { // $data['is_next_payroll'] !=1 &&
                $enableEVE = 1;
                $untracked = $this->payable_request($data); // update payable in everee
                $pay_type = 'Bank';
            } else {
                $enableEVE = 0;
                $pay_type = 'Manualy';
            }
            // $payroll = Payroll::where(['id' => $data->id,'status'=>'2'])->first();
            if (isset($untracked) || $enableEVE == 0) {
                $createdata = [
                    'payroll_id' => $data->id,
                    'user_id' => $data->user_id,
                    'position_id' => $data->position_id,
                    'everee_status' => $enableEVE,
                    'commission' => $data->commission,
                    'override' => $data->override,
                    'reimbursement' => $data->reimbursement,
                    'clawback' => $data->clawback,
                    'deduction' => $data->deduction,
                    'adjustment' => $data->adjustment,
                    'reconciliation' => $data->reconciliation,
                    'net_pay' => $data->net_pay,
                    'pay_period_from' => $data->pay_period_from,
                    'pay_period_to' => $data->pay_period_to,
                    'status' => '3',
                    'pay_type' => $pay_type,
                    'pay_frequency_date' => $data->created_at,
                    'everee_external_id' => $data->everee_external_id,
                    'everee_payment_status' => $enableEVE,
                    'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : null,
                    'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : null,
                    'everee_json_response' => isset($untracked) ? json_encode($untracked) : null,
                ];
                $check = PayrollHistory::where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->count();
                if ($check == 0) {
                    $insert = PayrollHistory::create($createdata);
                }

                $this->updatePayrollData($data->id, $data->user_id, $start_date, $end_date);
                $this->createPayrollData($data->position_id, $data->user_id, $start_date, $end_date, $new_start_date, $new_end_date);

                // Retrieve data from PayrollAdjustmentData - only non-zero commission amounts
                $PayrollAdjustmentData = PayrollAdjustment::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                ->where(function($q) {
                    $q->whereNotNull('commission_amount')->where('commission_amount', '!=', 0);
                })
                ->get();

                if ($PayrollAdjustmentData) {
                    foreach ($PayrollAdjustmentData->toArray() as $value) {
                        PayrollAdjustmentLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from PayrollAdjustmentDetailData - only non-zero amounts
                $PayrollAdjustmentDetailData = PayrollAdjustmentDetail::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                ->where(function($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into PayrollAdjustmentDetailLock using Eloquent
                if ($PayrollAdjustmentDetailData) {
                    foreach ($PayrollAdjustmentDetailData->toArray() as $value) {
                        PayrollAdjustmentDetailLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                $UserReconciliationCommissionlData = UserReconciliationCommission::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 'paid',
                ])
                ->where(function($q) {
                    $q->whereNotNull('net_amount')->where('net_amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into UserReconciliationCommissionLock using Eloquent
                if ($UserReconciliationCommissionlData) {
                    foreach ($UserReconciliationCommissionlData->toArray() as $value) {
                        UserReconciliationCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from UserCommission - only non-zero amounts
                $userCommissionData = UserCommission::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                ->where(function($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into UserCommissionLock using Eloquent
                if ($userCommissionData) {
                    foreach ($userCommissionData->toArray() as $value) {
                        UserCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from UserOverrides - only non-zero amounts
                $UserOverridesData = UserOverrides::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                ->where(function($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into UserOverridesLock using Eloquent
                if ($UserOverridesData) {
                    foreach ($UserOverridesData->toArray() as $value) {
                        UserOverridesLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from ClawbackSettlement - only non-zero amounts
                $ClawbackSettlementData = ClawbackSettlement::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                ->where(function($q) {
                    $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into ClawbackSettlementLock using Eloquent
                if ($ClawbackSettlementData) {
                    foreach ($ClawbackSettlementData->toArray() as $value) {
                        ClawbackSettlementLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from ApprovalsAndRequest - only non-zero amounts
                $ApprovalsAndRequestData = ApprovalsAndRequest::where([
                    'user_id' => $data->user_id,
                    'status' => 'Paid',
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                ])
                ->where(function($q) {
                    $q->whereNotNull('amount')->where('amount', '!=', 0);
                })
                ->get();

                // Inserting data directly into ApprovalsAndRequestLock using Eloquent
                if ($ApprovalsAndRequestData) {
                    foreach ($ApprovalsAndRequestData->toArray() as $value) {
                        ApprovalsAndRequestLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Added By DeepaK
                // $payFrequency = $this->payFrequencyNew($start_date, $data['usersdata']['sub_position_id']);
                $payFrequency = $this->openPayFrequency($data['usersdata']['sub_position_id'], $data['usersdata']['id']);

                $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                $adjustmentTotal = 0;
                $addApprovalsAndRequestIds = [];
                $approvelAndRequestData = ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'status' => 'Paid', 'adjustment_type_id' => 4, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                foreach ($approvelAndRequestData as $key => $appuser) {
                    $addApprovalsAndRequest = ApprovalsAndRequest::create([
                        'user_id' => $appuser->user_id,
                        'manager_id' => $appuser->manager_id,
                        'approved_by' => $appuser->approved_by,
                        'adjustment_type_id' => $appuser->adjustment_type_id,
                        'state_id' => $appuser->state_id,
                        'dispute_type' => $appuser->dispute_type,
                        'customer_pid' => $appuser->customer_pid,
                        'cost_tracking_id' => $appuser->cost_tracking_id,
                        'cost_date' => $appuser->cost_date,
                        'request_date' => $appuser->request_date,
                        'amount' => (0 - $appuser->amount),
                        'status' => 'Accept',
                        'pay_period_from' => isset($startDateNext) ? $startDateNext : null,
                        'pay_period_to' => isset($endDateNext) ? $endDateNext : null,
                    ]);
                    $addApprovalsAndRequestIds[] = $addApprovalsAndRequest->id;
                    $adjustmentTotal += $appuser->amount;
                }

                if ($adjustmentTotal > 0) {
                    $payroll = Payroll::where(['user_id' => $data->user_id, 'pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])->where('is_next_payroll', 0)->where('is_mark_paid', 0)->where('status', '!=', 6)->first();
                    if (empty($payroll)) {
                        $payroll = Payroll::create([
                            'user_id' => $data->user_id,
                            'position_id' => $data->position_id,
                            'adjustment' => (0 - $adjustmentTotal),
                            'pay_period_from' => isset($startDateNext) ? $startDateNext : null,
                            'pay_period_to' => isset($endDateNext) ? $endDateNext : null,
                            'status' => 1,
                        ]);
                    } else {
                        Payroll::where(['user_id' => $data->user_id, 'pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])
                            ->update([
                                'adjustment' => ((0 - $adjustmentTotal) + $payroll->adjustment),
                            ]);
                    }
                    ApprovalsAndRequest::whereIn('id', $addApprovalsAndRequestIds)->update(['payroll_id' => $payroll->id]);
                }

                create_paystub_employee([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
                $note = Notification::create([
                    'user_id' => $data->user_id,
                    'type' => 'Execute PayRoll',
                    'description' => 'Execute PayRoll Data',
                    'is_read' => 0,
                ]);

                $notificationData = [
                    'user_id' => $data['usersdata']['user_id'],
                    'device_token' => $data['usersdata']['device_token'],
                    'title' => 'Execute PayRoll Data.',
                    'sound' => 'sound',
                    'type' => 'Execute PayRoll',
                    'body' => 'Updated Execute PayRoll Data',
                ];
                $this->sendNotification($notificationData);
            }

        });

        if ($currentIndex == $totalIIndex) {
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $fileContent = explode(',', $fileContent);
                $userIdArray = array_unique($fileContent);
                unlink($filePath);
                // throw new Exception();
                // printtr();

                // $pusher_message = "Executed";
                // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 4);
                $allUsersDetails = $this->generatePdfAndSendMail($userIdArray, $start_date, $end_date);

            }

        }
        // DB::rollBack();
    }

    private function generatePdfAndSendMail($processedUsers, $start_date, $end_date)
    {
        // $userId = $processedUsers;
        // $adminMailDetail = [];
        foreach ($processedUsers as $userId) {

            if ($userId == '') {
                continue;
            }
            // ---------------  Genrete pdf -----------------------
            $newData['CompanyProfile'] = CompanyProfile::first();

            $newData['id'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('id');

            $newData['pay_stub']['pay_date'] = date('Y-m-d', strtotime(PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('updated_at')));
            $newData['pay_stub']['net_pay'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->sum('net_pay');

            $newData['pay_stub']['pay_period_from'] = $start_date;
            $newData['pay_stub']['pay_period_to'] = $end_date;

            $newData['pay_stub']['period_sale_count'] = UserCommissionLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
            $newData['pay_stub']['ytd_sale_count'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];

            $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id')->first();
            $newData['employee'] = $user;
            $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');

            $newData['earnings']['commission']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->sum('commission');
            $newData['earnings']['commission']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('commission');
            // dd($newData['earnings']['commission']['period_total']); die();
            $newData['earnings']['overrides']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->sum('override');
            $newData['earnings']['overrides']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('override');

            $newData['earnings']['reconciliation']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->sum('reconciliation');
            $newData['earnings']['reconciliation']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('reconciliation');

            $newData['deduction']['standard_deduction']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->sum('deduction');
            $newData['deduction']['standard_deduction']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->sum('deduction');

            $newData['miscellaneous']['adjustment']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->sum('adjustment');
            $newData['miscellaneous']['adjustment']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->sum('adjustment');

            $newData['miscellaneous']['reimbursement']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->sum('reimbursement');
            $newData['miscellaneous']['reimbursement']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->sum('reimbursement');

            // ----------------- create pdf of user information--------------------------
            $pdfPath = public_path('/template/'.$user->first_name.'_'.$user->last_name.'_'.time().'_pay_stub.pdf');
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $file_link = $image_file_path.'/'.$newData['CompanyProfile']->logo;
                $newData['CompanyProfile']['logo'] = $file_link;
            } else {
                $newData['CompanyProfile']['logo'] = $newData['CompanyProfile']->logo;
            }
            $pdf = \PDF::loadView('mail.downloadPayStub', [
                'user' => $user,
                'email' => $user->email,
                'start_date' => $user->startDate,
                'end_date' => $user->endDate,
                'path' => $pdfPath,
                'data' => $newData,
            ]);
            $pdf->save($pdfPath);
            $filePath = config('app.domain_name').'/'.'paystyb/'.$user->first_name.'_'.$user->last_name.'_'.time().'_pay_stub.pdf';
            $s3Data = s3_upload($filePath, $pdfPath, true, 'public');
            $s3filePath = config('app.aws_s3bucket_url').'/'.$filePath;
            // ----------------- end create pdf of user information--------------------------
        }
    }

    private function updatePayrollData($id, $user_id, $start_date, $end_date)
    {
        // Update status to 3 in multiple tables
        // 'UserReconciliationCommission',
        $tablesToUpdate = ['UserCommission', 'UserOverrides', 'ClawbackSettlement',  'ApprovalsAndRequest', 'PayrollAdjustment', 'PayrollAdjustmentDetail'];
        foreach ($tablesToUpdate as $table) {
            $fullClassName = 'App\Models\\'.$table;
            // Use the fully qualified class name to instantiate the model
            $modelInstance = new $fullClassName;
            $updateData = ['status' => '3'];
            $whereConditions = ['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_next_payroll' => 0];

            if ($table === 'UserReconciliationCommission') {
                $updateData = ['status' => 'paid'];
                $whereConditions['payroll_id'] = $id;
            }

            $modelInstance::where($whereConditions)->update($updateData);
        }

        $newFromDate = 'new_from_date'; // replace with the actual new from_date
        $newEndDate = 'new_end_date'; // replace with the actual new end_date
        // 'UserReconciliationCommission',
        $markPaidTables = ['UserCommission', 'UserOverrides', 'ClawbackSettlement',  'ApprovalsAndRequest', 'PayrollAdjustment', 'PayrollAdjustmentDetail'];

        // Fetch ref_ids from each markPaid table (use correct model per table)
        $refIds = [];
        foreach ($markPaidTables as $table) {
            $fullClassName = 'App\Models\\'.$table;
            $modelInstance = new $fullClassName;
            $ids = $modelInstance::where([
                'user_id' => $user_id,
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
                'is_next_payroll' => 1,
            ])->pluck('ref_id')->filter()->unique()->values()->toArray();
            $refIds = array_merge($refIds, $ids);
        }
        $refIds = array_values(array_unique(array_filter($refIds)));
        if (!empty($refIds)) {
            PayrollCommon::whereIn('id', $refIds)->update(['orig_payfrom' => $start_date, 'orig_payto' => $start_date]);
        }

        // Update is_next_payroll, pay_period_from, and pay_period_to in a single query for the second set of tables
        foreach ($markPaidTables as $table) {
            $fullClassName = 'App\Models\\'.$table;
            // Use the fully qualified class name to instantiate the model
            $modelInstance = new $fullClassName;

            $updateData = ['is_next_payroll' => 0, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newEndDate];
            $whereConditions = ['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date];

            if ($table === 'UserReconciliationCommission' || $table === 'ApprovalsAndRequest') {
                $whereConditions['payroll_id'] = $id;
            }

            $modelInstance::where($whereConditions)->update($updateData);
        }

        // Delete from Payroll table
        Payroll::where(['id' => $id, 'status' => '2'])->delete();

    }

    private function createPayrollData($position_id, $user_id, $start_date, $end_date, $newFromDate, $newEndDate)
    {
        // Update status to 3 in multiple tables
        // 'UserReconciliationCommission',
        $tablesToUpdate = ['UserCommission', 'UserOverrides', 'ClawbackSettlement',  'ApprovalsAndRequest', 'PayrollAdjustment', 'PayrollAdjustmentDetail'];
        foreach ($tablesToUpdate as $table) {
            $fullClassName = 'App\Models\\'.$table;
            // Use the fully qualified class name to instantiate the model
            $modelInstance = new $fullClassName;
            $whereConditions = ['user_id' => $user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'status' => 1];
            $newPayroll_qry = $modelInstance::where(['user_id' => $user_id,  'is_next_payroll' => '0', 'pay_period_from' => $newFromDate, 'pay_period_to' => $newEndDate, 'status' => 1]);
            if ($table == 'UserCommission' || $table == 'ClawbackSettlement') {
                $newPayroll_qry = $newPayroll_qry->where('position_id', $position_id);
            }
            $newPayroll = $newPayroll_qry->first();
            if (isset($newPayroll->payroll_id)) {
                $newpayroll_id = $newPayroll->payroll_id;
            } else {
                $payroll = Payroll::create([
                    'user_id' => $user_id,
                    'position_id' => $position_id,
                    'pay_period_from' => $newFromDate,
                    'pay_period_to' => $newEndDate,
                    'status' => 1,
                ]);
                $newpayroll_id = $payroll->id;
            }
            $updateData = ['payroll_id' => $newpayroll_id, 'is_next_payroll' => '0', 'pay_period_from' => $newFromDate, 'pay_period_to' => $newEndDate];
            $modelInstance::where($whereConditions)->update($updateData);
        }
    }
}
