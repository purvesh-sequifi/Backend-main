<?php

namespace App\Services;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\OneTimePayments;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertime;
use App\Models\PayrollOvertimeLock;
use App\Models\paystubEmployee;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OneTimePayStubService
 *
 * This service handles the generation and delivery of one-time payment pay stubs.
 * It manages PDF generation, S3 upload, and email notifications for one-time payments.
 *
 * Responsibilities:
 * - Generate pay stub data for one-time payments
 * - Create and save PDF pay stubs
 * - Upload PDFs to S3 storage
 * - Send email notifications with pay stub attachments
 * - Handle all pay stub calculations and breakdowns
 *
 * This service is used by controllers to process one-time payment pay stubs
 * synchronously, replacing the previous job-based approach for better performance
 * and easier debugging.
 */
class OneTimePayStubService
{
    use EmailNotificationTrait, EvereeTrait, PayFrequencyTrait, PushNotificationTrait;

    /**
     * Process one-time payment pay stub generation and delivery
     *
     * @return array Result with success status and details
     */
    public function processPayStub(User $user, OneTimePayments $onetimePayment): array
    {
        try {
            if (empty($user)) {
                return [
                    'success' => false,
                    'message' => 'User data is required',
                    'error' => 'Missing user parameter',
                ];
            }

            Log::info('Processing one-time pay stub', [
                'user_id' => $user->id,
                'payment_id' => $onetimePayment->id,
            ]);

            $result = $this->generatePdfAndSendMail($user, $onetimePayment);

            return [
                'success' => true,
                'message' => 'Pay stub processed successfully',
                'data' => $result,
            ];

        } catch (Exception $e) {
            Log::error('OneTimePayStubService error: '.$e->getMessage(), [
                'user_id' => $user->id ?? null,
                'payment_id' => $onetimePayment->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process pay stub',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate PDF and send email notification
     *
     * @return array Processing result
     */
    public function generatePdfAndSendMail(User $user, OneTimePayments $onetimePayment): array
    {
        try {
            // Gather all pay stub data
            $newData = $this->getOnetimePaystub($onetimePayment->id, $onetimePayment->user_id);
            $adjustment_details = $this->getOnetimePayStubAdjustmentDetails($onetimePayment->id, $onetimePayment->user_id);
            $reimbursement_details = $this->getOnetimePayStubReimbursementDetails($onetimePayment->id, $onetimePayment->user_id);
            $commission_details_lock = $this->getOnetimePayStubCommissionDetails($onetimePayment->id, $onetimePayment->user_id);
            $override_details_lock = $this->getOnetimePayStubUserOverrideDetails($onetimePayment->id, $onetimePayment->user_id);
            $deductions_details_lock = $this->getOnetimePayStubPayrollDeductionsDetails($onetimePayment->id, $onetimePayment->user_id);
            $additional_value_details_lock = $this->getOnetimePayStubAdditionalDetails($onetimePayment->id, $onetimePayment->user_id);
            $wages_details_lock = $this->getOnetimePayStubWagesDetails($onetimePayment->id, $onetimePayment->user_id);

            // Generate PDF
            $pdfResult = $this->generatePdf($user, $newData, [
                'adjustment_details' => $adjustment_details,
                'reimbursement_details' => $reimbursement_details,
                'commission_details' => $commission_details_lock,
                'override_details' => $override_details_lock,
                'deductions_details' => $deductions_details_lock,
                'additional_value_details' => $additional_value_details_lock,
                'wages_value_details' => $wages_details_lock,
            ]);

            if (! $pdfResult['success']) {
                return $pdfResult;
            }

            // Send email notification
            $emailResult = $this->sendPaystubEmail($user, $newData, $pdfResult['s3_url']);

            return [
                'pdf_path' => $pdfResult['pdf_path'],
                's3_url' => $pdfResult['s3_url'],
                'email_sent' => $emailResult['success'],
                'email_message' => $emailResult['message'],
            ];

        } catch (Exception $e) {
            Log::error('Error in generatePdfAndSendMail: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate PDF for pay stub
     *
     * @return array Result with PDF path and S3 URL
     */
    private function generatePdf(User $user, array $data, array $details): array
    {
        try {
            $uniqueTime = time();
            $fileName = $user->first_name.'_'.$user->last_name.'_'.$uniqueTime.'_onetime_pay_stub.pdf';
            $pdfPath = public_path('/template/'.$fileName);

            $pdf = Pdf::loadView('mail.paystub_available_one_time', array_merge([
                'user' => $user,
                'email' => $user->email,
                'start_date' => $user->startDate ?? null,
                'end_date' => $user->endDate ?? null,
                'path' => $pdfPath,
                'data' => $data,
            ], $details));

            $pdf->save($pdfPath);

            // Upload to S3
            $s3FilePath = config('app.domain_name').'/paystyb/'.$fileName;
            $s3Data = s3_upload($s3FilePath, $pdfPath, true, 'public');
            $s3Url = config('app.aws_s3bucket_url').'/'.$s3FilePath;

            return [
                'success' => true,
                'pdf_path' => $pdfPath,
                's3_url' => $s3Url,
                'file_name' => $fileName,
            ];

        } catch (Exception $e) {
            Log::error('PDF generation failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'PDF generation failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email notification with pay stub
     *
     * @param  array  $data
     * @param  string  $s3Url
     * @return array Email sending result
     */
    private function sendPaystubEmail(User $user, array $newData, string $s3filePath): array
    {
        try {
            $mailArray = [
                'email' => $user->email,
                'subject' => 'New Paystub Available',
            ];

            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');

            $mailArray['template'] = view('mail.executeUser', compact('newData', 'user', 'start_date', 'end_date', 's3filePath'));

            // Only send email in production environments
            if (! in_array(config('app.domain_name'), ['dev', 'testing', 'preprod'])) {
                $mailSent = $this->sendEmailNotification($mailArray);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                ];
            }

            return [
                'success' => true,
                'message' => 'Email skipped (non-production environment)',
            ];

        } catch (Exception $e) {
            Log::error('Email sending failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Email sending failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    // === DATA GATHERING METHODS ===
    // (All the helper methods from the original job converted to private methods)

    /**
     * Get one-time payment pay stub data
     */
    private function getOnetimePaystub($id, $userId)
    {
        $data1 = OneTimePayments::with('userData', 'adjustment')->where(['payment_status' => 3, 'everee_payment_status' => 1])->where('id', $id)->first();
        $result = [];
        if ($data1) {
            $creted_date = isset($data1->created_at) ? date('Y-m-d', strtotime($data1->created_at)) : null;
            $adjustment_id = isset($data1->adjustment_type_id) ? $data1->adjustment_type_id : 0;
            $net_pay = isset($data1->amount) ? $data1->amount : 0;
            $gross_total = $net_pay;
            $payperiod = $this->getOnetimePaymentPaystubPayperiod($id, $userId);
            $start_date = $payperiod ? $payperiod->pay_period_from : $data1->pay_date;
            $end_date = $payperiod ? $payperiod->pay_period_to : $data1->pay_date;
            $paystubQuery = paystubEmployee::where('user_id', $userId)
                ->where('pay_period_from', '=', $start_date)
                ->where('pay_period_to', '=', $end_date);
            if ($paystubQuery->count() <= 0) {
                $paystubQuery = paystubEmployee::where('user_id', $userId)
                    ->whereNull('pay_period_from')
                    ->whereNull('pay_period_to');
            }

            $result['CompanyProfile'] = $paystubQuery->select(

                'company_name as name',
                'company_address as address',
                'company_website as company_website',
                'company_phone_number as phone_number',
                'company_type as company_type',
                'company_email as company_email',
                'company_business_name as business_name',
                'company_mailing_address as mailing_address',
                'company_business_ein as business_ein',
                'company_business_phone as business_phone',
                'company_business_address as business_address',
                'company_business_city as business_city',
                'company_business_state as business_state',
                'company_business_zip as business_zip',
                'company_mailing_state as mailing_state',
                'company_mailing_city as mailing_city',
                'company_mailing_zip as mailing_zip',
                'company_time_zone as time_zone',
                'company_business_address_1 as business_address_1',
                'company_business_address_2 as business_address_2',
                'company_business_lat as business_lat',
                'company_business_long as business_long',
                'company_mailing_address_1 as mailing_address_1',
                'company_mailing_address_2 as mailing_address_2',
                'company_mailing_lat as mailing_lat',
                'company_mailing_long as mailing_long',
                'company_business_address_time_zone as business_address_time_zone',
                'company_mailing_address_time_zone as mailing_address_time_zone',
                'company_margin as company_margin',
                'company_country as country',
                'company_logo as logo',
                'company_lat as lat',
                'company_lng as lng'
            )->first();

            if (isset($result['CompanyProfile']) && $result['CompanyProfile'] != null) {
                $s3_bucket_public_url = config('app.aws_s3bucket_url');
                if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                    $image_file_path = $s3_bucket_public_url.'/'.config('app.domain_name');
                    $file_link = $image_file_path.'/'.$result['CompanyProfile']->logo;
                    $result['CompanyProfile']['logo'] = $file_link;
                    $result['CompanyProfile']['company_logo_s3'] = $file_link;

                }
            }
            $result['payroll_id'] = 0;

            $pay_date = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('id', $id)->value('pay_date');

            // Check if the pay_date is set and not null
            if (isset($pay_date)) {
                $result['pay_stub']['pay_date'] = date('Y-m-d', strtotime($pay_date));
            } else {
                $result['pay_stub']['pay_date'] = null;  // or set a default value if needed
            }

            $result['pay_stub']['net_pay'] = OneTimePayments::where(['id' => $id, 'user_id' => $userId, 'payment_status' => '3'])->sum('amount');
            $result['pay_stub']['net_ytd'] = OneTimePayments::where(['id' => $id, 'user_id' => $userId, 'payment_status' => '3'])->whereYear('created_at', date('Y'))->sum('amount');

            $result['pay_stub']['pay_period_from'] = $start_date ?? '';
            $result['pay_stub']['pay_period_to'] = $end_date ?? '';
            $result['pay_stub']['pay_frequency'] = '0';

            $result['pay_stub']['period_sale_count'] = '';
            $result['pay_stub']['ytd_sale_count'] = '';
            /* user data */
            $user = $paystubQuery->with('positionDetailTeam')
                ->select(
                    'user_first_name as first_name',
                    'user_middle_name as middle_name',
                    'user_last_name as last_name',
                    'user_employee_id as employee_id',
                    'user_name_of_bank as name_of_bank',
                    'user_social_sequrity_no',
                    'user_routing_no',
                    'user_account_no',
                    'user_type_of_account as type_of_account',
                    'user_home_address as home_address',
                    'user_zip_code as zip_code',
                    'user_email as email',
                    'user_work_email as work_email',
                    'user_position_id as position_id',
                    'user_entity_type as entity_type',
                    'user_business_name as business_name',
                    'user_business_type as business_type',
                    'user_business_ein',
                )
                ->first();
            /* encrypt data modificticate set value */
            $user->account_no = $user->user_account_no;
            $user->routing_no = $user->user_routing_no;
            $user->social_sequrity_no = $user->user_social_sequrity_no;
            $user->business_ein = $user->user_business_ein;
            $result['employee'] = $user;

            $result['earnings']['commission']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'commission', $start_date, $end_date);
            $result['earnings']['commission']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'commission', $start_date, $end_date);

            $result['earnings']['overrides']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'overrides', $start_date, $end_date);
            $result['earnings']['overrides']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'overrides', $start_date, $end_date);

            $result['earnings']['reconciliation']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'reconciliation', $start_date, $end_date);

            $result['earnings']['additional']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'additional', $start_date, $end_date);
            $result['earnings']['additional']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'additional', $start_date, $end_date);

            $result['earnings']['wages']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'wages', $start_date, $end_date);
            $result['earnings']['wages']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'wages', $start_date, $end_date);

            $result['deduction']['standard_deduction']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'standard_deduction', $start_date, $end_date);
            $result['deduction']['standard_deduction']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'standard_deduction', $start_date, $end_date);

            $result['miscellaneous']['adjustment']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'adjustment', $start_date, $end_date);
            $result['miscellaneous']['adjustment']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'adjustment', $start_date, $end_date);

            $result['miscellaneous']['reimbursement']['period_total'] = $this->calculateOnetimePaystubSinglePeriodTotal($userId, $id, 'reimbursement', $start_date, $end_date);
            $result['miscellaneous']['reimbursement']['ytd_total'] = $this->calculateOnetimePaystubSingleYtdTotal($userId, $id, 'reimbursement', $start_date, $end_date);

            $result['type'] = 'onetimepayment';

        }

        return $result;
    }

    /**
     * Get one-time payment pay period information
     */
    private function getOnetimePaymentPaystubPayperiod($onetime_id, $userId)
    {
        $payStubPayPeriod = Payroll::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = PayrollHistory::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = UserCommission::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = UserOverrides::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = PayrollAdjustmentDetail::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = ApprovalsAndRequest::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = PayrollDeductions::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = PayrollHourlySalary::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        }
        $payStubPayPeriod = PayrollOvertime::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $onetime_id])->select('pay_period_from', 'pay_period_to')->first();
        if ($payStubPayPeriod) {
            return $payStubPayPeriod;
        } else {
            return $payStubPayPeriod = [];
        }
    }

    /**
     * Calculate period totals for different payment types
     */
    private function calculateOnetimePaystubSinglePeriodTotal($userId, $oneTimePaymentId, $type, $payperiodStartDate, $payperiodEndDate)
    {

        if ($type == 'adjustment') {
            $adjustmentwithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 12)->where('adjustment_type_id', '!=', 2)->where('id', $oneTimePaymentId)->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $adjustmentwithpayroll = PayrollAdjustmentDetail::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where(['payroll_type' => 'commission'])->sum('amount');
                if (empty($adjustmentwithpayroll)) {
                    $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid' => '0', 'user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->sum(DB::raw('commission_amount + overrides_amount + deductions_amount'));
                    $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['is_mark_paid' => '0', 'user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->sum(DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
                    $adjustmentToAdd = ApprovalsAndRequest::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->where(['is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                    $adjustmentToNigative = ApprovalsAndRequest::where(['user_id' => $userId, 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->where(['is_mark_paid' => '0', 'status' => 'Paid'])->where('adjustment_type_id', 5)->sum('amount');
                    $adjustmentwithpayroll = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);
                }
            } else {
                $adjustmentwithpayroll = $adjustmentwithoutpayroll;
            }

            return $totaladjustment = $adjustmentwithpayroll;
        }

        if ($type == 'overrides') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 11)->where('adjustment_type_id', '!=', 12)->where('id', $oneTimePaymentId)->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserOverridesLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'commission') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserCommission::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'standard_deduction') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',11)->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = PayrollDeductions::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reimbursement') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 2)->where('adjustment_type_id', '!=', 12)->where('id', $oneTimePaymentId)->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ApprovalsAndRequest::where('user_id', $userId)->where('is_onetime_payment', 1)->where('adjustment_type_id', 2)->where('one_time_payment_id', $oneTimePaymentId)->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reconciliation') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ReconciliationFinalizeHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where('status', 3)->sum('net_amount');
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'additional') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = CustomFieldHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('value');
                if ($overrideswithpayroll == 0) {
                    $overrideswithpayroll = CustomField::with(['getColumn', 'getApprovedBy'])->where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('value');
                }
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'wages') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',2)->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $hourlySalarySum = PayrollHourlySalaryLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('total');
                $overtimeSum = PayrollOvertimeLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->sum('total');
                $overrideswithpayroll = $hourlySalarySum + $overtimeSum;
            } else {
                // $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

    }

    /**
     * Calculate YTD totals for different payment types
     */
    private function calculateOnetimePaystubSingleYtdTotal($userId, $oneTimePaymentId, $type, $payperiodStartDate, $payperiodEndDate)
    {

        if ($type == 'adjustment') {
            $adjustmentwithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', '!=', 5)->where('adjustment_type_id', '!=', 12)->where('adjustment_type_id', '!=', 2)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $adjustmentwithpayroll = PayrollAdjustmentDetailLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where(['payroll_type' => 'commission'])->whereYear('updated_at', date('Y'))->sum('amount');
                if (empty($adjustmentwithpayroll)) {
                    $adjustmentwithpayroll = PayrollHistory::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 1, 'one_time_payment_id' => $oneTimePaymentId])->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('adjustment');
                }
            } else {
                $adjustmentwithpayroll = $adjustmentwithoutpayroll;
            }

            return $totaladjustment = $adjustmentwithpayroll;
        }

        if ($type == 'overrides') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 11)->where('adjustment_type_id', '!=', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserOverridesLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'commission') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = UserCommission::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'standard_deduction') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',11)->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->sum('amount');
            $overridewithoutpayroll = '';
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = PayrollDeductions::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('total');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reimbursement') {
            $overrideswithpayroll = 0;
            $overridewithoutpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 2)->where('adjustment_type_id', '!=', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ApprovalsAndRequest::where('user_id', $userId)->where('is_onetime_payment', 1)->where('adjustment_type_id', 2)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('amount');
            } else {
                $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'reconciliation') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = ReconciliationFinalizeHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where('status', 3)->whereYear('updated_at', date('Y'))->sum('net_amount');
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'additional') {
            $overrideswithpayroll = 0;
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                $overrideswithpayroll = CustomFieldHistory::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('value');
                if ($overrideswithpayroll == 0) {
                    $overrideswithpayroll = CustomField::with(['getColumn', 'getApprovedBy'])->where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->sum('value');
                }
            }

            return $totaloverride = $overrideswithpayroll;
        }

        if ($type == 'wages') {
            $overrideswithpayroll = 0;
            // $overridewithoutpayroll = OneTimePayments::where(['user_id'=>$userId,'payment_status'=>'3'])->where('adjustment_type_id',2)->where('adjustment_type_id','!=',12)->where('id',$oneTimePaymentId)->whereYear('created_at',date('Y'))->sum('amount');
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $userId, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $oneTimePaymentId)->whereYear('created_at', date('Y'))->get();
            if (! empty($onetimepaymentcheckwithpayroll->toArray())) {
                if ($payperiodStartDate && $payperiodEndDate) {
                    $hourlySalarySumYtd = PayrollHourlySalaryLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('total');
                    $overtimeSumYtd = PayrollOvertimeLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $payperiodEndDate)->whereYear('pay_period_from', date('Y', strtotime($payperiodStartDate)))->where('payroll_id', '!=', 0)->sum('total');
                } else {
                    $hourlySalarySumYtd = PayrollHourlySalaryLock::where('user_id', $userId)->where('is_onetime_payment', 1)->where('one_time_payment_id', $oneTimePaymentId)->whereYear('updated_at', date('Y'))->where('payroll_id', '!=', 0)->sum('total');
                    $overtimeSumYtd = PayrollOvertimeLock::where(['user_id' => $userId, 'status' => '3'])->whereYear('updated_at', date('Y'))->where('payroll_id', '!=', 0)->sum('total');
                }
                $overrideswithpayroll = $hourlySalarySumYtd + $overtimeSumYtd;

            } else {
                // $overrideswithpayroll = $overridewithoutpayroll;
            }

            return $totaloverride = $overrideswithpayroll;
        }

    }

    /**
     * Get adjustment details for pay stub
     */
    private function getOnetimePayStubAdjustmentDetails($id, $user_id)
    {
        $data = [];

        if (! empty($user_id)) {
            $adjustment = OneTimePayments::with('userData', 'adjustment', 'paidBy')->where('payment_status', '3')->where(['id' => $id, 'user_id' => $user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();

            if (count($adjustment) > 0) {
                foreach ($adjustment as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->paidBy->first_name) ? $value->paidBy->first_name : null,
                        'last_name' => isset($value->paidBy->last_name) ? $value->paidBy->last_name : null,
                        'image' => isset($value->paidBy->image) ? $value->paidBy->image : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'position_id' => isset($value->paidBy->position_id) ? $value->paidBy->position_id : null,
                        'sub_position_id' => isset($value->paidBy->sub_position_id) ? $value->paidBy->sub_position_id : null,
                        'is_super_admin' => isset($value->paidBy->is_super_admin) ? $value->paidBy->is_super_admin : null,
                        'is_manager' => isset($value->paidBy->is_manager) ? $value->paidBy->is_manager : null,
                    ];
                }
            }

            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {

                $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
                $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->whereIn('adjustment_type_id', [5])->get();

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
                            'is_onetime_payment' => $value->is_onetime_payment,
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
                            'is_onetime_payment' => $value->is_onetime_payment,
                        ];
                    }
                }

                $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $user_id])->where(['is_onetime_payment' => 1, 'one_time_payment_id' => $id])->pluck('payroll_id');
                $PayrollAdjustmentDetail = PayrollAdjustmentDetail::whereIn('payroll_id', $PayrollHistoryPayrollIDs)->where(['user_id' => $user_id, 'is_onetime_payment' => 1, 'one_time_payment_id' => $id])->get();
                if (count($PayrollAdjustmentDetail) > 0) {
                    foreach ($PayrollAdjustmentDetail as $key => $value) {
                        if ($value->pid) {
                            $customer = SalesMaster::where('pid', $value->pid)->first();
                            $customer_name = $customer->customer_name;
                        } else {
                            $customer_name = '';
                        }
                        $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['is_onetime_payment' => 1, 'one_time_payment_id' => $id, 'status' => '3'])->first();
                        $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['is_onetime_payment' => 1, 'one_time_payment_id' => $id, 'status' => '3'])->first();
                        $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['is_onetime_payment' => 1, 'one_time_payment_id' => $id, 'status' => '3'])->first();
                        if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                            $is_mark_paid = 1;

                        } else {
                            $is_mark_paid = 0;
                        }

                        // Approved user
                        $approvUser = $value->commented_by;

                        $data[] = [
                            'id' => $value->user_id,
                            'first_name' => $approvUser?->first_name,
                            'last_name' => $approvUser?->last_name,
                            'image' => null,
                            // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                            'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                            'amount' => isset($value->amount) ? $value->amount : null,
                            'type' => $value->payroll_type,
                            'description' => $value->comment,
                            'is_mark_paid' => $is_mark_paid,
                            'customer_name' => $customer_name,

                        ];
                    }
                }

            }
        }

        return $data;

    }

    /**
     * Get reimbursement details for pay stub
     */
    private function getOnetimePayStubReimbursementDetails($id, $user_id)
    {
        $data = [];

        if (! empty($user_id)) {
            $adjustmentRe = OneTimePayments::with('userData', 'adjustment', 'paidBy')->where('payment_status', '3')->where(['id' => $id, 'user_id' => $user_id])->whereIn('adjustment_type_id', [2])->get();

            if (count($adjustmentRe) > 0) {
                foreach ($adjustmentRe as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->paidBy->first_name) ? $value->paidBy->first_name : null,
                        'last_name' => isset($value->paidBy->last_name) ? $value->paidBy->last_name : null,
                        'image' => isset($value->paidBy->image) ? $value->paidBy->image : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'position_id' => isset($value->paidBy->position_id) ? $value->paidBy->position_id : null,
                        'sub_position_id' => isset($value->paidBy->sub_position_id) ? $value->paidBy->sub_position_id : null,
                        'is_super_admin' => isset($value->paidBy->is_super_admin) ? $value->paidBy->is_super_admin : null,
                        'is_manager' => isset($value->paidBy->is_manager) ? $value->paidBy->is_manager : null,
                    ];
                }
            }

            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
                $reimbursement = ApprovalsAndRequestLock::with('user', 'approvedBy')->where('user_id', $user_id)->where('adjustment_type_id', 2)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 'Paid')->get();
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
            }

        }

        return $data;
    }

    /**
     * Get commission details for pay stub
     */
    private function getOnetimePayStubCommissionDetails($id, $user_id)
    {
        $data = [];

        if (! empty($user_id)) {
            $adjustmentRe = OneTimePayments::with('userData', 'adjustment', 'paidBy')->where('payment_status', '3')->where(['id' => $id, 'user_id' => $user_id])->where('adjustment_type_id', 10)->where('adjustment_type_id', '!=', 12)->get();
            if (count($adjustmentRe) > 0) {
                foreach ($adjustmentRe as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->paidBy->first_name) ? $value->paidBy->first_name : null,
                        'last_name' => isset($value->paidBy->last_name) ? $value->paidBy->last_name : null,
                        'image' => isset($value->paidBy->image) ? $value->paidBy->image : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'position_id' => isset($value->paidBy->position_id) ? $value->paidBy->position_id : null,
                        'sub_position_id' => isset($value->paidBy->sub_position_id) ? $value->paidBy->sub_position_id : null,
                        'is_super_admin' => isset($value->paidBy->is_super_admin) ? $value->paidBy->is_super_admin : null,
                        'is_manager' => isset($value->paidBy->is_manager) ? $value->paidBy->is_manager : null,
                    ];
                }
            }

            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
                $usercommission = UserCommissionLock::with(['userdata', 'saledata', 'oneTimePaymentDetail', 'oneTimePaymentDetail.paidBy', 'oneTimePaymentDetail.adjustment'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();
                $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['clawback_type' => 'next payroll'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();

                if (count($usercommission) > 0) {
                    foreach ($usercommission as $key => $value) {
                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->first();

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
            }
        }

        return $data;
    }

    /**
     * Get user override details for pay stub
     */
    private function getOnetimePayStubUserOverrideDetails($id, $user_id)
    {
        $data = [];

        if (! empty($user_id)) {
            $adjustmentRe = OneTimePayments::with('userData', 'adjustment', 'paidBy')->where('payment_status', '3')->where(['id' => $id, 'user_id' => $user_id])->where('adjustment_type_id', 11)->where('adjustment_type_id', '!=', 12)->get();

            if (count($adjustmentRe) > 0) {
                foreach ($adjustmentRe as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->paidBy->first_name) ? $value->paidBy->first_name : null,
                        'last_name' => isset($value->paidBy->last_name) ? $value->paidBy->last_name : null,
                        'image' => isset($value->paidBy->image) ? $value->paidBy->image : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'position_id' => isset($value->paidBy->position_id) ? $value->paidBy->position_id : null,
                        'sub_position_id' => isset($value->paidBy->sub_position_id) ? $value->paidBy->sub_position_id : null,
                        'is_super_admin' => isset($value->paidBy->is_super_admin) ? $value->paidBy->is_super_admin : null,
                        'is_manager' => isset($value->paidBy->is_manager) ? $value->paidBy->is_manager : null,
                    ];
                }
            }

            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {

                $userdata = UserOverridesLock::where(['overrides_settlement_type' => 'during_m2'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();

                if (count($userdata) > 0) {

                    foreach ($userdata as $key => $value) {

                        $adjustmentAmount = PayrollAdjustmentDetailLock::where(['pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->first();

                        $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                        $sale = SalesMaster::where(['pid' => $value->pid])->first();

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
                            'is_onetime_payment' => $value->is_onetime_payment,

                        ];
                    }
                }
            }

        }

        return $data;
    }

    /**
     * Get payroll deductions details for pay stub
     */
    private function getOnetimePayStubPayrollDeductionsDetails($id, $user_id)
    {
        $data = [];

        if (! empty($user_id)) {
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {

                $paydata = PayrollDeductions::with('costcenter')->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->where('status', 3)->get();
                if (count($paydata) > 0) {
                    foreach ($paydata as $d) {

                        $data[] = [
                            'Type' => $d->costcenter->name,
                            'Amount' => $d->amount,
                            'Limit' => $d->limit,
                            'Total' => $d->total,
                            'Outstanding' => $d->outstanding,
                            'cost_center_id' => $d->cost_center_id,
                        ];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get additional field details for pay stub
     */
    private function getOnetimePayStubAdditionalDetails($id, $user_id)
    {
        $data = [];
        if (! empty($user_id)) {
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
                $adjustmentwithpayroll = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->get();
                if (empty($adjustmentwithpayroll->toArray())) {
                    $adjustmentwithpayroll = CustomField::with(['getColumn', 'getApprovedBy'])->where('user_id', $user_id)->where('is_onetime_payment', 1)->where('one_time_payment_id', $id)->get();
                }
                if (count($adjustmentwithpayroll) > 0) {
                    foreach ($adjustmentwithpayroll as $key => $customeFields) {

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

                        $data[] = [
                            'id' => $customeFields->id,
                            'custom_field_id' => $customeFields->column_id,
                            'custom_field_name' => $customeFields->getColumn->field_name,
                            'amount' => isset($customeFields->value) ? ($customeFields->value) : 0,
                            'type' => $customeFields->getColumn->field_name ?? '',
                            'date' => $date,
                            'comment' => $customeFields->comment,
                            'adjustment_by' => $customeFields->approved_by,
                            'adjustment_by_detail' => $approved_by_detail,
                        ];

                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get wages details for pay stub
     */
    private function getOnetimePayStubWagesDetails($id, $user_id)
    {
        $data = [];
        if (! empty($user_id)) {
            $onetimepaymentcheckwithpayroll = OneTimePayments::where(['user_id' => $user_id, 'payment_status' => '3'])->where('adjustment_type_id', 12)->where('id', $id)->get();
            if ($onetimepaymentcheckwithpayroll) {
                $adjustmentwithpayroll = PayrollHourlySalaryLock::with(['oneTimePaymentDetail', 'oneTimePaymentDetail.paidBy', 'oneTimePaymentDetail.adjustment'])
                    ->leftjoin('payroll_overtimes_lock', function ($join) {
                        $join->on('payroll_overtimes_lock.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                            ->on('payroll_overtimes_lock.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                    })
                    ->leftjoin('payroll_adjustment_details', function ($join) {
                        $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                            ->on('payroll_adjustment_details.user_id', '=', 'payroll_hourly_salary_lock.user_id');
                    })
                    ->where('payroll_hourly_salary_lock.user_id', $user_id)->where('payroll_hourly_salary_lock.is_onetime_payment', 1)->where('payroll_hourly_salary_lock.one_time_payment_id', $id)
                    ->where('payroll_hourly_salary_lock.is_next_payroll', 0)
                    ->select('payroll_hourly_salary_lock.*', 'payroll_overtimes_lock.overtime', 'payroll_overtimes_lock.total as overtime_total', 'payroll_adjustment_details.amount as adjustment_amount')
                    ->get();
                if (count($adjustmentwithpayroll) > 0) {
                    $total = 0;
                    foreach ($adjustmentwithpayroll as $d) {
                        $total += ($d->total + $d->overtime_total);

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
                }
            }
        }

        return $data;
    }
}
