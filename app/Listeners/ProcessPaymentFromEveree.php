<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\FrequencyType;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use App\Core\Traits\EvereeTrait;
use App\Models\DailyPayFrequency;
use App\Models\WeeklyPayFrequency;
use Illuminate\Support\Facades\DB;
use App\Models\MonthlyPayFrequency;
use App\Models\ApprovalsAndRequest;
use App\Models\AdvancePaymentSetting;
use App\Models\W2PayrollTaxDeduction;
use App\Models\AdditionalPayFrequency;
use App\Core\Traits\PayFrequencyTrait;
use App\Traits\EmailNotificationTrait;
use App\Jobs\Payroll\PayrollPayStubJob;
use App\Events\PaymentReceivedFromEveree;

class ProcessPaymentFromEveree
{
    use EvereeTrait, PayFrequencyTrait, EmailNotificationTrait;

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(PaymentReceivedFromEveree $event)
    {
        $resultArray = $event->payload;
        if (isset($resultArray['paymentId']) && isset($resultArray['externalWorkerId'])) {
            $userExists = User::where('employee_id', $resultArray['externalWorkerId'])->first();
            if ($userExists) {
                DB::beginTransaction();
                $jsonData = json_encode($resultArray, JSON_PRETTY_PRINT);
                try {
                    if (isset($resultArray['payableIds']) && !empty($resultArray['payableIds']) && count($resultArray['payableIds']) > 0) {
                        $payableIds = $resultArray['payableIds'];
                        foreach ($payableIds as $payableId) {
                            $checkPayable = PayrollHistory::select('id', 'user_id', 'everee_paymentId')->where('everee_external_id', 'LIKE', '%' . $payableId . '%')->first();
                            if ($checkPayable && empty($checkPayable->everee_paymentId)) {
                                PayrollHistory::where('id', $checkPayable->id)->update(['everee_paymentId' => $resultArray['paymentId']]);
                            }
                            $checkPayable = OneTimePayments::select('id', 'user_id', 'everee_paymentId')->where('everee_external_id', 'LIKE', '%' . $payableId . '%')->first();
                            if ($checkPayable && empty($checkPayable->everee_paymentId)) {
                                OneTimePayments::where('id', $checkPayable->id)->update(['everee_paymentId' => $resultArray['paymentId']]);
                            }
                        }
                    }

                    $payrollHistory = PayrollHistory::select('pay_period_from', 'pay_period_to', 'worker_type', 'pay_frequency', 'is_onetime_payment', 'one_time_payment_id', 'payroll_id')->where('everee_paymentId', $resultArray['paymentId'])->first();
                    $oneTimePayment = OneTimePayments::select('pay_period_from', 'pay_period_to', 'user_worker_type', 'pay_frequency')->where('everee_paymentId', $resultArray['paymentId'])->first();
                    if ($payrollHistory) {
                        $workerType = $payrollHistory->worker_type;
                        $payFrequency = $payrollHistory->pay_frequency;
                        $payPeriodFrom = $payrollHistory->pay_period_from;
                        $payPeriodTo = $payrollHistory->pay_period_to;
                        $param = [
                            'pay_frequency' => $payFrequency,
                            'worker_type' => $workerType,
                            'pay_period_from' => $payPeriodFrom,
                            'pay_period_to' => $payPeriodTo
                        ];

                        PayrollHistory::where('everee_paymentId', $resultArray['paymentId'])->update(['everee_payment_status' => 3, 'everee_webhook_json' => $jsonData]);
                        if ($payrollHistory->is_onetime_payment) {
                            OneTimePayments::where('id', $payrollHistory->one_time_payment_id)->update(['everee_payment_status' => 1, 'everee_webhook_response' => $jsonData]);
                        }
                        $payrollCount = PayrollHistory::applyFrequencyFilter($param, ['pay_type' => 'Bank'])->whereIn('everee_payment_status', [1, 2])->count();
                        if ($payrollCount == 0 && !$payrollHistory->is_onetime_payment) {
                            if ($payFrequency == FrequencyType::WEEKLY_ID) {
                                $class = WeeklyPayFrequency::class;
                            } else if ($payFrequency == FrequencyType::MONTHLY_ID) {
                                $class = MonthlyPayFrequency::class;
                            } else if ($payFrequency == FrequencyType::BI_WEEKLY_ID) {
                                $class = AdditionalPayFrequency::class;
                                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
                            } else if ($payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                                $class = AdditionalPayFrequency::class;
                                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
                            } else if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                                $class = DailyPayFrequency::class;
                            }

                            if (!isset($class)) {
                                DB::rollBack();
                                return response()->json([
                                    'ApiName' => 'close-payroll',
                                    'status' => false,
                                    'message' => 'Invalid pay frequency.'
                                ], 400);
                            }

                            $frequency = $class::query();
                            if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                                $frequency = $frequency->whereRaw('"' . $payPeriodFrom . '" between `pay_period_from` and `pay_period_to`');
                            } else {
                                $frequency = $frequency->where(["pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo]);
                            }
                            if ($payFrequency == FrequencyType::BI_WEEKLY_ID || $payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                                $frequency = $frequency->where('type', $type);
                            }

                            $frequency = $frequency->first();
                            if (!$frequency) {
                                DB::rollBack();
                                return response()->json([
                                    'ApiName' => 'close-payroll',
                                    'status' => false,
                                    'message' => 'Pay period not found.'
                                ], 400);
                            }

                            if ($workerType == 'w2') {
                                $frequency->w2_open_status_from_bank = 0;
                                $frequency->save();
                            } else {
                                $frequency->open_status_from_bank = 0;
                                $frequency->save();
                            }
                        }

                        $this->employeePayStub($resultArray['paymentId'], 0, $payrollHistory->payroll_id);
                    } else if ($oneTimePayment) {
                        OneTimePayments::where('everee_paymentId', $resultArray['paymentId'])->update(['everee_payment_status' => 1, 'everee_webhook_response' => $jsonData]);
                        $this->onetimePaymentUpdate($resultArray['paymentId']);
                    } else {
                        $getPayable = $this->get_payable_by_id($resultArray['externalWorkerId'], '', ['payment-id' => $resultArray['paymentId']]);
                        if (isset($getPayable['items']) && !empty($getPayable['items'])) {
                            $paymentId = $getPayable['items'][0]['paymentId'];
                            $payablePaymentRequestId = $getPayable['items'][0]['payablePaymentRequestId'];

                            $payrollHistory = PayrollHistory::where('everee_payment_requestId', $payablePaymentRequestId)->first();
                            $onetimePayment = OneTimePayments::where('everee_payment_req_id', $payablePaymentRequestId)->first();
                            if ($payrollHistory) {
                                $payrollHistory->everee_paymentId = $paymentId;
                                $payrollHistory->everee_payment_status = 3;
                                $payrollHistory->everee_webhook_json = $jsonData;
                                $payrollHistory->save();

                                if ($payrollHistory->is_onetime_payment) {
                                    OneTimePayments::where('id', $payrollHistory->one_time_payment_id)->update(['everee_payment_status' => 1, 'everee_webhook_response' => $jsonData]);
                                }

                                $payrollHistory = PayrollHistory::select('pay_period_from', 'pay_period_to', 'worker_type', 'pay_frequency', 'payroll_id')->where('everee_paymentId', $paymentId)->first();
                                $workerType = $payrollHistory->worker_type;
                                $payFrequency = $payrollHistory->pay_frequency;
                                $payPeriodFrom = $payrollHistory->pay_period_from;
                                $payPeriodTo = $payrollHistory->pay_period_to;
                                $param = [
                                    'pay_frequency' => $payFrequency,
                                    'worker_type' => $workerType,
                                    'pay_period_from' => $payPeriodFrom,
                                    'pay_period_to' => $payPeriodTo
                                ];
                                $payrollCount = PayrollHistory::applyFrequencyFilter($param, ['pay_type' => 'Bank'])->whereIn('everee_payment_status', [1, 2])->count();
                                if ($payrollCount == 0 && !$payrollHistory->is_onetime_payment) {
                                    if ($payFrequency == FrequencyType::WEEKLY_ID) {
                                        $class = WeeklyPayFrequency::class;
                                    } else if ($payFrequency == FrequencyType::MONTHLY_ID) {
                                        $class = MonthlyPayFrequency::class;
                                    } else if ($payFrequency == FrequencyType::BI_WEEKLY_ID) {
                                        $class = AdditionalPayFrequency::class;
                                        $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
                                    } else if ($payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                                        $class = AdditionalPayFrequency::class;
                                        $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
                                    } else if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                                        $class = DailyPayFrequency::class;
                                    }

                                    if (!isset($class)) {
                                        DB::rollBack();
                                        return response()->json([
                                            'ApiName' => 'close-payroll',
                                            'status' => false,
                                            'message' => 'Invalid pay frequency.'
                                        ], 400);
                                    }

                                    $frequency = $class::query();
                                    $frequency = $frequency->where(["pay_period_from" => $payPeriodFrom, "pay_period_to" => $payPeriodTo]);
                                    if ($payFrequency == FrequencyType::BI_WEEKLY_ID || $payFrequency == FrequencyType::SEMI_MONTHLY_ID) {
                                        $frequency = $frequency->where('type', $type);
                                    }

                                    $frequency = $frequency->first();
                                    if (!$frequency) {
                                        DB::rollBack();
                                        return response()->json([
                                            'ApiName' => 'close-payroll',
                                            'status' => false,
                                            'message' => 'Pay period not found.'
                                        ], 400);
                                    }

                                    if ($workerType == 'w2') {
                                        $frequency->w2_open_status_from_bank = 0;
                                        $frequency->save();
                                    } else {
                                        $frequency->open_status_from_bank = 0;
                                        $frequency->save();
                                    }
                                }

                                $this->employeePayStub($paymentId, 0, $payrollHistory->payroll_id);
                            } else if ($onetimePayment) {
                                $onetimePayment->everee_paymentId = $paymentId;
                                $onetimePayment->everee_payment_status = 1;
                                $onetimePayment->everee_webhook_response = $jsonData;
                                $onetimePayment->save();

                                $this->onetimePaymentUpdate($paymentId);
                            }
                        } else {
                            DB::rollBack();
                            return response()->json([
                                'ApiName' => 'everee_webhook',
                                'status' => true,
                                'message' => 'everee_paymentId not exist in PaymentReceivedFromEveree'
                            ]);
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
        }
    }

    protected function onetimePaymentUpdate($paymentId)
    {
        $oneTimePayment = OneTimePayments::where('everee_paymentId', $paymentId)->first();
        if ($oneTimePayment) {
            $date = date('Y-m-d');
            $approvalAndRequest = ApprovalsAndRequest::where(['id' => $oneTimePayment->req_id, 'status' => 'Accept'])->first();
            if ($approvalAndRequest && !empty($oneTimePayment->req_id)) {
                $approvalAndRequest->status = 'Paid';
                $approvalAndRequest->txn_id = $oneTimePayment->req_no;
                $approvalAndRequest->payroll_id = 0;
                $approvalAndRequest->pay_period_from = $date;
                $approvalAndRequest->pay_period_to = $date;
                $approvalAndRequest->save();
            }

            if ($oneTimePayment->adjustment_type_id == 4) {
                $nextFromDate = NULL;
                $nextToDate = NULL;
                $advanceRequestStatus = "Approved";
                $advanceSetting = AdvancePaymentSetting::first();
                $user = User::where('id', $oneTimePayment->user_id)->first();
                $nextPeriod = $this->openPayFrequency($user->sub_position_id, $user->id);
                if (isset($nextPeriod['pay_period_from']) && $advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                    $nextFromDate = $nextPeriod['pay_period_from'];
                    $nextToDate = $nextPeriod['pay_period_to'];
                    $advanceRequestStatus = "Accept";
                }

                $duplicateCheck = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4])->where('txn_id', $oneTimePayment->req_no)->first();
                if (!$duplicateCheck) {
                    $description = null;
                    if ($approvalAndRequest && !empty($approvalAndRequest->req_no)) {
                        $description = 'Advance payment request Id: ' . $approvalAndRequest->req_no . ' Date of request: ' . date("m/d/Y");
                    }else if ($oneTimePayment && !empty($oneTimePayment->req_no)) {
                        $description = 'Advance one-time payment request Id: ' . $oneTimePayment->req_no . ' Date of request: ' . date("m/d/Y");
                    }

                    $approvalAndRequest = ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'id' => $oneTimePayment->req_id, 'status' => 'Paid', 'adjustment_type_id' => 4])->first();
                    ApprovalsAndRequest::create([
                        'user_id' => isset($approvalAndRequest) ? $approvalAndRequest->user_id : $oneTimePayment->user_id,
                        'parent_id' => isset($approvalAndRequest) ? $approvalAndRequest->id : null,
                        'manager_id' => isset($approvalAndRequest) ? $approvalAndRequest->manager_id : $user->manager_id,
                        'approved_by' => isset($approvalAndRequest) ? $approvalAndRequest->approved_by : $user->id,
                        'adjustment_type_id' => isset($approvalAndRequest) ? $approvalAndRequest->adjustment_type_id : $oneTimePayment->adjustment_type_id,
                        'state_id' => isset($approvalAndRequest) ? $approvalAndRequest->state_id : null,
                        'dispute_type' => isset($approvalAndRequest) ? $approvalAndRequest->dispute_type : null,
                        'customer_pid' => isset($approvalAndRequest) ? $approvalAndRequest->customer_pid : null,
                        'cost_tracking_id' => isset($approvalAndRequest) ? $approvalAndRequest->cost_tracking_id : null,
                        'cost_date' => isset($approvalAndRequest) ? $approvalAndRequest->cost_date : $date,
                        'request_date' => isset($approvalAndRequest) ? $approvalAndRequest->request_date : $date,
                        'amount' => isset($approvalAndRequest) ? (0 - $approvalAndRequest->amount) : (0 - $oneTimePayment->amount),
                        'status' => $advanceRequestStatus,
                        'description' => $description,
                        'pay_period_from' => isset($nextFromDate) ? $nextFromDate : NULL,
                        'pay_period_to' => isset($nextToDate) ? $nextToDate : NULL,
                        'user_worker_type' => isset($approvalAndRequest) ? $approvalAndRequest->user_worker_type : $oneTimePayment->user_worker_type,
                        'pay_frequency' => isset($approvalAndRequest) ? $approvalAndRequest->pay_frequency : $oneTimePayment->pay_frequency,
                        'txn_id' => $oneTimePayment->req_no
                    ]);
                }
            }

            $this->employeePayStub($paymentId, 1, $oneTimePayment->id);
        }
    }

    public function employeePayStub($paymentId, $isOnetimePayment = 0, $historyId = null)
    {
        if (!empty($paymentId)) {
            if ($isOnetimePayment) {
                $oneTimePayment = OneTimePayments::select('user_id', 'pay_period_from', 'pay_period_to', 'pay_frequency', 'user_worker_type')->where('everee_paymentId', $paymentId)->first();
                if (!$oneTimePayment) {
                    return;
                }
                $statements = $this->get_pay_statement_paymentid($oneTimePayment->user_id, $paymentId);
                $payPeriodFrom = $oneTimePayment->pay_period_from;
                $payPeriodTo = $oneTimePayment->pay_period_to;
                $userId = $oneTimePayment->user_id;
                $oneTimePaymentId = null;
                $payFrequency = $oneTimePayment->pay_frequency;
                $workerType = $oneTimePayment->user_worker_type;
            } else {
                $payrollHistory = PayrollHistory::select('user_id', 'pay_period_from', 'pay_period_to', 'is_onetime_payment', 'one_time_payment_id', 'pay_frequency', 'worker_type')->where(['everee_paymentId' => $paymentId])->first();
                if (!$payrollHistory) {
                    return;
                }
                $statements = $this->get_pay_statement_paymentid($payrollHistory->user_id, $paymentId);
                $payPeriodFrom = $payrollHistory->pay_period_from;
                $payPeriodTo = $payrollHistory->pay_period_to;
                $userId = $payrollHistory->user_id;
                $isOnetimePayment = $payrollHistory->is_onetime_payment;
                $oneTimePaymentId = $payrollHistory->one_time_payment_id;
                $payFrequency = $payrollHistory->pay_frequency;
                $workerType = $payrollHistory->worker_type;
            }

            if (!empty($statements) && ($workerType == 'w2' || $workerType == 'W2')) {
                $stateIncomeTax = 0;
                $federalIncomeTax = 0;
                $medicareTax = 0;
                $socialSecurityTax = 0;
                $additionalMedicareTax = 0;

                if (!empty($statements['taxesWithheld'])) {
                    foreach ($statements['taxesWithheld'] as $statement) {
                        if ($statement['name'] == "State Income Tax") {
                            $stateIncomeTax = $statement['amount']['amount'] ?? 0;
                        }

                        if ($statement['name'] == "Federal Income Tax") {
                            $federalIncomeTax = $statement['amount']['amount'] ?? 0;
                        }

                        if ($statement['name'] == "Medicare") {
                            $medicareTax = $statement['amount']['amount'] ?? 0;
                        }

                        if ($statement['name'] == "Social Security") {
                            $socialSecurityTax = $statement['amount']['amount'] ?? 0;
                        }

                        if ($statement['name'] == "Additional Medicare") {
                            $additionalMedicareTax = $statement['amount']['amount'] ?? 0;
                        }
                    }
                }

                $data = [
                    'user_id' => $userId,
                    'fica_tax' => isset($statements['totalTaxesWithheld']['amount']) ? $statements['totalTaxesWithheld']['amount'] : 0,
                    'medicare_withholding' => isset($statements['totalTaxesWithheld']['amount']) ? $statements['totalTaxesWithheld']['amount'] : 0,
                    'social_security_withholding' => isset($statements['totalTaxesWithheld']['amount']) ? $statements['totalTaxesWithheld']['amount'] : 0,
                    'state_income_tax' => $stateIncomeTax,
                    'federal_income_tax' => $federalIncomeTax,
                    'medicare_tax' => $medicareTax,
                    'social_security_tax' => $socialSecurityTax,
                    'additional_medicare_tax' => $additionalMedicareTax,
                    'pay_period_from' => isset($payPeriodFrom) ? $payPeriodFrom : null,
                    'pay_period_to' => isset($payPeriodTo) ? $payPeriodTo : null,
                    'payment_id' => isset($paymentId) ? $paymentId : null,
                    'response' => json_encode($statements),
                    'is_onetime_payment' => $isOnetimePayment,
                    'one_time_payment_id' => $oneTimePaymentId,
                    'pay_frequency' => $payFrequency,
                    'user_worker_type' => $workerType
                ];

                if (!W2PayrollTaxDeduction::where('payment_id', $paymentId)->first()) {
                    W2PayrollTaxDeduction::create($data);
                }
            }

            if (!empty($userId)) {
                PayrollPayStubJob::dispatch($isOnetimePayment, $historyId);
            }
        }
    }
}
