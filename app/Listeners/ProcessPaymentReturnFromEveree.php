<?php

namespace App\Listeners;

use App\Models\FrequencyType;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use App\Core\Traits\EvereeTrait;
use App\Models\DailyPayFrequency;
use App\Models\WeeklyPayFrequency;
use Illuminate\Support\Facades\DB;
use App\Models\MonthlyPayFrequency;
use App\Models\ApprovalsAndRequest;
use App\Models\AdditionalPayFrequency;
use App\Events\PaymentReturnFromEveree;

class ProcessPaymentReturnFromEveree
{
    use EvereeTrait;

    public function handle(PaymentReturnFromEveree $event)
    {
        $payload = $event->payload;
        if (isset($payload['paymentId']) && isset($payload['externalWorkerId'])) {
            $jsonData = json_encode($payload, JSON_PRETTY_PRINT);
            if (isset($payload['event_type']) && $payload['event_type'] == "payment.deposit-returned") {
                $changeArr = [
                    'paymentErrorMessage' => "Deposit returned from bank – please check and update your details in Sequifi",
                    'paymentStatus' => "ERROR"
                ];
                $jsonData = json_encode($changeArr, JSON_PRETTY_PRINT);
            }

            try {
                DB::beginTransaction();
                if (isset($payload['payableIds']) && !empty($payload['payableIds']) && count($payload['payableIds']) > 0) {
                    $payableIds = $payload['payableIds'];
                    foreach ($payableIds as $payableId) {
                        $checkPayable = PayrollHistory::select('id', 'user_id', 'everee_paymentId')->where('everee_external_id', 'LIKE', '%' . $payableId . '%')->first();
                        if ($checkPayable && empty($checkPayable->everee_paymentId)) {
                            PayrollHistory::where('id', $checkPayable->id)->update(['everee_paymentId' => $payload['paymentId']]);
                        }
                        $checkPayable = OneTimePayments::select('id', 'user_id', 'everee_paymentId')->where('everee_external_id', 'LIKE', '%' . $payableId . '%')->first();
                        if ($checkPayable && empty($checkPayable->everee_paymentId)) {
                            OneTimePayments::where('id', $checkPayable->id)->update(['everee_paymentId' => $payload['paymentId']]);
                        }
                    }
                }

                $payrollHistory = PayrollHistory::select('pay_period_from', 'pay_period_to', 'worker_type', 'pay_frequency', 'is_onetime_payment', 'one_time_payment_id')->where('everee_paymentId', $payload['paymentId'])->first();
                $oneTimePayment = OneTimePayments::select('pay_period_from', 'pay_period_to', 'user_worker_type', 'pay_frequency')->where('everee_paymentId', $payload['paymentId'])->first();
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

                    PayrollHistory::where('everee_paymentId', $payload['paymentId'])->update(['everee_payment_status' => 2, 'everee_webhook_json' => $jsonData, 'is_deposit_returned' => 1]);
                    if ($payrollHistory->is_onetime_payment) {
                        OneTimePayments::where('id', $payrollHistory->one_time_payment_id)->update(['everee_payment_status' => 2, 'everee_webhook_response' => $jsonData, 'is_deposit_returned' => 1]);
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
                            if ($frequency->w2_closed_status) {
                                $frequency->w2_open_status_from_bank = 1;
                                $frequency->save();
                            }
                        } else {
                            if ($frequency->closed_status) {
                                $frequency->open_status_from_bank = 1;
                                $frequency->save();
                            }
                        }
                    }
                } else if ($oneTimePayment) {
                    OneTimePayments::where('everee_paymentId', $payload['paymentId'])->update(['everee_payment_status' => 2, 'everee_webhook_response' => $jsonData, 'is_deposit_returned' => 1]);

                    $paidCheck = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4, 'status' => 'Paid'])->where('txn_id', $oneTimePayment->req_no)->first();
                    if (!$paidCheck) {
                        ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4,  'status' => 'Paid'])->where('txn_id', $oneTimePayment->req_no)->update(['status' => 'Accept']);
                        $approvalsAndRequest = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4])->where('txn_id', $oneTimePayment->req_no)->first();
                        if ($approvalsAndRequest) {
                            $approvalsAndRequest->delete();
                        }
                    }
                } else {
                    $getPayable = $this->get_payable_by_id($payload['externalWorkerId'], '', ['payment-id' => $payload['paymentId']]);
                    if (isset($getPayable['items']) && !empty($getPayable['items'])) {
                        $paymentId = $getPayable['items'][0]['paymentId'];
                        $payablePaymentRequestId = $getPayable['items'][0]['payablePaymentRequestId'];

                        $payrollHistory = PayrollHistory::where('everee_payment_requestId', $payablePaymentRequestId)->first();
                        $onetimePayment = OneTimePayments::where('everee_payment_req_id', $payablePaymentRequestId)->first();
                        if ($payrollHistory) {
                            $payrollHistory->everee_paymentId = $paymentId;
                            $payrollHistory->everee_payment_status = 2;
                            $payrollHistory->everee_webhook_json = $jsonData;
                            $payrollHistory->is_deposit_returned = 1;
                            $payrollHistory->save();

                            if ($payrollHistory->is_onetime_payment) {
                                OneTimePayments::where('id', $payrollHistory->one_time_payment_id)->update(['everee_payment_status' => 2, 'everee_webhook_response' => $jsonData]);
                            }

                            $payrollHistory = PayrollHistory::select('pay_period_from', 'pay_period_to', 'worker_type', 'pay_frequency')->where('everee_paymentId', $paymentId)->first();
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
                                    if ($frequency->w2_closed_status) {
                                        $frequency->w2_open_status_from_bank = 1;
                                        $frequency->save();
                                    }
                                } else {
                                    if ($frequency->closed_status) {
                                        $frequency->open_status_from_bank = 1;
                                        $frequency->save();
                                    }
                                }
                            }
                        } else if ($onetimePayment) {
                            $onetimePayment->everee_paymentId = $paymentId;
                            $onetimePayment->everee_payment_status = 2;
                            $onetimePayment->everee_webhook_response = $jsonData;
                            $onetimePayment->is_deposit_returned = 1;
                            $onetimePayment->save();

                            $paidCheck = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4, 'status' => 'Paid'])->where('txn_id', $oneTimePayment->req_no)->first();
                            if (!$paidCheck) {
                                ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4,  'status' => 'Paid'])->where('txn_id', $oneTimePayment->req_no)->update(['status' => 'Accept']);
                                $approvalsAndRequest = ApprovalsAndRequest::where('amount', '<', 0)->whereNull('req_no')->where(['user_id' => $oneTimePayment->user_id, 'adjustment_type_id' => 4])->where('txn_id', $oneTimePayment->req_no)->first();
                                if ($approvalsAndRequest) {
                                    $approvalsAndRequest->delete();
                                }
                            }
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