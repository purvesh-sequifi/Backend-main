<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\Payroll;
use App\Models\PositionReconciliations;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserWagesHistory;
use App\Traits\EmailNotificationTrait;
use Barryvdh\DomPDF\PDF;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class finalizeW2PayrollJob implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, EvereeTrait, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $data;

    public $totalIIndex;

    public $currentIndex;

    public $start_date;

    public $end_date;

    public $adminMail;

    public $timeout = 1200; // 20 minutes

    public $tries = 3;

    public function __construct($data, $currentIndex, $totalIIndex, $start_date, $end_date, $auth)
    {
        $this->data = $data;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->totalIIndex = $totalIIndex;
        $this->currentIndex = $currentIndex;
        $this->adminMail = $auth->email;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = public_path('file.txt');
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        $data = $this->data;
        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $totalIIndex = $this->totalIIndex;
        $currentIndex = $this->currentIndex;
        $filePath = public_path('/'.$start_date.'_'.$end_date.'_finalizedUser.txt');
        $customfilePath = public_path('/_custom_finalizedUser.txt');
        $myArray = [];

        if ($currentIndex == 1) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            file_put_contents($filePath, '[', FILE_APPEND);
        }
        // $pusher_message = "Finalizing";
        // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 1);

        if (count($data) > 0) {
            foreach ($data as $k1 => $val) {

                $actualNetPay = $val['net_pay'];

                $userWagesHistory = UserWagesHistory::where(['user_id' => $val->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
                $unitRate = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : null;
                $pay_rate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : '0';

                if (! empty($CrmData) && $unitRate == 'Hourly' && $val['is_mark_paid'] != 1 && $val['net_pay'] > 0 && $val['status'] != 6 && $val['status'] != 7) {
                    $user_attendance = UserAttendance::where('user_id', $val->user_id)
                        ->whereBetween('date', [$start_date, $end_date])->get();
                    // dd(count($user_attendance));
                    $untracked = [];
                    $external_id = '';
                    $enableEVE = 0;
                    if (count($user_attendance) > 0) {
                        // $data = $this->sendAttendanceData($user_attendance);

                        foreach ($user_attendance as $data1) {
                            $attendance_details_obj = UserAttendanceDetail::where('user_attendance_id', $data1->id)->get()->toArray();
                            // dd($attendance_details_obj);
                            $types = array_column($attendance_details_obj, 'type');
                            $dates = array_column($attendance_details_obj, 'attendance_date');
                            // dd($types, $dates);
                            $payload = [];
                            $findUser = User::find($data1->user_id);
                            $payload['user_id'] = $data1->user_id;
                            $payload['clockIn'] = $dates[array_search('clock in', $types)];
                            $payload['clockOut'] = $dates[array_search('clock out', $types)];
                            $payload['lunch'] = $dates[array_search('lunch', $types)];
                            $payload['lunchEnd'] = $dates[array_search('end lunch', $types)];
                            $payload['break'] = $dates[array_search('break', $types)];
                            $payload['breakEnd'] = $dates[array_search('end break', $types)];
                            $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                            $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;
                            // dd($payload);
                            $untracked = $this->send_timesheet_data($payload);

                        }
                        $external_id = '';
                        $enableEVE = 1;
                    }
                } else {
                    $external_id = '';
                    $enableEVE = 0;
                }

                $checkPayroll = Payroll::where('id', $val->id)->first();
                if ($checkPayroll->net_pay != $actualNetPay) {
                    $errorMessage = 'netpay amount is sennding to everee is '.$val->net_pay.' and netpay in payroll is now '.$checkPayroll->net_pay;
                    $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                    if ($enableEVE == 1) {
                        $myArray[$val->user_id] = $errorMessage;
                    }
                } else {
                    if ((isset($untracked['success']['status']) && $untracked['success']['status'] == true) || $enableEVE == 0) {
                        $status = Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => $external_id, 'everee_message' => null]);
                        $errorMessage = '';
                        if ($enableEVE == 1) {
                            $myArray[$val->user_id] = $errorMessage;
                        }
                    } elseif ((isset($untracked['fail']['status']) && $untracked['fail']['status'] == false) || $enableEVE == 0) {
                        $errorMessage = isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : '';
                        $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                        if ($enableEVE == 1) {
                            $myArray[$val->user_id] = $errorMessage;
                        }
                    } else {
                        $errorMessage = 'something went wrong!';
                        $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                        $myArray[$val->user_id] = $errorMessage;
                    }
                }

                // $status = Payroll::where('id',$val->id)->update(['status'=>2,'finalize_status'=> 2,'everee_message' => null]);
            }
        }
        // dd($myArray);
        // DB::rollback();
        file_put_contents($filePath, json_encode($myArray), FILE_APPEND);
        file_put_contents($filePath, ($currentIndex != $totalIIndex) ? ',' : '', FILE_APPEND);
        if ($currentIndex == $totalIIndex) {
            file_put_contents($filePath, ']', FILE_APPEND);
            $fileContent = file_get_contents($filePath);

            $usersIidWithmessages = json_decode($fileContent, true);
            $userIdArray = [];
            foreach ($usersIidWithmessages as $key => $value) {
                foreach ($value as $user_id => $message) {
                    $userIdArray[$user_id] = $message;
                }
            }
            // unlink($filePath);
            $allUsersDetails = $this->generatePdfAndSendMail($userIdArray, $start_date, $end_date);
            if (count($allUsersDetails) > 0) {
                $newData['CompanyProfile'] = CompanyProfile::first();
                $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
                $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
                if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                    $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                    $file_link = $image_file_path.'/'.$newData['CompanyProfile']->logo;
                    $newData['CompanyProfile']['logo'] = $file_link;
                } else {
                    $newData['CompanyProfile']['logo'] = $newData['CompanyProfile']->logo;
                }

                // $pusher_message = "Executing";
                // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 2);
                $finalize['email'] = $this->adminMail;
                $finalize['subject'] = 'PayRoll Processes Info.';
                $finalize['template'] = view('mail.payroll_prossed', compact('finalize', 'allUsersDetails', 'start_date', 'end_date', 'newData'));
                \File::append($file, date('d-m-Y h:i:s')." => send mail job\n");
                if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod') {
                    \File::append($file, date('d-m-Y h:i:s')." => send admin mail\n");
                    $maidData = $this->sendEmailNotification($finalize, true);
                }

            }
        }

    }

    private function generatePdfAndSendMail($processedUsers, $start_date, $end_date)
    {
        $file = public_path('file.txt');
        $adminMailDetail = [];
        foreach ($processedUsers as $userId => $message) {

            // ---------------  Genrete pdf -----------------------
            $newData['CompanyProfile'] = CompanyProfile::first();

            $newData['id'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('id', '!=', 0)->where('everee_external_id', '!=', '')->value('id');

            $newData['pay_stub']['pay_date'] = date('Y-m-d', strtotime(Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('everee_external_id', '!=', '')->where('id', '!=', 0)->value('updated_at')));
            $newData['pay_stub']['net_pay'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('everee_external_id', '!=', '')->where('id', '!=', 0)->sum('net_pay');

            $newData['pay_stub']['pay_period_from'] = $start_date;
            $newData['pay_stub']['pay_period_to'] = $end_date;

            $newData['pay_stub']['period_sale_count'] = UserCommission::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
            $newData['pay_stub']['ytd_sale_count'] = UserCommission::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];

            $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id')->first();
            $newData['employee'] = $user;
            $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');

            $newData['earnings']['commission']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('everee_external_id', '!=', '')->sum('commission');
            $newData['earnings']['commission']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('everee_external_id', '!=', '')->sum('commission');
            // dd($newData['earnings']['commission']['period_total']); die();
            $newData['earnings']['overrides']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('everee_external_id', '!=', '')->sum('override');
            $newData['earnings']['overrides']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('everee_external_id', '!=', '')->sum('override');

            $newData['earnings']['reconciliation']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('everee_external_id', '!=', '')->sum('reconciliation');
            $newData['earnings']['reconciliation']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('everee_external_id', '!=', '')->sum('reconciliation');

            $newData['deduction']['standard_deduction']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('deduction');
            $newData['deduction']['standard_deduction']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('deduction');

            $newData['miscellaneous']['adjustment']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('adjustment');
            $newData['miscellaneous']['adjustment']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('adjustment');

            $newData['miscellaneous']['reimbursement']['period_total'] = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '2'])->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('reimbursement');
            $newData['miscellaneous']['reimbursement']['ytd_total'] = Payroll::where(['user_id' => $userId, 'status' => '2'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('id', '!=', 0)->where('everee_external_id', '!=', '')->sum('reimbursement');

            // You can pass data to the view here if needed

            // UserCommission::where(['status'=>1,'user_id' =>  $userId, 'pay_period_from' =>  $start_date,'pay_period_to' =>  $end_date])->update(['status'=>2]);
            // UserOverrides::where(['status'=>1,'user_id' => $userId, 'pay_period_from' =>  $start_date,'pay_period_to' =>  $end_date])->update(['status'=>2]);

            // ----------------- create pdf of user information--------------------------
            // $pdfPath = "/template/".$user->first_name.'-'.$user->last_name."_pay_stub.pdf";
            // $pdf = \PDF::loadView('mail.downloadPayStub',[
            //         'user' => $user,
            //         'email' => $user->email,
            //         'start_date' => $user->startDate,
            //         'end_date' => $user->endDate,
            //         'path' => $pdfPath,
            //         'data' => $newData,
            // ]);
            // $pdf->save(public_path($pdfPath));
            // ----------------- end create pdf of user information--------------------------

            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $file_link = $image_file_path.'/'.$newData['CompanyProfile']->logo;
                $newData['CompanyProfile']['logo'] = $file_link;
            } else {
                $newData['CompanyProfile']['logo'] = $newData['CompanyProfile']->logo;
            }

            // ------------------- email sending to the users ---------------------
            // $userMailName = preg_replace('/[^a-zA-Z0-9\s]/', '', $user->first_name). '-' . preg_replace('/[^a-zA-Z0-9\s]/', '', $user->last_name);
            $finalize['email'] = $user->email;
            $finalize['subject'] = 'Finalize PayRoll info';
            $finalize['template'] = view('mail.payroll_finalized', compact('newData', 'finalize', 'user', 'start_date', 'end_date'));
            if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod') {
                \File::append($file, date('d-m-Y h:i:s')." => send user mail\n");
                $this->sendEmailNotification($finalize);
            }

            // ---------------------- End email sending to the users-------------------------

            // ----------------- create a new array administrator mail -------------------------
            if ($message != '') {
                $adminMailDetail['error'][] = [
                    'name' => $user->first_name.' '.$user->last_name,
                    'remark' => $message,
                    'net_pay' => $newData['pay_stub']['net_pay'],
                ];
            } else {
                $adminMailDetail['success'][] = [
                    'name' => $user->first_name.' '.$user->last_name,
                    'remark' => $message,
                    'net_pay' => $newData['pay_stub']['net_pay'],
                ];
            }
            // ----------------- End create a new array administrator mail -------------------------
        }

        return $adminMailDetail;
    }

    private function sendTemplateBasedNotificationMail($userId)
    {
        if ($userId) {

            $mailData = [];
            $user = \App\Models\User::find($userId);
            if ($user) {
                $mailData = \App\Models\SequiDocsEmailSettings::current_pay_stub_notification_email_content($user);

                if ($mailData) {

                    if ($mailData['is_active'] == 1 && $mailData['template'] != '') {

                        $mailData['email'] = $user->email;
                        if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod') {
                            $this->sendEmailNotification($mailData);
                        }
                    }
                }

            }
        } else {
            // Log::debug('USER ID NOT FOUND');
        }

    }
}
